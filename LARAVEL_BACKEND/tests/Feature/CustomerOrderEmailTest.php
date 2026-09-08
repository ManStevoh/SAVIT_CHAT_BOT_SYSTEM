<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\StorefrontCustomer;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingMailService extends MailService
{
    /** @var list<array{to: string, subject: string, htmlBody: string}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $this->sent[] = compact('to', 'subject', 'htmlBody');
    }
}

class CustomerOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_purchase_sends_confirmation_to_checkout_email(): void
    {
        $mail = $this->bindRecordingMail();
        [$company, $ebook] = $this->seedDigitalStore();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", ['productId' => $ebook->id, 'quantity' => 1]);
        $this->post("/s/{$slug}/checkout?phone=254711888000", [
            'customerName' => 'Pat Buyer',
            'customerPhone' => '254711888000',
            'customerEmail' => 'pat@example.com',
            'fulfillmentType' => 'digital',
            'acceptTerms' => true,
        ])->assertRedirect();

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pat@example.com', $order->customer_email);
        $this->assertNotEmpty($mail->sent);
        $this->assertTrue(
            $this->mailContains($mail, 'pat@example.com', $order->order_number),
            'Confirmation email should include the order number for the checkout address.'
        );
        $this->assertTrue($this->mailContains($mail, 'pat@example.com', 'Brand Playbook'));
    }

    public function test_paid_digital_order_emails_download_link(): void
    {
        Storage::fake('local');
        $mail = $this->bindRecordingMail();

        [$company, $ebook] = $this->seedDigitalStore();
        $path = 'products/'.$company->id.'/digital/guide.pdf';
        Storage::disk('local')->put($path, 'PDF-BYTES');
        $ebook->update([
            'digital_file_path' => $path,
            'digital_file_name' => 'guide.pdf',
            'digital_file_mime' => 'application/pdf',
            'digital_file_size' => 9,
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-MAIL-1',
            'customer_name' => 'Pat Buyer',
            'customer_email' => 'pat@example.com',
            'customer_phone' => '254711888000',
            'total' => 25,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $ebook->id,
            'name' => $ebook->name,
            'quantity' => 1,
            'price' => 25,
            'fulfillment_data' => $ebook->fresh()->fulfillmentSnapshot(),
        ]);

        $mail->sent = [];
        app(OrderPaymentService::class)->markOrderPaid($order->fresh());

        $line = $order->orderProducts()->first();
        $downloadUrl = $line?->fulfillment_data['digitalFileUrl'] ?? null;
        $this->assertNotEmpty($downloadUrl);
        $this->assertNotEmpty($mail->sent, 'Payment should email the customer.');
        $this->assertTrue(
            $this->mailContains($mail, 'pat@example.com', (string) $downloadUrl),
            'Paid digital orders should email the signed download link.'
        );
        $this->assertTrue($this->mailContains($mail, 'pat@example.com', 'Payment received'));
        $this->assertTrue($this->mailContains($mail, 'pat@example.com', 'guide.pdf'));
    }

    public function test_confirmation_is_skipped_when_no_customer_email(): void
    {
        $mail = $this->bindRecordingMail();
        [$company] = $this->seedDigitalStore();
        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-NO-MAIL',
            'customer_name' => 'No Email',
            'customer_phone' => '254700000000',
            'total' => 10,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        app(MailService::class)->sendCustomerOrderConfirmation($order);
        app(MailService::class)->sendCustomerPaymentFulfillment($order);

        $this->assertSame([], $mail->sent);
    }

    public function test_paid_order_auto_provisions_customer_account_and_dispatches_password_setup_email(): void
    {
        $mail = $this->bindRecordingMail();
        [$company, $ebook] = $this->seedDigitalStore();

        // 1. Pending unpaid order: No password set
        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-AUTO-ACC',
            'customer_name' => 'Auto User',
            'customer_email' => 'autouser@example.com',
            'customer_phone' => '254711999111',
            'total' => 25,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseMissing('storefront_customers', [
            'company_id' => $company->id,
            'email' => 'autouser@example.com',
        ]);

        // 2. Mark order as paid: Automatically creates StorefrontCustomer and dispatches setup email
        app(OrderPaymentService::class)->markOrderPaid($order->fresh());

        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', 'autouser@example.com')
            ->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->password);
        $this->assertTrue(
            $this->mailContains($mail, 'autouser@example.com', 'account'),
            'Account setup email should be sent.'
        );

        // 3. check-email API reports customer exists without password
        $slug = $company->store_slug;
        $res = $this->postJson("/s/{$slug}/account/check-email", ['email' => 'autouser@example.com']);
        $res->assertOk()
            ->assertJson([
                'exists' => true,
                'hasPassword' => false,
                'name' => 'Auto User',
            ]);

        // 4. Set password
        $customer->update(['password' => bcrypt('Secret123!')]);

        $res2 = $this->postJson("/s/{$slug}/account/check-email", ['email' => 'autouser@example.com']);
        $res2->assertOk()
            ->assertJson([
                'exists' => true,
                'hasPassword' => true,
                'name' => 'Auto User',
            ]);

        // 5. Duplicate registration attempt fails cleanly
        $resRegister = $this->postJson("/s/{$slug}/account/register", [
            'name' => 'Auto User',
            'email' => 'autouser@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'acceptTerms' => true,
        ]);
        $resRegister->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    private function bindRecordingMail(): RecordingMailService
    {
        $mail = new RecordingMailService;
        $this->app->instance(MailService::class, $mail);

        return $mail;
    }

    private function mailContains(RecordingMailService $mail, string $to, string $needle): bool
    {
        foreach ($mail->sent as $message) {
            if ($message['to'] !== $to) {
                continue;
            }
            $haystack = html_entity_decode($message['subject'].' '.$message['htmlBody'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: Company, 1: Product}
     */
    private function seedDigitalStore(): array
    {
        $company = Company::create([
            'name' => 'Mail Studio',
            'email' => 'studio@test.local',
            'status' => 'active',
            'store_slug' => 'mail-studio',
            'storefront_enabled' => true,
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_cod' => true,
            'notifications_enabled' => false,
            'display_currency' => 'KES',
        ]);
        $ebook = Product::create([
            'company_id' => $company->id,
            'name' => 'Brand Playbook',
            'slug' => 'brand-playbook',
            'price' => 25,
            'stock' => 100,
            'status' => 'active',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
        ]);

        return [$company, $ebook];
    }
}
