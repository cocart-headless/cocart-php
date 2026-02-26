<?php
declare(strict_types=1);

/**
 * cURL HTTP Adapter
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

use CoCart\Exceptions\CoCartException;

/**
 * cURL HTTP Adapter
 * 
 * Uses PHP's cURL extension - widely available and performant.
 */
class CurlAdapter implements HttpAdapterInterface
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
        $ch = curl_init();

        $timeout = $options['timeout'] ?? 30;
        $verifySsl = $options['verify_ssl'] ?? true;

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if ($body !== null) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $body;
                }
                break;
            case 'PUT':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($body !== null) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $body;
                }
                break;
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if ($body !== null) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $body;
                }
                break;
            case 'PATCH':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                if ($body !== null) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $body;
                }
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new CoCartException("cURL Error ({$errno}): {$error}");
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        $responseHeaders = $this->parseHeaders(substr($response, 0, $headerSize));
        $responseBody = substr($response, $headerSize);

        return new HttpResponse($statusCode, $responseHeaders, $responseBody);
    }

    /**
     * Format headers array for cURL
     *
     * @param array $headers
     * @return array
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }
        return $formatted;
    }

    /**
     * Parse response headers string
     *
     * @param string $headerString
     * @return array
     */
    protected function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * {@inheritDoc}
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('curl') && function_exists('curl_init');
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'curl';
    }
}
