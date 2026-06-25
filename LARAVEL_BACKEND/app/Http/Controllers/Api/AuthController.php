<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, PlatformSetting::maxLoginAttempts())) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        if (($user->status ?? 'active') === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Contact support.',
                'code' => 'account_inactive',
            ], 403);
        }

        if ($user->company_id) {
            $company = $user->company;
            if (! $company || $company->status === 'suspended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your company account is not active. Contact support.',
                    'code' => 'company_inactive',
                ], 403);
            }
        }

        if (PlatformSetting::requiresEmailVerification() && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
                'code' => 'email_not_verified',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth')->plainTextToken;

        $user->load('company');
        $userData = $this->userToArray($user);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $userData,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        if (! PlatformSetting::allowsNewRegistrations()) {
            return response()->json([
                'success' => false,
                'message' => 'New registrations are currently closed.',
            ], 403);
        }

        // Support both snake_case (password_confirmation) and camelCase (confirmPassword) from frontend
        $request->merge([
            'password_confirmation' => $request->input('password_confirmation') ?? $request->input('confirmPassword'),
        ]);

        $validated = $request->validate([
            'companyName' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:50',
            'password' => ['required', 'confirmed', PlatformSetting::passwordRule()],
            'acceptTerms' => 'accepted',
        ], [
            'acceptTerms.accepted' => 'You must accept the terms and conditions.',
        ]);

        $company = Company::create([
            'name' => $validated['companyName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => 'pending',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $user->role = 'company_owner';
        $user->save();

        $this->createDefaultTrialSubscription($company);

        $requireVerification = PlatformSetting::requiresEmailVerification();

        if ($requireVerification) {
            $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'requireEmailVerification' => true,
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! You can now sign in.',
            'requireEmailVerification' => false,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with this email, you will receive a password reset link.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge(['password_confirmation' => $request->input('confirmPassword')]);
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PlatformSetting::passwordRule()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful! You can now login with your new password.',
        ]);
    }

    /**
     * Resend email verification link. POST /api/auth/resend-verification { email }
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        if (! PlatformSetting::requiresEmailVerification()) {
            return response()->json([
                'success' => true,
                'message' => 'Email verification is not required for this platform. You can log in with your password.',
            ]);
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with this email, a new verification link has been sent.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'This account is already verified. You can log in.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'A new verification link has been sent to your email address.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return response()->json([
            'user' => $this->userToArray($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:50',
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($emailChanged && PlatformSetting::requiresEmailVerification()) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && PlatformSetting::requiresEmailVerification()) {
            $user->sendEmailVerificationNotification();
        }

        $user->load('company');

        $message = 'Profile updated successfully.';
        if ($emailChanged && PlatformSetting::requiresEmailVerification()) {
            $message = 'Profile updated. Please verify your new email address.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $this->userToArray($user),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->merge([
            'password_confirmation' => $request->input('confirmPassword', $request->input('password_confirmation')),
        ]);

        $validated = $request->validate([
            'currentPassword' => 'required|string',
            'password' => ['required', 'confirmed', PlatformSetting::passwordRule()],
        ]);

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    private function loginThrottleKey(Request $request): string
    {
        $email = Str::transliterate(Str::lower((string) $request->email));

        return 'login:'.$email.'|'.$request->ip();
    }

    /**
     * Create default free trial subscription for a new company.
     * All companies get a trial so they can use the system until trial ends or they subscribe.
     */
    private function createDefaultTrialSubscription(Company $company): void
    {
        $planSlug = config('subscription.default_plan_slug', 'starter');
        $trialDays = config('subscription.default_trial_days', 14);

        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            $planSlug = 'starter';
        }

        Subscription::create([
            'company_id' => $company->id,
            'plan' => $planSlug,
            'status' => 'trial',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays($trialDays)->format('Y-m-d'),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
    }

    private function userToArray(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'companyId' => $user->company_id ? (string) $user->company_id : null,
            'companyName' => $user->company?->name,
            'status' => $user->status,
            'lastLogin' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at->format('Y-m-d'),
        ];
    }
}
