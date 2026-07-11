<?php



namespace App\Services\Agent\Integrations;



use App\Models\Company;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;



/**

 * Registry of commerce integrations — weather/delivery shipped; CRM/ERP/shipping adapters.

 */

final class ConnectorRegistry

{

    /**

     * @return list<array<string, mixed>>

     */

    public function catalog(): array

    {

        return [

            [

                'type' => 'weather',

                'name' => 'Weather API',

                'category' => 'utility',

                'status_label' => 'Implemented',

                'description' => 'Agent get_weather tool for delivery and event planning.',

            ],

            [

                'type' => 'delivery_status',

                'name' => 'Delivery status',

                'category' => 'logistics',

                'status_label' => 'Implemented',

                'description' => 'Order delivery status from platform orders table.',

            ],

            [

                'type' => 'shipping_quote',

                'name' => 'Shipping quote API (generic)',

                'category' => 'logistics',

                'status_label' => config('agent.external.shipping_enabled') ? 'Configured' : 'Needs API URL',

                'description' => 'External shipping rate lookup when AGENT_SHIPPING_API_URL is set.',

            ],

            [

                'type' => 'dhl_shipping',

                'name' => 'DHL Express',

                'category' => 'logistics',

                'status_label' => 'Adapter v1 — rates via DHL API URL',

                'description' => 'DHL rate lookup when api_url + api_key configured (MyDHL API compatible endpoint).',

            ],

            [

                'type' => 'sendy_logistics',

                'name' => 'Sendy (Kenya logistics)',

                'category' => 'logistics',

                'status_label' => 'Adapter v1 — delivery quote API',

                'description' => 'Sendy-compatible delivery quote endpoint (api_url + api_key).',

            ],

            [

                'type' => 'crm_webhook',

                'name' => 'CRM webhook (generic)',

                'category' => 'crm',

                'status_label' => 'Outbound sync',

                'description' => 'POST customer/order events to your CRM webhook URL.',

            ],

            [

                'type' => 'erp_inventory',

                'name' => 'ERP inventory sync',

                'category' => 'erp',

                'status_label' => 'Read-only pull',

                'description' => 'Pull stock levels from ERP endpoint (manual sync).',

            ],

        ];

    }



    public function has(string $type): bool

    {

        return collect($this->catalog())->contains(fn ($c) => $c['type'] === $type);

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    public function connect(Company $company, string $type, array $config): array

    {

        return match ($type) {

            'weather', 'delivery_status' => ['success' => true, 'message' => 'Built-in connector — no setup required.'],

            'shipping_quote' => $this->connectShipping($config),

            'dhl_shipping' => $this->connectCarrierApi($config, 'DHL'),

            'sendy_logistics' => $this->connectCarrierApi($config, 'Sendy'),

            'crm_webhook' => $this->connectCrmWebhook($config),

            'erp_inventory' => $this->connectErp($config),

            default => ['success' => false, 'message' => 'Unknown connector.'],

        };

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    public function sync(Company $company, string $type, array $config): array

    {

        return match ($type) {

            'crm_webhook' => $this->dispatchCrmEvent($config, [

                'event' => 'sync_ping',

                'company_id' => $company->id,

            ]),

            'erp_inventory' => $this->pullErpInventory($config),

            'dhl_shipping' => $this->pingCarrierQuote($config, ['carrier' => 'dhl', 'test' => true]),

            'sendy_logistics' => $this->pingCarrierQuote($config, ['carrier' => 'sendy', 'test' => true]),

            default => ['success' => true, 'message' => 'No sync required.'],

        };

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    private function connectShipping(array $config): array

    {

        if (! config('agent.external.shipping_enabled')) {

            return ['success' => false, 'message' => 'Shipping tool disabled in platform config.'];

        }



        $url = $config['api_url'] ?? config('agent.external.shipping_api_url');

        if (! is_string($url) || $url === '') {

            return ['success' => false, 'message' => 'Provide api_url in config or set AGENT_SHIPPING_API_URL.'];

        }



        return ['success' => true, 'message' => 'Shipping connector registered.'];

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    private function connectCarrierApi(array $config, string $label): array

    {

        $url = trim((string) ($config['api_url'] ?? ''));

        $key = trim((string) ($config['api_key'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {

            return ['success' => false, 'message' => "{$label} api_url required."];

        }

        if ($key === '') {

            return ['success' => false, 'message' => "{$label} api_key required."];

        }



        return ['success' => true, 'message' => "{$label} connector registered — use sync to test quote endpoint."];

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    private function connectCrmWebhook(array $config): array

    {

        $url = trim((string) ($config['webhook_url'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {

            return ['success' => false, 'message' => 'Valid webhook_url required.'];

        }



        return $this->dispatchCrmEvent($config, ['event' => 'connection_test']);

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    private function connectErp(array $config): array

    {

        $url = trim((string) ($config['inventory_url'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {

            return ['success' => false, 'message' => 'Valid inventory_url required.'];

        }



        return ['success' => true, 'message' => 'ERP connector registered — use sync to pull inventory.'];

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array{success: bool, message?: string}

     */

    private function pullErpInventory(array $config): array

    {

        $url = trim((string) ($config['inventory_url'] ?? ''));

        if ($url === '') {

            return ['success' => false, 'message' => 'inventory_url not configured.'];

        }



        try {

            $response = Http::timeout(8)->get($url);

            if (! $response->successful()) {

                return ['success' => false, 'message' => 'ERP returned HTTP '.$response->status()];

            }



            return ['success' => true, 'message' => 'ERP inventory pull succeeded (SKU mapping in future release).'];

        } catch (\Throwable $e) {

            Log::warning('ERP inventory sync failed', ['error' => $e->getMessage()]);



            return ['success' => false, 'message' => $e->getMessage()];

        }

    }



    /**

     * @param  array<string, mixed>  $config

     * @param  array<string, mixed>  $payload

     * @return array{success: bool, message?: string}

     */

    private function dispatchCrmEvent(array $config, array $payload): array

    {

        $url = trim((string) ($config['webhook_url'] ?? ''));

        if ($url === '') {

            return ['success' => false, 'message' => 'Webhook URL required.'];

        }



        try {

            $response = Http::timeout(8)->post($url, $payload);



            return [

                'success' => $response->successful(),

                'message' => $response->successful() ? 'Webhook reachable.' : 'Webhook returned HTTP '.$response->status(),

            ];

        } catch (\Throwable $e) {

            return ['success' => false, 'message' => $e->getMessage()];

        }

    }



    /**

     * @param  array<string, mixed>  $config

     * @param  array<string, mixed>  $payload

     * @return array{success: bool, message?: string}

     */

    private function pingCarrierQuote(array $config, array $payload): array

    {

        $url = trim((string) ($config['api_url'] ?? ''));

        $key = trim((string) ($config['api_key'] ?? ''));

        if ($url === '') {

            return ['success' => false, 'message' => 'api_url not configured.'];

        }



        try {

            $response = Http::timeout(8)

                ->withHeaders(array_filter(['Authorization' => $key !== '' ? 'Bearer '.$key : null]))

                ->post($url, $payload);



            return [

                'success' => $response->successful(),

                'message' => $response->successful()

                    ? 'Carrier quote endpoint reachable.'

                    : 'Carrier API returned HTTP '.$response->status(),

            ];

        } catch (\Throwable $e) {

            return ['success' => false, 'message' => $e->getMessage()];

        }

    }

}


