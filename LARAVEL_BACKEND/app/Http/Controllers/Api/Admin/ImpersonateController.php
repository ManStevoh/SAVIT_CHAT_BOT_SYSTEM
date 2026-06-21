<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ImpersonateController extends Controller
{
    /**
     * Impersonate a user (get token to log in as them). Cannot impersonate admins.
     */
    public function impersonateUser(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate an admin user.',
            ], 403);
        }

        $user->load('company');
        $token = $user->createToken('impersonation')->plainTextToken;
        $userData = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'companyId' => $user->company_id ? (string) $user->company_id : null,
            'companyName' => $user->company?->name,
            'status' => $user->status ?? 'active',
            'lastLogin' => $user->last_login_at?->toIso8601String() ?? '',
            'createdAt' => $user->created_at->format('Y-m-d'),
        ];

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $userData,
        ]);
    }

    /**
     * Impersonate the first user of a company (log in as that company).
     */
    public function impersonateCompany(Company $company): JsonResponse
    {
        $user = $company->users()->orderBy('id')->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Company has no users to impersonate.',
            ], 404);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate an admin user.',
            ], 403);
        }

        $user->load('company');
        $token = $user->createToken('impersonation')->plainTextToken;
        $userData = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'companyId' => $user->company_id ? (string) $user->company_id : null,
            'companyName' => $user->company?->name,
            'status' => $user->status ?? 'active',
            'lastLogin' => $user->last_login_at?->toIso8601String() ?? '',
            'createdAt' => $user->created_at->format('Y-m-d'),
        ];

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $userData,
        ]);
    }
}
