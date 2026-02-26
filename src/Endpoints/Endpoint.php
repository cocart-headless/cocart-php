<?php
declare(strict_types=1);

/**
 * Abstract Endpoint base class
 * 
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\CoCartInterface;
use CoCart\Exceptions\CoCartException;
use CoCart\Response;

abstract class Endpoint
{
    /**
     * CoCart client instance
     *
     * @var CoCartInterface
     */
    protected CoCartInterface $client;

    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = '';

    /**
     * Constructor
     *
     * @param CoCartInterface $client CoCart client instance
     */
    public function __construct(CoCartInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Build endpoint path
     *
     * @param string $path Additional path
     * @return string
     */
    protected function buildPath(string $path = ''): string
    {
        if (empty($path)) {
            return $this->endpoint;
        }
        return rtrim($this->endpoint, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Make a GET request
     *
     * @param string $path   Additional path
     * @param array  $params Query parameters
     * @return Response
     */
    protected function get(string $path = '', array $params = []): Response
    {
        try {
            return $this->client->get($this->buildPath($path), $params);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    /**
     * Make a POST request
     *
     * @param string $path   Additional path
     * @param array  $data   Request body data
     * @param array  $params Query parameters
     * @return Response
     */
    protected function post(string $path = '', array $data = [], array $params = []): Response
    {
        try {
            return $this->client->post($this->buildPath($path), $data, $params);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    /**
     * Make a PUT request
     *
     * @param string $path   Additional path
     * @param array  $data   Request body data
     * @param array  $params Query parameters
     * @return Response
     */
    protected function put(string $path = '', array $data = [], array $params = []): Response
    {
        try {
            return $this->client->put($this->buildPath($path), $data, $params);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    /**
     * Make a DELETE request
     *
     * @param string $path   Additional path
     * @param array  $params Query parameters
     * @return Response
     */
    protected function delete(string $path = '', array $params = []): Response
    {
        try {
            return $this->client->delete($this->buildPath($path), $params);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    /**
     * Handle rest_no_route errors with a friendly message
     *
     * @param CoCartException $e The original exception
     * @return never
     * @throws CoCartException
     */
    protected function handleNoRoute(CoCartException $e): never
    {
        if ($e->getErrorCode() === 'rest_no_route') {
            throw new CoCartException(
                'This method is only available with another CoCart plugin. Please ask support for assistance!',
                404,
                'cocart_plugin_required'
            );
        }

        throw $e;
    }
}
