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

