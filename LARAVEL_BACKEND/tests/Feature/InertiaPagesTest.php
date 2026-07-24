<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InertiaPagesTest extends TestCase
{
    /**
     * Major Inertia web routes served by PageController.
     *
     * @return array<string, array{0: string}>
     */
    public static function inertiaWebRoutesProvider(): array
    {
        return [
            'home' => ['/'],
            'pricing' => ['/pricing'],
            'about' => ['/about'],
            'contact' => ['/contact'],
            'blog' => ['/blog'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
            'reset password' => ['/reset-password'],
            'order paid' => ['/order-paid'],
            'dashboard account' => ['/dashboard/account'],
            'dashboard' => ['/dashboard'],
            'dashboard analytics' => ['/dashboard/analytics'],
            'dashboard chats' => ['/dashboard/chats'],
            'dashboard customers' => ['/dashboard/customers'],
            'dashboard faq' => ['/dashboard/faq'],
            'dashboard growth' => ['/dashboard/growth'],
            'dashboard whatsapp campaigns' => ['/dashboard/whatsapp/campaigns'],
            'dashboard orders' => ['/dashboard/orders'],
            'dashboard products' => ['/dashboard/products'],
            'dashboard bookings' => ['/dashboard/bookings'],
            'dashboard settings' => ['/dashboard/settings'],
            'dashboard subscription' => ['/dashboard/subscription'],
            'admin account' => ['/admin/account'],
            'admin' => ['/admin'],
            'admin ai usage' => ['/admin/ai-usage'],
            'admin ai learning' => ['/admin/ai-learning'],
            'admin ai models' => ['/admin/ai-models'],
            'admin companies' => ['/admin/companies'],
            'admin growth' => ['/admin/growth'],
            'admin cms' => ['/admin/cms'],
            'admin blog' => ['/admin/blog'],
            'admin landing faqs' => ['/admin/landing-faqs'],
            'admin logs' => ['/admin/logs'],
            'admin payment gateways' => ['/admin/payment-gateways'],
            'admin plans' => ['/admin/plans'],
            'admin offers' => ['/admin/offers'],
            'admin revenue' => ['/admin/revenue'],
            'admin settings' => ['/admin/settings'],
            'admin whatsapp' => ['/admin/whatsapp'],
            'admin subscriptions' => ['/admin/subscriptions'],
            'admin testimonials' => ['/admin/testimonials'],
            'admin users' => ['/admin/users'],
        ];
    }

    #[DataProvider('inertiaWebRoutesProvider')]
    public function test_inertia_web_route_returns_ok_with_inertia_payload(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $this->assertInertiaResponse($response, $path);
    }

    public function test_inertia_partial_request_returns_inertia_header(): void
    {
        $initial = $this->get('/dashboard');
        $initial->assertOk();

        $content = $initial->getContent();
        preg_match('/"version":"([^"]+)"/', (string) $content, $matches);
        $version = $matches[1] ?? '';

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/dashboard');

        $response->assertOk();
        $response->assertHeader('X-Inertia', 'true');
    }

    private function assertInertiaResponse(TestResponse $response, string $path): void
    {
        $content = $response->getContent();
        $hasDataPage = is_string($content) && str_contains($content, 'data-page');
        $hasInertiaHeader = $response->headers->has('X-Inertia');

        $this->assertTrue(
            $hasInertiaHeader || $hasDataPage,
            "Expected Inertia response for [{$path}] (X-Inertia header or data-page content)."
        );
    }
}
