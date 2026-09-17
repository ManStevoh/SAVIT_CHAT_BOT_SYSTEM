<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\MerchantLifecycleSend;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\MerchantLifecycleService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MerchantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        Mail::fake();
        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'allow_new_registrations' => true,
            'require_email_verification' => false,
        ]);
    }

    public function test_register_sends_welcome_and_copies_phone_for_whatsapp(): void
    {
        $this->postJson('/api/auth/register', [
            'companyName' => 'Drip Co',
            'name' => 'Amina',
            'email' => 'amina@drip.test',
            'phone' => '254711000111',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
            'marketingConsent' => true,
        ])->assertOk();

        $user = User::where('email', 'amina@drip.test')->firstOrFail();
        $this->assertSame('254711000111', $user->phone);
        $this->assertTrue($user->marketing_consent);

        $this->assertDatabaseHas('merchant_lifecycle_sends', [
            'user_id' => $user->id,
            'step' => MerchantLifecycleService::STEP_WELCOME,
            'channel' => 'email',
            'status' => MerchantLifecycleSend::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('merchant_lifecycle_sends', [
            'user_id' => $user->id,
            'step' => MerchantLifecycleService::STEP_WELCOME,
            'channel' => 'whatsapp',
            'status' => MerchantLifecycleSend::STATUS_SKIPPED,
        ]);

        $settings = CompanySetting::where('company_id', $user->company_id)->first();
        $this->assertNotNull($settings);
        $this->assertSame('254711000111', $settings->owner_whatsapp_phone);
    }

    public function test_email_verify_sends_verified_step(): void
    {
        PlatformSetting::query()->update(['require_email_verification' => true]);

        $this->postJson('/api/auth/register', [
            'companyName' => 'Verify Co',
            'name' => 'Ben',
            'email' => 'ben@verify.test',
            'phone' => '254711000222',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
        ])->assertOk();

        $user = User::where('email', 'ben@verify.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        $this->get($url)->assertRedirect();

        $this->assertDatabaseHas('merchant_lifecycle_sends', [
            'user_id' => $user->id,
            'step' => MerchantLifecycleService::STEP_VERIFIED,
            'channel' => 'email',
            'status' => MerchantLifecycleSend::STATUS_SENT,
        ]);
    }

    public function test_process_due_sends_product_nudge_and_skips_marketing_without_consent(): void
    {
        config()->set('merchant_lifecycle.delays.add_first_product', 0);
        config()->set('merchant_lifecycle.delays.marketing_growth', 0);
        config()->set('merchant_lifecycle.delays.marketing_tips', 0);
        config()->set('merchant_lifecycle.delays.connect_whatsapp', 864000);
        config()->set('merchant_lifecycle.delays.enable_payments', 864000);
        config()->set('merchant_lifecycle.delays.share_store', 864000);

        $company = Company::factory()->create(['status' => 'active', 'plan' => 'professional']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
            'marketing_consent' => false,
            'phone' => '254700111222',
            'created_at' => now()->subDay(),
        ]);

        $sent = app(MerchantLifecycleService::class)->processDue($user->id);
        $this->assertGreaterThan(0, $sent['sent']);

        $this->assertDatabaseHas('merchant_lifecycle_sends', [
            'user_id' => $user->id,
            'step' => MerchantLifecycleService::STEP_ADD_FIRST_PRODUCT,
            'channel' => 'email',
            'status' => MerchantLifecycleSend::STATUS_SENT,
        ]);
        $this->assertDatabaseMissing('merchant_lifecycle_sends', [
            'user_id' => $user->id,
            'step' => MerchantLifecycleService::STEP_MARKETING_GROWTH,
        ]);
    }

    public function test_unsubscribe_clears_marketing_consent(): void
    {
        $user = User::factory()->create([
            'marketing_consent' => true,
            'marketing_consent_at' => now(),
        ]);
        $url = URL::temporarySignedRoute('marketing.unsubscribe', now()->addDay(), ['user' => $user->id]);
        $this->get($url)->assertOk()->assertSee('unsubscribed', false);
        $this->assertFalse($user->fresh()->marketing_consent);
    }

    public function test_lifecycle_command_runs_sync(): void
    {
        $this->artisan('merchant:lifecycle-run', ['--sync' => true])
            ->assertSuccessful();
    }
}
