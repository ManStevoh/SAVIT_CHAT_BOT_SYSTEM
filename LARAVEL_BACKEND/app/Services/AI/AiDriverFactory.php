<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Services\AI\Drivers\AnthropicDriver;
use App\Services\AI\Drivers\Contracts\AiProviderDriver;
use App\Services\AI\Drivers\GoogleGeminiDriver;
use App\Services\AI\Drivers\OpenAiDriver;

class AiDriverFactory
{
    public function driverFor(AiProvider $provider): AiProviderDriver
    {
        return match ($provider->slug) {
            'anthropic' => app(AnthropicDriver::class),
            'google' => app(GoogleGeminiDriver::class),
            default => app(OpenAiDriver::class),
        };
    }
}
