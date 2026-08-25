<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Tools\SendOrderInvoiceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendOrderInvoiceToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_and_sends_whatsapp_document(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'media-1'], 200)
                ->push(['messages' => [['id' => 'wamid.invoice-1']]], 200),
        ]);

        $company = Company::create([
            'name' => 'Wafula Stores',
            'email' => 'wafula@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'USD',
            'agent_commerce_enabled' => true,
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-inv',
            'access_token' => 'token-inv',
            'whatsapp_business_account_id' => 'waba-inv',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111222',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-INV-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111222',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 2000,
        ]);
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Headphones',
            'quantity' => 10,
            'price' => 200,
        ]);

        $context = new AgentToolContext(
            company: $company->fresh(['settings', 'whatsappAccount']),
            chat: $chat,
            customerPhone: '254700111222',
            customerName: 'Buyer',
            incomingMessage: 'share the invoice',
        );

        $result = app(SendOrderInvoiceTool::class)->execute($context, []);

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertArrayNotHasKey('pdf_error', $result, json_encode($result));
        $this->assertSame('ORD-INV-1', $result['order_number']);
        $this->assertTrue($result['whatsapp_sent'] ?? false);
        $this->assertNotEmpty($result['receipt_url'] ?? null);
        $this->assertNotEmpty($result['pdf_filename'] ?? null, json_encode($result));
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists(
                'invoices/'.$company->id.'/'.$result['pdf_filename']
            )
        );
    }

    public function test_returns_clear_error_when_no_order(): void
    {
        $company = Company::create([
            'name' => 'Empty Co',
            'email' => 'empty@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-empty',
            'access_token' => 'token',
            'whatsapp_business_account_id' => 'waba',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000000',
            'customer_name' => 'NoOrder',
            'status' => 'active',
        ]);

        $context = new AgentToolContext(
            company: $company->fresh(['settings', 'whatsappAccount']),
            chat: $chat,
            customerPhone: '254700000000',
            customerName: 'NoOrder',
            incomingMessage: 'invoice please',
        );

        $result = app(SendOrderInvoiceTool::class)->execute($context, []);
        $this->assertFalse($result['success']);
        $this->assertSame('no_order', $result['error']);
    }
}
