<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\Http\HttpAdapterInterface;
use CoCart\Http\HttpResponse;

/**
 * Mock HTTP adapter for testing.
 *
 * Records all requests and returns pre-configured responses.
 */
class MockHttpAdapter implements HttpAdapterInterface
{
    /** @var HttpResponse[] */
    private array $responses = [];

    /** @var array<array{method: string, url: string, headers: array, body: ?string}> */
    private array $requests = [];

    /**
     * Queue a response to be returned on the next request.
     */
    public function queueResponse(int $statusCode = 200, array $headers = [], string $body = '{}'): self
    {
        $this->responses[] = new HttpResponse($statusCode, $headers, $body);
        return $this;
    }

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
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        if (empty($this->responses)) {
            return new HttpResponse(200, [], '{}');
        }

        return array_shift($this->responses);
    }

    /**
     * Get all recorded requests.
     *
     * @return array<array{method: string, url: string, headers: array, body: ?string}>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the last recorded request.
     *
     * @return array{method: string, url: string, headers: array, body: ?string}|null
     */
    public function getLastRequest(): ?array
    {
        return empty($this->requests) ? null : end($this->requests);
    }

    /**
     * {@inheritDoc}
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'mock';
    }
}
