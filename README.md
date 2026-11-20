# CoCart PHP SDK

[![Tests](https://img.shields.io/github/actions/workflow/status/cocart-headless/cocart-php/tests.yml?label=tests&style=for-the-badge&labelColor=000000)](https://github.com/cocart-headless/cocart-php/actions/workflows/tests.yml)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cocart-headless/cocart-php?style=for-the-badge&labelColor=000000)](https://packagist.org/packages/cocart-headless/cocart-php)
[![Packagist Version](https://img.shields.io/packagist/v/cocart-headless/cocart-php?style=for-the-badge&labelColor=000000)](https://packagist.org/packages/cocart-headless/cocart-php)
[![License](https://img.shields.io/github/license/cocart-headless/cocart-php?color=9cf&style=for-the-badge&labelColor=000000)](https://github.com/cocart-headless/cocart-php/blob/main/LICENSE)

---

The Official PHP SDK for the [CoCart REST API](https://docs.cocartapi.com).

> Supports API v2 for both **CoCart Starter** and the **CoCart Community plugin** (`cart-rest-api-for-woocommerce` v4.x).

> [!IMPORTANT]
> This SDK is still in development and not yet ready for production use. Provide feedback if you experience a bug.

## TODO to complete the SDK

* [ ] Add SDK docs to documentation site
* [ ] Add support for Cart API extras
* [x] Add Checkout API support — implemented ([`$client->checkout()`](docs/checkout.md)); needs CoCart Plus 2.0, which isn't released yet
* [x] Add Customers Account API support — implemented ([`$client->account()`](docs/account.md)); needs CoCart Plus 2.0, which isn't released yet

---

## Requirements

- PHP 8.2 or higher
- CoCart Starter plugin installed on your WooCommerce store
- CoCart Plus 2.0 for the account and checkout APIs (not released yet)
- CoCart JWT Authentication plugin (optional, for JWT auth)
- One of: Guzzle (recommended), cURL, or PHP Streams

## Support Policy

See [SUPPORT.md](SUPPORT.md) for our versioning policy, supported PHP versions, and support lifecycle.

## Features

- Full cart management (add, update, remove, clear items)
- Guest customer support with automatic cart key tracking
- Authenticated user support (Basic Auth & JWT)
- JWT token lifecycle (login, refresh, validate, auto-refresh)
- Session management and cart transfer on login
- Fetch products easy, search and filter results
- Batch requests — multiple operations in a single HTTP call
- Sessions management (admin)
- Multiple storage adapters for cart key and token persistence
- Multiple HTTP adapters (Guzzle, cURL, WordPress HTTP API, PHP Streams)
- `CoCartInterface` for easy mocking in tests
- ETag conditional requests for reduced bandwidth (enabled by default)
- Legacy CoCart plugin support with version-aware endpoint guards
- Comprehensive error handling
- PSR-4 autoloading

## Installation

```bash
composer require cocart/sdk

# Recommended: install Guzzle for best performance
composer require guzzlehttp/guzzle
```

See [Installation Guide](docs/installation.md) for manual install, HTTP adapter options, and full configuration reference.

## Quick Start

```php
// Guest customer — cart key is persisted to PHP session automatically
$client = new CoCart('https://your-store.com');
$client->cart()->addItem(123, 2);
$cart = $client->cart()->get();
```

## Documentation

| Guide | Description |
|-------|-------------|
| [Installation](docs/installation.md) | Requirements, Composer/manual install, HTTP adapters, configuration options |
| [Authentication](docs/authentication.md) | Guest sessions, Basic Auth, JWT (login/refresh/validate/auto-refresh), consumer keys, white-labelling |
| [Cart](docs/cart.md) | Add/update/remove items, coupons, customer details, shipping, payment, fees, totals |
| [Products](docs/products.md) | List/search/filter products, pagination, variations, categories, tags, attributes, reviews |
| [Sessions](docs/sessions.md) | Admin sessions API, SessionManager, storage adapters, cart transfer on login |
| [Account](docs/account.md) | Registration, customer profile, password, address, order history, downloads, reviews and subscriptions (needs CoCart Plus 2.0, not released yet) |
| [Checkout](docs/checkout.md) | Place orders and take payment, 3D Secure, wallets and vouchers, saved cards, the thank-you page, paying for unpaid orders, finding out how a checkout ended (needs CoCart Plus 2.0, not released yet) |
| [Error Handling](docs/error-handling.md) | Exception hierarchy, catching errors, HTTP status mapping, response error helpers, expired carts, rate limits |
| [Utilities](docs/utilities.md) | Currency and timezone utilities helpers that operate on data already returned by the API. |
| [Extension Data](docs/extension-data.md) | Plugin data under `extensions` (CoCart Plus integrations), and sending a plan, gift or warranty choice |

## Configuration

```php
$client = new CoCart('https://your-store.com', [
    'cart_key'          => 'existing_cart_key',      // Guest session
    'username'          => 'customer@email.com',     // Basic Auth
    'password'          => 'password',
    'jwt_token'         => 'your-jwt-token',         // JWT Auth
    'jwt_refresh_token' => 'your-refresh-token',
    'consumer_key'      => 'ck_xxxxx',               // Admin (Sessions API)
    'consumer_secret'   => 'cs_xxxxx',
    'auth_header'       => 'Authorization',          // Custom auth header for proxies
    'timeout'           => 30,                       // HTTP settings
    'verify_ssl'        => true,
    'rest_prefix'       => 'wp-json',                // Custom REST prefix
    'namespace'         => 'cocart',                 // Custom namespace - Only supported if you have the WhiteLabel add-on
    'main_plugin'       => 'starter',                 // 'starter' (default) or 'legacy' for CoCart Community plugin
    'etag'              => true,                     // ETag conditional requests (default true)
    'auto_storage'      => true,                     // Auto-persist cart key to $_SESSION
    'session_key'       => 'cocart_cart_key',        // Session key name
]);
```

On a server, make **one client per customer**: a client holds one cart key, one login and the last response, so sharing it between customers (in a long-running worker, for example) would mix up their carts.

## Checkout at a Glance

> Requires CoCart Plus 2.0. See [Checkout](docs/checkout.md) for the whole flow.

1. Call `$client->checkout()->get()` for the page the customer reviews: CoCart Plus remembers the total it showed (don't call it just before placing the order). Save their input with `update()`, and show what's in `result.not_applied`.
2. Place the order with the gateway's token. `payment_data` carries gateway tokens only, never card details:

```php
$result = $client->checkout()->placeOrder([
    'billing_address' => [...],
    'payment_method'  => 'stripe',
    'payment_data'    => ['wc-stripe-payment-method' => $paymentMethodId],
]);
```

3. Read the `CheckoutResult` (`getCode()` is `order_placed`, `action_required`, `payment_pending` or `payment_failed`):

| Result | What to do |
|---|---|
| `isComplete()` | Show `getOrderReceived($result->getOrderId(), $result->getOrderKey())` |
| `isAwaitingPayment()` | The order is placed; the customer pays with the gateway's SDK (a wallet, a voucher, BLIK) |
| `needsConfirmation()` | Confirm `getClientSecret()` in the page, then make the same call again |
| `needsRedirect()` | Send the customer to `getRedirectUrl()`, then call `confirmReturn()` |
| `isPending()` | Confirm again shortly |
| `isFailed()` | Show `getMessage()` (plain text: escape it), then pay again with `payForOrder()` |

A lost answer or `409 cocart_payment_in_progress` throws `CheckoutOutcomeUnknownException`: ask `getStatus($e->getCheckoutId())`, whose `code` is `order_placed`, `payment_required` (pay with `payForOrder()`), `in_progress` (ask again) or `not_placed` (place it again). `409 cocart_checkout_total_mismatch` means the total changed: get the checkout again and show the new total.

## CoCart Channels

We have different channels at your disposal where you can find information about the CoCart project, discuss it and get involved:

[![Twitter: cocartapi](https://img.shields.io/twitter/follow/cocartapi?style=social)](https://twitter.com/cocartapi) [![CoCart GitHub Stars](https://img.shields.io/github/stars/cocart-headless/cocart-js?style=social)](https://github.com/cocart-headless/cocart-js)

<ul>
  <li>📖 <strong>Documentation</strong>: this is the place to learn how to use CoCart API. <a href="https://cocartapi.com/docs/?utm_medium=gh&utm_source=github&utm_campaign=readme&utm_content=cocart">Get started!</a></li>
  <li>👪 <strong>Community</strong>: use our Discord chat room to share any doubts, feedback and meet great people. This is your place too to share <a href="https://cocartapi.com/community/?utm_medium=gh&utm_source=github&utm_campaign=readme&utm_content=cocart">how are you planning to use CoCart!</a></li>
  <li>🐞 <strong>GitHub</strong>: we use GitHub for bugs and pull requests, doubts are solved with the community.</li>
  <li>🐦 <strong>Social media</strong>: a more informal place to interact with CoCart users, reach out to us on <a href="https://twitter.com/cocartapi">X/Twitter.</a></li>
</ul>

## Credits

Website [cocartapi.com](https://cocartapi.com/?ref=github) &nbsp;&middot;&nbsp;
GitHub [@cocart-headless](https://github.com/cocart-headless) &nbsp;&middot;&nbsp;
X/Twitter [@cocartapi](https://twitter.com/cocartapi) &nbsp;&middot;&nbsp;
[Facebook](https://www.facebook.com/cocartforwc/) &nbsp;&middot;&nbsp;
[Instagram](https://www.instagram.com/cocartheadless/)

## License

MIT