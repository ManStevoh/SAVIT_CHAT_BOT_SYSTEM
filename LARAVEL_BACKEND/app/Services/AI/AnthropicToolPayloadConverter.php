<?php

namespace App\Services\AI;

/**
 * Convert OpenAI-style chat+tools payloads to Anthropic Messages API shapes.
 */
final class AnthropicToolPayloadConverter
{
    /**
     * @param  array<int, array<string, mixed>>  $openaiTools
     * @return array<int, array<string, mixed>>
     */
    public function tools(array $openaiTools): array
    {
        $out = [];
        foreach ($openaiTools as $tool) {
            $fn = is_array($tool['function'] ?? null) ? $tool['function'] : [];
            $name = (string) ($fn['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'description' => (string) ($fn['description'] ?? ''),
                'input_schema' => is_array($fn['parameters'] ?? null)
                    ? $fn['parameters']
                    : ['type' => 'object', 'properties' => new \stdClass],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{system: string, messages: list<array<string, mixed>>}
     */
    public function messages(array $messages): array
    {
        $system = '';
        $anthropic = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            if ($role === 'system') {
                $system .= ($system !== '' ? "\n\n" : '').(string) ($message['content'] ?? '');

                continue;
            }

            if ($role === 'tool') {
                $toolResult = [
                    'type' => 'tool_result',
                    'tool_use_id' => (string) ($message['tool_call_id'] ?? ''),
                    'content' => (string) ($message['content'] ?? ''),
                ];
                $last = $anthropic !== [] ? $anthropic[array_key_last($anthropic)] : null;
                if ($last && ($last['role'] ?? '') === 'user' && is_array($last['content'] ?? null)) {
                    $anthropic[array_key_last($anthropic)]['content'][] = $toolResult;
                } else {
                    $anthropic[] = ['role' => 'user', 'content' => [$toolResult]];
                }

                continue;
            }

            if ($role === 'assistant') {
                $contentBlocks = [];
                $text = $message['content'] ?? null;
                if (is_string($text) && $text !== '') {
                    $contentBlocks[] = ['type' => 'text', 'text' => $text];
                }
                foreach ($message['tool_calls'] ?? [] as $tc) {
                    if (! is_array($tc)) {
                        continue;
                    }
                    $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
                    $argsRaw = (string) ($fn['arguments'] ?? '{}');
                    $decoded = json_decode($argsRaw, true);
                    $contentBlocks[] = [
                        'type' => 'tool_use',
                        'id' => (string) ($tc['id'] ?? uniqid('tool_', true)),
                        'name' => (string) ($fn['name'] ?? ''),
                        'input' => is_array($decoded) ? $decoded : new \stdClass,
                    ];
                }
                if ($contentBlocks !== []) {
                    $anthropic[] = ['role' => 'assistant', 'content' => $contentBlocks];
                }

                continue;
            }

            // user
            $anthropic[] = [
                'role' => 'user',
                'content' => (string) ($message['content'] ?? ''),
            ];
        }

        return ['system' => $system, 'messages' => $anthropic];
    }

    /**
     * @param  array<int, mixed>  $contentBlocks
     * @return array{content: ?string, toolCalls: list<array{id: string, name: string, arguments: string}>}
     */
    public function parseResponseContent(array $contentBlocks): array
    {
        $text = '';
        $toolCalls = [];
        foreach ($contentBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
            if ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => json_encode($block['input'] ?? new \stdClass, JSON_UNESCAPED_UNICODE) ?: '{}',
                ];
            }
        }

        return [
            'content' => $text !== '' ? $text : null,
            'toolCalls' => $toolCalls,
        ];
    }
}
