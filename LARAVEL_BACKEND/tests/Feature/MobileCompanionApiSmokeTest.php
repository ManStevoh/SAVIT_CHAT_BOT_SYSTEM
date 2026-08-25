<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCompanionApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): array
    {
        $company = Company::create(['name' => 'Mobile Smoke', 'email' => 'smoke@test.local', 'status' => 'active']);
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

    public function test_mobile_companion_core_endpoints_respond(): void
    {
        [$company, $user] = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700999888',
            'customer_name' => 'Smoke Customer',
            'status' => 'active',
            'last_message' => 'Hello',
            'last_message_at' => now(),
            'unread_count' => 3,
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello',
            'sender' => 'customer',
            'status' => 'sent',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/app-branding')->assertOk()->assertJsonStructure(['applicationName']);

        $this->getJson('/api/company/chats')
            ->assertOk()
            ->assertJsonFragment(['customerPhone' => '254700999888']);

        $this->getJson("/api/company/chats/{$chat->id}/messages")
            ->assertOk()
            ->assertJsonFragment(['content' => 'Hello']);

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'unread_count' => 0,
        ]);

        $this->getJson('/api/company/orders')->assertOk()->assertJsonStructure(['orders', 'total']);
        $this->getJson('/api/company/products')->assertOk();
        $this->getJson('/api/company/faqs')->assertOk();
        $this->getJson('/api/company/customers')->assertOk()->assertJsonStructure(['customers']);
        $this->getJson('/api/company/analytics')->assertOk()->assertJsonStructure(['totalMessages', 'totalOrders']);
        $this->getJson('/api/company/notifications')->assertOk()->assertJsonStructure(['items', 'unreadCount']);
        $this->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('plan', 'professional')
            ->assertJsonPath('status', 'active')
            ->assertJsonStructure(['planName', 'daysRemaining', 'accessEndsLabel', 'isExpiringSoon']);
        $this->getJson('/api/company/growth/analytics')->assertOk();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email', $user->email);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        $product = $this->postJson('/api/company/products', [
            'name' => 'Smoke Product',
            'price' => 10.5,
            'stock' => 3,
            'category' => 'Test',
            'productType' => 'digital',
            'fulfillmentType' => 'link',
            'trackInventory' => false,
            'requiresDeliveryAddress' => false,
            'accessUrl' => 'https://example.com/course-access',
            'fulfillmentInstructions' => 'We will send your access after payment.',
        ])->assertSuccessful()
            ->assertJsonPath('product.productType', 'digital')
            ->assertJsonPath('product.fulfillmentType', 'link')
            ->assertJsonPath('product.trackInventory', false)
            ->assertJsonPath('product.requiresDeliveryAddress', false)
            ->assertJsonPath('product.accessUrl', 'https://example.com/course-access')
            ->json('product');

        $this->assertNotEmpty($product['id'] ?? null);

        $variant = $this->postJson("/api/company/products/{$product['id']}/variants", [
            'label' => 'Small',
            'price' => 9.5,
            'stock' => 2,
            'status' => 'active',
        ])->assertCreated()->json('variant');

        $this->assertSame('Small', $variant['label'] ?? null);

        $this->putJson("/api/company/product-variants/{$variant['id']}", [
            'stock' => 5,
        ])->assertOk()->assertJsonPath('success', true);

        $faq = $this->postJson('/api/company/faqs', [
            'question' => 'Hours?',
            'answer' => '9-5',
            'category' => 'general',
        ])->assertSuccessful()->json('faq');

        $this->assertNotEmpty($faq['id'] ?? null);

        $post = $this->postJson('/api/company/growth/posts', [
            'platform' => 'whatsapp',
            'title' => 'Smoke post',
            'content' => 'Hello from mobile smoke test',
            'contentType' => 'text',
        ])->assertSuccessful()->json('post');

        $this->assertNotEmpty($post['id'] ?? null);

        $this->postJson("/api/company/growth/posts/{$post['id']}/approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->deleteJson("/api/company/product-variants/{$variant['id']}")->assertOk();
        $this->deleteJson("/api/company/products/{$product['id']}")->assertOk();
        $this->deleteJson("/api/company/faqs/{$faq['id']}")->assertOk();
    }
}
