<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    /**
     * Impersonate a user (get token to log in as them). Cannot impersonate admins.
     */
    public function impersonateUser(Request $request, User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate an admin user.',
            ], 403);
        }

        return $this->issueImpersonationToken($request, $user, 'user');
    }

    /**
     * Impersonate the first user of a company (log in as that company).
     */
    public function impersonateCompany(Request $request, Company $company): JsonResponse
    {
        $user = $company->users()->orderBy('id')->first();

        if (! $user) {
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

        return $this->issueImpersonationToken($request, $user, 'company');
    }

    private function issueImpersonationToken(Request $request, User $user, string $targetType): JsonResponse
    {
        $admin = $request->user();
        $user->load('company');

        $tokenResult = $user->createToken(
            'impersonation',
            ['impersonation'],
            now()->addHour()
        );

        SystemLog::create([
            'type' => 'security',
            'message' => 'Admin impersonation started',
            'source' => 'admin',
            'details' => json_encode([
                'admin_id' => $admin?->id,
                'admin_email' => $admin?->email,
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'target_type' => $targetType,
                'company_id' => $user->company_id,
                'ip' => $request->ip(),
            ]),
        ]);

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
            'token' => $tokenResult->plainTextToken,
            'user' => $userData,
        ]);
    }
}
