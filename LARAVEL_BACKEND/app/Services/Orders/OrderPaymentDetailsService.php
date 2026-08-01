<?php

namespace App\Services\Orders;

use App\Models\Company;
use App\Models\Order;
use App\Services\MpesaService;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\Services\PaymentGateways\PaymentGatewayRegistry;
use App\Support\MoneyFormatter;

/**
 * Resolve and present real payment options for an unpaid order (no inventing).
 */
class OrderPaymentDetailsService
{
    public function __construct(
        protected ?PaymentGatewayRegistry $registry = null,
    ) {
        $this->registry = $registry ?? app(PaymentGatewayRegistry::class);
    }

    /**
     * @return array{
     *   success: bool,
     *   order_number?: string,
     *   total?: string,
     *   payment_status?: string,
     *   methods?: list<string>,
     *   message?: string,
     *   customer_message?: string,
     *   error?: string
     * }
     */
    public function shareForCustomer(
        Company $company,
        string $customerPhone,
        ?string $orderNumber = null,
    ): array {
        $company->loadMissing('settings');
        $order = $this->resolveUnpaidOrder($company, $customerPhone, $orderNumber);
        if (! $order) {
            return [
                'success' => false,
                'error' => 'no_unpaid_order',
                'message' => 'No unpaid order found for this customer yet. '
                    .'Next: YOU call process_order_message with a synthesized checkout command from the thread '
                    .'(e.g. "{qty} x {ExactProductName}", then "done", then "confirm") — do not ask the customer to type that. '
                    .'Then call share_payment_details again. '
                    .'Do not transfer_to_human and do not invent payment setup messages.',
                'customer_message' => null,
            ];
        }

        $drivers = $this->registry->getAvailableDrivers($company);
        $methods = array_map(fn ($d) => $d->getId(), $drivers);
        $currency = $company->settings?->displayCurrencyCode() ?? 'USD';
        $moneyOpts = MoneyFormatter::displayOptionsFromSettings($company->settings);
        $total = MoneyFormatter::format((float) $order->total, $currency, $moneyOpts);
        $taxTotal = (float) ($order->tax_total ?? 0);

        if ($methods === []) {
            return [
                'success' => false,
                'error' => 'no_payment_methods',
                'order_number' => $order->order_number,
                'total' => $total,
                'payment_status' => $order->payment_status,
                'methods' => [],
                'message' => 'This business has no payment methods configured yet. Ask them to enable M-Pesa, Paystack, Stripe, or add manual payment instructions in Settings.',
                'customer_message' => "Order #{$order->order_number} (total {$total}) is ready, but payment options are not configured on our side yet. Please check back shortly or ask for a human if you need urgent help.",
            ];
        }

        $lines = [
            "Order #{$order->order_number}",
        ];
        if ($taxTotal > 0) {
            $lines[] = 'Subtotal: '.MoneyFormatter::format((float) ($order->subtotal ?? 0), $currency, $moneyOpts);
            $breakdown = is_array($order->tax_breakdown) ? $order->tax_breakdown : [];
            if ($breakdown === []) {
                $lines[] = 'Tax: '.MoneyFormatter::format($taxTotal, $currency, $moneyOpts);
            } else {
                foreach ($breakdown as $row) {
                    $label = (string) (($row['code'] ?? null) ?: ($row['name'] ?? 'Tax'));
                    $rate = rtrim(rtrim(number_format((float) ($row['rate'] ?? 0), 4, '.', ''), '0'), '.');
                    $lines[] = "{$label} ({$rate}%): ".MoneyFormatter::format((float) ($row['amount'] ?? 0), $currency, $moneyOpts);
                }
            }
        }
        $lines[] = "Total due: {$total}";
        $lines[] = 'Payment status: '.($order->payment_status ?? 'unpaid');
        $lines[] = '';

        if (count($drivers) === 1 && $drivers[0]->getId() === 'manual') {
            $instructions = $drivers[0]->getInstructions($company, $order);
            $lines[] = 'Pay using these details:';
            $lines[] = $instructions;
            $lines[] = '';
            $lines[] = 'Reply here once you have paid.';
        } else {
            $lines[] = 'Available payment options:';
            foreach ($drivers as $index => $driver) {
                $n = $index + 1;
                $lines[] = "{$n}. {$driver->getDisplayName()}";
                $inst = $driver->getInstructions($company, $order);
                if ($inst && $driver->getCategory() === 'manual') {
                    $lines[] = '';
                    $lines[] = 'Manual payment details:';
                    $lines[] = $inst;
                }
            }
            $lines[] = '';
            $lines[] = 'Reply with the option number (or name) to continue.';
        }

        $lines[] = '';
        $lines[] = 'Pay online: '.$order->publicPayUrl();
        $lines[] = 'Invoice: '.$order->publicInvoiceUrl();
        $lines[] = '';
        $lines[] = 'View invoice / receipt:';
        $lines[] = $order->publicReceiptUrl();

        $manualDriver = $this->registry->getDriver('manual');

        return [
            'success' => true,
            'order_number' => $order->order_number,
            'total' => $total,
            'payment_status' => $order->payment_status,
            'methods' => $methods,
            'manual_instructions' => ($manualDriver && $manualDriver->isReady($company))
                ? $manualDriver->getInstructions($company, $order)
                : null,
            'customer_message' => implode("\n", $lines),
            'message' => 'Share the customer_message with the customer. Do not invent payment options. Do not transfer_to_human for payment details.',
        ];
    }

