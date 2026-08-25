<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileChatStartTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): array
    {
        $company = Company::create(['name' => 'Mobile Co', 'email' => 'mobile@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    public function test_start_chat_creates_new_conversation(): void
    {
        [$company, $user] = $this->companyUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/chats/start', [
            'phone' => '+254 700 111-222',
            'name' => 'Jane Doe',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('chat.customerName', 'Jane Doe')
            ->assertJsonPath('chat.customerPhone', '254700111222');

        $this->assertDatabaseHas('chats', [
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Jane Doe',
        ]);
    }

    public function test_start_chat_reuses_existing_phone(): void
    {
        [$company, $user] = $this->companyUser();
        $existing = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Existing',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/chats/start', [
            'phone' => '254700111222',
            'name' => 'Renamed',
        ]);

        $response->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('chat.id', (string) $existing->id)
            ->assertJsonPath('chat.customerName', 'Renamed');

        $this->assertSame(1, Chat::where('company_id', $company->id)->where('customer_phone', '254700111222')->count());
    }

    public function test_start_chat_requires_phone(): void
    {
        [, $user] = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/chats/start', ['name' => 'No Phone'])
            ->assertStatus(422);
    }
}
