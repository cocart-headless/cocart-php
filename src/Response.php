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
        return $this->getHeader('Cart-Key') ?? $this->getHeader('CoCart-API-Cart-Key');
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
     * Get notices from response, keyed by type (`success`, `error`, `notice`, `info`)
     *
     * For example `['success' => ['Item added to cart.']]`. Empty when there
     * are none (CoCart Starter 5 sends `{}`, CoCart 4.x `[]`).
     *
     * @return array<string, string[]>
     */
    public function getNotices(): array
    {
        $notices = $this->get('notices', []);

        return is_array($notices) && !array_is_list($notices) ? $notices : [];
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
     * Get the cart's shipping packages, each with its rates
     *
     * CoCart sends them keyed by package ID under `shipping.packages`; this
     * returns them as a list. A list (older or filtered responses) is
     * returned as is.
     *
     * @return list<array<string, mixed>>
     */
    public function getShippingMethods(): array
    {
        $shipping = $this->get('shipping');

        if (!is_array($shipping)) {
            return [];
        }

        if (array_is_list($shipping)) {
            return $shipping;
        }

        $packages = $shipping['packages'] ?? [];

        return is_array($packages) ? array_values($packages) : [];
    }

    /**
     * Get the cart's whole `shipping` field: the package count, whether
     * shipping has been calculated, and the packages keyed by package ID
     *
     * @return array{
     *     total_packages?: int,
     *     show_package_details?: bool,
     *     has_calculated_shipping?: bool,
     *     packages?: array<string, array<string, mixed>>
     * }|null Null when the response has no shipping field
     */
    public function getShipping(): ?array
    {
        $shipping = $this->get('shipping');

        return is_array($shipping) && $shipping !== [] && !array_is_list($shipping) ? $shipping : null;
    }

    /**
     * Get cart fees from response, as a list
     *
     * The cart keys fees by fee ID (`{}` when there are none; `[]` from
     * CoCart 4.x).
     *
     * @return list<array<string, mixed>>
     */
    public function getFees(): array
    {
        $fees = $this->get('fees', []);

        return is_array($fees) ? array_values($fees) : [];
    }

    /**
     * Get the cart's `extensions`: data plugins add to the cart, such as
     * subscription `recurring_totals` (see docs/extension-data.md)
     *
     * @return array<string, mixed> Empty when there is none
     */
    public function getExtensions(): array
    {
        $extensions = $this->get('extensions', []);

        return is_array($extensions) && !array_is_list($extensions) ? $extensions : [];
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

    /**
     * Get cart tax lines from response data, normalized to a flat array of
     * `['key' => ..., 'name' => ..., 'price' => ...]` entries.
     *
     * CoCart Starter 5.0+ already returns `taxes` as an array in this shape.
     * The community CoCart plugin (and older Starter versions) instead
     * return an object keyed by the tax rate code, e.g.
     * `{ "US-US-1": { "name": ..., "price": ... } }` — that legacy shape is
     * detected and converted here so callers never need to branch on which
     * plugin/version they're talking to.
     *
     * @return array
     */
    public function getTaxes(): array
    {
        $raw = $this->get('taxes', []);

        if (empty($raw)) {
            return [];
        }

        // Already a flat, list-style array — return as-is.
        if (array_is_list($raw)) {
            return $raw;
        }

        // Legacy object keyed by tax rate code — normalize to a flat array.
        $taxes = [];
        foreach ($raw as $key => $tax) {
            $taxes[] = [
                'key' => $key,
                'name' => is_array($tax) ? ($tax['name'] ?? null) : null,
                'price' => is_array($tax) ? ($tax['price'] ?? null) : null,
            ];
        }

        return $taxes;
    }

    /**
     * Check if the cart has any tax lines
     *
     * @return bool
     */
    public function hasTaxes(): bool
    {
        return !empty($this->getTaxes());
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
     * Get the `RateLimit-*` headers (CoCart Plus, when rate limiting is on)
     *
     * Each value is null when the header wasn't sent.
     *
     * @return array{limit: int|null, remaining: int|null, reset: int|null, retry_after: int|null}
     *         `reset` is the Unix time the window resets; `retry_after` the
     *         seconds to wait, on a 429
     */
    public function getRateLimit(): array
    {
        $number = function (string $name): ?int {
            $value = $this->getHeader($name);
            return $value !== null && is_numeric($value) ? (int) $value : null;
        };

        return [
            'limit' => $number('RateLimit-Limit'),
            'remaining' => $number('RateLimit-Remaining'),
            'reset' => $number('RateLimit-Reset'),
            'retry_after' => $number('RateLimit-Retry-After'),
        ];
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
