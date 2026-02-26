<?php
declare(strict_types=1);

/**
 * HTTP Adapter Interface
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

/**
 * HTTP Adapter Interface
 * 
 * Defines the contract for HTTP client implementations.
 */
interface HttpAdapterInterface
{
    /**
     * Send an HTTP request
     *
     * @param string      $method  HTTP method (GET, POST, PUT, DELETE)
     * @param string      $url     Full URL to request
     * @param array       $headers Request headers
     * @param string|null $body    Request body (JSON string)
     * @param array       $options Additional options (timeout, verify_ssl)
     * @return HttpResponse
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $options = []
    ): HttpResponse;

    /**
     * Check if this adapter is available in the current environment
     *
     * @return bool
     */
    public static function isAvailable(): bool;

    /**
     * Get the adapter name
     *
     * @return string
     */
    public static function getName(): string;
}
