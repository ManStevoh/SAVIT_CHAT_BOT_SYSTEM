<?php

namespace App\Services\AI;

/**
 * Canonical AI use-case identifiers for orchestration routing and logging.
 */
final class AiUseCase
{
    public const WHATSAPP = 'whatsapp';

    public const WHATSAPP_FAST = 'whatsapp_fast';

    public const AGENT_REASONING = 'agent_reasoning';

    public const AGENT_COMMERCE = 'agent_commerce';

    public const AGENT_VISION = 'agent_vision';

    public const AGENT_REFLECTION = 'agent_reflection';

    public const AGENT_MEMORY = 'agent_memory_extraction';

    public const AGENT_PROACTIVE = 'agent_proactive';

    public const SPEECH_TO_TEXT = 'speech_to_text';

    public const TEXT_TO_SPEECH = 'text_to_speech';

    public const EMBEDDING = 'embedding';

    public const IMAGE = 'image_generation';

    public const GROWTH = 'growth';

    public const INTENT = 'intent_classification';

    public const ENTITY = 'entity_extraction';

    public const OWNER_ANALYTICS = 'owner_analytics';

    public const MORNING_BRIEF = 'commerce_morning_brief';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::WHATSAPP,
            self::WHATSAPP_FAST,
            self::AGENT_REASONING,
            self::AGENT_COMMERCE,
            self::AGENT_VISION,
            self::AGENT_REFLECTION,
            self::AGENT_MEMORY,
            self::AGENT_PROACTIVE,
            self::SPEECH_TO_TEXT,
            self::TEXT_TO_SPEECH,
            self::EMBEDDING,
            self::IMAGE,
            self::GROWTH,
            self::INTENT,
            self::ENTITY,
            self::OWNER_ANALYTICS,
            self::MORNING_BRIEF,
        ];
    }
}
