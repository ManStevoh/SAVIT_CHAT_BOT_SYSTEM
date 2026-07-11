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



