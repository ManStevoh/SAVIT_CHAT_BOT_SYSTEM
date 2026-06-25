<?php

namespace App\Services\WhatsApp;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Support\Collection;

final class WhatsAppCampaignSegmentService
{
    public const SEGMENTS = ['all', 'recent', 'inactive', 'ordered'];

    /**
     * @return Collection<int, array{phone: string, name: ?string}>
     */
    public function recipients(Company $company, string $segment = 'all'): Collection
    {
        if (! in_array($segment, self::SEGMENTS, true)) {
            $segment = 'all';
        }

        $query = Chat::query()
            ->where('company_id', $company->id)
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '');

        if ($segment === 'recent') {
            $query->where('last_message_at', '>=', now()->subDays(30));
        } elseif ($segment === 'inactive') {
            $query->where(function ($q) {
                $q->whereNull('last_message_at')
                    ->orWhere('last_message_at', '<', now()->subDays(30));
            });
        } elseif ($segment === 'ordered') {
            $orderPhones = Order::query()
                ->where('company_id', $company->id)
                ->whereNotNull('customer_phone')
                ->where('customer_phone', '!=', '')
                ->pluck('customer_phone');
            $query->whereIn('customer_phone', $orderPhones);
        }

        return $query
            ->orderByDesc('last_message_at')
            ->get(['customer_phone', 'customer_name'])
            ->unique('customer_phone')
            ->values()
            ->map(fn (Chat $chat) => [
                'phone' => (string) $chat->customer_phone,
                'name' => $chat->customer_name,
            ]);
    }

    public function countAudience(Company $company, string $segment = 'all'): int
    {
        return $this->recipients($company, $segment)->count();
    }
}
