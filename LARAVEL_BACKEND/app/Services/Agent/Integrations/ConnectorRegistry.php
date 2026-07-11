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

