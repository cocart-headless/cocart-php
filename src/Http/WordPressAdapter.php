<?php
declare(strict_types=1);

/**
 * WordPress HTTP Adapter
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

use CoCart\Exceptions\CoCartException;

/**
 * WordPress HTTP Adapter
 * 
 * Uses WordPress's built-in HTTP API (wp_remote_request).
 * Useful when building WordPress plugins that use this SDK.
 */
class WordPressAdapter implements HttpAdapterInterface
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
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => $options['timeout'] ?? 30,
            'sslverify' => $options['verify_ssl'] ?? true,
        ];

        if ($body !== null) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new CoCartException(
                "WordPress HTTP Error: " . $response->get_error_message()
            );
        }

        // Convert WordPress headers to array
        $wpHeaders = wp_remote_retrieve_headers($response);
        $responseHeaders = [];
        
        if ($wpHeaders instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary 
            || $wpHeaders instanceof \Requests_Utility_CaseInsensitiveDictionary) {
            $responseHeaders = $wpHeaders->getAll();
        } elseif (is_array($wpHeaders)) {
            $responseHeaders = $wpHeaders;
        }

        return new HttpResponse(
            wp_remote_retrieve_response_code($response),
            $responseHeaders,
            wp_remote_retrieve_body($response)
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function isAvailable(): bool
    {
        return function_exists('wp_remote_request');
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'wordpress';
    }
}
