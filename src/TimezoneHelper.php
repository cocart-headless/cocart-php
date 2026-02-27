<?php
declare(strict_types=1);

/**
 * Timezone utility for converting WooCommerce date strings between timezones
 *
 * @package CoCart\SDK
 */

namespace CoCart;

class TimezoneHelper
{
    /**
     * Detect the system timezone
     *
     * Returns an IANA timezone string (e.g., "America/New_York").
     *
     * @return string
     */
    public function detectTimezone(): string
    {
        return date_default_timezone_get();
    }

    /**
     * Convert a date string from one timezone to another
     *
     * If the input string has no timezone offset, it is treated as being
     * in the source timezone.
     *
     * @param string $dateString ISO 8601 date string (e.g., "2025-01-15T10:00:00")
     * @param string $fromTz     Source IANA timezone (e.g., "UTC", "America/Chicago")
     * @param string $toTz       Target IANA timezone
     * @return string Date string in the target timezone (ISO 8601 format without offset)
     */
    public function convert(string $dateString, string $fromTz, string $toTz): string
    {
        $from = new \DateTimeZone($fromTz);
        $to = new \DateTimeZone($toTz);

        $date = new \DateTime($dateString, $from);
        $date->setTimezone($to);

        return $date->format('Y-m-d\TH:i:s');
    }

    /**
     * Convert a store date string to the local (system) timezone
     *
     * @param string $dateString ISO 8601 date string from the API
     * @param string $storeTz    The store's timezone (default: "UTC")
     * @return string Date string in the local timezone
     */
    public function toLocal(string $dateString, string $storeTz = 'UTC'): string
    {
        return $this->convert($dateString, $storeTz, $this->detectTimezone());
    }
}
