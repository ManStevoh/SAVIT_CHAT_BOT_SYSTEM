<?php

namespace App\Services\PaymentGateways;

use App\Models\Company;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;
use App\Services\PaymentGateways\Drivers\CodGatewayDriver;
use App\Services\PaymentGateways\Drivers\FlutterwaveGatewayDriver;
use App\Services\PaymentGateways\Drivers\ManualGatewayDriver;
use App\Services\PaymentGateways\Drivers\MpesaGatewayDriver;
use App\Services\PaymentGateways\Drivers\PaystackGatewayDriver;
use App\Services\PaymentGateways\Drivers\PesapalGatewayDriver;
use App\Services\PaymentGateways\Drivers\StripeGatewayDriver;

class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayDriverInterface> */
    protected array $drivers = [];

    public function __construct()
    {
        $this->registerDriver(new MpesaGatewayDriver());
        $this->registerDriver(new StripeGatewayDriver());
        $this->registerDriver(new PaystackGatewayDriver());
        $this->registerDriver(new PesapalGatewayDriver());
        $this->registerDriver(new FlutterwaveGatewayDriver());
        $this->registerDriver(new CodGatewayDriver());
        $this->registerDriver(new ManualGatewayDriver());
    }

    public function registerDriver(PaymentGatewayDriverInterface $driver): void
    {
        $this->drivers[$driver->getId()] = $driver;
    }

    public function getDriver(string $id): ?PaymentGatewayDriverInterface
    {
        return $this->drivers[$id] ?? null;
    }

    /**
     * Get all active and ready payment gateway drivers for a company, sorted deterministically by sort order.
     *
     * @return list<PaymentGatewayDriverInterface>
     */
    public function getAvailableDrivers(Company $company): array
    {
        $company->loadMissing('settings');

        $active = [];
        foreach ($this->drivers as $driver) {
            if ($driver->isReady($company)) {
                $active[] = $driver;
            }
        }

        usort($active, fn (PaymentGatewayDriverInterface $a, PaymentGatewayDriverInterface $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $active;
    }

    /**
     * Match customer selection input (number "1", "2" or keyword "mpesa", "card") against available drivers for this company.
     */
    public function matchCustomerSelection(Company $company, string $input): ?PaymentGatewayDriverInterface
    {
        $drivers = $this->getAvailableDrivers($company);
        $lower = strtolower(trim($input));

        foreach ($drivers as $index => $driver) {
            if ($driver->matchesCustomerInput($lower, $index)) {
                return $driver;
            }
        }

        return null;
    }
}
