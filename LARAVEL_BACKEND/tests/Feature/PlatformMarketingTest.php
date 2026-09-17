<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformMarketingMessage;
use App\Models\PlatformMarketingSend;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlatformMarketingService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
    }

    public function test_admin_can_create_schedule_and_send_campaign(): void
    {
        $this->actingAsAdmin();
        $merchant = $this->makeMerchant(consent: true);

        $create = $this->postJson('/api/admin/marketing', [
            'name' => 'April growth',
            'title' => 'Launch ads this week',
            'bodyText' => 'Growth Engine can draft posts for you.',
            'channelEmail' => true,
            'channelWhatsapp' => true,
            'channelPopup' => true,
            'audience' => 'marketing_consent',
            'status' => 'active',
            'sendMode' => 'manual',
            'ctaLabel' => 'Open Growth',
            'ctaUrl' => 'https://relayiq.app/dashboard/growth',
        ])->assertCreated()->assertJsonPath('success', true);

        $id = $create->json('message.id');
        $this->assertNotNull($id);

        $this->postJson("/api/admin/marketing/{$id}/send")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('platform_marketing_sends', [
            'message_id' => $id,
            'user_id' => $merchant->id,
            'channel' => 'email',
            'status' => PlatformMarketingSend::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('platform_marketing_sends', [
            'message_id' => $id,
            'user_id' => $merchant->id,
            'channel' => 'whatsapp',
            'status' => PlatformMarketingSend::STATUS_SKIPPED,
        ]);

        Sanctum::actingAs($merchant);
        $this->getJson('/api/company/marketing-popups')
            ->assertOk()
            ->assertJsonPath('popups.0.title', 'Launch ads this week');

        $this->postJson("/api/company/marketing-popups/{$id}/dismiss")->assertOk();
        $this->getJson('/api/company/marketing-popups')
            ->assertOk()
            ->assertJsonPath('popups', []);
    }

    public function test_email_and_whatsapp_skip_merchants_without_consent(): void
    {
        $this->actingAsAdmin();
        $merchant = $this->makeMerchant(consent: false);

        $id = $this->postJson('/api/admin/marketing', [
            'name' => 'No consent blast',
            'title' => 'Please ignore',
            'bodyText' => 'Should not email.',
            'channelEmail' => true,
            'channelWhatsapp' => true,
            'channelPopup' => false,
            'audience' => 'all_merchants',
            'status' => 'active',
            'sendMode' => 'manual',
        ])->json('message.id');

        $this->postJson("/api/admin/marketing/{$id}/send")->assertOk();

        $this->assertDatabaseHas('platform_marketing_sends', [
            'message_id' => $id,
            'user_id' => $merchant->id,
            'channel' => 'email',
            'status' => PlatformMarketingSend::STATUS_SKIPPED,
        ]);
    }

    public function test_scheduled_campaign_sends_when_due(): void
    {
        $this->makeMerchant(consent: true);
        $message = PlatformMarketingMessage::create([
            'name' => 'Scheduled tip',
            'title' => 'Share your shop',
            'body_text' => 'Put the link on WhatsApp status.',
            'channel_email' => true,
            'channel_whatsapp' => false,
            'channel_popup' => false,
            'audience' => 'marketing_consent',
            'status' => PlatformMarketingMessage::STATUS_SCHEDULED,
            'send_mode' => PlatformMarketingMessage::MODE_SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

        $result = app(PlatformMarketingService::class)->processDue();
        $this->assertGreaterThan(0, $result['sent']);
        $this->assertSame(PlatformMarketingMessage::STATUS_ACTIVE, $message->fresh()->status);
        $this->assertDatabaseHas('platform_marketing_sends', [
            'message_id' => $message->id,
            'channel' => 'email',
            'status' => PlatformMarketingSend::STATUS_SENT,
        ]);
    }

    public function test_recurring_campaign_sends_once_per_period(): void
    {
        $this->makeMerchant(consent: true);
        $message = PlatformMarketingMessage::create([
            'name' => 'Weekly tip',
            'title' => 'Weekly growth',
            'body_text' => 'Post today.',
            'channel_email' => true,
            'channel_whatsapp' => false,
            'channel_popup' => false,
            'audience' => 'marketing_consent',
            'status' => PlatformMarketingMessage::STATUS_ACTIVE,
            'send_mode' => PlatformMarketingMessage::MODE_RECURRING,
            'recurring_interval' => 'daily',
        ]);

        $marketing = app(PlatformMarketingService::class);
        $first = $marketing->processDue();
        $second = $marketing->processDue();
        $this->assertGreaterThan(0, $first['sent']);
        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, PlatformMarketingSend::query()->where('message_id', $message->id)->where('channel', 'email')->count());
    }

    public function test_register_trigger_sends_to_opted_in_user(): void
    {
        PlatformMarketingMessage::create([
            'name' => 'Hello trigger',
            'title' => 'Thanks for joining',
            'body_text' => 'Add a product today.',
            'channel_email' => true,
            'channel_whatsapp' => false,
            'channel_popup' => false,
            'audience' => 'marketing_consent',
            'status' => PlatformMarketingMessage::STATUS_ACTIVE,
            'send_mode' => PlatformMarketingMessage::MODE_TRIGGER,
            'trigger' => 'registered',
            'trigger_delay_seconds' => 0,
        ]);

        $this->seed(\Database\Seeders\PlanSeeder::class);
        PlatformSetting::query()->update([
            'allow_new_registrations' => true,
            'require_email_verification' => false,
        ]);

        $this->postJson('/api/auth/register', [
            'companyName' => 'Trigger Co',
            'name' => 'Cara',
            'email' => 'cara@trigger.test',
            'phone' => '254700333444',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
            'marketingConsent' => true,
        ])->assertOk();

        $user = User::where('email', 'cara@trigger.test')->firstOrFail();
        $this->assertDatabaseHas('platform_marketing_sends', [
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => PlatformMarketingSend::STATUS_SENT,
        ]);
    }

    public function test_login_trigger_and_popup_and_whatsapp_when_platform_number_configured(): void
    {
        $this->mock(WhatsAppMessageSenderService::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->andReturn(['success' => false, 'error' => 'missing template']);
            $mock->shouldReceive('sendInteractiveCtaUrl')->andReturn(['success' => true, 'message_id' => 'wamid.test']);
            $mock->shouldReceive('sendText')->andReturn(['success' => true, 'message_id' => 'wamid.text']);
        });

        PlatformSetting::query()->update([
            'lifecycle_whatsapp_enabled' => true,
            'lifecycle_whatsapp_phone_number_id' => '123456789',
            'lifecycle_whatsapp_access_token' => 'token-plain',
        ]);

        $merchant = $this->makeMerchant(consent: true);
        PlatformMarketingMessage::create([
            'name' => 'Login hello',
            'title' => 'Welcome back',
            'body_text' => 'Check yesterday’s orders.',
            'channel_email' => false,
            'channel_whatsapp' => true,
            'channel_popup' => true,
            'audience' => 'marketing_consent',
            'status' => PlatformMarketingMessage::STATUS_ACTIVE,
            'send_mode' => PlatformMarketingMessage::MODE_TRIGGER,
            'trigger' => 'login',
            'trigger_delay_seconds' => 0,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $merchant->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('platform_marketing_sends', [
            'user_id' => $merchant->id,
            'channel' => 'whatsapp',
            'status' => PlatformMarketingSend::STATUS_SENT,
        ]);

        Sanctum::actingAs($merchant->fresh());
        $this->getJson('/api/company/marketing-popups')
            ->assertOk()
            ->assertJsonPath('popups.0.title', 'Welcome back');
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $merchant = $this->makeMerchant(consent: true);
        Sanctum::actingAs($merchant);
        $this->getJson('/api/admin/marketing')->assertForbidden();
    }

    public function test_marketing_command_runs(): void
    {
        $this->artisan('platform:marketing-run', ['--sync' => true])->assertSuccessful();
    }

    public function test_admin_can_save_platform_whatsapp_credentials(): void
    {
        $this->actingAsAdmin();
        $this->putJson('/api/admin/settings', [
            'lifecycleWhatsappEnabled' => true,
            'lifecycleWhatsappPhoneNumberId' => '999888',
            'lifecycleWhatsappAccessToken' => 'secret-token',
            'lifecycleWhatsappTemplateLang' => 'en',
        ])->assertOk();

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('lifecycleWhatsappPhoneNumberId', '999888')
            ->assertJsonPath('lifecycleWhatsappAccessToken', '********');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));
    }

    private function makeMerchant(bool $consent): User
    {
        $company = Company::factory()->create(['status' => 'active']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
            'marketing_consent' => $consent,
            'phone' => '254722000111',
            'password' => 'password',
        ]);
    }
}
