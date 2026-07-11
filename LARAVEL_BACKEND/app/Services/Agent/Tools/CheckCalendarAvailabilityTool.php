<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\External\CalendarAvailabilityService;

final class CheckCalendarAvailabilityTool implements AgentTool
{
    public function __construct(protected CalendarAvailabilityService $calendar) {}

    public function name(): string
    {
        return 'check_calendar_availability';
    }

    public function description(): string
    {
        return 'Check business calendar availability and working hours for appointments or visits.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days_ahead' => ['type' => 'integer', 'description' => 'Days to look ahead (default 7)'],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        if (! config('agent.external.calendar_enabled', true)) {
            return ['enabled' => false, 'message' => 'Calendar availability is disabled.'];
        }

        $days = min(14, max(1, (int) ($arguments['days_ahead'] ?? 7)));

        return $this->calendar->availability($context->company, $days);
    }
}
