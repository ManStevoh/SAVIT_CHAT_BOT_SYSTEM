<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * List team members (users belonging to the company).
     * GET /api/company/team
     *
     * Returns a JSON array for backward compatibility, plus X-Team-Limit headers.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company = $user->company;
        $members = $company->users()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status']);

        $items = $members->map(fn ($u) => [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role === 'company_owner' ? 'Admin' : (str_contains((string) $u->role, 'admin') ? 'Admin' : 'Agent'),
            'status' => $u->status,
        ])->values()->all();

        $limit = PlanLimitService::getTeamLimitForPlan(PlanLimitService::getCurrentPlanSlug($company));

        return response()->json($items)
            ->header('X-Team-Used', (string) count($items))
            ->header('X-Team-Limit', (string) $limit)
            ->header('X-Team-Can-Add', PlanLimitService::canAddTeamMember($company) ? '1' : '0');
    }

    /**
     * Invite / create a team agent within plan seat limit.
     * POST /api/company/team
     */
    public function store(Request $request, MailService $mail): JsonResponse
    {
        $actor = $request->user();
        $company = $actor->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        if (! in_array($actor->role, ['company_owner', 'admin', 'company_admin'], true)) {
            return response()->json(['message' => 'Only company admins can invite team members.'], 403);
        }

        if (! PlanLimitService::canAddTeamMember($company)) {
            $limit = PlanLimitService::getTeamLimitForPlan(PlanLimitService::getCurrentPlanSlug($company));

            return response()->json([
                'message' => "Team seat limit reached ({$limit}). Upgrade your plan to add more agents.",
                'code' => 'team_limit_reached',
                'limit' => $limit,
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => 'sometimes|string|in:agent,company_admin',
        ]);

        $temporaryPassword = Str::password(12);
        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'company_id' => $company->id,
            'role' => $validated['role'] ?? 'agent',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        try {
            $mail->send(
                $member->email,
                '['.config('app.name').'] You were added to '.$company->name,
                '<p>Hi '.e($member->name).',</p><p>You have been added to <strong>'.e($company->name).'</strong> on '.e(config('app.name')).'.</p><p>Email: '.e($member->email).'<br>Temporary password: <code>'.e($temporaryPassword).'</code></p><p>Please sign in and change your password.</p>',
                'You were added to '.$company->name.'. Temporary password: '.$temporaryPassword
            );
        } catch (\Throwable) {
            // Invitation still succeeds; admin can share the password manually.
        }

        return response()->json([
            'success' => true,
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role === 'company_admin' ? 'Admin' : 'Agent',
                'status' => $member->status,
            ],
            'temporaryPassword' => $temporaryPassword,
            'message' => 'Team member created. Share the temporary password securely.',
        ], 201);
    }
}
