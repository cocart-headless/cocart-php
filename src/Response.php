<?php
declare(strict_types=1);

/**
 * Response class for handling API responses
 *
 * @package CoCart\SDK
 */

namespace CoCart;

class Response implements \ArrayAccess
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode;

    /**
     * Response headers
     *
     * @var array
     */
    protected array $headers;

    /**
     * Raw response body
     *
     * @var string
     */
    protected string $body;

    /**
     * Decoded response data
     *
     * @var array|null
     */
    protected ?array $data = null;

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

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header
     *
     * @param string      $name    Header name
     * @param string|null $default Default value if header not found
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        // Check case-insensitive
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Get raw body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get decoded data as array
     *
     * @return array
     */
    public function toArray(): array
    {
        if ($this->data === null) {
            $this->data = json_decode($this->body, true) ?? [];
        }
        return $this->data;
    }

    /**
     * Get decoded data as object
     *
     * @return object
     */
    public function toObject(): object
    {
        return json_decode($this->body) ?? new \stdClass();
    }

    /**
     * Check if response was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response was an error
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }

    /**
     * Get cart key from response headers
     *
     * @return string|null
     */
    public function getCartKey(): ?string
    {
        return $this->getHeader('Cart-Key');
    }

    /**
     * Get cart hash from response data
     *
     * @return string|null
     */
    public function getCartHash(): ?string
    {
        $data = $this->toArray();
        return $data['cart_hash'] ?? null;
    }

    /**
     * Get a specific value from the response data
     *
     * @param string $key     Dot-notation key (e.g., 'totals.total')
     * @param mixed  $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $data = $this->toArray();
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (!is_array($data) || !array_key_exists($k, $data)) {
                return $default;
            }
            $data = $data[$k];
        }

        return $data;
    }

    /**
     * Check if the response contains a specific key
     *
     * @param string $key Dot-notation key
     * @return bool
     */
    public function has(string $key): bool
    {
        $data = $this->toArray();
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (!is_array($data) || !array_key_exists($k, $data)) {
                return false;
            }
            $data = $data[$k];
        }

        return true;
    }

    // --- Cart helpers ---

    /**
     * Get cart items from response
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->get('items', []);
    }

    /**
     * Get cart totals from response
     *
     * @return array
     */
    public function getTotals(): array
    {
        return $this->get('totals', []);
    }

    /**
     * Get item count from response
     *
     * @return int
     */
    public function getItemCount(): int
    {
        return (int) $this->get('item_count', 0);
    }

    /**
     * Check if cart has items
     *
     * @return bool
     */
    public function hasItems(): bool
    {
        return $this->getItemCount() > 0;
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->getItemCount() === 0;
    }

    /**
     * Get notices from response
     *
     * @return array
     */
    public function getNotices(): array
    {
        return $this->get('notices', []);
    }

    /**
     * Get applied coupons
     *
     * @return array
     */
    public function getCoupons(): array
    {
        return $this->get('coupons', []);
    }

    /**
     * Check if cart has coupons applied
     *
     * @return bool
     */
    public function hasCoupons(): bool
    {
        return !empty($this->getCoupons());
    }

    /**
     * Get customer details from response
     *
     * @return array
     */
    public function getCustomer(): array
    {
        return $this->get('customer', []);
    }

    /**
     * Get currency information from response
     *
     * @return array
     */
    public function getCurrency(): array
    {
        return $this->get('currency', []);
    }

    /**
     * Get shipping methods from response
     *
     * @return array
     */
    public function getShippingMethods(): array
    {
        return $this->get('shipping', []);
    }

    /**
     * Get cart fees from response
     *
     * @return array
     */
    public function getFees(): array
    {
        return $this->get('fees', []);
    }

    /**
     * Get cross-sell products from response
     *
     * @return array
     */
    public function getCrossSells(): array
    {
        return $this->get('cross_sells', []);
    }

    // --- ETag / Cache helpers ---

    /**
     * Get ETag header from response
     *
     * @return string|null
     */
    public function getETag(): ?string
    {
        return $this->getHeader('ETag');
    }

    /**
     * Check if response is 304 Not Modified
     *
     * @return bool
     */
    public function isNotModified(): bool
    {
        return $this->statusCode === 304;
    }

    /**
     * Get CoCart cache status header (HIT, MISS, or SKIP)
     *
     * @return string|null
     */
    public function getCacheStatus(): ?string
    {
        return $this->getHeader('CoCart-Cache');
    }

    // --- Pagination helpers (WP REST API standard headers) ---

    /**
     * Get total number of results (from X-WP-Total header)
     *
     * @return int|null
     */
    public function getTotalResults(): ?int
    {
        $total = $this->getHeader('X-WP-Total');
        return $total !== null ? (int) $total : null;
    }

    /**
     * Get total number of pages (from X-WP-TotalPages header)
     *
     * @return int|null
     */
    public function getTotalPages(): ?int
    {
        $pages = $this->getHeader('X-WP-TotalPages');
        return $pages !== null ? (int) $pages : null;
    }

    // --- Error helpers ---

    /**
     * Get error code from error response
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        if (!$this->isError()) {
            return null;
        }
        return $this->get('code');
    }

    /**
     * Get error message from error response
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        if (!$this->isError()) {
            return null;
        }
        return $this->get('message');
    }

    // --- Data access ---

    /**
     * Magic method to access data as properties
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * Convert response to JSON string
     *
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }

    // --- ArrayAccess implementation ---

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Read-only
    }

    public function offsetUnset(mixed $offset): void
    {
        // Read-only
    }
}
