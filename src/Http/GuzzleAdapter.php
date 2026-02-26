<?php
declare(strict_types=1);

/**
 * Guzzle HTTP Adapter
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

use CoCart\Exceptions\CoCartException;

/**
 * Guzzle HTTP Adapter (Recommended)
 * 
 * Uses Guzzle HTTP client - the most popular and feature-rich
 * HTTP client for PHP.
 * 
 * Install via: composer require guzzlehttp/guzzle
 */
class GuzzleAdapter implements HttpAdapterInterface
{
    /**
     * Guzzle client instance
     *
     * @var \GuzzleHttp\Client|null
     */
    protected $client = null;

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
        $client = $this->getClient();

        $requestOptions = [
            'headers' => $headers,
            'timeout' => $options['timeout'] ?? 30,
            'verify' => $options['verify_ssl'] ?? true,
            'http_errors' => false, // Don't throw on 4xx/5xx
        ];

        if ($body !== null) {
            $requestOptions['body'] = $body;
        }

        try {
            $response = $client->request($method, $url, $requestOptions);

            // Convert Guzzle headers to simple array
            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }

            return new HttpResponse(
                $response->getStatusCode(),
                $responseHeaders,
                (string) $response->getBody()
            );
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new CoCartException("Guzzle HTTP Error: " . $e->getMessage(), 0, null, $e);
        }
    }

    /**
     * Get or create Guzzle client
     *
     * @return \GuzzleHttp\Client
     */
    protected function getClient(): \GuzzleHttp\Client
    {
        if ($this->client === null) {
            $this->client = new \GuzzleHttp\Client();
        }
        return $this->client;
    }

    /**
     * Set a custom Guzzle client (useful for testing or custom config)
     *
     * @param \GuzzleHttp\Client $client
     * @return void
     */
    public function setClient(\GuzzleHttp\Client $client): void
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public static function isAvailable(): bool
    {
        return class_exists('\GuzzleHttp\Client');
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'guzzle';
    }
}
