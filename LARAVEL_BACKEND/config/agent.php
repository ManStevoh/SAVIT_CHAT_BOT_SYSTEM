<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent commerce loop limits
    |--------------------------------------------------------------------------
    */
    'max_loop_iterations' => (int) env('AGENT_MAX_LOOP_ITERATIONS', 8),
    'max_tool_calls_per_turn' => (int) env('AGENT_MAX_TOOL_CALLS', 12),
    'tool_result_max_chars' => (int) env('AGENT_TOOL_RESULT_MAX_CHARS', 8000),
    'conversation_history_limit' => (int) env('AGENT_HISTORY_LIMIT', 16),

    /*
    |--------------------------------------------------------------------------
    | Default agent mode for new companies (overridden by company_settings)
    |--------------------------------------------------------------------------
    */
    'default_agent_commerce_enabled' => (bool) env('AGENT_COMMERCE_DEFAULT', false),

    /*
    |--------------------------------------------------------------------------
    | Memory limits (avoid prompt bloat)
    |--------------------------------------------------------------------------
    */
    'customer_memory_limit' => 20,
    'agent_reflection_limit' => 10,

    /*
    |--------------------------------------------------------------------------
    | Business goals the Chief Agent optimizes for
    |--------------------------------------------------------------------------
    */
    'business_goals' => [
        'increase_revenue' => 'Increase revenue through helpful recommendations and relevant upsells',
        'reduce_refunds' => 'Reduce refunds by clarifying policies and setting clear expectations',
        'increase_repeat_customers' => 'Build loyalty and encourage repeat purchases',
        'improve_response_time' => 'Resolve customer needs quickly with minimal back-and-forth',
        'clear_old_inventory' => 'Promote slow-moving or overstocked items when appropriate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Proactive event-driven outreach
    |--------------------------------------------------------------------------
    */
    'proactive' => [
        'abandoned_cart_hours' => (int) env('AGENT_ABANDONED_CART_HOURS', 24),
        'max_outreach_per_run' => (int) env('AGENT_MAX_PROACTIVE_OUTREACH', 15),
        'memory_extraction_delay_minutes' => (int) env('AGENT_MEMORY_EXTRACT_DELAY', 30),
        'reflection_delay_minutes' => (int) env('AGENT_REFLECTION_DELAY', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Company OS (reasoning, graph, brief, reflection)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'reasoning_enabled' => (bool) env('AGENT_REASONING_ENABLED', true),
        'operating_guide_limit' => 8,
        'low_stock_threshold' => (int) env('AGENT_LOW_STOCK_THRESHOLD', 5),
        'reorder_threshold_ratio' => 0.85,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Platform OS — world model, trust, skills, approvals
    |--------------------------------------------------------------------------
    */
    'platform' => [
        'background_thinking_delay_minutes' => (int) env('AGENT_BG_THINKING_DELAY', 50),
        'tool_risk_levels' => [
            'search_products' => 'low',
            'search_faq' => 'low',
            'search_knowledge' => 'low',
            'get_customer_profile' => 'low',
            'search_orders' => 'low',
            'get_catalog' => 'low',
            'remember_customer' => 'low',
            'get_business_info' => 'low',
            'trace_customer_graph' => 'low',
            'get_product_relationships' => 'low',
            'check_delivery_status' => 'low',
            'get_weather' => 'low',
            'check_mpesa_payment' => 'low',
            'get_shipping_quote' => 'low',
            'check_calendar_availability' => 'low',
            'get_marketing_performance' => 'low',
            'send_whatsapp_campaign' => 'high',
            'issue_order_refund' => 'high',
            'process_order_message' => 'medium',
            'transfer_to_human' => 'medium',
        ],
        'skill_modules' => [
            'retail' => [
                'name' => 'Retail Assistant',
                'description' => 'General product commerce',
                'tools' => ['search_products', 'get_catalog', 'trace_customer_graph'],
                'prompt_addon' => 'Focus on product discovery, stock accuracy, and clear pricing.',
            ],
            'restaurant' => [
                'name' => 'Hospitality Assistant',
                'description' => 'Menu and orders',
                'tools' => ['search_products', 'get_catalog', 'process_order_message'],
                'prompt_addon' => 'Treat menu items as products. Clarify delivery/pickup timing.',
            ],
            'services' => [
                'name' => 'Services Assistant',
                'description' => 'Appointments and quotes',
                'tools' => ['search_faq', 'get_business_info', 'remember_customer'],
                'prompt_addon' => 'Qualify service needs, timing, and location before quoting.',
            ],
            'other' => [
                'name' => 'General Commerce Assistant',
                'description' => 'Default module',
                'tools' => [],
                'prompt_addon' => 'Adapt to the business catalog and policies.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 3 — Multi-agent specialists, event bus, world tools
    |--------------------------------------------------------------------------
    */
    'specialists' => [
        'types' => ['sales', 'support', 'inventory'],
        'consult_on_turn' => (bool) env('AGENT_SPECIALISTS_ON_TURN', true),
        'background_enabled' => (bool) env('AGENT_SPECIALISTS_BACKGROUND', true),
        'use_llm' => (bool) env('AGENT_SPECIALISTS_USE_LLM', true),
    ],

    'events' => [
        'detection_enabled' => (bool) env('AGENT_EVENTS_ENABLED', true),
        'delivery_delay_days' => (int) env('AGENT_DELIVERY_DELAY_DAYS', 5),
        'customer_outreach_types' => ['delivery_delay', 'customer_birthday'],
        'owner_alert_types' => ['low_stock', 'sales_drop'],
    ],

    'world' => [
        'weather_enabled' => (bool) env('AGENT_WEATHER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 4 — Vision, unified brain, owner analytics, external tool bus
    |--------------------------------------------------------------------------
    */
    'vision' => [
        'enabled' => (bool) env('AGENT_VISION_ENABLED', true),
    ],

    'brain' => [
        'enabled' => (bool) env('AGENT_BRAIN_ENABLED', true),
        'snapshot_max_age_minutes' => (int) env('AGENT_BRAIN_MAX_AGE', 60),
    ],

    'owner_analytics' => [
        'enabled' => (bool) env('AGENT_OWNER_ANALYTICS_ENABLED', true),
        'use_llm' => (bool) env('AGENT_OWNER_ANALYTICS_LLM', true),
    ],

    'external' => [
        'mpesa_tool_enabled' => (bool) env('AGENT_MPESA_TOOL_ENABLED', true),
        'shipping_enabled' => (bool) env('AGENT_SHIPPING_ENABLED', true),
        'shipping_api_url' => env('AGENT_SHIPPING_API_URL'),
        'shipping_api_key' => env('AGENT_SHIPPING_API_KEY'),
        'calendar_enabled' => (bool) env('AGENT_CALENDAR_ENABLED', true),
    ],

    'max_output_chars' => (int) env('AGENT_MAX_OUTPUT_CHARS', 1200),

    /*
    |--------------------------------------------------------------------------
    | Phase 5 — Executive dashboard, voice commands, approvals, learning, A/B
    |--------------------------------------------------------------------------
    */
    'voice' => [
        'enabled' => (bool) env('AGENT_VOICE_ENABLED', true),
        'whisper_model' => env('AGENT_WHISPER_MODEL', 'whisper-1'),
    ],

    'learning' => [
        'cross_business_enabled' => (bool) env('AGENT_CROSS_BUSINESS_LEARNING', true),
    ],

    'experiments' => [
        'enabled' => (bool) env('AGENT_EXPERIMENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Cognitive Architecture (#36–#55)
    |--------------------------------------------------------------------------
    */
    'cognitive' => [
        'confidence_auto_respond' => (float) env('AGENT_CONFIDENCE_AUTO', 0.7),
        'confidence_clarify' => (float) env('AGENT_CONFIDENCE_CLARIFY', 0.45),
        'business_dna_defaults' => [
            'retail' => [
                'tone' => 'helpful and efficient',
                'values' => ['fair pricing', 'honest stock info', 'fast resolution'],
                'risk_tolerance' => 'medium',
                'service_philosophy' => 'Make buying easy; fix problems quickly',
                'escalation_culture' => 'Escalate refunds and disputes early',
                'communication_style' => 'Clear, concise, friendly',
            ],
            'restaurant' => [
                'tone' => 'warm and welcoming',
                'values' => ['freshness', 'timing', 'hospitality'],
                'risk_tolerance' => 'low',
                'service_philosophy' => 'Delight guests; never overpromise delivery times',
                'escalation_culture' => 'Manager handles complaints about food quality',
                'communication_style' => 'Conversational, appetizing descriptions',
            ],
            'services' => [
                'tone' => 'professional and reassuring',
                'values' => ['expertise', 'reliability', 'transparency'],
                'risk_tolerance' => 'low',
                'service_philosophy' => 'Qualify needs before quoting; set clear expectations',
                'escalation_culture' => 'Escalate scope changes and custom contracts',
                'communication_style' => 'Structured, step-by-step',
            ],
            'other' => [
                'tone' => 'professional and friendly',
                'values' => ['honesty', 'customer care'],
                'risk_tolerance' => 'medium',
                'service_philosophy' => 'Solve problems with clarity',
                'escalation_culture' => 'Escalate when policy unclear',
                'communication_style' => 'Adapt to customer tone',
            ],
        ],
        'workforce' => [
            ['id' => 'ceo', 'title' => 'CEO AI', 'objective' => 'Top decisions and cross-director coordination', 'reports' => 'Morning commerce brief'],
            ['id' => 'sales_director', 'title' => 'Sales Director AI', 'objective' => 'Conversion, upsell, lead follow-up', 'reports' => 'Pipeline and at-risk leads'],
            ['id' => 'finance_director', 'title' => 'Finance Director AI', 'objective' => 'Margin, cash flow, discount policy', 'reports' => 'Unpaid orders and margin risks'],
            ['id' => 'marketing_director', 'title' => 'Marketing Director AI', 'objective' => 'Campaigns, bundles, retention', 'reports' => 'Opportunity engine output'],
            ['id' => 'support_director', 'title' => 'Support Director AI', 'objective' => 'Resolution speed and satisfaction', 'reports' => 'Unresolved issues summary'],
            ['id' => 'operations_director', 'title' => 'Operations Director AI', 'objective' => 'Delivery and fulfillment', 'reports' => 'Pending orders and delays'],
            ['id' => 'inventory_director', 'title' => 'Inventory Director AI', 'objective' => 'Stock levels and slow movers', 'reports' => 'Restock and clearance opportunities'],
        ],
        'platform_patterns_seed' => [
            [
                'pattern_key' => 'fast_reply_conversion',
                'pattern_type' => 'engagement',
                'description' => 'Businesses that reply within 5 minutes tend to convert more leads.',
                'industries' => ['all'],
            ],
            [
                'pattern_key' => 'bundle_aov',
                'pattern_type' => 'revenue',
                'description' => 'Bundling frequently co-purchased items often increases average order value.',
                'industries' => ['retail', 'restaurant'],
            ],
        ],
    ],
];
