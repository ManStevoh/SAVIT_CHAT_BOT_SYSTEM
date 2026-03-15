<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
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
        $validated = $request->validate([
            'companyName' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:50',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
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

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Please check your email to verify your account.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

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
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    private function userToArray(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'companyId' => $user->company_id ? (string) $user->company_id : null,
            'companyName' => $user->company?->name,
            'status' => $user->status,
            'lastLogin' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at->format('Y-m-d'),
        ];
    }
}
