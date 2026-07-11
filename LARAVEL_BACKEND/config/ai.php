<?php

use App\Models\AiModel;

/**
 * SAVIT AI orchestration — maps use cases to model capabilities and selection policy.
 *
 * Application code should call AiOrchestrator (not providers directly).
 * Capabilities are stored in ai_models; this config defines routing only.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Use case → capability routing
    |--------------------------------------------------------------------------
    |
    | capability: which ai_models.capability row to resolve
    | prefer: cost (cheapest enabled) | quality (highest cost enabled)
    | model_key: optional hard hint (falls back to platform default for capability)
    | dedicated: when true, company "specific chat model" selection is ignored
    |
    */
    'use_cases' => [
        // Customer-facing WhatsApp (may downgrade to fast_chat via orchestrator)
        'whatsapp' => [
            'capability' => AiModel::CAPABILITY_CHAT,
            'prefer' => 'cost',
            'dedicated' => false,
        ],
        'whatsapp_fast' => [
            'capability' => AiModel::CAPABILITY_FAST_CHAT,
            'prefer' => 'cost',
            'dedicated' => true,
        ],

        // Agent brain — reasoning, tools, reflection
        'agent_reasoning' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'quality',
            'dedicated' => true,
        ],
        'agent_commerce' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'quality',
            'dedicated' => true,
        ],
        'agent_reflection' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'quality',
            'dedicated' => true,
        ],
        'agent_memory_extraction' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'agent_proactive' => [
            'capability' => AiModel::CAPABILITY_CHAT,
            'prefer' => 'cost',
            'dedicated' => false,
        ],
        'agent_specialist_sales' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'agent_specialist_support' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'agent_specialist_inventory' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],

        // Vision / OCR (multimodal)
        'agent_vision' => [
            'capability' => AiModel::CAPABILITY_VISION,
            'prefer' => 'quality',
            'dedicated' => true,
        ],

        // Speech
        'speech_to_text' => [
            'capability' => AiModel::CAPABILITY_STT,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'text_to_speech' => [
            'capability' => AiModel::CAPABILITY_TTS,
            'prefer' => 'cost',
            'dedicated' => true,
        ],

        // Lightweight classification (fast model, JSON out)
        'intent_classification' => [
            'capability' => AiModel::CAPABILITY_FAST_CHAT,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'entity_extraction' => [
            'capability' => AiModel::CAPABILITY_FAST_CHAT,
            'prefer' => 'cost',
            'dedicated' => true,
        ],

        // Embeddings & images
        'embedding' => [
            'capability' => AiModel::CAPABILITY_EMBEDDING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
        'image_generation' => [
            'capability' => AiModel::CAPABILITY_IMAGE,
            'prefer' => 'cost',
            'dedicated' => true,
        ],

        // Growth / owner analytics
        'growth' => [
            'capability' => AiModel::CAPABILITY_CHAT,
            'prefer' => 'cost',
            'dedicated' => false,
        ],
        'owner_analytics' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'quality',
            'dedicated' => true,
        ],
        'commerce_morning_brief' => [
            'capability' => AiModel::CAPABILITY_REASONING,
            'prefer' => 'cost',
            'dedicated' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommended platform defaults (informational — actual defaults live in DB)
    |--------------------------------------------------------------------------
    */
    'recommended_defaults' => [
        AiModel::CAPABILITY_REASONING => 'gpt-4o',
        AiModel::CAPABILITY_CHAT => 'gpt-4o-mini',
        AiModel::CAPABILITY_FAST_CHAT => 'gpt-4o-mini',
        AiModel::CAPABILITY_VISION => 'gpt-4o',
        AiModel::CAPABILITY_EMBEDDING => 'text-embedding-3-small',
        AiModel::CAPABILITY_STT => 'whisper-1',
        AiModel::CAPABILITY_TTS => 'tts-1',
        AiModel::CAPABILITY_IMAGE => 'gemini-2.5-flash-image',
    ],

    /*
    |--------------------------------------------------------------------------
    | Non-LLM workloads (deterministic — do not route through chat models)
    |--------------------------------------------------------------------------
    */
    'deterministic_handlers' => [
        'tax_calculation' => 'business_rules',
        'mpesa_verification' => 'api_integration',
        'inventory_count' => 'database',
        'route_optimization' => 'algorithm',
        'fraud_scoring' => 'ml_classifier',
        'sales_forecast' => 'forecast_service',
        'product_recommendations' => 'recommendation_engine',
    ],

    // When true, ambiguous intents may call the fast_chat model for classification.
    'intent_llm_fallback' => env('AI_INTENT_LLM_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | pgvector (PostgreSQL only — optional scale path)
    |--------------------------------------------------------------------------
    */
    'pgvector' => [
        'enabled' => (bool) env('AI_PGVECTOR_ENABLED', false),
        'dimensions' => (int) env('AI_PGVECTOR_DIMENSIONS', 1536),
    ],

];
