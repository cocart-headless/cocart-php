<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\TimezoneHelper;
use PHPUnit\Framework\TestCase;

class TimezoneHelperTest extends TestCase
{
    public function testDetectTimezoneReturnsNonEmptyString(): void
    {
        $tz = new TimezoneHelper();
        $result = $tz->detectTimezone();

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testConvertUtcToNewYork(): void
    {
        $tz = new TimezoneHelper();
        // 2025-01-15 15:00:00 UTC = 2025-01-15 10:00:00 EST (UTC-5)
        $result = $tz->convert('2025-01-15T15:00:00', 'UTC', 'America/New_York');

        $this->assertSame('2025-01-15T10:00:00', $result);
    }

    public function testConvertLondonToTokyo(): void
    {
        $tz = new TimezoneHelper();
        // 2025-07-15 12:00:00 BST (UTC+1) -> JST (UTC+9) = 20:00:00
        $result = $tz->convert('2025-07-15T12:00:00', 'Europe/London', 'Asia/Tokyo');

        $this->assertSame('2025-07-15T20:00:00', $result);
    }

    public function testConvertSameTimezone(): void
    {
        $tz = new TimezoneHelper();
        $result = $tz->convert('2025-06-01T08:30:00', 'UTC', 'UTC');

        $this->assertSame('2025-06-01T08:30:00', $result);
    }

    public function testToLocalConvertsToSystemTimezone(): void
    {
        $tz = new TimezoneHelper();
        $systemTz = date_default_timezone_get();

        $result = $tz->toLocal('2025-01-15T12:00:00', 'UTC');
        $expected = $tz->convert('2025-01-15T12:00:00', 'UTC', $systemTz);

        $this->assertSame($expected, $result);
    }

    public function testToLocalDefaultsToUtcSource(): void
    {
        $tz = new TimezoneHelper();

        // Calling without storeTz should default to UTC
        $result = $tz->toLocal('2025-01-15T12:00:00');
        $expected = $tz->convert('2025-01-15T12:00:00', 'UTC', $tz->detectTimezone());

        $this->assertSame($expected, $result);
    }

    public function testTimezoneNaiveInputTreatedAsFromTz(): void
    {
        $tz = new TimezoneHelper();
        // Input without timezone info should be treated as fromTz
        $result = $tz->convert('2025-03-20 14:30:00', 'America/Chicago', 'UTC');

        // Chicago CDT (UTC-5 in March) -> UTC = +5 hours = 19:30:00
        // March 20 is after DST spring forward (March 9), so CDT = UTC-5
        $this->assertSame('2025-03-20T19:30:00', $result);
    }

    public function testOutputFormatIsIso8601(): void
    {
        $tz = new TimezoneHelper();
        $result = $tz->convert('2025-06-15T09:00:00', 'UTC', 'Europe/Paris');

        // Should match ISO 8601 format without offset
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $result);
    }
}
