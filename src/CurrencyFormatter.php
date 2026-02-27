<?php
declare(strict_types=1);

/**
 * Currency formatting utility
 *
 * CoCart API returns prices as smallest-unit integers (e.g., 4599 for $45.99).
 * This class converts those values into human-readable formatted strings using
 * the currency metadata from the API response.
 *
 * @package CoCart\SDK
 */

namespace CoCart;

class CurrencyFormatter
{
    /**
     * Locale for formatting (e.g., 'en_US', 'de_DE')
     *
     * @var string|null
     */
    private ?string $locale;

    /**
     * Constructor
     *
     * @param string|null $locale Locale for formatting (e.g., 'en_US', 'de_DE').
     *                            Requires ext-intl. Falls back to prefix/suffix
     *                            formatting from the API response if not available.
     */
    public function __construct(?string $locale = null)
    {
        $this->locale = $locale;
    }

    /**
     * Format a smallest-unit integer into a locale-aware currency string
     *
     * Uses PHP's NumberFormatter (intl extension) when available for proper
     * locale-aware formatting. Falls back to the prefix/suffix metadata
     * from the API response.
     *
     * @param int   $amount       Price in smallest currency unit (e.g., cents)
     * @param array $currencyInfo Currency metadata from Response::getCurrency()
     * @return string Formatted string (e.g., "$45.99", "€12,50")
     */
    public function format(int $amount, array $currencyInfo): string
    {
        $minorUnit = $currencyInfo['currency_minor_unit'] ?? 2;
        $value = $amount / pow(10, $minorUnit);

        if (extension_loaded('intl')) {
            $fmt = new \NumberFormatter(
                $this->locale ?? \Locale::getDefault(),
                \NumberFormatter::CURRENCY
            );
            $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $minorUnit);
            $result = $fmt->formatCurrency($value, $currencyInfo['currency_code'] ?? 'USD');
            if ($result !== false) {
                return $result;
            }
        }

        // Fallback: use prefix/suffix from API response
        $prefix = $currencyInfo['currency_prefix'] ?? '';
        $suffix = $currencyInfo['currency_suffix'] ?? '';
        $decSep = $currencyInfo['currency_decimal_separator'] ?? '.';
        $thousandSep = $currencyInfo['currency_thousand_separator'] ?? ',';

        return $prefix . number_format($value, $minorUnit, $decSep, $thousandSep) . $suffix;
    }

    /**
     * Format a smallest-unit integer into a plain decimal string (no currency symbol)
     *
     * @param int   $amount       Price in smallest currency unit (e.g., cents)
     * @param array $currencyInfo Currency metadata from Response::getCurrency()
     * @return string Decimal string (e.g., "45.99")
     */
    public function formatDecimal(int $amount, array $currencyInfo): string
    {
        $minorUnit = $currencyInfo['currency_minor_unit'] ?? 2;
        $value = $amount / pow(10, $minorUnit);
        return number_format($value, $minorUnit, '.', '');
    }
}
