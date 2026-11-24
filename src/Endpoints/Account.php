<?php
declare(strict_types=1);

/**
 * Account Endpoint
 *
 * Handles all my-account API operations: profile, address, orders,
 * downloads, reviews, subscriptions and email verification (CoCart Plus).
 *
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\CheckoutResult;
use CoCart\Exceptions\CoCartException;
use CoCart\Response;

class Account extends Endpoint
{
    /**
     * Endpoint prefix (unused — all calls use raw routing)
     *
     * @var string
     */
    protected string $endpoint = '';

    // MARK: - Helpers

    private function rawPath(string $sub = ''): string
    {
        return $sub === '' ? 'cocart/v2/my-account' : 'cocart/v2/my-account/' . ltrim($sub, '/');
    }

    private function raw(string $method, string $route, array $params = [], ?array $data = null): Response
    {
        try {
            return $this->client->requestRaw($method, $route, $params, $data);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    private function getRaw(string $sub = '', array $params = []): Response
    {
        return $this->raw('GET', $this->rawPath($sub), self::query($params));
    }

    /**
     * Query parameters as strings: a list is comma-joined (`status`,
     * `meta_keys`), a bool is `true` or `false` (`formatted`), and null values
     * are left out
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

    private function postRaw(string $sub = '', ?array $data = []): Response
    {
        return $this->raw('POST', $this->rawPath($sub), [], $data);
    }

    // MARK: - Profile

    /**
     * Get the signed-in customer: name, email, addresses and order stats
     *
     * @param array $params Optional: `formatted` (bool), `meta_keys` (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getProfile(array $params = []): Response
    {
        return $this->getRaw('', $params);
    }

    /**
     * Save the account details: send only what changes
     *
     * Returns the customer with `account_updated` in `result`. Every field at
     * fault is reported at once (CoCartException::getFields()).
     *
     * @param array $data Profile fields (account_first_name, account_last_name, account_display_name, account_email)
     * @return Response
     * @throws CoCartException
     */
    public function updateProfile(array $data): Response
    {
        return $this->postRaw('', $data);
    }

    /**
     * Change the authenticated user's password (`POST /my-account`)
     *
     * Returns the customer with `account_updated` in `result`. A wrong current
     * password is a ValidationException
     * (`cocart_account_incorrect_current_password`).
     *
     * @param string $current  Current password
     * @param string $password New password
     * @param string $confirm  Confirm new password
     * @return Response
     * @throws CoCartException
     */
    public function changePassword(string $current, string $password, string $confirm): Response
    {
        return $this->postRaw('', [
            'password_current' => $current,
            'password_1'       => $password,
            'password_2'       => $confirm,
        ]);
    }

    /**
     * Update the customer's saved billing and/or shipping address (requires CoCart Plus)
     *
     * Fields are unprefixed (`first_name`, `address_1`…). Returns the customer.
     *
     * @param array $data ['billing_address' => [...], 'shipping_address' => [...]]
     * @return Response
     * @throws CoCartException
     */
    public function updateAddress(array $data): Response
    {
        return $this->postRaw('address', $data);
    }

    // MARK: - Orders

    /**
     * Get the customer's orders, newest first
     *
     * Returns `{orders, pagination}`; the totals are also in the `X-WP-Total`
     * and `X-WP-TotalPages` headers.
     *
     * @param array $params Query params: page, per_page, order (ASC|DESC),
     *                      status (one status or a list), after and before (ISO 8601 dates),
     *                      subscription_order_type (parent, renewal, switch or resubscribe),
     *                      formatted (bool), meta_keys (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getOrders(array $params = []): Response
    {
        return $this->getRaw('orders', $params);
    }

    /**
     * Get one of the customer's orders
     *
     * Someone else's order is `404 cocart_order_not_found`.
     *
     * @param int   $id     Order ID
     * @param array $params Optional: `formatted` (bool), `meta_keys` (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getOrder(int $id, array $params = []): Response
    {
        return $this->getRaw("orders/{$id}", $params);
    }

    /**
     * Get a guest's order, looked up with its billing email
     *
     * It comes without its `key`. Lookups are rate limited
     * (`429 cocart_guest_lookup_rate_limited`).
     *
     * @param int    $id     Order ID
     * @param string $email  Billing email used when placing the order
     * @param array  $params Optional: `formatted` (bool), `meta_keys` (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getGuestOrder(int $id, string $email, array $params = []): Response
    {
        return $this->getRaw("orders/{$id}", array_merge($params, ['email' => $email]));
    }

    /**
     * Cancel one of the customer's orders, where WooCommerce allows it (e.g.
     * pending or failed). Requires CoCart Plus.
     *
     * @param int $id Order ID
     * @return Response The order, with `result`
     * @throws CoCartException
     */
    public function cancelOrder(int $id): Response
    {
        return $this->postRaw("orders/{$id}/cancel", null);
    }

    // MARK: - Downloads

    /**
     * Get the files one of the customer's orders can download
     *
     * @param int $id Order ID
     * @return Response The list of downloads
     * @throws CoCartException
     */
    public function getOrderDownloads(int $id): Response
    {
        return $this->getRaw("orders/{$id}/downloads");
    }

    /**
     * Get the files a guest's order can download, looked up with its billing email
     *
     * @param int    $id    Order ID
     * @param string $email Billing email used when placing the order
     * @return Response The list of downloads
     * @throws CoCartException
     */
    public function getGuestOrderDownloads(int $id, string $email): Response
    {
        return $this->getRaw("orders/{$id}/downloads", ['email' => $email]);
    }

    /**
     * Get every file the customer can download
     *
     * @return Response The list of downloads
     * @throws CoCartException
     */
    public function getDownloads(): Response
    {
        return $this->getRaw('downloads');
    }

    // MARK: - Reviews

    /**
     * Get the authenticated user's product reviews
     *
     * Uses `GET /products/reviews/mine`; same as `products()->myReviews()`.
     *
     * @param array $params Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function getReviews(array $params = []): Response
    {
        return $this->client->products()->myReviews($params);
    }

    // MARK: - Email verification (CoCart Plus)

    /**
     * Confirm a pending email change with the `key` and `user_id` from the emailed link
     *
     * @param string $key    The key from the link
     * @param int    $userId The user ID from the link
     * @return Response
     * @throws CoCartException
     */
    public function verifyEmail(string $key, int $userId): Response
    {
        return $this->getRaw('verify-email', ['key' => $key, 'user_id' => (string) $userId]);
    }

    /**
     * Email the customer a link proving they own their account email, so
     * WooCommerce can link past guest orders placed with it
     *
     * Returns where it went, with `retry_after` (seconds before another can
     * be sent). WooCommerce 11+.
     *
     * @return Response
     * @throws CoCartException
     */
    public function sendOrderEmailVerification(): Response
    {
        return $this->postRaw('verify-order-email', null);
    }

    /**
     * Confirm the order-email verification with the `key` and `user_id` from the emailed link
     *
     * @param string $key    The key from the link
     * @param int    $userId The user ID from the link
     * @return Response The customer, with `result`
     * @throws CoCartException
     */
    public function verifyOrderEmail(string $key, int $userId): Response
    {
        return $this->getRaw('verify-order-email', ['key' => $key, 'user_id' => (string) $userId]);
    }

    // MARK: - Subscriptions (CoCart Plus with WooCommerce Subscriptions)

    /**
     * Get the customer's subscriptions, with `pagination`
     *
     * @param array $params Query params: page, per_page, order (ASC|DESC), status,
     *                      formatted (bool), meta_keys (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getSubscriptions(array $params = []): Response
    {
        return $this->getRaw('subscriptions', $params);
    }

    /**
     * Get one subscription. Pass `$email` to view it as a guest.
     *
     * @param int         $id     Subscription ID
     * @param string|null $email  Billing email, for a guest
     * @param array       $params Optional: `formatted` (bool), `meta_keys` (a list of meta keys to include)
     * @return Response
     * @throws CoCartException
     */
    public function getSubscription(int $id, ?string $email = null, array $params = []): Response
    {
        return $this->getRaw("subscriptions/{$id}", $email !== null ? array_merge($params, ['email' => $email]) : $params);
    }

    /**
     * Suspend (put on hold) a subscription
     *
     * @param int $id Subscription ID
     * @return Response
     * @throws CoCartException
     */
    public function suspendSubscription(int $id): Response
    {
        return $this->postRaw("subscriptions/{$id}/suspend", null);
    }

    /**
     * Cancel a subscription
     *
     * @param int $id Subscription ID
     * @return Response
     * @throws CoCartException
     */
    public function cancelSubscription(int $id): Response
    {
        return $this->postRaw("subscriptions/{$id}/cancel", null);
    }

    /**
     * Reactivate a suspended subscription
     *
     * @param int $id Subscription ID
     * @return Response
     * @throws CoCartException
     */
    public function reactivateSubscription(int $id): Response
    {
        return $this->postRaw("subscriptions/{$id}/reactivate", null);
    }

    /**
     * Turn automatic renewal on or off
     *
     * @param int  $id      Subscription ID
     * @param bool $enabled Whether the subscription renews automatically
     * @return Response
     * @throws CoCartException
     */
    public function setSubscriptionAutoRenew(int $id, bool $enabled): Response
    {
        return $this->postRaw("subscriptions/{$id}/auto-renew", ['enabled' => $enabled]);
    }

    /**
     * Change the card a subscription renews with: a saved card or a new one
     *
     * A new card may need 3D Secure, so this returns a CheckoutResult, read as at
     * checkout: isComplete() once changed (`payment_method_changed`, with
     * getSubscriptionId()); on needsConfirmation(), confirm getClientSecret()
     * with the gateway's SDK and call again with the same input; on
     * needsRedirect(), send the customer to getRedirectUrl(). A declined card
     * is a failed result, not an exception. Another change still running is
     * `409 cocart_payment_in_progress`.
     *
     * @param int   $id   Subscription ID
     * @param array $data payment_method (gateway ID, e.g. 'stripe'), and token_id (a saved
     *                    card's ID) or payment_data (a new card, e.g.
     *                    ['wc-stripe-payment-method' => 'pm_…'])
     * @return CheckoutResult
     * @throws CoCartException
     */
    public function updateSubscriptionPaymentMethod(int $id, array $data): CheckoutResult
    {
        if (isset($data['payment_data']) && is_array($data['payment_data'])) {
            $data['payment_data'] = CheckoutResult::toPaymentDataPairs($data['payment_data']);
        }

        return CheckoutResult::fromResponse($this->postRaw("subscriptions/{$id}/payment-method", $data));
    }

    /**
     * Put an ended subscription's items in the cart to subscribe again
     * (checkout follows as usual)
     *
     * @param int $id Subscription ID
     * @return Response
     * @throws CoCartException
     */
    public function resubscribe(int $id): Response
    {
        return $this->postRaw("subscriptions/{$id}/resubscribe", null);
    }

    /**
     * Undo `resubscribe()`: restore the cart as it was before
     *
     * @param int $id Subscription ID
     * @return Response
     * @throws CoCartException
     */
    public function cancelResubscribe(int $id): Response
    {
        return $this->raw('DELETE', $this->rawPath("subscriptions/{$id}/resubscribe"));
    }

    // MARK: - Order withdrawal (CoCart Plus)

    /**
     * Submit an order withdrawal (right of withdrawal) request
     *
     * Doesn't need the customer logged in.
     *
     * @param array $data first_name, last_name, email, email_confirmation, order_number,
     *                    and optionally withdrawal_type ('full_order' or 'specific_items_only')
     *                    and additional_details (required for 'specific_items_only')
     * @return Response
     * @throws CoCartException
     */
    public function requestOrderWithdrawal(array $data): Response
    {
        return $this->raw('POST', 'cocart/v2/order-withdrawal', [], $data);
    }

    // MARK: - Registration (CoCart Plus)

    /**
     * Register a new customer
     *
     * Unauthenticated: the customer doesn't need to be logged in. The
     * username and password are sent as `requested_username` and
     * `requested_password`, because CoCart treats a request with `username`
     * and `password` fields as a Basic Auth attempt. Returns `201` with the
     * customer and `account_created` in `result`; log in next. An email or
     * username already used is `409` (`cocart_account_exists`,
     * `cocart_registration_username_exists`).
     *
     * @param string      $email    The customer's email
     * @param string|null $username A username; the store makes one when omitted
     * @param string|null $password A password; the store makes one when omitted
     * @return Response The customer
     * @throws CoCartException
     */
    public function register(string $email, ?string $username = null, ?string $password = null): Response
    {
        $data = ['email' => $email];

        if ($username !== null) {
            $data['requested_username'] = $username;
        }

        if ($password !== null) {
            $data['requested_password'] = $password;
        }

        return $this->raw('POST', 'cocart/v2/register', [], $data);
    }
}
