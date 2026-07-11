<?php

namespace App\Services\Agent\Cognitive;

/**
 * Digital workforce (#46) — director AI personas with objectives.
 */
final class DigitalWorkforceRegistry
{
    /**
     * @return list<array{id: string, title: string, objective: string, reports: string}>
     */
    public function directors(): array
    {
        return config('agent.cognitive.workforce', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(): array
    {
        return [
            'workforce' => $this->directors(),
            'description' => 'Each director AI has objectives and reports daily via commerce brief.',
        ];
    }
}
