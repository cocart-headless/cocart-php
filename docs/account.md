# Account API

> **Note:** The Account API is fully supported by this SDK, but the Account API
> itself is not yet available in a released version of the CoCart plugin —
> it's coming in a future release. This documentation is ready for when it
> ships.

The Account endpoint gives an authenticated customer access to their own profile, password, address, order history, downloads, reviews and subscriptions, plus methods that don't need a login: registering a new customer and requesting an order withdrawal.

All other methods require the customer to be authenticated (Basic Auth or JWT).

```php
$client = new CoCart('https://your-store.com', [
    'jwt_token' => 'eyJ...',
]);
```

### Register

Registers a new customer account. Unlike the other account methods, `register()` doesn't need the customer to be logged in: it's the entry point before authentication.

```php
$client = new CoCart('https://your-store.com');

$response = $client->account()->register(
    'jane@example.com',
    'jane',              // optional if the store generates usernames from email
    'a-strong-password', // optional if the store generates passwords
);

$response->getStatusCode();       // 201
echo $response->get('id');         // 42
echo $response->get('result.code'); // 'account_created'
```

It returns the new customer; log in next. The username and password are sent as `requested_username` and `requested_password`, not `username` and `password`: CoCart treats a request with non-empty `username`/`password` fields as a Basic Auth attempt, which would reject the registration.

| Status | Code | Meaning |
|---|---|---|
| `400` | `cocart_invalid_email` | Email missing or invalid |
| `400` | `cocart_registration_reserved_username`, `cocart_registration_invalid_username` | The requested username is reserved (e.g. `admin`) or not allowed |
| `400` | `cocart_registration_missing_password`, `cocart_registration_weak_password` | Password required by the store but not provided, or too weak |
| `400` | `cocart_account_creation_failed` | The account couldn't be created |
| `409` | `cocart_account_exists` | Email is already registered |
| `409` | `cocart_registration_username_exists` | Username is already taken |

### Documents and Results

Account calls return documents: a customer, an order, a subscription. Each has `object` (`'customer'`, `'order'`, `'subscription'`) and `actions` (what can be done next). A call that changes something also carries `result`, with a `code` to branch on and a `message` for the customer:

```php
$response = $client->account()->updateProfile(['account_first_name' => 'Jane']);

$response->get('result.code');    // 'account_updated'
$response->get('result.message');
```

Reads take two optional parameters: `formatted` (`true` for amounts formatted for display instead of minor units) and `meta_keys` (a list of meta keys to include, from those the store allows). `getProfile()`, `getOrders()`, `getOrder()`, `getGuestOrder()`, `getSubscriptions()` and `getSubscription()` accept them.

### Get Profile

```php
$response = $client->account()->getProfile();
$response = $client->account()->getProfile(['formatted' => true]);

$response->get('email');
$response->get('billing');
$response->get('stats.orders_count');
```

### Update Profile

