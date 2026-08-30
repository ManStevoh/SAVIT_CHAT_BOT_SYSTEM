<?php

namespace App\Support;

use App\Services\MailService;
use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

/**
 * Parse admin datetime-local values in the platform (wall-clock) timezone.
 *
 * Browsers send naive strings like "2026-08-28T12:32" with no offset. Treating those
 * as UTC makes an East-Africa "starts now" offer sit three hours in the future.
 */
class PlatformDateTime
{
    public static function timezone(): string
    {
        try {
            $tz = MailService::platformTimezone();
        } catch (Throwable) {
            $tz = (string) config('app.timezone', 'UTC');
        }

        if ($tz === '' || strtoupper($tz) === 'UTC') {
            return 'Africa/Nairobi';
        }

        return $tz;
    }

    public static function parse(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $raw)) {
            return Carbon::parse($raw);
        }

        return Carbon::parse($raw, self::timezone());
    }

    public static function toApiString(null|Carbon|DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $dt = $value instanceof Carbon ? $value->copy() : Carbon::instance($value);

        return $dt->timezone(self::timezone())->toIso8601String();
    }
}
