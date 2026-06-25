<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Product;
use App\Services\AI\ReplyGuardService;
use App\Services\AI\TokenEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSupportServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_estimator_approximates_length(): void
    {
        $this->assertSame(3, TokenEstimator::estimate('hello world!'));
    }

    public function test_reply_guard_rejects_unknown_prices(): void
    {
        $company = Company::create([
            'name' => 'Shop',
            'email' => 'shop@test.local',
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'price' => 99.00,
            'status' => 'active',
        ]);

        $guard = app(ReplyGuardService::class);
        $safe = $guard->guard($company, 'The Widget costs 99.');
        $unsafe = $guard->guard($company, 'That item is only 49.99 today.');

        $this->assertStringContainsString('99', $safe);
        $this->assertStringContainsString('see catalog for price', $unsafe);
        $this->assertStringContainsString('full product list', $unsafe);
    }
}
