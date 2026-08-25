<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Services\AI\AiLearningConfig;
use App\Services\ConversationLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_flags_suspect_price_reply_as_pending(): void
    {
        PlatformSetting::create(['platform_name' => 'Test']);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Test Co', 'email' => 'test@test.local']);
        CompanySetting::create(['company_id' => $company->id]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'price' => 100.0,
            'status' => 'active',
        ]);

        $service = app(ConversationLearningService::class);

        // Assistant reply contains an unverified price (999.00)
        $sample = $service->storeSample(
            $company->id,
            'How much is the super Widget?',
            'The super Widget is priced at 999 dollars each.',
        );

        $this->assertNotNull($sample);
        $this->assertSame(ConversationLearningSample::STATUS_PENDING, $sample->status);
        $this->assertSame('auto_flagged: reply contained unverified price', $sample->review_notes);
    }

    public function test_approves_verified_price_reply_when_review_not_required(): void
    {
        PlatformSetting::create(['platform_name' => 'Test']);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Test Co', 'email' => 'test@test.local']);
        CompanySetting::create(['company_id' => $company->id]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'price' => 100.0,
            'status' => 'active',
        ]);

        $service = app(ConversationLearningService::class);

        // Assistant reply contains verified price (100)
        $sample = $service->storeSample(
            $company->id,
            'How much is the Widget?',
            'The Widget costs 100 dollars.',
        );

        $this->assertNotNull($sample);
        $this->assertSame(ConversationLearningSample::STATUS_APPROVED, $sample->status);
        $this->assertNull($sample->review_notes);
    }
}
