<?php

namespace Tests\Unit;

use App\DTOs\IntentResult;
use App\Enums\CommerceIntent;
use Tests\TestCase;

class IntentResultTest extends TestCase
{
    public function test_from_array_parses_intent_and_entities(): void
    {
        $payload = [
            'intent' => 'add_to_cart',
            'confidence' => 0.95,
            'entities' => [
                'product' => 'Red Earphones',
                'quantity' => 2,
            ],
            'requires_clarification' => false,
        ];

        $result = IntentResult::fromArray($payload);

        $this->assertEquals(CommerceIntent::ADD_TO_CART, $result->intent);
        $this->assertEquals(0.95, $result->confidence);
        $this->assertEquals('Red Earphones', $result->product);
        $this->assertEquals(2, $result->quantity);
        $this->assertFalse($result->requiresClarification);
        $this->assertTrue($result->isHighConfidence());
    }

    public function test_respects_configurable_min_confidence(): void
    {
        config(['agent.ai_intent_min_confidence' => 0.82]);

        $lowConf = IntentResult::fromArray(['intent' => 'add_to_cart', 'confidence' => 0.75]);
        $highConf = IntentResult::fromArray(['intent' => 'add_to_cart', 'confidence' => 0.85]);

        $this->assertFalse($lowConf->isHighConfidence());
        $this->assertTrue($highConf->isHighConfidence());
    }

    public function test_handles_unknown_intent_gracefully(): void
    {
        $result = IntentResult::fromArray(['intent' => 'invalid_intent_key', 'confidence' => 0.1]);

        $this->assertEquals(CommerceIntent::UNKNOWN, $result->intent);
        $this->assertFalse($result->isHighConfidence());
    }
}
