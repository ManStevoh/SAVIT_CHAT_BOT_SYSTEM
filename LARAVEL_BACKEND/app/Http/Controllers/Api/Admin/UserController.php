<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('company');

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->get();
        $data = $users->map(fn (User $u) => [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'companyId' => $u->company_id ? (string) $u->company_id : null,
            'companyName' => $u->company?->name,
            'avatar' => $u->avatar ? Storage::url($u->avatar) : null,
            'status' => $u->status ?? 'active',
            'lastLogin' => $u->last_login_at?->toIso8601String() ?? '',
            'createdAt' => $u->created_at->format('Y-m-d'),
            'termsAcceptedAt' => $u->terms_accepted_at?->toIso8601String(),
            'marketingConsent' => (bool) $u->marketing_consent,
            'marketingConsentAt' => $u->marketing_consent_at?->toIso8601String(),
            'selectedPlanId' => $u->selected_plan_id ? (string) $u->selected_plan_id : null,
        ]);

        return response()->json($data->values()->all());
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $user->update(['status' => $request->status]);

        if ($request->status === 'inactive') {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
        ]);
    }

    /**
     * Set or reset password for a user (admin only). Cannot change admin user password.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change password for an admin user.',
            ], 403);
        }

        $request->merge(['password_confirmation' => $request->input('confirmPassword', $request->input('password_confirmation'))]);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', PlatformSetting::passwordRule()],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}
