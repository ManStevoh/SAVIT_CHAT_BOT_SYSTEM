<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\CompanyEntitlementOverride;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Carbon\Carbon;

/**
 * DB-backed plan entitlements — single source of truth for enforceable limits.
 */
final class EntitlementService
{
    /** @var array<string, array<string, mixed>> */
    public const DEFAULTS = [
        'starter' => [
            'messages' => 5000,
            'team' => 3,
            'whatsapp_numbers' => 1,
            'ai_cost_usd' => 5.0,
            'ai_model_modes' => ['auto'],
            'allow_byok' => false,
            'credential_modes' => ['platform'],
            'api_access' => false,
            'analytics' => false,
            'attribution' => true,
            'ai_posts_per_month' => 20,
            'ai_images_per_month' => 10,
            'social_platforms' => 1,
            'growth_enabled' => true,
            'agent_commerce' => true,
            'allow_physical' => true,
            'allow_digital' => true,
            'allow_service' => false,
            'allow_bookings' => false,
            'max_bookings_per_month' => 0,
        ],
        'professional' => [
            'messages' => 50000,
            'team' => 10,
            'whatsapp_numbers' => 1,
            'ai_cost_usd' => 50.0,
            'ai_model_modes' => ['auto', 'platform_default'],
            'allow_byok' => true,
            'credential_modes' => ['platform', 'company_preferred'],
            'api_access' => true,
            'analytics' => true,
            'attribution' => true,
            'ai_posts_per_month' => 100,
            'ai_images_per_month' => 50,
            'social_platforms' => 3,
            'growth_enabled' => true,
            'agent_commerce' => true,
            'allow_physical' => true,
            'allow_digital' => true,
            'allow_service' => true,
            'allow_bookings' => true,
            'max_bookings_per_month' => 50,
        ],
        'enterprise' => [
            'messages' => null, // unlimited
            'team' => 50,
            'whatsapp_numbers' => 1,
            'ai_cost_usd' => null,
            'ai_model_modes' => ['auto', 'platform_default', 'specific'],
            'allow_byok' => true,
            'credential_modes' => ['platform', 'company_preferred', 'company'],
            'api_access' => true,
            'analytics' => true,
            'attribution' => true,
            'ai_posts_per_month' => 500,
            'ai_images_per_month' => 200,
            'social_platforms' => 10,
            'growth_enabled' => true,
            'agent_commerce' => true,
            'allow_physical' => true,
            'allow_digital' => true,
            'allow_service' => true,
            'allow_bookings' => true,
            'max_bookings_per_month' => null,
        ],
    ];

    /**
     * Blank template used when normalizing admin input for any plan slug.
     *
     * @return array<string, mixed>
     */
    public static function blankTemplate(): array
    {
        return [
            'messages' => 5000,
            'team' => 3,
            'whatsapp_numbers' => 1,
            'ai_cost_usd' => 5.0,
            'ai_model_modes' => ['auto'],
            'allow_byok' => false,
            'credential_modes' => ['platform'],
            'api_access' => false,
            'analytics' => false,
            'attribution' => true,
            'ai_posts_per_month' => 20,
            'ai_images_per_month' => 10,
            'social_platforms' => 1,
            'growth_enabled' => true,
            'agent_commerce' => true,
            'allow_physical' => true,
            'allow_digital' => true,
            'allow_service' => false,
            'allow_bookings' => false,
            'max_bookings_per_month' => 0,
        ];
    }

