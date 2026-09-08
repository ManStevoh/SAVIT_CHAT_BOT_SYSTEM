<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlatformSetting;

/**
 * Resolves which plan a newly registered company should receive.
 */
final class RegistrationPlanService
{
    public function configuredDefaultSlug(): string
    {
        $settings = PlatformSetting::query()->first();
        $slug = trim((string) ($settings?->default_registration_plan_slug ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        return (string) config('subscription.default_plan_slug', 'starter');
    }

    public function configuredDefault(): ?Plan
    {
        $slug = $this->configuredDefaultSlug();

        return $slug !== '' ? Plan::query()->where('slug', $slug)->first() : null;
    }

    public function shouldForceDefault(): bool
    {
        $settings = PlatformSetting::query()->first();
        $explicit = trim((string) ($settings?->default_registration_plan_slug ?? ''));

        return (bool) ($settings?->force_default_registration_plan ?? false)
            && $explicit !== ''
            && $this->configuredDefault() !== null;
    }

    public function resolveForSignup(?Plan $selectedPlan): ?Plan
    {
        if ($this->shouldForceDefault()) {
            return $this->configuredDefault();
        }

        if ($selectedPlan && $selectedPlan->has_trial && ! $selectedPlan->is_free) {
            return $selectedPlan;
        }

        if ($selectedPlan && $selectedPlan->is_free) {
            return $selectedPlan;
        }

        $default = $this->configuredDefault();
        if ($default && ($default->has_trial || $default->is_free)) {
            return $default;
        }

        return Plan::query()->where('has_trial', true)->orderBy('sort_order')->first()
            ?? Plan::query()->where('is_free', true)->orderBy('sort_order')->first();
    }
}
