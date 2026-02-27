<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    private function usdCurrency(): array
    {
        return [
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'currency_minor_unit' => 2,
            'currency_decimal_separator' => '.',
            'currency_thousand_separator' => ',',
            'currency_prefix' => '$',
            'currency_suffix' => '',
        ];
    }

    private function eurCurrency(): array
    {
        return [
            'currency_code' => 'EUR',
            'currency_symbol' => '€',
            'currency_minor_unit' => 2,
            'currency_decimal_separator' => ',',
            'currency_thousand_separator' => '.',
            'currency_prefix' => '',
            'currency_suffix' => '€',
        ];
    }

    private function jpyCurrency(): array
    {
        return [
            'currency_code' => 'JPY',
            'currency_symbol' => '¥',
            'currency_minor_unit' => 0,
            'currency_decimal_separator' => '',
            'currency_thousand_separator' => ',',
            'currency_prefix' => '¥',
            'currency_suffix' => '',
        ];
    }

    // --- formatDecimal ---

    public function testFormatDecimalUsd(): void
    {
        $fmt = new CurrencyFormatter();
        $this->assertSame('45.99', $fmt->formatDecimal(4599, $this->usdCurrency()));
    }

    public function testFormatDecimalZeroCents(): void
    {
        $fmt = new CurrencyFormatter();
        $this->assertSame('100.00', $fmt->formatDecimal(10000, $this->usdCurrency()));
    }

    public function testFormatDecimalJpy(): void
    {
        $fmt = new CurrencyFormatter();
        $this->assertSame('1500', $fmt->formatDecimal(1500, $this->jpyCurrency()));
    }

    public function testFormatDecimalSmallAmount(): void
    {
        $fmt = new CurrencyFormatter();
        $this->assertSame('0.99', $fmt->formatDecimal(99, $this->usdCurrency()));
    }

    public function testFormatDecimalZero(): void
    {
        $fmt = new CurrencyFormatter();
        $this->assertSame('0.00', $fmt->formatDecimal(0, $this->usdCurrency()));
    }

    // --- format (fallback path) ---

    public function testFormatFallbackUsd(): void
    {
        $fmt = new CurrencyFormatter();
        $result = $fmt->format(4599, $this->usdCurrency());

        // Either intl-formatted or fallback — both should contain 45.99 or equivalent
        $this->assertNotEmpty($result);

        if (!extension_loaded('intl')) {
            $this->assertSame('$45.99', $result);
        }
    }

    public function testFormatFallbackEurSuffix(): void
    {
        $fmt = new CurrencyFormatter();
        $result = $fmt->format(1250, $this->eurCurrency());

        if (!extension_loaded('intl')) {
            // Fallback uses prefix/suffix from API: "" + "12,50" + "€"
            $this->assertSame('12,50€', $result);
        } else {
            $this->assertNotEmpty($result);
        }
    }

    public function testFormatFallbackJpyNoDecimals(): void
    {
        $fmt = new CurrencyFormatter();
        $result = $fmt->format(1500, $this->jpyCurrency());

        if (!extension_loaded('intl')) {
            $this->assertSame('¥1,500', $result);
        } else {
            $this->assertNotEmpty($result);
        }
    }

    public function testFormatDefaultMinorUnit(): void
    {
        $fmt = new CurrencyFormatter();
        // Currency info without minor_unit should default to 2
        $result = $fmt->formatDecimal(4599, ['currency_code' => 'USD']);
        $this->assertSame('45.99', $result);
    }

    // --- Constructor with locale ---

    public function testConstructorAcceptsLocale(): void
    {
        $fmt = new CurrencyFormatter('de_DE');
        $result = $fmt->format(4599, $this->usdCurrency());
        $this->assertNotEmpty($result);
    }

    public function testConstructorNullLocaleWorks(): void
    {
        $fmt = new CurrencyFormatter(null);
        $result = $fmt->format(4599, $this->usdCurrency());
        $this->assertNotEmpty($result);
    }
}
