# Error Handling

## Exception Hierarchy

```
CoCartException (base)       (404, 409, 429, 5xx…)
├── AuthenticationException   (401, 403)
│   └── TwoFactorRequiredException
├── ValidationException       (400)
└── CheckoutOutcomeUnknownException (see Checkout)
```

All exceptions extend `CoCart\Exceptions\CoCartException`, which extends PHP's built-in `\Exception`.

## Catching Exceptions

```php
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\ValidationException;

try {
    $response = $client->cart()->addItem(999, 1);
} catch (ValidationException $e) {
    // 400 — product not found, out of stock, invalid quantity, etc.
    echo "Validation Error: " . $e->getMessage();
    echo "Error Code: " . $e->getErrorCode();     // e.g. 'cocart_product_not_found'
    echo "HTTP Code: " . $e->getHttpCode();        // 400
} catch (AuthenticationException $e) {
    // 401 or 403 — invalid credentials, expired token, forbidden
    echo "Auth Error: " . $e->getMessage();
    echo "Error Code: " . $e->getErrorCode();      // e.g. 'cocart_authentication_error'
    echo "HTTP Code: " . $e->getHttpCode();         // 401 or 403
} catch (CoCartException $e) {
    // Any other API error (404, 500, etc.)
    echo "API Error: " . $e->getMessage();
    echo "HTTP Code: " . $e->getHttpCode();
}
```

## Exception Methods

All exceptions provide these methods:

