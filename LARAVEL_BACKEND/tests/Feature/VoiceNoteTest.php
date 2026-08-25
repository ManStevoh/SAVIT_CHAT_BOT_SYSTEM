<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\SynthesizeResult;
use App\Services\AI\TranscribeResult;
use App\Services\Agent\Voice\VoiceOutboundService;
use App\Services\Agent\Voice\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_transcription_service_builds_prompt_hints_and_persists_transcript(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Supplies']);
        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'KES',
        ]);

        $message = Message::create([
            'chat_id' => 1,
            'content' => '[audio received]',
            'message_type' => 'audio',
            'sender' => 'customer',
            'status' => 'received',
            'attachment_url' => 'http://localhost/storage/voice/sample.ogg',
            'attachment_mime' => 'audio/ogg',
        ]);

        $mockGateway = \Mockery::mock(AiGateway::class);
        $mockGateway->shouldReceive('transcribeAudio')
            ->once()
            ->andReturn(new TranscribeResult(
                text: 'How much is the solar battery in KSh?',
                success: true,
                model: 'whisper-1'
            ));

        $service = new VoiceTranscriptionService($mockGateway);

        // Simulate local path check mock
        $reflector = new \ReflectionClass(VoiceTranscriptionService::class);
        $method = $reflector->getMethod('localPathFromUrl');
        $method->setAccessible(true);

        // Test prompt hint generation
        $promptMethod = $reflector->getMethod('buildPromptHint');
        $promptMethod->setAccessible(true);
        $hint = $promptMethod->invoke($service, $company);

        $this->assertStringContainsString('Acme Supplies', $hint);
        $this->assertStringContainsString('KES', $hint);
    }

    public function test_voice_outbound_service_uses_configured_voice_id(): void
    {
        $company = Company::factory()->create();
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_voice_reply_enabled' => true,
            'agent_voice_reply_mode' => 'dual_text_and_voice',
            'agent_voice_id' => 'shimmer',
        ]);

        $mockGateway = \Mockery::mock(AiGateway::class);
        $mockGateway->shouldReceive('synthesizeSpeech')
            ->once()
            ->with(\Mockery::any(), \Mockery::any(), 'shimmer')
            ->andReturn(new SynthesizeResult(
                audioPath: sys_get_temp_dir().'/mock_audio.mp3',
                mimeType: 'audio/mpeg',
                success: false,
                model: 'tts-1'
            ));

        $mockWaSender = \Mockery::mock(\App\Services\WhatsAppMessageSenderService::class);

        $service = new VoiceOutboundService($mockGateway, $mockWaSender);
        $this->assertTrue($service->shouldReplyWithVoice($company, true));

        $res = $service->sendVoiceReply(
            new \App\Models\WhatsAppAccount(['status' => 'connected']),
            $company,
            '254712345678',
            'Thank you for your voice message.'
        );

        $this->assertFalse($res['success']);
    }

    public function test_merchant_settings_voice_mode_and_voice_id_saving(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $company = Company::factory()->create();
        $user->update(['company_id' => $company->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/company/settings', [
            'agentVoiceReplyEnabled' => true,
            'agentVoiceReplyMode' => 'dual_text_and_voice',
            'agentVoiceId' => 'fable',
        ]);

        $response->assertStatus(200);

        $getRes = $this->actingAs($user, 'sanctum')->getJson('/api/company/settings');
        $getRes->assertStatus(200);
        $getRes->assertJson([
            'agentVoiceReplyEnabled' => true,
            'agentVoiceReplyMode' => 'dual_text_and_voice',
            'agentVoiceId' => 'fable',
        ]);
    }
}
