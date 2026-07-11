<?php

namespace App\Services\Agent\External;

use App\Models\Company;
use Carbon\Carbon;

/**
 * Calendar / appointment availability from company working hours.
 */
final class CalendarAvailabilityService
{
    /**
     * @return array{timezone: ?string, slots: list<array{date: string, day: string, open: string, close: string, available: bool}>}
     */
    public function availability(Company $company, int $daysAhead = 7): array
    {
        $settings = $company->settings;
        $timezone = $settings?->timezone ?? config('app.timezone', 'UTC');
        $workingHours = $settings?->working_hours ?? [];
        $tz = Carbon::now($timezone);

        $slots = [];
        for ($i = 0; $i < $daysAhead; $i++) {
            $day = $tz->copy()->addDays($i);
            $dayName = strtolower($day->englishDayOfWeek);
            $hours = $this->hoursForDay($workingHours, $dayName);

            $slots[] = [
                'date' => $day->toDateString(),
                'day' => $day->englishDayOfWeek,
                'open' => $hours['open'] ?? '09:00',
                'close' => $hours['close'] ?? '17:00',
                'available' => $hours['enabled'] ?? true,
            ];
        }

        return [
            'timezone' => $timezone,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $workingHours
     * @return array{enabled: bool, open: string, close: string}
     */
    private function hoursForDay(array $workingHours, string $dayName): array
    {
        if ($workingHours === []) {
            return ['enabled' => true, 'open' => '09:00', 'close' => '17:00'];
        }

        foreach ($workingHours as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entryDay = strtolower((string) ($entry['day'] ?? $entry['weekday'] ?? ''));
            if ($entryDay === $dayName || $entryDay === substr($dayName, 0, 3)) {
                return [
                    'enabled' => (bool) ($entry['enabled'] ?? $entry['open'] ?? true),
                    'open' => (string) ($entry['open'] ?? $entry['from'] ?? '09:00'),
                    'close' => (string) ($entry['close'] ?? $entry['to'] ?? '17:00'),
                ];
            }
        }

        return ['enabled' => false, 'open' => '09:00', 'close' => '17:00'];
    }
}