| Method | Return | Description |
|--------|--------|-------------|
| `getMessage()` | `string` | Human-readable error message from the API |
| `getErrorCode()` | `?string` | API error code (e.g. `cocart_product_not_found`) |
| `getHttpCode()` | `int` | HTTP status code (400, 401, 403, 500, etc.) |
| `getCode()` | `int` | Same as `getHttpCode()` (inherited from `\Exception`) |
| `getResponseData()` | `array` | Full API response body for debugging |
| `getCart()` | `?array` | The cart sent with the error (see [Item No Longer in the Cart](#item-no-longer-in-the-cart)), or `null` |
| `getFields()` | `array` | Every field at fault, by path, with its message (`data.fields`), or `[]` (see [Field Errors](#field-errors)) |
| `getField()` | `?string` | The first field at fault, as a path such as `billing.postcode` (`data.field`), or `null` |
| `getReason()` | `?string` | The underlying plugin's own reason (`data.reason`), or `null` |

Messages never include the request URL: its query string can carry the cart key, so an app can log or show them safely. That includes connection errors from the HTTP adapters, whose URLs lose their query string.

### ValidationException Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getValidationErrors()` | `array` | Field-level validation errors (e.g. `['email' => 'required']`) |

### AuthenticationException Methods

| Method | Return | Description |
|--------|--------|-------------|
| `isTokenExpired()` | `bool` | Whether the error is due to an expired JWT token |

## Inspecting the Full API Response

Every exception carries the full API response data for debugging:

```php
try {
    $client->cart()->addItem(999, 1);
} catch (CoCartException $e) {
    // Full response from the API
    $data = $e->getResponseData();
    // e.g. ['code' => 'cocart_product_not_found', 'message' => '...', 'data' => [...]]
    print_r($data);
}
```

## Field Errors

CoCart Plus checks a whole form at once (checkout, account details, addresses) and reports every field at fault together, by path:

```php
try {
    $client->checkout()->update(['billing_address' => ['postcode' => 'nope', 'phone' => '']]);
} catch (CoCartException $e) {
    $e->getFields(); // ['billing.postcode' => 'Please enter a valid postcode.', 'billing.phone' => 'Enter a phone number.']
    $e->getField();  // 'billing.postcode'
    $e->getReason(); // the underlying plugin's own reason, or null
}
```

The messages are plain text: escape them when you put them in a page.

## Validation Errors

When the API returns field-level errors, extract them directly:

```php
try {
    $client->cart()->updateCustomer(billing: ['email' => 'not-an-email']);
} catch (ValidationException $e) {
    $errors = $e->getValidationErrors();
    // e.g. ['email' => 'Invalid email format']

    foreach ($errors as $field => $reason) {
        echo "{$field}: {$reason}\n";
    }
}
```

## Expired JWT Tokens

Check if an auth error is specifically a token expiration:

```php
try {
    $client->cart()->get();
} catch (AuthenticationException $e) {
    if ($e->isTokenExpired()) {
        // Token expired — refresh and retry
        $jwt->refresh();
        $cart = $client->cart()->get();
    } else {
        // Credentials are wrong, not just expired
        throw $e;
    }
}
```

Or let the SDK handle it automatically:

```php
use CoCart\JwtManager;

$jwt = new JwtManager($client, null, ['auto_refresh' => true]);
$client->setJwtManager($jwt);

// Expired tokens are refreshed and retried automatically
$cart = $client->cart()->get();
```

See [Authentication](authentication.md#auto-refresh-on-authentication-errors) for details.

## HTTP Status Code Mapping

| HTTP Status | Exception Thrown | Typical Causes |
|-------------|-----------------|----------------|
| 400 | `ValidationException` | Invalid product ID, out of stock, invalid quantity, missing required fields |
| 401 | `AuthenticationException` | Missing or invalid credentials |
| 403 | `AuthenticationException` | Expired JWT token, insufficient permissions |
| 404 | `CoCartException` | Endpoint not found, item key not found |
| 409 | `CoCartException` | A state that refuses the request: an order already paid (`cocart_order_invalid_status`), a payment still running (`cocart_payment_in_progress`), a changed total (`cocart_checkout_total_mismatch`) |
| 429 | `CoCartException` | Rate limited |
| 5xx | `CoCartException` | Server error |

A 409, a 429 or a 5xx is always a plain `CoCartException`, never a `ValidationException`, even when its code contains `invalid` or `missing`. For other statuses, a code containing `invalid` or `missing` gives a `ValidationException`, and one containing `authenticat` an `AuthenticationException`.

## Response Error Helpers

When you have a `Response` object, you can check for errors directly:

```php
$response = $client->cart()->get();

if ($response->isError()) {
    echo $response->getErrorCode();    // API error code
    echo $response->getErrorMessage(); // Human-readable message
    echo $response->getStatusCode();   // HTTP status code
}

if ($response->isSuccessful()) {
    $data = $response->toArray();
}
```

## Response as Array

The `Response` object implements `ArrayAccess` for convenient data access:

```php
$response = $client->cart()->get();

// Array-style access
$items = $response['items'];
$total = $response['totals'];
$currency = $response['currency'];

// Cart state helpers
$response->hasItems();    // true if cart has items
$response->isEmpty();     // true if cart is empty
$response->hasCoupons();  // true if coupons are applied

// Pagination helpers (for product listings)
$response->getTotalResults(); // total items across all pages
$response->getTotalPages();   // total number of pages
```

## Common Error Scenarios

### Product Not Found

```php
try {
    $client->cart()->addItem(999999, 1);
} catch (ValidationException $e) {
    // $e->getMessage()   => 'Product not found'
    // $e->getErrorCode() => 'cocart_product_not_found'
}
```

### Out of Stock

```php
try {
    $client->cart()->addItem(123, 100);
} catch (ValidationException $e) {
    // $e->getErrorCode() => 'cocart_not_enough_in_stock'
}
```

### Expired Cart Key

CoCart Starter 5 refuses a cart key with no cart session on every cart route (`404 cocart_invalid_cart`), for example after the cart expired. The client then forgets the stored key (including the one in `$_SESSION`) and retries the request once as a new guest, so this error doesn't reach your code for a guest; a fresh cart key comes back as usual.

If you keep the cart key with a `SessionManager` storage adapter, save the new key there with `$session->setCartKey($client->getCartKey())`.

### Item No Longer in the Cart

Getting, updating or removing an item whose key isn't in the cart (for example, another tab already removed it) fails with `404 cocart_item_not_in_cart`. Since CoCart Starter 5 the error carries the cart as it is now, so you can show it without fetching the cart again.

```php
try {
    $client->cart()->updateItem($itemKey, 2);
} catch (CoCartException $e) {
    if ($e->getErrorCode() === 'cocart_item_not_in_cart') {
        $cart = $e->getCart(); // the current cart, or null with CoCart 4.x
        if ($cart !== null) {
            render($cart);
        }
    }
}
```

### Quantity Reduced to the Maximum

Adding more than the purchase limit or the stock allows isn't an error: CoCart adds the maximum and says so with a notice.

```php
$response = $client->cart()->addItem(123, 50);
$notices = $response->getNotices()['notice'] ?? []; // ['The quantity of "Beanie" was reduced to 5, the most allowed in the cart.']
```

### Rate Limits

With rate limiting on (CoCart Plus), every response carries `RateLimit-*` headers:

```php
$rateLimit = $response->getRateLimit();
// ['limit' => 25, 'remaining' => 24, 'reset' => 1791108883, 'retry_after' => null]
```

`reset` is the Unix time the window resets, and `retry_after` the seconds to wait after a 429. Exceeding the limit returns 429 `rate_limit_exceeded`, which the client retries when `max_retries` is set. It waits as long as `RateLimit-Retry-After` says (what CoCart Plus sends), or `Retry-After` (what a proxy may send), up to 60 seconds.

Requests made with `requestRaw()`, which the checkout and account calls use, are never sent again by the client, whatever `max_retries` says: they can take payment.