Send only what changes. Every field at fault is reported at once ([`getFields()`](error-handling.md#field-errors)).

```php
$response = $client->account()->updateProfile([
    'account_first_name' => 'Jane',
    'account_last_name' => 'Doe',
    'account_display_name' => 'Jane Doe',
    'account_email' => 'jane@example.com',
]);
```

### Change Password

```php
$response = $client->account()->changePassword(
    current: 'old-password',
    password: 'new-password',
    confirm: 'new-password',
);
```

It sends the three password fields to `POST /my-account` (`password_current`, `password_1`, `password_2`) and returns the customer with `account_updated` in `result`. A wrong current password is a `ValidationException` (`cocart_account_incorrect_current_password`).

### Update Address

Saves the customer's billing and/or shipping address. Fields are unprefixed:

```php
$response = $client->account()->updateAddress([
    'billing_address' => ['first_name' => 'Jane', 'address_1' => '1 Main St', 'city' => 'Paris', 'postcode' => '75001', 'country' => 'FR'],
    'shipping_address' => ['first_name' => 'Jane', 'address_1' => '1 Main St', 'city' => 'Paris', 'postcode' => '75001', 'country' => 'FR'],
]);
```

### Get Order History

```php
$response = $client->account()->getOrders();

// With pagination
$response = $client->account()->getOrders(['page' => 2, 'per_page' => 10, 'order' => 'DESC']);

// Filtered
$response = $client->account()->getOrders([
    'status'                  => ['processing', 'on-hold'], // one status, or a list
    'after'                   => '2026-01-01T00:00:00',     // ISO 8601
    'before'                  => '2026-12-31T23:59:59',
    'subscription_order_type' => 'renewal',                 // parent, renewal, switch or resubscribe
]);

foreach ($response->get('orders', []) as $order) {
    echo $order['number'] . ': ' . $order['status']['label'] . "\n";
}
$response->get('pagination');
$response->getHeader('X-WP-Total');
$response->getHeader('X-WP-TotalPages');
```

A list is sent comma-joined, and `true`/`false` as `'true'`/`'false'`.

### Get a Single Order

Someone else's order is `404 cocart_order_not_found`.

```php
$response = $client->account()->getOrder(123);
$response = $client->account()->getOrder(123, ['formatted' => true, 'meta_keys' => ['gift_message']]);
```

### Get a Guest Order

Looks up an order placed as a guest, verified by billing email. It comes without its `key`. Lookups are rate limited (`429 cocart_guest_lookup_rate_limited`).

```php
$response = $client->account()->getGuestOrder(123, 'jane@example.com');
```

### Cancel an Order

Cancels one of the customer's orders where WooCommerce allows it (for example a pending or failed order). Otherwise it's `409 cocart_order_cannot_be_cancelled`.

```php
$response = $client->account()->cancelOrder(123);
echo $response->get('status.code'); // 'cancelled'
```

### Get Downloads for an Order

```php
$response = $client->account()->getOrderDownloads(123);

// For a guest order
$response = $client->account()->getGuestOrderDownloads(123, 'jane@example.com');

foreach ($response->get('downloads', []) as $download) {
    echo $download['download_name'] . "\n";
}
```

### Get All Downloads

```php
$response = $client->account()->getDownloads();
$response->get('downloads');
```

### Get Reviews

Returns the reviews the customer has submitted (`GET /products/reviews/mine`, the same as `products()->myReviews()`):

```php
$response = $client->account()->getReviews();
```

### Email Verification

```php
// Confirm a pending email change, with the key and user ID from the emailed link.
$client->account()->verifyEmail($key, $userId);

// Prove ownership of the account email so WooCommerce links past guest orders to it (WooCommerce 11+).
$sent = $client->account()->sendOrderEmailVerification();
$sent->get('result.code'); // 'verification_sent', or 'verification_throttled'
$sent->get('retry_after'); // seconds before another can be sent
$client->account()->verifyOrderEmail($key, $userId);
```

### Subscriptions

Needs WooCommerce Subscriptions.

```php
$list = $client->account()->getSubscriptions(['page' => 1, 'per_page' => 10, 'status' => 'active']);
$one = $client->account()->getSubscription(512);
$guest = $client->account()->getSubscription(512, 'jane@example.com'); // as a guest

$client->account()->suspendSubscription(512);
$client->account()->reactivateSubscription(512);
$client->account()->cancelSubscription(512);
$client->account()->setSubscriptionAutoRenew(512, false);

// Resubscribe puts the ended subscription's items in the cart; check out as usual, or undo it.
$client->account()->resubscribe(512);
$client->account()->cancelResubscribe(512);
```

### Changing a Subscription's Payment Method

> Requires WooCommerce Subscriptions. The customer must be logged in.

A subscription can switch to a card the customer has saved, or to a new one. Use a gateway that can charge renewals (`supports.subscriptions` in `getPaymentMethods()`, or `supports_subscriptions` on a saved card).

`updateSubscriptionPaymentMethod()` can take payment like checkout, so it returns the same [`CheckoutResult`](checkout.md#reading-the-result), read from the subscription it returns: `getObject()` is `'subscription'`, with `getSubscriptionId()`, and `getCode()` is `payment_method_changed`, `action_required`, `payment_pending` or `payment_failed`. A new card may need 3D Secure.

**With a saved card:**

```php
$cards = array_filter(
    $client->checkout()->getSavedPaymentMethods(),
    fn ($card) => $card['supports_subscriptions'],
);

$result = $client->account()->updateSubscriptionPaymentMethod(512, [
    'payment_method' => $card['gateway_id'],
    'token_id'       => $card['id'],
]);
```

**With a new card**, create a payment method in the browser as in the [Quick Start](checkout.md#quick-start), with Elements in `mode: 'setup'` (nothing is charged now):

```php
$result = $client->account()->updateSubscriptionPaymentMethod(512, [
    'payment_method' => 'stripe',
    'payment_data'   => ['wc-stripe-payment-method' => $_POST['payment_method_id']],
]);
```

The new card is saved to the customer's account, since the renewals charge it.

**Reading the result:**

```php
if ($result->isComplete()) {
    echo htmlspecialchars($result->getMessage());             // 'Your subscription will now be paid with Visa ending in 4242.'
    $result->getResponse()->get('payment.title');             // 'Visa ending in 4242'
    $result->getResponse()->get('payment.auto_renew');        // true: it renews automatically again
} elseif ($result->needsConfirmation()) {
    // Confirm in the browser, then call updateSubscriptionPaymentMethod() again with the same input.
} elseif ($result->needsRedirect()) {
    header('Location: ' . $result->getRedirectUrl());
} elseif ($result->isPending()) {
    // The gateway hasn't confirmed yet: check getSubscription(512) shortly.
} else {
    echo htmlspecialchars($result->getMessage());
}
```

Confirm as in [3D Secure in the Page](checkout.md#3d-secure-in-the-page); a new card usually gives a SetupIntent (`getIntentType()` is `'setup_intent'`), which `stripe.handleNextAction()` handles too. After an off-site step, [`confirmReturn()`](checkout.md#off-site-steps) finishes the change, and `getSubscription(512)` shows the new method.

| Code | HTTP | Meaning |
|---|---|---|
| `cocart_subscription_not_found` | 404 | No such subscription for this customer |
| `cocart_subscription_payment_method_change_unsupported` | 400 | The subscription's payment method can't be changed |
| `cocart_subscription_payment_token_required` | 400 | Pass a saved card's `token_id`, or a new card in `payment_data` |
| `cocart_invalid_payment_method` | 400 | The gateway or the saved card can't be used |
| `cocart_subscription_payment_token_needs_confirmation` | 400 | The saved card must be confirmed before it can pay renewals |
| `cocart_subscription_cannot_change_payment_method` | 409 | The subscription's state doesn't allow a change |
| `cocart_payment_in_progress` | 409 | Another change is still running: check the subscription before trying again |

A declined card isn't an error: it comes back as a failed result, as at checkout.

### Order Withdrawal

Submits a right-of-withdrawal request (WooCommerce's order withdrawal feature). No login needed; the response is the same whether or not the order matched:

```php
$client->account()->requestOrderWithdrawal([
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'email' => 'jane@example.com',
    'email_confirmation' => 'jane@example.com',
    'order_number' => '1042',
    'withdrawal_type' => 'full_order', // or 'specific_items_only' with additional_details
]);
```

See [Error Handling](error-handling.md) for how a missing CoCart plugin/version is reported (a `cocart_plugin_required` error) when calling these methods.
