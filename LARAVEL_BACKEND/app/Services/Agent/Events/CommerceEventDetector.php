<?php

namespace App\Services\Agent\Events;

use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CustomerMemory;
use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\Cognitive\CausalReasoningService;
use App\Services\Agent\Timeline\BusinessTimelineService;

/**
 * Detects commerce events for proactive outreach and owner alerts.
 */
final class CommerceEventDetector
{
    public function __construct(
        protected CausalReasoningService $causal,
        protected BusinessTimelineService $timeline,
    ) {}

    /**
     * @return list<CommerceAgentEvent>
     */
    public function detectForCompany(Company $company): array
    {
        $created = [];
        foreach ($this->detectLowStock($company) as $event) {
            $created[] = $event;
        }
        foreach ($this->detectSalesDrop($company) as $event) {
            $created[] = $event;
        }
        foreach ($this->detectDeliveryDelays($company) as $event) {
            $created[] = $event;
        }
        foreach ($this->detectBirthdays($company) as $event) {
            $created[] = $event;
        }

        return $created;
    }

    /**
     * @return list<CommerceAgentEvent>
     */
    private function detectLowStock(Company $company): array
    {
        $threshold = (int) config('agent.company.low_stock_threshold', 5);
        $products = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('stock', '<=', $threshold)
            ->limit(10)
            ->get(['id', 'name', 'stock']);

        $events = [];
        foreach ($products as $product) {
            $events[] = $this->storeEvent(
                $company->id,
                'low_stock',
                "low_stock:product:{$product->id}",
                ['product_id' => $product->id, 'name' => $product->name, 'stock' => $product->stock],
            );
        }

        return array_filter($events);
    }

    /**
     * @return list<CommerceAgentEvent>
     */
    private function detectSalesDrop(Company $company): array
    {
        $analysis = $this->causal->analyzeSalesChange($company);
        if (($analysis['change'] ?? '') !== 'dropped') {
            return [];
        }

        $event = $this->storeEvent(
            $company->id,
            'sales_drop',
            'sales_drop:'.now()->toDateString(),
            $analysis,
        );

        return $event ? [$event] : [];
    }

    /**
     * @return list<CommerceAgentEvent>
     */
    private function detectDeliveryDelays(Company $company): array
    {
        $days = (int) config('agent.events.delivery_delay_days', 5);
        $orders = Order::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['confirmed', 'shipped'])
            ->where('payment_status', 'paid')
            ->where('created_at', '<=', now()->subDays($days))
            ->whereNotNull('customer_phone')
            ->limit(10)
            ->get(['id', 'order_number', 'customer_phone', 'status', 'created_at']);

        $events = [];
        foreach ($orders as $order) {
            $events[] = $this->storeEvent(
                $company->id,
                'delivery_delay',
                "delivery_delay:order:{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_phone' => $order->customer_phone,
                    'status' => $order->status,
                    'days_since_order' => $order->created_at?->diffInDays(now()),
                ],
            );
        }

        return array_filter($events);
    }

    /**
     * @return list<CommerceAgentEvent>
     */
    private function detectBirthdays(Company $company): array
    {
        $today = now()->format('m-d');
        $memories = CustomerMemory::query()
            ->where('company_id', $company->id)
            ->whereIn('memory_key', ['birthday', 'birth_date', 'date_of_birth'])
            ->limit(20)
            ->get(['customer_phone', 'memory_value']);

        $events = [];
        foreach ($memories as $memory) {
            $value = trim((string) $memory->memory_value);
            if ($value === '') {
                continue;
            }
            if (! $this->matchesToday($value, $today)) {
                continue;
            }
            $events[] = $this->storeEvent(
                $company->id,
                'customer_birthday',
                "birthday:{$memory->customer_phone}:".now()->toDateString(),
                ['customer_phone' => $memory->customer_phone, 'birthday' => $value],
            );
        }

        return array_filter($events);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeEvent(int $companyId, string $type, string $key, array $payload): ?CommerceAgentEvent
    {
        $existing = CommerceAgentEvent::query()
            ->where('company_id', $companyId)
            ->where('event_key', $key)
            ->whereIn('status', ['open', 'handled'])
            ->first();

        if ($existing) {
            return null;
        }

        $event = CommerceAgentEvent::create([
            'company_id' => $companyId,
            'event_type' => $type,
            'event_key' => $key,
            'payload' => $payload,
            'status' => 'open',
        ]);

        $company = Company::find($companyId);
        if ($company) {
            $this->timeline->record(
                $company,
                $type,
                ucfirst(str_replace('_', ' ', $type)),
                (string) ($payload['summary'] ?? $key),
                $payload,
                'commerce_agent_event',
                (int) $event->id,
                in_array($type, ['sales_drop', 'low_stock'], true) ? 85 : 60,
                $event->created_at,
                'signal',
            );
        }

        return $event;
    }

    private function matchesToday(string $value, string $todayMd): bool
    {
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})/', $value, $m)) {
            $normalized = sprintf('%02d-%02d', (int) $m[1], (int) $m[2]);

            return $normalized === $todayMd;
        }

        return str_contains($value, $todayMd);
    }
}
