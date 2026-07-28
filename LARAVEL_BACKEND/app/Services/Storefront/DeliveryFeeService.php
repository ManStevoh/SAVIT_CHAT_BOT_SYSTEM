<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\DeliveryZone;

/**
 * Resolves the delivery fee for a customer address, mirroring Take App's zone-based
 * delivery pricing with an optional free-delivery threshold.
 */
class DeliveryFeeService
{
    /**
     * @return array{fee: float, zone_name: ?string, free: bool}
     */
    public function quote(Company $company, ?string $address, float $subtotal, ?string $fulfillmentType = 'delivery'): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;

        if (! $settings || ! $settings->delivery_fees_enabled) {
            return ['fee' => 0.0, 'zone_name' => null, 'free' => false];
        }

        // Pickup / dine-in orders never carry a delivery fee.
        if ($fulfillmentType !== null && $fulfillmentType !== 'delivery') {
            return ['fee' => 0.0, 'zone_name' => null, 'free' => false];
        }

        $freeAbove = $settings->free_delivery_above !== null ? (float) $settings->free_delivery_above : null;
        if ($freeAbove !== null && $freeAbove > 0 && $subtotal >= $freeAbove) {
            return ['fee' => 0.0, 'zone_name' => null, 'free' => true];
        }

        $zone = $this->matchZone($company, $address);
        if ($zone) {
            return ['fee' => (float) $zone->fee, 'zone_name' => $zone->name, 'free' => false];
        }

        return ['fee' => (float) $settings->default_delivery_fee, 'zone_name' => null, 'free' => false];
    }

    /**
     * Case-insensitive "contains any keyword" match against active delivery zones.
     */
    protected function matchZone(Company $company, ?string $address): ?DeliveryZone
    {
        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }
        $lower = mb_strtolower($address);

        $zones = DeliveryZone::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($zones as $zone) {
            $keywords = is_array($zone->keywords) ? $zone->keywords : [];
            foreach ($keywords as $keyword) {
                $keyword = mb_strtolower(trim((string) $keyword));
                if ($keyword !== '' && str_contains($lower, $keyword)) {
                    return $zone;
                }
            }
        }

        return null;
    }
}
