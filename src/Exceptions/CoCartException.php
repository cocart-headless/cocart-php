<?php
declare(strict_types=1);

/**
 * Base Exception for CoCart SDK
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class CoCartException extends \Exception
{
    /**
     * Error code from API
     *
     * @var string|null
     */
    protected ?string $errorCode;

    /**
     * Full response data from the API
     *
     * @var array
     */
    protected array $responseData;

    /**
     * Constructor
     *
     * @param string          $message      Error message
     * @param int             $httpCode     HTTP status code
     * @param string|null     $errorCode    API error code
     * @param array           $responseData Full API response data
     * @param \Throwable|null $previous     Previous exception
     */
    public function __construct(
        string $message = '',
        int $httpCode = 0,
        ?string $errorCode = null,
        array $responseData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpCode, $previous);
        $this->errorCode = $errorCode;
        $this->responseData = $responseData;
    }

    /**
     * Get the API error code
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->getCode();
    }

    /**
     * Get the full API response data
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
