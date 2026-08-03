<?php

namespace App\Services\PlatformPayments;

use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\PlatformPayments\Drivers\PlatformFlutterwaveDriver;
use App\Services\PlatformPayments\Drivers\PlatformManualDriver;
use App\Services\PlatformPayments\Drivers\PlatformMpesaDriver;
use App\Services\PlatformPayments\Drivers\PlatformPaystackDriver;
use App\Services\PlatformPayments\Drivers\PlatformPesapalDriver;
use App\Services\PlatformPayments\Drivers\PlatformStripeDriver;

class PlatformPaymentRegistry
{
    /** @var array<string, PlatformPaymentDriverInterface> */
    protected array $drivers = [];

    public function __construct(
        PlatformStripeDriver $stripe,
        PlatformPaystackDriver $paystack,
        PlatformPesapalDriver $pesapal,
        PlatformFlutterwaveDriver $flutterwave,
        PlatformMpesaDriver $mpesa,
        PlatformManualDriver $manual,
    ) {
        $this->registerDriver($stripe);
        $this->registerDriver($paystack);
        $this->registerDriver($pesapal);
        $this->registerDriver($flutterwave);
        $this->registerDriver($mpesa);
        $this->registerDriver($manual);
    }

    public function registerDriver(PlatformPaymentDriverInterface $driver): void
    {
        $this->drivers[$driver->getId()] = $driver;
    }

    /**
     * Get all active and available platform drivers for paying the admin.
     *
     * @return list<PlatformPaymentDriverInterface>
     */
    public function getAvailableDrivers(): array
    {
        $available = array_filter($this->drivers, fn (PlatformPaymentDriverInterface $d) => $d->isAvailable());

        usort($available, fn (PlatformPaymentDriverInterface $a, PlatformPaymentDriverInterface $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return array_values($available);
    }

    /**
     * Get a specific driver by slug (e.g. 'stripe', 'paystack', 'mpesa', 'manual').
     */
    public function getDriver(string $id): ?PlatformPaymentDriverInterface
    {
        return $this->drivers[$id] ?? null;
    }
}
