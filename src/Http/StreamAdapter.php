<?php
declare(strict_types=1);

/**
 * PHP Stream HTTP Adapter
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

use CoCart\Exceptions\CoCartException;

/**
 * PHP Stream HTTP Adapter
 * 
 * Uses PHP's native file_get_contents() with stream contexts.
 * This is the fallback for environments without cURL or Guzzle.
 * 
 * Note: This adapter has limitations:
 * - Less performant than cURL/Guzzle
 * - May have issues with some SSL configurations
 * - Limited timeout control on some systems
 */
class StreamAdapter implements HttpAdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $options = []
    ): HttpResponse {
        $timeout = $options['timeout'] ?? 30;
        $verifySsl = $options['verify_ssl'] ?? true;

        // Build headers string
        $headerString = '';
        foreach ($headers as $name => $value) {
            $headerString .= "{$name}: {$value}\r\n";
        }

        // Build stream context options
        $httpOptions = [
            'method' => strtoupper($method),
            'header' => $headerString,
            'timeout' => $timeout,
            'ignore_errors' => true, // Don't fail on 4xx/5xx
        ];

        if ($body !== null) {
            $httpOptions['content'] = $body;
        }

        $contextOptions = [
            'http' => $httpOptions,
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ];

        $context = stream_context_create($contextOptions);

        // Capture warnings without suppressing them globally
        $errorMessage = null;
        set_error_handler(function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;
            return true;
        });
        $responseBody = file_get_contents($url, false, $context);
        restore_error_handler();

        if ($responseBody === false) {
            throw new CoCartException(
                "Stream HTTP Error: " . ($errorMessage ?? 'Failed to connect')
            );
        }

        // Parse response headers from $http_response_header
        $responseHeaders = [];
        $statusCode = 0;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                // First line is the status line
                if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                } elseif (strpos($header, ':') !== false) {
                    [$name, $value] = explode(':', $header, 2);
                    $responseHeaders[trim($name)] = trim($value);
                }
            }
        }

        return new HttpResponse($statusCode, $responseHeaders, $responseBody);
    }

    /**
     * {@inheritDoc}
     */
    public static function isAvailable(): bool
    {
        // Check if allow_url_fopen is enabled
        return (bool) ini_get('allow_url_fopen');
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'stream';
    }
}
