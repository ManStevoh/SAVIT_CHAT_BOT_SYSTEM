<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PlatformSetting;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\ReplyGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyGuardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithProduct(float $price = 500.0): Company
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 'test@test.local']);
        CompanySetting::create(['company_id' => $company->id]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'price' => $price,
            'status' => 'active',
        ]);

        return $company;
    }

    public function test_allows_known_price(): void
    {
        $company = $this->makeCompanyWithProduct(500.0);
        $guard = app(ReplyGuardService::class);

        $reply = $guard->guardPrices($company, 'The Widget costs 500.');
        $this->assertStringContainsString('500', $reply);
        $this->assertStringNotContainsString('see catalog for price', $reply);
    }

    public function test_replaces_unknown_price(): void
    {
        $company = $this->makeCompanyWithProduct(500.0);
        $guard = app(ReplyGuardService::class);

        $reply = $guard->guardPrices($company, 'The Widget costs 999.');
        $this->assertStringContainsString('see catalog for price', $reply);
    }

    public function test_ignores_numbers_below_default_threshold(): void
    {
        $company = $this->makeCompanyWithProduct(500.0);
        $guard = app(ReplyGuardService::class);

        // 15 is below the default threshold of 20, should be ignored
        $reply = $guard->guardPrices($company, 'We have 15 items in stock at 500 each.');
        $this->assertStringNotContainsString('see catalog for price', $reply);
    }

    public function test_configurable_threshold_catches_low_prices(): void
    {
        config(['agent.reply_guard.ignore_below_price' => 0]);

        $company = $this->makeCompanyWithProduct(500.0);
        $guard = app(ReplyGuardService::class);

        // With threshold = 0, price 15 should be flagged as unknown
        $reply = $guard->guardPrices($company, 'The item costs 15 only.');
        $this->assertStringContainsString('see catalog for price', $reply);
    }

    public function test_ignores_year_like_numbers(): void
    {
        $company = $this->makeCompanyWithProduct(500.0);
        $guard = app(ReplyGuardService::class);

        $reply = $guard->guardPrices($company, 'Founded in 2024, we sell at 500.');
        $this->assertStringNotContainsString('see catalog for price', $reply);
    }

    public function test_stock_guard_flags_out_of_stock_claims(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 'test@test.local']);
        CompanySetting::create(['company_id' => $company->id]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Gadget',
            'price' => 100.0,
            'stock' => 0,
            'status' => 'active',
        ]);

        $guard = app(ReplyGuardService::class);
        $reply = $guard->guardStockClaims($company, 'The Gadget is in stock and available now!');
        $this->assertStringContainsString('may be out of stock', $reply);
    }
}
