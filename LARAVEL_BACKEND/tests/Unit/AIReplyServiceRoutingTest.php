<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\AIReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIReplyServiceRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AIReplyService
    {
        return app(AIReplyService::class);
    }

    private function companyWithMode(string $mode): Company
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local']);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_reply_mode' => $mode,
            'auto_reply_enabled' => true,
        ]);

        return $company->fresh(['settings']);
    }

    public function test_is_pure_greeting_only_for_exact_greetings(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isPureGreeting('hello'));
        $this->assertTrue($service->isPureGreeting('Hi!'));
        $this->assertFalse($service->isPureGreeting('hello, what are your hours?'));
        $this->assertFalse($service->isPureGreeting('hi do you deliver to westlands'));
    }

    public function test_should_skip_scripted_opening_for_substantive_first_message_in_ai_first(): void
    {
        $company = $this->companyWithMode('ai_first');
        $service = $this->service();

        $this->assertTrue($service->shouldSkipScriptedOpening($company, 'What is your refund policy?'));
        $this->assertFalse($service->shouldSkipScriptedOpening($company, 'hello'));
    }

    public function test_should_not_skip_scripted_opening_in_balanced_mode(): void
    {
        $company = $this->companyWithMode('balanced');
        $service = $this->service();

        $this->assertFalse($service->shouldSkipScriptedOpening($company, 'What is your refund policy?'));
    }

    public function test_uses_ai_first_by_default(): void
    {
        $company = Company::create(['name' => 'Default Co', 'email' => 'd@test.local']);
        CompanySetting::create(['company_id' => $company->id]);

        $this->assertTrue($this->service()->usesAiFirstRouting($company->fresh(['settings'])));
    }
}
