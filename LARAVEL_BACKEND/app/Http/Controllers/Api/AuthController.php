<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MailService;
use App\Services\Platform\NotificationDispatcher;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function register(Request $request, RecaptchaService $recaptcha): JsonResponse
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
            'marketingConsent' => 'sometimes|boolean',
            'planId' => 'sometimes|nullable|string',
            'recaptchaToken' => 'nullable|string|max:4000',
        ], [
            'acceptTerms.accepted' => 'You must accept the terms and conditions.',
        ]);

        $recaptcha->assertValid($validated['recaptchaToken'] ?? null, $request->ip());

        $selectedPlan = null;
        if (! empty($validated['planId'])) {
            $selectedPlan = Plan::find($validated['planId']);
        }

        $company = Company::create([
            'name' => $validated['companyName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => 'pending',
        ]);

        $marketingConsent = (bool) ($validated['marketingConsent'] ?? false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'company_id' => $company->id,
            'status' => 'active',
            'terms_accepted_at' => now(),
            'marketing_consent' => $marketingConsent,
            'marketing_consent_at' => $marketingConsent ? now() : null,
            'selected_plan_id' => $selectedPlan?->id,
        ]);
        $user->role = 'company_owner';
        $user->save();

        $trial = $this->createTrialSubscriptionForRegistration($company, $selectedPlan);
        app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company);

        $requiresPayment = $selectedPlan
            && ! $selectedPlan->is_free
            && ! (bool) $selectedPlan->has_trial
            && (float) ($selectedPlan->price_amount ?? 0) > 0;

        $requireVerification = PlatformSetting::requiresEmailVerification();

        if ($requireVerification) {
            $user->sendEmailVerificationNotification();
        } else {
            $user->markEmailAsVerified();
        }

        // Always send welcome (includes trial details when applicable). Verification email is separate when required.
        $this->sendRegistrationWelcome($user, $company, $trial);

        return response()->json([
            'success' => true,
            'message' => $requireVerification
                ? 'Registration successful! Please check your email to verify your account.'
                : 'Registration successful! You can now sign in.',
            'requireEmailVerification' => $requireVerification,
            'trialStarted' => $trial !== null,
            'trialDays' => $trial['days'] ?? null,
            'trialPlan' => $trial['plan_slug'] ?? null,
            'trialPlanName' => $trial['plan_name'] ?? null,
            'requiresPayment' => $requiresPayment,
            'selectedPlanId' => $selectedPlan ? (string) $selectedPlan->id : null,
            'postLoginPath' => $requiresPayment && $selectedPlan
                ? '/dashboard/subscription?subscribe='.$selectedPlan->id
                : ($trial
                    ? '/dashboard?trial_started=1'
                    : '/dashboard'),
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

    /**
     * Start a free trial for a new company based on admin plan flags (has_trial / trial_days).
     * Prefers the plan selected on pricing; falls back to the default trial-enabled plan.
     *
     * @return array{days: int, plan_slug: string, plan_name: string, end_date: string}|null
     */
    private function createTrialSubscriptionForRegistration(Company $company, ?Plan $selectedPlan): ?array
    {
        $plan = null;
        if ($selectedPlan && $selectedPlan->has_trial && ! $selectedPlan->is_free) {
            $plan = $selectedPlan;
        } elseif ($selectedPlan && $selectedPlan->is_free) {
            $plan = $selectedPlan;
        }

        if (! $plan) {
            $defaultSlug = config('subscription.default_plan_slug', 'starter');
            $default = Plan::where('slug', $defaultSlug)->first();
            if ($default && ($default->has_trial || $default->is_free)) {
                $plan = $default;
            } else {
                $plan = Plan::where('has_trial', true)->orderBy('sort_order')->first()
                    ?? Plan::where('is_free', true)->orderBy('sort_order')->first();
            }
        }

        if (! $plan) {
            return null;
        }

        // Free plans: active (not trial). Trial-enabled paid plans: trial status.
        if ($plan->is_free && ! $plan->has_trial) {
            $days = max(1, (int) config('subscription.default_trial_days', 14));
            Subscription::create([
                'company_id' => $company->id,
                'plan' => $plan->slug,
                'status' => 'active',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addYears(10)->format('Y-m-d'),
                'amount' => 0,
                'billing_cycle' => 'monthly',
            ]);

            return [
                'days' => $days,
                'plan_slug' => $plan->slug,
                'plan_name' => $plan->name,
                'end_date' => now()->addYears(10)->format('F j, Y'),
                'is_free' => true,
            ];
        }

        if (! $plan->has_trial) {
            return null;
        }

        $days = (int) ($plan->trial_days ?: config('subscription.default_trial_days', 14));
        if ($days < 1) {
            $days = 14;
        }
        $endDate = now()->addDays($days);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan->slug,
            'status' => 'trial',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        return [
            'days' => $days,
            'plan_slug' => $plan->slug,
            'plan_name' => $plan->name,
            'end_date' => $endDate->format('F j, Y'),
            'is_free' => false,
        ];
    }

    /**
     * @param  array{days: int, plan_slug: string, plan_name: string, end_date: string, is_free?: bool}|null  $trial
     */
    private function sendRegistrationWelcome(User $user, Company $company, ?array $trial): void
    {
        try {
            if ($user->email) {
                app(MailService::class)->sendWelcomeTrialEmail(
                    $user->email,
                    $user->name,
                    $trial['plan_name'] ?? 'Starter',
                    $trial['days'] ?? (int) config('subscription.default_trial_days', 14),
                    $trial['end_date'] ?? now()->addDays(14)->format('F j, Y'),
                    ! empty($trial) && empty($trial['is_free'])
                );
            }

            if ($trial) {
                app(NotificationDispatcher::class)->dispatch($company, 'subscription.trial_started', [
                    'plan' => $trial['plan_name'],
                    'days' => $trial['days'],
                    'end_date' => $trial['end_date'],
                    'owner_email' => $company->email,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Registration welcome failed: '.$e->getMessage());
        }
    }

    private function loginThrottleKey(Request $request): string
    {
        $email = Str::transliterate(Str::lower((string) $request->email));

        return 'login:'.$email.'|'.$request->ip();
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
            'termsAcceptedAt' => $user->terms_accepted_at?->toIso8601String(),
            'marketingConsent' => (bool) $user->marketing_consent,
            'marketingConsentAt' => $user->marketing_consent_at?->toIso8601String(),
            'selectedPlanId' => $user->selected_plan_id ? (string) $user->selected_plan_id : null,
        ];
    }
}
