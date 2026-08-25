<?php

namespace App\Services\AI;

/**
 * Convert OpenAI-style chat+tools payloads to Gemini generateContent shapes.
 */
final class GeminiToolPayloadConverter
{
    /**
     * @param  array<int, array<string, mixed>>  $openaiTools
     * @return array<string, mixed>
     */
    public function toolConfig(array $openaiTools): array
    {
        $declarations = [];
        foreach ($openaiTools as $tool) {
            $fn = is_array($tool['function'] ?? null) ? $tool['function'] : [];
            $name = (string) ($fn['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $params = is_array($fn['parameters'] ?? null) ? $fn['parameters'] : ['type' => 'object', 'properties' => new \stdClass];
            $declarations[] = [
                'name' => $name,
                'description' => (string) ($fn['description'] ?? ''),
                'parameters' => $params,
            ];
        }

        return [
            'functionDeclarations' => $declarations,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{system: string, contents: list<array<string, mixed>>}
     */
    public function contents(array $messages): array
    {
        $system = '';
        $contents = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            if ($role === 'system') {
                $system .= ($system !== '' ? "\n\n" : '').(string) ($message['content'] ?? '');

                continue;
            }

            if ($role === 'tool') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => (string) ($message['name'] ?? 'tool'),
                            'response' => [
                                'result' => (string) ($message['content'] ?? ''),
                            ],
                        ],
                    ]],
                ];

                continue;
            }

            if ($role === 'assistant') {
                $parts = [];
                $text = $message['content'] ?? null;
                if (is_string($text) && $text !== '') {
                    $parts[] = ['text' => $text];
                }
                foreach ($message['tool_calls'] ?? [] as $tc) {
                    if (! is_array($tc)) {
                        continue;
                    }
                    $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
                    $argsRaw = (string) ($fn['arguments'] ?? '{}');
                    $decoded = json_decode($argsRaw, true);
                    $parts[] = [
                        'functionCall' => [
                            'name' => (string) ($fn['name'] ?? ''),
                            'args' => is_array($decoded) ? $decoded : new \stdClass,
                        ],
                    ];
                }
                if ($parts !== []) {
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                }

                continue;
            }

            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => (string) ($message['content'] ?? '')]],
            ];
        }

        return ['system' => $system, 'contents' => $contents];
    }

    /**
     * @param  array<int, mixed>  $parts
     * @return array{content: ?string, toolCalls: list<array{id: string, name: string, arguments: string}>}
     */
    public function parseParts(array $parts): array
    {
        $text = '';
        $toolCalls = [];
        $i = 0;
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $fc = $part['functionCall'];
                $name = (string) ($fc['name'] ?? '');
                $args = $fc['args'] ?? new \stdClass;
                $toolCalls[] = [
                    'id' => 'gemini_'.$i.'_'.$name,
                    'name' => $name,
                    'arguments' => json_encode($args, JSON_UNESCAPED_UNICODE) ?: '{}',
                ];
                $i++;
            }
        }

        return [
            'content' => $text !== '' ? $text : null,
            'toolCalls' => $toolCalls,
        ];
    }
}
