<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * List team members (users belonging to the company).
     * GET /api/company/team
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $members = $user->company->users()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status']);

        $items = $members->map(fn ($u) => [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role === 'company_owner' ? 'Admin' : (str_contains($u->role, 'admin') ? 'Admin' : 'Agent'),
            'status' => $u->status,
        ]);

        return response()->json($items);
    }
}
