<?php

namespace Database\Seeders;

use App\Models\MarketplaceModule;
use Illuminate\Database\Seeder;

class MarketplaceModuleSeeder extends Seeder
{
    public function run(): void
    {
        $fromConfig = config('agent.platform.skill_modules', []);
        $sort = 10;

        foreach ($fromConfig as $key => $module) {
            MarketplaceModule::updateOrCreate(
                ['module_key' => $key],
                [
                    'name' => (string) ($module['name'] ?? $key),
                    'description' => (string) ($module['description'] ?? ''),
                    'category' => 'industry',
                    'publisher' => 'platform',
                    'required_plan' => null,
                    'prompt_addon' => (string) ($module['prompt_addon'] ?? ''),
                    'tools' => $module['tools'] ?? [],
                    'manifest' => [
                        'sdk_version' => '1',
                        'module_key' => $key,
                        'type' => 'platform',
                    ],
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );
            $sort += 10;
        }

        $extended = [
            'pharmacy' => [
                'name' => 'Pharmacy Assistant',
                'description' => 'Prescription-aware commerce, stock safety, and regulatory tone',
                'required_plan' => 'professional',
                'tools' => ['search_products', 'search_faq', 'get_catalog', 'search_orders'],
                'prompt_addon' => 'Never diagnose. Clarify prescription requirements, expiry, and dosage only from approved catalog/FAQ. Escalate medical advice requests.',
            ],
            'school' => [
                'name' => 'School & Education',
                'description' => 'Enrollment, fees, and parent communication',
                'required_plan' => 'professional',
                'tools' => ['search_faq', 'get_business_info', 'remember_customer', 'search_orders'],
                'prompt_addon' => 'Support parents with enrollment, fees, and schedules. Be warm and precise with deadlines and payment steps.',
            ],
            'healthcare' => [
                'name' => 'Healthcare Front Desk',
                'description' => 'Appointments, intake, and service information',
                'required_plan' => 'professional',
                'tools' => ['search_faq', 'get_business_info', 'check_calendar_availability', 'remember_customer'],
                'prompt_addon' => 'Triage appointment requests. Never provide medical diagnosis. Collect intake details and offer available slots.',
            ],
            'demo_procurement_agent' => [
                'name' => 'Procurement Agent (SDK Demo)',
                'description' => 'Third-party SDK demo — external webhook tools',
                'category' => 'capability',
                'publisher' => 'third_party',
                'required_plan' => 'enterprise',
                'tools' => [],
                'prompt_addon' => 'You may use external procurement tools when the owner has configured a webhook base URL.',
                'manifest' => [
                    'sdk_version' => '1',
                    'module_key' => 'demo_procurement_agent',
                    'type' => 'third_party',
                    'tools' => [
                        [
                            'name' => 'check_supplier_quote',
                            'description' => 'Request a supplier quote via the connected procurement webhook.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'sku' => ['type' => 'string', 'description' => 'Product SKU'],
                                    'quantity' => ['type' => 'integer', 'description' => 'Requested quantity'],
                                ],
                                'required' => ['sku', 'quantity'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($extended as $key => $module) {
            MarketplaceModule::updateOrCreate(
                ['module_key' => $key],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'category' => $module['category'] ?? 'industry',
                    'publisher' => $module['publisher'] ?? 'platform',
                    'required_plan' => $module['required_plan'] ?? null,
                    'prompt_addon' => $module['prompt_addon'],
                    'tools' => $module['tools'],
                    'manifest' => $module['manifest'] ?? ['sdk_version' => '1', 'module_key' => $key],
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );
            $sort += 10;
        }
    }
}
