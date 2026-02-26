<?php
declare(strict_types=1);

/**
 * HTTP Response Container
 * 
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

/**
 * HTTP Response container
 * 
 * Simple value object to hold HTTP response data from adapters.
 */
class HttpResponse
{
    /**
     * HTTP status code
     *
     * @var int
     */
    public readonly int $statusCode;

    /**
     * Response headers
     *
     * @var array
     */
    public readonly array $headers;

    /**
     * Response body
     *
     * @var string
     */
    public readonly string $body;

    /**
     * Constructor
     *
     * @param int    $statusCode HTTP status code
     * @param array  $headers    Response headers
     * @param string $body       Response body
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }
}
