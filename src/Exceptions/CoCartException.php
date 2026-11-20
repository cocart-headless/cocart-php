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

    /**
     * Get the cart sent with the error, when CoCart sends one
     *
     * `cocart_item_not_in_cart` (an item key that isn't in the cart) carries
     * the cart as it is now since CoCart Starter 5, so you can show it
     * without another request.
     *
     * @return array|null The cart, or null when the error has none
     */
    public function getCart(): ?array
    {
        $cart = $this->responseData['data']['cart'] ?? null;

        return is_array($cart) && $cart !== [] && !array_is_list($cart) ? $cart : null;
    }

    /**
     * Every field at fault, by path (e.g. `billing.postcode`), with its message
     *
     * CoCart Plus checks a whole form at once and sends them in `data.fields`.
     *
     * @return array<string, string> Empty when the error isn't about fields
     */
    public function getFields(): array
    {
        $fields = $this->responseData['data']['fields'] ?? null;
        if (!is_array($fields) || array_is_list($fields)) {
            return [];
        }

        return array_filter($fields, 'is_string');
    }

    /**
     * The first field at fault, as a path such as `billing.postcode` (`data.field`)
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        $field = $this->responseData['data']['field'] ?? null;

        return is_string($field) && $field !== '' ? $field : null;
    }

    /**
     * The underlying plugin's own reason, when there is one (`data.reason`)
     *
     * @return string|null
     */
    public function getReason(): ?string
    {
        $reason = $this->responseData['data']['reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
