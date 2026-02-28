<?php
declare(strict_types=1);

/**
 * Batch Endpoint
 *
 * Allows submitting multiple write operations in a single HTTP request
 * for performance optimization. Requires the CoCart Plus plugin.
 *
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\ValidationException;
use CoCart\Response;

class Batch extends Endpoint
{
    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = 'batch';

    /**
     * Queued requests
     *
     * @var array<array{method: string, path: string, body?: array}>
     */
    private array $requests = [];

    /**
     * Validation mode
     *
     * @var string
     */
    private string $validation = 'normal';

    /**
     * Queue a POST request
     *
     * @param string $path Short path (e.g. 'cart/add-item')
     * @param array  $body Request body data
     * @return static
     */
    public function add(string $path, array $body = []): static
    {
        return $this->queue('POST', $path, $body);
    }

    /**
     * Queue a PUT request
     *
     * @param string $path Short path (e.g. 'cart/item/abc123')
     * @param array  $body Request body data
     * @return static
     */
    public function update(string $path, array $body = []): static
    {
        return $this->queue('PUT', $path, $body);
    }

    /**
     * Queue a DELETE request
     *
     * @param string $path Short path (e.g. 'cart/item/abc123')
     * @return static
     */
    public function remove(string $path): static
    {
        return $this->queue('DELETE', $path);
    }

    /**
     * Set the validation mode
     *
     * @param string $mode 'normal' or 'require-all-validate'
     * @return static
     */
    public function setValidation(string $mode): static
    {
        $this->validation = $mode;
        return $this;
    }

    /**
     * Execute all queued requests as a single batch
     *
     * @return Response
     * @throws ValidationException If no requests are queued
     * @throws CoCartException
     */
    public function execute(): Response
    {
        if (empty($this->requests)) {
            throw new ValidationException('No requests queued for batch execution.', 400, 'cocart_batch_empty');
        }

        $this->client->requiresBasic('batch()->execute');

        $data = [
            'validation' => $this->validation,
            'requests' => $this->requests,
        ];

        try {
            $response = $this->client->requestRaw('POST', $this->buildNamespacedPath(), [], $data);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }

        // Clear queue after execution
        $this->requests = [];

        return $response;
    }

    /**
     * Clear all queued requests without executing
     *
     * @return static
     */
    public function clear(): static
    {
        $this->requests = [];
        return $this;
    }

    /**
     * Get the number of queued requests
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->requests);
    }

    /**
     * Queue a request
     *
     * @param string $method HTTP method
     * @param string $path   Short path
     * @param array  $body   Request body data
     * @return static
     * @throws ValidationException If max requests exceeded
     */
    private function queue(string $method, string $path, array $body = []): static
    {
        if (count($this->requests) >= 25) {
            throw new ValidationException('Maximum of 25 requests per batch.', 400, 'cocart_batch_max_exceeded');
        }

        $request = [
            'method' => $method,
            'path' => $this->buildVersionedPath($path),
        ];

        if (!empty($body)) {
            $request['body'] = $body;
        }

        $this->requests[] = $request;

        return $this;
    }

    /**
     * Build the full versioned path for a request
     *
     * Converts short paths like 'cart/add-item' to '/cocart/v2/cart/add-item'
     *
     * @param string $path Short path
     * @return string
     */
    private function buildVersionedPath(string $path): string
    {
        $namespace = $this->client->getNamespace();
        $apiVersion = 'v2';

        return '/' . $namespace . '/' . $apiVersion . '/' . ltrim($path, '/');
    }

    /**
     * Build the namespaced path for the batch endpoint itself
     *
     * @return string
     */
    private function buildNamespacedPath(): string
    {
        return $this->client->getNamespace() . '/' . $this->endpoint;
    }
}
