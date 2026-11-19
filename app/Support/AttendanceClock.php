<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Centralised conversion between the application storage timezone (UTC) and
 * the operational timezone used by the attendance module (Asia/Jakarta).
 *
 * All datetime columns are stored in the application timezone. User facing
 * input and output is expressed in WIB. Controllers and services must use
 * this helper instead of ad-hoc timezone conversions.
 */
final class AttendanceClock
{
    public const OPERATIONAL_TIMEZONE = 'Asia/Jakarta';

    public const OPERATIONAL_LABEL = 'WIB';

    /**
     * Timezone used to persist datetimes.
     */
    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Current time expressed in the storage timezone.
     */
    public static function nowUtc(): Carbon
    {
        return Carbon::now(self::storageTimezone());
    }

    /**
     * Current time expressed in the operational timezone.
     */
    public static function nowWib(): Carbon
    {
        return Carbon::now(self::OPERATIONAL_TIMEZONE);
    }

    /**
     * Convert an operational (WIB) datetime to the storage timezone.
     *
     * A Carbon instance has its wall-clock value interpreted in the operational
     * timezone, then normalised to the storage timezone. A string without an
     * explicit offset is interpreted as WIB.
     */
    public static function wibToUtc(Carbon|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value, self::OPERATIONAL_TIMEZONE)
            ->setTimezone(self::storageTimezone());
    }

    /**
     * Convert a stored datetime to the operational (WIB) timezone.
     */
    public static function utcToWib(Carbon|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()
                ->setTimezone(self::storageTimezone())
                ->setTimezone(self::OPERATIONAL_TIMEZONE);
        }

        return Carbon::parse($value, self::storageTimezone())
            ->setTimezone(self::OPERATIONAL_TIMEZONE);
    }

    /**
     * Build the UTC start datetime of a shift.
     *
     * @param  string  $workDateWib  Operational date in Y-m-d format (WIB).
     */
    public static function scheduledStart(string $workDateWib, string $startTime): Carbon
    {
        return Carbon::parse(
            trim($workDateWib.' '.$startTime),
            self::OPERATIONAL_TIMEZONE
        )->setTimezone(self::storageTimezone());
    }

    /**
     * Build the UTC end datetime of a shift, honouring cross-midnight offsets.
     *
     * @param  string  $workDateWib  Operational date in Y-m-d format (WIB).
     */
    public static function scheduledEnd(
        string $workDateWib,
        string $endTime,
        int $endDayOffset = 0
    ): Carbon {
        $date = Carbon::parse($workDateWib, self::OPERATIONAL_TIMEZONE)
            ->addDays($endDayOffset)
            ->toDateString();

        return Carbon::parse(
            trim($date.' '.$endTime),
            self::OPERATIONAL_TIMEZONE
        )->setTimezone(self::storageTimezone());
    }

    /**
     * Format a stored datetime for display in WIB.
     */
    public static function formatWib(?Carbon $value, string $format = 'd M Y, H:i'): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::utcToWib($value)->format($format);
    }

    /**
     * Format a stored datetime for an HTML datetime-local input (WIB).
     */
    public static function formatWibInput(?Carbon $value): ?string
    {
        return self::formatWib($value, 'Y-m-d\TH:i');
    }

    /**
     * Today's operational date (WIB) in Y-m-d format.
     */
    public static function todayWib(): string
    {
        return self::nowWib()->toDateString();
    }
}
