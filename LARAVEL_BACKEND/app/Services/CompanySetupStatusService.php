<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\WhatsAppAccount;

/**
 * Compute Getting Started checklist progress for a company dashboard.
 */
class CompanySetupStatusService
{
    /**
     * @return array{
     *   steps: list<array{id: string, title: string, description: string, href: string, done: bool}>,
     *   completedCount: int,
     *   totalCount: int,
     *   percent: int,
     *   dismissed: bool,
     *   isComplete: bool
     * }
     */
    public function status(Company $company): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;

        $whatsappConnected = WhatsAppAccount::query()
            ->where('company_id', $company->id)
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhereIn('onboarding_status', ['active', 'completed', 'connected']);
            })
            ->exists();

        $hasProduct = Product::query()->where('company_id', $company->id)->exists();

        $paymentsEnabled = $settings && (
            (bool) $settings->orders_accept_mpesa
            || (bool) $settings->orders_accept_stripe
            || (bool) $settings->orders_accept_paystack
            || (bool) $settings->orders_accept_pesapal
            || (bool) $settings->orders_accept_flutterwave
            || (bool) $settings->orders_accept_paypal
            || (bool) $settings->orders_accept_cod
        );

        $phone = trim((string) ($company->phone ?? ''));
        $ownerPhone = trim((string) ($settings?->owner_whatsapp_phone ?? ''));
        $hasContactPhone = $phone !== '' || $ownerPhone !== '';
        $businessBasics = $hasContactPhone;

        $storefrontReady = (bool) $company->storefront_enabled
            && is_string($company->store_slug)
            && trim($company->store_slug) !== '';

        $steps = [
            [
                'id' => 'whatsapp',
                'title' => 'Connect WhatsApp',
                'description' => 'Link your WhatsApp Business number so the AI can reply to customers.',
                'href' => '/dashboard/settings?tab=whatsapp',
                'done' => $whatsappConnected,
            ],
            [
                'id' => 'product',
                'title' => 'Add your first product',
                'description' => 'Add a physical, digital, or service item to your catalog.',
                'href' => '/dashboard/products',
                'done' => $hasProduct,
            ],
            [
                'id' => 'payments',
                'title' => 'Turn on a payment method',
                'description' => 'Enable M-Pesa, Paystack, Stripe, or cash on delivery for orders.',
                'href' => '/dashboard/settings?tab=order-payments',
                'done' => $paymentsEnabled,
            ],
            [
                'id' => 'business',
                'title' => 'Set business basics',
                'description' => 'Add your business phone and timezone so customers get accurate replies.',
                'href' => '/dashboard/settings?tab=profile',
                'done' => $businessBasics,
            ],
            [
                'id' => 'storefront',
                'title' => 'Enable your storefront',
                'description' => 'Turn on the web shop and choose a store slug customers can visit.',
                'href' => '/dashboard/storefront',
                'done' => $storefrontReady,
            ],
        ];

        $completed = count(array_filter($steps, static fn (array $s): bool => $s['done']));
        $total = count($steps);
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 100;

        return [
            'steps' => $steps,
            'completedCount' => $completed,
            'totalCount' => $total,
            'percent' => $percent,
            'dismissed' => $company->setup_checklist_dismissed_at !== null,
            'isComplete' => $completed >= $total,
        ];
    }
}
