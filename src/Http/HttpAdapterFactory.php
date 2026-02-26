<?php
declare(strict_types=1);

/**
 * HTTP Adapter Factory
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

use CoCart\Exceptions\CoCartException;

/**
 * HTTP Adapter Factory
 * 
 * Automatically selects the best available HTTP adapter.
 */
class HttpAdapterFactory
{
    /**
     * Adapter priority order (best to worst)
     *
     * @var array<class-string<HttpAdapterInterface>>
     */
    protected static array $adapterPriority = [
        GuzzleAdapter::class,
        CurlAdapter::class,
        WordPressAdapter::class,
        StreamAdapter::class,
    ];

    /**
     * Create the best available HTTP adapter
     *
     * @param string|null $preferred Preferred adapter name (guzzle, curl, wordpress, stream)
     * @return HttpAdapterInterface
     * @throws CoCartException If no adapter is available
     */
    public static function create(?string $preferred = null): HttpAdapterInterface
    {
        // If a specific adapter is requested, use it or throw
        if ($preferred !== null) {
            return self::createByName($preferred);
        }

        // Auto-detect best available adapter
        foreach (self::$adapterPriority as $adapterClass) {
            if ($adapterClass::isAvailable()) {
                return new $adapterClass();
            }
        }

        throw new CoCartException(
            "No HTTP adapter available. Please install Guzzle (composer require guzzlehttp/guzzle) " .
            "or enable the cURL extension."
        );
    }

    /**
     * Create adapter by name
     *
     * @param string $name Adapter name
     * @return HttpAdapterInterface
     * @throws CoCartException If adapter is unknown or unavailable
     */
    protected static function createByName(string $name): HttpAdapterInterface
    {
        $adapters = [
            'guzzle' => GuzzleAdapter::class,
            'curl' => CurlAdapter::class,
            'wordpress' => WordPressAdapter::class,
            'stream' => StreamAdapter::class,
        ];

        $name = strtolower($name);

        if (!isset($adapters[$name])) {
            throw new CoCartException(
                "Unknown HTTP adapter '{$name}'. Available adapters: " . implode(', ', array_keys($adapters)) . '.'
            );
        }

        $adapterClass = $adapters[$name];

        if (!$adapterClass::isAvailable()) {
            $hints = [
                'guzzle' => 'Install with: composer require guzzlehttp/guzzle',
                'curl' => 'Enable the cURL PHP extension in php.ini',
                'wordpress' => 'Only available inside a WordPress environment',
                'stream' => 'Enable allow_url_fopen in php.ini',
            ];
            throw new CoCartException(
                "HTTP adapter '{$name}' is not available. " . ($hints[$name] ?? '')
            );
        }

        return new $adapterClass();
    }

    /**
     * Get list of available adapters
     *
     * @return array<string>
     */
    public static function getAvailableAdapters(): array
    {
        $available = [];

        foreach (self::$adapterPriority as $adapterClass) {
            if ($adapterClass::isAvailable()) {
                $available[] = $adapterClass::getName();
            }
        }

        return $available;
    }

    /**
     * Check if a specific adapter is available
     *
     * @param string $name Adapter name
     * @return bool
     */
    public static function isAdapterAvailable(string $name): bool
    {
        return in_array(strtolower($name), self::getAvailableAdapters(), true);
    }

    /**
     * Register a custom adapter
     *
     * @param class-string<HttpAdapterInterface> $adapterClass
     * @param int $priority Lower number = higher priority (0 = highest)
     * @return void
     */
    public static function registerAdapter(string $adapterClass, int $priority = 50): void
    {
        // Remove if already exists
        self::$adapterPriority = array_filter(
            self::$adapterPriority,
            fn($class) => $class !== $adapterClass
        );

        // Insert at priority position
        array_splice(self::$adapterPriority, $priority, 0, [$adapterClass]);
    }
}