    /**
     * Normalize admin-submitted entitlements into the DB shape.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeAdminEntitlements(array $input, ?string $planSlug = null): array
    {
        $base = $planSlug && isset(self::DEFAULTS[$planSlug])
            ? self::DEFAULTS[$planSlug]
            : self::blankTemplate();

        $out = $base;

        if (array_key_exists('messages', $input)) {
            $messages = $input['messages'];
            if ($messages === null || $messages === '' || $messages === 'unlimited') {
                $out['messages'] = null;
            } else {
                $out['messages'] = max(0, (int) $messages);
            }
        }

        if (array_key_exists('team', $input)) {
            $out['team'] = max(1, (int) $input['team']);
        }
        if (array_key_exists('whatsapp_numbers', $input)) {
            $out['whatsapp_numbers'] = max(1, (int) $input['whatsapp_numbers']);
        }
        if (array_key_exists('ai_cost_usd', $input)) {
            $cost = $input['ai_cost_usd'];
            $out['ai_cost_usd'] = ($cost === null || $cost === '') ? null : max(0, (float) $cost);
        }
        if (array_key_exists('ai_model_modes', $input) && is_array($input['ai_model_modes'])) {
            $allowed = ['auto', 'platform_default', 'specific'];
            $modes = array_values(array_intersect($allowed, array_map('strval', $input['ai_model_modes'])));
            $out['ai_model_modes'] = $modes !== [] ? $modes : ['auto'];
        }
        if (array_key_exists('allow_byok', $input)) {
            $out['allow_byok'] = (bool) $input['allow_byok'];
        }
        if (array_key_exists('credential_modes', $input) && is_array($input['credential_modes'])) {
            $allowed = ['platform', 'company_preferred', 'company'];
            $modes = array_values(array_intersect($allowed, array_map('strval', $input['credential_modes'])));
            $out['credential_modes'] = $modes !== [] ? $modes : ['platform'];
        }
        foreach ([
            'api_access',
            'analytics',
            'attribution',
            'growth_enabled',
            'agent_commerce',
            'allow_physical',
            'allow_digital',
            'allow_service',
            'allow_bookings',
        ] as $boolKey) {
            if (array_key_exists($boolKey, $input)) {
                $out[$boolKey] = (bool) $input[$boolKey];
            }
        }
        foreach (['ai_posts_per_month', 'ai_images_per_month', 'social_platforms'] as $intKey) {
            if (array_key_exists($intKey, $input)) {
                $out[$intKey] = max(0, (int) $input[$intKey]);
            }
        }
        if (array_key_exists('max_bookings_per_month', $input)) {
            $bookings = $input['max_bookings_per_month'];
            $out['max_bookings_per_month'] = ($bookings === null || $bookings === '' || $bookings === 'unlimited')
                ? null
                : max(0, (int) $bookings);
        }

        if (! $out['allow_byok']) {
            $out['credential_modes'] = ['platform'];
        }

        return $out;
    }

    /**
     * API-facing entitlements (camelCase) for admin UI.
     *
     * @return array<string, mixed>
     */
    public function entitlementsForApi(Plan $plan): array
    {
        $e = $this->limitsForPlanSlug($plan->slug);

        return [
            'messages' => $e['messages'],
            'messagesUnlimited' => $e['messages'] === null,
            'team' => (int) ($e['team'] ?? 3),
            'whatsappNumbers' => (int) ($e['whatsapp_numbers'] ?? 1),
            'aiCostUsd' => $e['ai_cost_usd'] ?? null,
            'aiModelModes' => $e['ai_model_modes'] ?? ['auto'],
            'allowByok' => (bool) ($e['allow_byok'] ?? false),
            'credentialModes' => $e['credential_modes'] ?? ['platform'],
            'apiAccess' => (bool) ($e['api_access'] ?? false),
            'analytics' => (bool) ($e['analytics'] ?? false),
            'attribution' => (bool) ($e['attribution'] ?? true),
            'aiPostsPerMonth' => (int) ($e['ai_posts_per_month'] ?? 20),
            'aiImagesPerMonth' => (int) ($e['ai_images_per_month'] ?? 10),
            'socialPlatforms' => (int) ($e['social_platforms'] ?? 1),
            'growthEnabled' => (bool) ($e['growth_enabled'] ?? true),
            'agentCommerce' => (bool) ($e['agent_commerce'] ?? false),
            'allowPhysical' => (bool) ($e['allow_physical'] ?? true),
            'allowDigital' => (bool) ($e['allow_digital'] ?? true),
            'allowService' => (bool) ($e['allow_service'] ?? false),
            'allowBookings' => (bool) ($e['allow_bookings'] ?? false),
            'maxBookingsPerMonth' => array_key_exists('max_bookings_per_month', $e)
                ? ($e['max_bookings_per_month'] === null ? null : (int) $e['max_bookings_per_month'])
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForPlanSlug(string $planSlug): array
    {
        $defaults = self::DEFAULTS[$planSlug] ?? self::blankTemplate();
        $plan = Plan::where('slug', $planSlug)->first();
        $fromDb = is_array($plan?->entitlements) ? $plan->entitlements : [];

        return array_merge($defaults, $fromDb);
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForCompany(Company $company): array
    {
        $slug = $this->currentPlanSlug($company);
        $limits = $this->limitsForPlanSlug($slug);
        $override = CompanyEntitlementOverride::where('company_id', $company->id)->first();
        if ($override && is_array($override->overrides)) {
            $limits = array_merge($limits, $override->overrides);
        }

        return $limits;
    }

    public function currentPlanSlug(Company $company): string
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->where('end_date', '>=', now()->toDateString())
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription) {
            $subscription = Subscription::where('company_id', $company->id)
                ->orderByDesc('end_date')
                ->first();
        }

        return $subscription?->plan ?? 'starter';
    }

    public function messageLimit(Company $company): ?int
    {
        $limits = $this->limitsForCompany($company);
        if (! array_key_exists('messages', $limits)) {
            return 5000;
        }

        // Explicit null in entitlements means unlimited (do not use ?? — it treats null as missing).
        return $limits['messages'] === null ? null : (int) $limits['messages'];
    }

    public function hasUnlimitedMessages(Company $company): bool
    {
        return $this->messageLimit($company) === null;
    }

    public function teamLimit(Company $company): int
    {
        return (int) ($this->limitsForCompany($company)['team'] ?? 3);
    }

    public function whatsappNumberLimit(Company $company): int
    {
        return max(1, (int) ($this->limitsForCompany($company)['whatsapp_numbers'] ?? 1));
    }

    public function canAddTeamMember(Company $company): bool
    {
        $count = User::where('company_id', $company->id)->count();

        return $count < $this->teamLimit($company);
    }

    public function canConnectWhatsAppNumber(Company $company, ?string $phoneNumberId = null): bool
    {
        $limit = $this->whatsappNumberLimit($company);
        $query = WhatsAppAccount::where('company_id', $company->id);

        // Reconnecting / replacing the same Meta phone number always allowed.
        if ($phoneNumberId) {
            $existingSame = (clone $query)->where('phone_number_id', $phoneNumberId)->exists();
            if ($existingSame) {
                return true;
            }
        }

        // Current product stores one account per company (updateOrCreate by company_id).
        // Allow first connection; block only when already at capacity with a different number.
        $count = $query->count();
        if ($count === 0) {
            return true;
        }

        // Single-row architecture: treating reconnect as replacement when limit is 1.
        if ($limit <= 1) {
            return true;
        }

        return $count < $limit;
    }

    public function hasApiAccess(Company $company): bool
    {
        return (bool) ($this->limitsForCompany($company)['api_access'] ?? false);
    }

    public function hasAnalyticsAccess(Company $company): bool
    {
        return (bool) ($this->limitsForCompany($company)['analytics'] ?? false);
    }

    public function hasAttribution(Company $company): bool
    {
        return (bool) ($this->limitsForCompany($company)['attribution'] ?? true);
    }

    public function allowsProductType(Company $company, string $type): bool
    {
        $key = match ($type) {
            'physical' => 'allow_physical',
            'digital' => 'allow_digital',
            'service' => 'allow_service',
            default => null,
        };

        return $key !== null && (bool) ($this->limitsForCompany($company)[$key] ?? false);
    }

    public function allowsBookings(Company $company): bool
    {
        return (bool) ($this->limitsForCompany($company)['allow_bookings'] ?? false);
    }

    public function maxBookingsPerMonth(Company $company): ?int
    {
        $limits = $this->limitsForCompany($company);
        $value = array_key_exists('max_bookings_per_month', $limits)
            ? $limits['max_bookings_per_month']
            : 0;

        return $value === null ? null : max(0, (int) $value);
    }

    public function aiCostLimit(Company $company): ?float
    {
        $value = $this->limitsForCompany($company)['ai_cost_usd'] ?? null;

        return $value === null ? null : (float) $value;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function currentBillingPeriod(Company $company): array
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->orderByDesc('end_date')
            ->first();

        $start = $subscription
            ? Carbon::parse($subscription->start_date)->startOfDay()
            : now()->startOfMonth();
        $end = $subscription
            ? Carbon::parse($subscription->end_date)->endOfDay()
            : now()->endOfMonth();

        return ['start' => $start, 'end' => $end];
    }
}
