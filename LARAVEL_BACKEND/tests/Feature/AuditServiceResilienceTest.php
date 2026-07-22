<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditServiceResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_noops_when_table_missing(): void
    {
        Schema::dropIfExists('audit_events');

        $user = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $event = app(AuditService::class)->log(
            'platform.settings.updated',
            PlatformSetting::class,
            1,
            ['a' => 1],
            ['a' => 2],
            null,
            $user,
            ['changed_keys' => ['a']],
        );

        $this->assertNull($event);
    }

    public function test_settings_update_succeeds_without_audit_table(): void
    {
        Schema::dropIfExists('audit_events');
        PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'Essem']);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->putJson('/api/admin/settings', [
            'whatsappWebhookVerifyToken' => 'token-123',
            'whatsappManualConnectEnabled' => true,
        ])->assertOk()->assertJsonPath('success', true);
    }
}
