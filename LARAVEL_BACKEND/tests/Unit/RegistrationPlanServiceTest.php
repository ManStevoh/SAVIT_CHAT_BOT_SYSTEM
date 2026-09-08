<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\RegistrationPlanService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['platform_name' => 'RelayIQ']);
    }

    public function test_falls_back_to_config_slug_when_unset(): void
    {
        $service = app(RegistrationPlanService::class);

        $this->assertSame('starter', $service->configuredDefaultSlug());
        $this->assertSame('starter', $service->configuredDefault()?->slug);
        $this->assertFalse($service->shouldForceDefault());
    }

    public function test_force_requires_an_explicit_plan_slug(): void
    {
        PlatformSetting::query()->update(['force_default_registration_plan' => true]);

        $this->assertFalse(app(RegistrationPlanService::class)->shouldForceDefault());
    }

    public function test_force_uses_configured_free_plan_over_selected_trial(): void
    {
        PlatformSetting::query()->update([
            'default_registration_plan_slug' => 'free',
            'force_default_registration_plan' => true,
        ]);

        $growth = Plan::where('slug', 'professional')->firstOrFail();
        $resolved = app(RegistrationPlanService::class)->resolveForSignup($growth);

        $this->assertSame('free', $resolved?->slug);
    }
}
