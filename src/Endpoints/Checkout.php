<?php
declare(strict_types=1);

/**
 * Checkout Endpoint
 *
 * Places orders and takes payment through CoCart Plus's checkout routes. Card
 * details never reach this code: the browser turns them into a payment method
 * ID with the gateway's own JavaScript, and the order is placed with that ID.
 *
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\CheckoutResult;
use CoCart\CoCartInterface;
use CoCart\Exceptions\CheckoutOutcomeUnknownException;
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\ValidationException;
use CoCart\Response;

class Checkout
{
    private const ROUTE = 'cocart/v2/checkout';
    private const API = 'cocart/v2';

    /** What `checkout_id` CoCart Plus accepts: 16 to 64 letters, digits, `-` or `_`. */
    private const CHECKOUT_ID = '/^[A-Za-z0-9_-]{16,64}$/';

    /**
     * @param CoCartInterface $client CoCart client
     */
    public function __construct(private CoCartInterface $client)
    {
    }

    /**
     * The checkout as it stands, like a receipt
     *
     * Items, coupons, fees, `totals` and `total_lines`, the payment method,
     * the addresses, each shipping package with its rates, and `actions`.
     * Call it for the page the customer reviews before paying: CoCart Plus
     * remembers the total it showed, and refuses the order if the total has
     * changed by then (`409 cocart_checkout_total_mismatch`). Don't call it
     * just before placing the order.
     *
     * @param array $params Optional: `currency` (a currency code), `formatted` (bool: amounts
     *                      as formatted strings), `thumb` (bool: item thumbnails)
     * @return Response
     * @throws CoCartException
     */
    public function get(array $params = []): Response
    {
        return $this->client->requestRaw('GET', self::ROUTE, self::query($params));
    }

    /**
     * Save what the customer has entered so far, without placing the order
     *
     * Addresses, the shipping method, the payment method, a coupon
     * (`coupon_code` with `coupon_action` `apply` or `remove`), the currency
     * or a note. Returns the checkout with what it did in `result`:
     * `checkout_updated`, or `checkout_partly_updated` with what wasn't
     * applied, and why, in `result.not_applied` (`[{field, code, message}]`).
     * Every address field at fault is reported at once
     * (CoCartException::getFields()).
     *
     * @param array $data Addresses, shipping method, payment method, coupon, note
     * @return Response
     * @throws CoCartException
     */
    public function update(array $data): Response
    {
        return $this->client->requestRaw('PUT', self::ROUTE, [], $data);
    }

    /**
     * Create the order and attempt payment, sending `payment_data` as given
     * (the low-level call behind placeOrder())
     *
     * @param array $data Checkout data
     * @return Response
     * @throws CoCartException
     */
    public function process(array $data): Response
    {
        return $this->client->requestRaw('POST', self::ROUTE, [], $data);
    }

    /**
     * Create the order and take payment
     *
     * `payment_data` is a map of the keys the WooCommerce gateway reads, e.g.
     * `['wc-stripe-payment-method' => 'pm_…']`: gateway tokens only, never
     * card numbers. A free order needs neither it nor `payment_method`.
     * Calling again for the same cart lands on the same order. A declined card
     * is a failed result, not an exception; errors before payment (missing
     * fields, an empty cart) are thrown.
     *
     * The order is sent with a random `checkout_id` (or the one in `$input`,
     * which must be 16 to 64 letters, digits, `-` or `_`). When no answer comes
     * back, or another request is still taking payment for the cart
     * (`409 cocart_payment_in_progress`), the order may have been placed: this
     * throws CheckoutOutcomeUnknownException, and
     * getStatus($e->getCheckoutId()) tells how it ended.
     *
     * A total that changed since get() or update() is
     * `409 cocart_checkout_total_mismatch`: get the checkout again and show
     * the new total.
     *
     * @param array $input Checkout data
     * @return CheckoutResult
     * @throws ValidationException When the `checkout_id` given is malformed
     *                             (`checkout_invalid_checkout_id`); nothing is sent
     * @throws CheckoutOutcomeUnknownException When no answer came back, or another payment is still running
     * @throws CoCartException
     */
    public function placeOrder(array $input): CheckoutResult
    {
        $checkoutId = $input['checkout_id'] ?? self::newCheckoutId();

        if (!is_string($checkoutId) || preg_match(self::CHECKOUT_ID, $checkoutId) !== 1) {
            throw new ValidationException(
                'checkout_id must be 16 to 64 letters, digits, "-" or "_".',
                0,
                'checkout_invalid_checkout_id'
            );
        }

        $input['checkout_id'] = $checkoutId;
        if (isset($input['meta_keys']) && is_array($input['meta_keys'])) {
            $input['meta_keys'] = implode(',', $input['meta_keys']);
        }

        try {
            return CheckoutResult::fromResponse($this->process($this->withPaymentDataPairs($input)));
        } catch (CoCartException $e) {
            if (self::isLostResponse($e) || self::isPaymentInProgress($e)) {
                throw new CheckoutOutcomeUnknownException($e, $checkoutId);
            }
            throw $e;
        }
    }

    /**
     * How a checkout ended, when placeOrder() threw CheckoutOutcomeUnknownException
     *
     * Ask from the same cart, or as the same customer. `code` is:
     *
     * - `order_placed`: show the order (`order_id`, `order_key`).
     * - `payment_required`: the order was placed but isn't paid; pay it with payForOrder().
     * - `in_progress`: the checkout is still running; ask again in a few seconds.
     * - `not_placed`: nothing went through; place the order again.
     *
     * `message` is for the customer. The order fields are null unless the
     * order was placed or needs payment.
     *
     * @param string $checkoutId The `checkout_id` the order was sent with
     * @return array{checkout_id: string, code: string, message: string, order_id: int|null, order_key: string|null, order_number: string|null, order_status: string|null}
     * @throws CoCartException
     */
    public function getStatus(string $checkoutId): array
    {
        $data = $this->client->requestRaw('GET', self::ROUTE . '/status', ['checkout_id' => $checkoutId])->toArray();
        $result = is_array($data['result'] ?? null) ? $data['result'] : [];
        $order = is_array($data['order'] ?? null) ? $data['order'] : [];
        $status = is_array($order['status'] ?? null) ? $order['status'] : [];

        return [
            'checkout_id' => is_string($data['checkout_id'] ?? null) ? $data['checkout_id'] : $checkoutId,
            'code' => is_string($result['code'] ?? null) ? $result['code'] : '',
            'message' => is_string($result['message'] ?? null) ? $result['message'] : '',
            'order_id' => is_int($order['id'] ?? null) ? $order['id'] : null,
            'order_key' => is_string($order['key'] ?? null) ? $order['key'] : null,
            'order_number' => isset($order['number']) ? (string) $order['number'] : null,
            'order_status' => is_string($status['code'] ?? null) ? $status['code'] : null,
        ];
    }

    /**
     * The store's form settings: fields, countries, shipping, account and store settings (no cart needed)
     *
     * Also `order_received_url` (for confirmReturn()), `address_autocomplete`
     * (`enabled`, and `providers` as `[{id, name}]`; without a provider,
     * searchAddresses() is `404 cocart_address_search_no_provider`), and in
     * `store`: `terms_text`, `terms_links` (`[{text, url}]`), `terms_url` and
     * `privacy_url`.
     *
     * @return Response
     * @throws CoCartException
     */
    public function getConfig(): Response
    {
        return $this->client->requestRaw('GET', self::ROUTE . '/config');
    }

    /**
     * The payment methods for this cart: `{payment_methods: [...]}`, each with
     * its `id`, `title`, `supports` and a logged-in customer's `saved_tokens`
     *
     * @return Response
     * @throws CoCartException
     */
    public function getPaymentMethods(): Response
    {
        return $this->client->requestRaw('GET', self::ROUTE . '/payment-methods');
    }

    /**
     * A logged-in customer's saved payment methods, for every gateway or one
     *
     * Read from getPaymentMethods(). Each has `id`, `gateway_id`, `label`
     * (e.g. "Visa ending in 4242"), `type`, `expires` (or null), `is_default`
     * and `supports_subscriptions`. Pay with one as
     * `wc-{gateway_id}-payment-token` in `payment_data`, or change a
     * subscription's card with its `id` as `token_id`. Empty for a guest.
     *
     * @param string|null $gatewayId Only this gateway's saved methods
     * @return array<array{id: int, gateway_id: string, label: string, type: string, expires: string|null, is_default: bool, supports_subscriptions: bool}>
     * @throws CoCartException
     */
    public function getSavedPaymentMethods(?string $gatewayId = null): array
    {
        $response = $this->getPaymentMethods()->toArray();
        $methods = is_array($response['payment_methods'] ?? null) ? $response['payment_methods'] : [];
        $cards = [];

        foreach ($methods as $method) {
            if (!is_array($method) || !is_string($method['id'] ?? null)) {
                continue;
            }
            if ($gatewayId !== null && $method['id'] !== $gatewayId) {
                continue;
            }

            $supports = is_array($method['supports'] ?? null) ? $method['supports'] : [];

            foreach (is_array($method['saved_tokens'] ?? null) ? $method['saved_tokens'] : [] as $token) {
                if (!is_array($token)) {
                    continue;
                }
                $cards[] = [
                    'id' => (int) ($token['id'] ?? 0),
                    'gateway_id' => $method['id'],
                    'label' => (string) ($token['label'] ?? ''),
                    'type' => (string) ($token['type'] ?? ''),
                    'expires' => is_string($token['expires'] ?? null) && $token['expires'] !== '' ? $token['expires'] : null,
                    'is_default' => ($token['is_default'] ?? false) === true,
                    'supports_subscriptions' => ($supports['subscriptions'] ?? false) === true,
                ];
            }
        }

        return $cards;
    }

    /**
     * Address suggestions (no cart needed; at least 3 characters)
     *
     * Without a server-side address provider it's
     * `404 cocart_address_search_no_provider`: check getConfig()'s
     * `address_autocomplete.enabled` first.
     *
     * @param string      $query    What the customer typed
     * @param string|null $country  Two-letter country code to search in
     * @param string|null $type     `billing` or `shipping`
     * @param string|null $provider Address provider ID
     * @return Response
     * @throws CoCartException
     */
    public function searchAddresses(string $query, ?string $country = null, ?string $type = null, ?string $provider = null): Response
    {
        $params = array_filter(
            ['query' => $query, 'country' => $country, 'type' => $type, 'provider' => $provider],
            fn ($value) => $value !== null
        );

        return $this->client->requestRaw('GET', self::API . '/address/search', $params);
    }

    /**
     * A suggestion's full address (no cart needed)
     *
     * @param string $addressId The suggestion's ID
     * @param string $provider  The provider that returned it
     * @return Response
     * @throws CoCartException
     */
    public function getAddressDetails(string $addressId, string $provider): Response
    {
        return $this->client->requestRaw('GET', self::API . '/address/details', [
            'address_id' => $addressId,
            'provider' => $provider,
        ]);
    }

    /**
     * The order, for the thank-you page
     *
     * `$orderKey` must match the order's key; a wrong key, or someone else's
     * order, is `404 cocart_order_not_found`.
     *
     * @param int    $orderId  Order ID
     * @param string $orderKey Order key
     * @param array  $params   Optional: `formatted` (bool: amounts as formatted strings),
     *                         `meta_keys` (a list of order meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getOrderReceived(int $orderId, string $orderKey, array $params = []): Response
    {
        return $this->client->requestRaw(
            'GET',
            self::API . "/order-received/{$orderId}",
            array_merge(self::query($params), ['order_key' => $orderKey])
        );
    }

    /**
     * Take payment for an unpaid (`pending` or `failed`) order
     *
     * Read the result as for placeOrder(): a card that needs 3D Secure gives
     * needsConfirmation(), then call again with the same input. A declined card
     * is a failed result, not an exception. The order key is sent in the body,
     * not the URL. An order that needs no payment or can't be paid is a `409`,
     * and so is another payment still running for it
     * (`cocart_payment_in_progress`).
     *
     * @param int    $orderId  Order ID
     * @param string $orderKey Order key
     * @param array  $input    `payment_method` and `payment_data`
     * @return CheckoutResult
     * @throws CoCartException
     */
    public function payForOrder(int $orderId, string $orderKey, array $input): CheckoutResult
    {
        $input['order_key'] = $orderKey;

        return CheckoutResult::fromResponse($this->client->requestRaw(
            'POST',
            self::API . "/order-received/{$orderId}/pay",
            [],
            $this->withPaymentDataPairs($input)
        ));
    }

    /**
     * Finish a payment after an off-site step, from the parameters the customer came back with
     *
     * Pass every query parameter the return URL carried, unchanged. The order
     * key is sent in the body. Safe to call more than once. confirmReturn()
     * does this straight from the return URL and reads the result.
     *
     * @param int    $orderId      Order ID
     * @param string $orderKey     Order key
     * @param array  $returnParams Every query parameter of the return URL
     * @return Response
     * @throws CoCartException
     */
    public function confirmOrderPayment(int $orderId, string $orderKey, array $returnParams = []): Response
    {
        return $this->client->requestRaw(
            'POST',
            self::API . "/order-received/{$orderId}/confirm",
            [],
            ['order_key' => $orderKey, 'return_params' => $returnParams]
        );
    }

    /**
     * Finish a payment from the URL the customer returned to after an off-site step
     *
     * Reads the order ID (`order_id`) and key (`key`) from the URL and forwards every
     * query parameter. isPending() means the gateway hasn't confirmed yet: call
     * again shortly. Safe to call more than once.
     *
     * @param string      $url      The return URL, e.g. `$_SERVER['REQUEST_URI']`
     * @param int|null    $orderId  The order ID, when the URL carries it elsewhere
     * @param string|null $orderKey The order key, when the URL carries it elsewhere
     * @return CheckoutResult
     * @throws CoCartException
     */
    public function confirmReturn(string $url, ?int $orderId = null, ?string $orderKey = null): CheckoutResult
    {
        $params = self::queryParams($url);
        $orderId ??= ctype_digit((string) ($params['order_id'] ?? '')) ? (int) $params['order_id'] : 0;
        $orderKey ??= (string) ($params['key'] ?? '');

        if ($orderId <= 0 || $orderKey === '') {
            throw new CoCartException(
                'The return URL has no order ID or order key. Pass them if your route carries them elsewhere.',
                0,
                'checkout_return_missing_order'
            );
        }

        return CheckoutResult::fromResponse($this->confirmOrderPayment($orderId, $orderKey, $params));
    }

    /**
     * A random ID for one checkout: `chk_` and 32 hex digits
     *
     * @return string
     */
    private static function newCheckoutId(): string
    {
        return 'chk_' . bin2hex(random_bytes(16));
    }

    /**
     * Whether no answer came back: the connection dropped, it timed out, or a proxy gave up
     *
     * @param CoCartException $e The error
     * @return bool
     */
    private static function isLostResponse(CoCartException $e): bool
    {
        $status = $e->getHttpCode();

        return ($status === 0 && $e->getErrorCode() === null) || $status === 502 || $status === 504;
    }

    /**
     * Whether another request is still taking payment for the same cart, so
     * how this one ends isn't known yet
     *
     * @param CoCartException $e The error
     * @return bool
     */
    private static function isPaymentInProgress(CoCartException $e): bool
    {
        return $e->getHttpCode() === 409 && $e->getErrorCode() === 'cocart_payment_in_progress';
    }

    private function withPaymentDataPairs(array $input): array
    {
        if (isset($input['payment_data']) && is_array($input['payment_data'])) {
            $input['payment_data'] = CheckoutResult::toPaymentDataPairs($input['payment_data']);
        }

        return $input;
    }

    /**
     * Query parameters as strings: a list is comma-joined, a bool is `true` or
     * `false`, and null values are left out
     *
     * @param array $params Query parameters
     * @return array<string, string>
     */
    private static function query(array $params): array
    {
        $query = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $query[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => implode(',', $value),
                default => (string) $value,
            };
        }

        return $query;
    }

    /**
     * A URL's query parameters, decoded, with every value as a string
     *
     * @return array<string, string>
     */
    private static function queryParams(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $params = [];

        foreach (explode('&', is_string($query) ? $query : '') as $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $params[urldecode($key)] = urldecode($value);
        }

        return $params;
    }
}