    public function resolveUnpaidOrder(Company $company, string $customerPhone, ?string $orderNumber = null): ?Order
    {
        $phone = preg_replace('/\D+/', '', $customerPhone) ?? $customerPhone;
        $query = Order::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($phone, $customerPhone) {
                $q->where('customer_phone', $customerPhone)
                    ->orWhere('customer_phone', $phone)
                    ->orWhere('customer_phone', 'like', '%'.substr($phone, -9).'%');
            })
            ->where(function ($q) {
                $q->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid', 'refunded']);
            })
            ->whereNotIn('status', ['cancelled', 'canceled']);

        if ($orderNumber && trim($orderNumber) !== '') {
            $found = (clone $query)->where('order_number', 'like', '%'.trim($orderNumber).'%')->orderByDesc('id')->first();
            if ($found) {
                return $found;
            }
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return array{mpesa: bool, stripe: bool, paystack: bool, manual: bool, cod: bool}
     */
    public function resolveAcceptance(Company $company): array
    {
        $drivers = $this->registry->getAvailableDrivers($company);
        $activeMap = [];
        foreach ($drivers as $d) {
            $activeMap[$d->getId()] = true;
        }

        return [
            'mpesa' => ! empty($activeMap['mpesa']),
            'stripe' => ! empty($activeMap['stripe']),
            'paystack' => ! empty($activeMap['paystack']),
            'manual' => ! empty($activeMap['manual']),
            'cod' => ! empty($activeMap['cod']),
        ];
    }

    /**
     * @param  array<string, bool>  $pay
     * @return list<string>
     */
    public function methodKeys(array $pay): array
    {
        $keys = [];
        foreach ($pay as $key => $isAvailable) {
            if ($isAvailable) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function promptBlockForCompany(Company $company): string
    {
        $company->loadMissing('settings');
        $drivers = $this->registry->getAvailableDrivers($company);
        if ($drivers === []) {
            return "Payment options configured: none. Do not invent till numbers or say methods are 'being set up' — tell the customer payment is not configured yet, or use transfer_to_human only if they insist on a person.";
        }

        $lines = ['Payment options configured for this business (authoritative — never invent others):'];
        foreach ($drivers as $driver) {
            $lines[] = '- '.$driver->getId().' ('.$driver->getDisplayName().')';
        }

        $manualDriver = $this->registry->getDriver('manual');
        if ($manualDriver && $manualDriver->isReady($company)) {
            $inst = $manualDriver->getInstructions($company);
            if ($inst) {
                $lines[] = 'Manual instructions to share when relevant:';
                $lines[] = $inst;
            }
        }

        $lines[] = 'When the customer wants to pay or asks for payment details: call share_payment_details (or process_order_message during active checkout). Never claim payment methods are unavailable if listed above.';

        return implode("\n", $lines);
    }
}
