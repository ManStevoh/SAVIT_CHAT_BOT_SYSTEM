<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppCampaignRecipientJob;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppCampaignWizardTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): array
    {
        $company = Company::create(['name' => 'Campaign Co', 'email' => 'camp@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pn-camp',
            'display_phone_number' => '254700000001',
            'access_token' => 'test-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    public function test_create_and_send_campaign_queues_jobs(): void
    {
        Queue::fake();
        [$company, $user] = $this->companyUser();

        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711111111',
            'customer_name' => 'Alice',
            'status' => 'open',
        ]);

        WhatsAppMessageTemplate::create([
            'company_id' => $company->id,
            'name' => 'promo_poster_v1',
            'language' => 'en',
            'category' => 'marketing',
            'status' => 'approved',
            'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE'],
                ['type' => 'BODY', 'text' => 'Hello {{1}}'],
            ],
            'body_preview' => 'Hello {{1}}',
        ]);

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/whatsapp/campaigns', [
            'name' => 'Summer promo',
            'segment' => 'all',
            'caption' => 'Big weekend sale — reply to order!',
            'posterUrl' => 'https://example.com/poster.png',
            'templateName' => 'promo_poster_v1',
        ]);
        $create->assertCreated()->assertJsonPath('success', true);
        $campaignId = $create->json('campaign.id');

        $send = $this->postJson("/api/company/whatsapp/campaigns/{$campaignId}/send");
        $send->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('whatsapp_campaigns', [
            'id' => $campaignId,
            'status' => 'sending',
            'total_recipients' => 1,
        ]);

        Queue::assertPushed(SendWhatsAppCampaignRecipientJob::class, 1);
    }

    public function test_recipient_job_sends_template_with_image_header(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        [$company, $user] = $this->companyUser();

        $campaign = WhatsAppCampaign::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Test',
            'status' => WhatsAppCampaign::STATUS_SENDING,
            'segment' => 'all',
            'template_name' => 'promo_poster_v1',
            'language_code' => 'en',
            'poster_url' => 'https://example.com/poster.png',
            'caption' => 'Sale now',
            'body_parameters' => ['Sale now'],
            'total_recipients' => 1,
        ]);

        $recipient = WhatsAppCampaignRecipient::create([
            'whatsapp_campaign_id' => $campaign->id,
            'customer_phone' => '254711111111',
            'customer_name' => 'Alice',
            'status' => WhatsAppCampaignRecipient::STATUS_PENDING,
        ]);

        $job = new SendWhatsAppCampaignRecipientJob($recipient->id);
        $job->handle(
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\WhatsApp\WhatsAppCampaignDispatchService::class),
        );

        $recipient->refresh();
        $campaign->refresh();

        $this->assertSame(WhatsAppCampaignRecipient::STATUS_SENT, $recipient->status);
        $this->assertSame(WhatsAppCampaign::STATUS_COMPLETED, $campaign->status);
        $this->assertSame(1, $campaign->sent_count);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $components = $body['template']['components'] ?? [];

            return ($components[0]['type'] ?? '') === 'header'
                && ($components[0]['parameters'][0]['type'] ?? '') === 'image';
        });
    }

    public function test_send_rejects_poster_without_image_header_template(): void
    {
        [$company, $user] = $this->companyUser();

        WhatsAppMessageTemplate::create([
            'company_id' => $company->id,
            'name' => 'text_only',
            'language' => 'en',
            'category' => 'utility',
            'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Hi']],
            'body_preview' => 'Hi',
        ]);

        $campaign = WhatsAppCampaign::create([
            'company_id' => $company->id,
            'name' => 'Bad',
            'status' => WhatsAppCampaign::STATUS_DRAFT,
            'segment' => 'all',
            'template_name' => 'text_only',
            'poster_url' => 'https://example.com/poster.png',
            'caption' => 'Hi',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/whatsapp/campaigns/{$campaign->id}/send")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
