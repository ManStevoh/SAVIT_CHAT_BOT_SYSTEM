<?php

namespace App\Services\Conversation;

use App\Models\Company;
use Carbon\Carbon;

/**
 * Working-hours checks for away-message routing.
 */
final class WorkingHoursService
{
    public function isOutsideWorkingHours(Company $company): bool
    {
        $settings = $company->settings;
        if (! $settings || ! $settings->working_hours || ! is_array($settings->working_hours)) {
            return false;
        }

        $tz = $settings->timezone ?? 'UTC';
        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            return false;
        }

        $day = strtolower($now->format('D'));
        $schedule = $settings->working_hours[$day] ?? $settings->working_hours['*'] ?? null;
        if (! $schedule || ! is_string($schedule)) {
            return false;
        }

        $parts = explode('-', $schedule);
        if (count($parts) !== 2) {
            return false;
        }

        $open = trim($parts[0]);
        $close = trim($parts[1]);
        $current = $now->format('H:i');

        return $current < $open || $current > $close;
    }

    public function awayMessage(Company $company): string
    {
        $away = $company->settings?->away_message;
        if ($away && trim($away) !== '') {
            return trim($away);
        }

        return "Thanks for your message. We're currently outside business hours. We'll get back to you as soon as we can.";
    }
}
