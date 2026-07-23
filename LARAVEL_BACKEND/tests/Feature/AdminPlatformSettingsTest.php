<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_show_returns_ok(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure(['platformName', 'whatsappManualConnectEnabled', 'aiLearningConfig']);
    }

    public function test_admin_settings_show_survives_invalid_json_columns(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        DB::table('platform_settings')->where('id', $settings->id)->update([
            'ai_learning_config' => '{not-json',
            'landing_trusted_companies' => '{not-json',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('platformName', 'RelayIQ');
    }

    public function test_admin_settings_update_does_not_overwrite_secrets_with_mask(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'whatsapp_webhook_verify_token' => 'real-verify-token',
            'meta_app_secret' => 'real-meta-secret',
            'whatsapp_embedded_app_secret' => 'real-embedded-secret',
        ])->save();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->putJson('/api/admin/settings', [
            'whatsappWebhookVerifyToken' => '********',
            'metaAppSecret' => '********',
            'whatsappEmbeddedAppSecret' => '********',
            'whatsappEmbeddedAppId' => '846055524940193',
        ])->assertOk()->assertJsonPath('success', true);

        $settings->refresh();
        $this->assertSame('real-verify-token', $settings->getRawOriginal('whatsapp_webhook_verify_token'));
        $this->assertSame('real-meta-secret', $settings->getRawOriginal('meta_app_secret'));
        $this->assertSame('real-embedded-secret', $settings->getRawOriginal('whatsapp_embedded_app_secret'));
        $this->assertSame('846055524940193', $settings->whatsapp_embedded_app_id);
    }
}
