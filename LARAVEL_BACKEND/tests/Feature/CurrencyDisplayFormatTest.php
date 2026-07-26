<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrencyDisplayFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_formatter_uses_custom_symbol_and_separators(): void
    {
        $formatted = MoneyFormatter::format(1234567.89, 'KES', [
            'symbol' => 'KSh',
            'thousands' => '.',
            'decimal' => ',',
        ]);

        $this->assertSame('KSh 1.234.567,89', $formatted);
    }

    public function test_settings_api_saves_and_returns_currency_display_options(): void
    {
        $company = Company::create([
            'name' => 'Currency Co',
            'email' => 'currency@test.local',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_admin',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'KES',
        ]);

        Sanctum::actingAs($user);

        $put = $this->putJson('/api/company/settings', [
            'displayCurrency' => 'KES',
            'currencySymbol' => 'KSh',
            'thousandsSeparator' => '.',
            'decimalSeparator' => ',',
        ]);
        $put->assertOk();

        $show = $this->getJson('/api/company/settings');
        $show->assertOk()
            ->assertJsonPath('displayCurrency', 'KES')
            ->assertJsonPath('currencySymbol', 'KSh')
            ->assertJsonPath('thousandsSeparator', '.')
            ->assertJsonPath('decimalSeparator', ',');

        $settings = CompanySetting::query()->where('company_id', $company->id)->first();
        $this->assertSame('KSh 1.234,56', MoneyFormatter::formatFromSettings(1234.56, $settings));
    }

    public function test_changing_thousands_separator_pairs_decimal_when_decimal_omitted(): void
    {
        $company = Company::create([
            'name' => 'Pair Co',
            'email' => 'pair@test.local',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_admin',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'EUR',
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/company/settings', [
            'thousandsSeparator' => '.',
        ])->assertOk();

        $show = $this->getJson('/api/company/settings');
        $show->assertOk()
            ->assertJsonPath('thousandsSeparator', '.')
            ->assertJsonPath('decimalSeparator', ',');
    }
}
