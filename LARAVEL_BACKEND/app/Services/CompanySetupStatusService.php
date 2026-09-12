<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\WhatsAppAccount;
use App\Services\Platform\EntitlementService;

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

        // Starter has no WhatsApp allowance — don't dangle an uncompletable step.
        // (Grandfathered connections still count: connected step shows done.)
        $limits = app(EntitlementService::class)->limitsForCompany($company);
        $planIncludesWhatsapp = ((int) ($limits['whatsapp_numbers'] ?? 0)) > 0 || $whatsappConnected;

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

        $steps = [];
        if ($planIncludesWhatsapp) {
            $steps[] = [
                'id' => 'whatsapp',
                'title' => 'Connect WhatsApp',
                'description' => 'Settings → WhatsApp → Continue with Facebook. Two minutes, no developer account — chats and AI replies land in your inbox.',
                'href' => '/dashboard/settings?tab=whatsapp',
                'done' => $whatsappConnected,
            ];
        }
        $steps[] = [
            'id' => 'product',
            'title' => 'Add your first product',
            'description' => 'Products → Add product: name, price, one photo. One live product opens your shop.',
            'href' => '/dashboard/products',
            'done' => $hasProduct,
        ];
        $steps[] = [
            'id' => 'payments',
            'title' => 'Switch on a payout method',
            'description' => 'Settings → Payments → M-Pesa on, then your Till or PayBill number. No payout method, no paid orders.',
            'href' => '/dashboard/settings?tab=order-payments',
            'done' => $paymentsEnabled,
        ];
        $steps[] = [
            'id' => 'business',
            'title' => 'Add your business phone',
            'description' => 'Settings → Business → phone number. Receipts, order alerts and your store footer use it.',
            'href' => '/dashboard/settings?tab=profile',
            'done' => $businessBasics,
        ];
        $steps[] = [
            'id' => 'storefront',
            'title' => 'Open your storefront',
            'description' => 'Storefront page → flip the switch on, pick your link (relayiq.com/s/your-shop), save it.',
            'href' => '/dashboard/storefront',
            'done' => $storefrontReady,
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
