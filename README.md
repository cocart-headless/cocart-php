# CoCart PHP SDK

[![Tests](https://img.shields.io/github/actions/workflow/status/cocart-headless/cocart-php/tests.yml?label=tests&style=for-the-badge&labelColor=000000)](https://github.com/cocart-headless/cocart-php/actions/workflows/tests.yml)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cocart-headless/cocart-php?style=for-the-badge&labelColor=000000)](https://packagist.org/packages/cocart-headless/cocart-php)
[![Packagist Version](https://img.shields.io/packagist/v/cocart-headless/cocart-php?style=for-the-badge&labelColor=000000)](https://packagist.org/packages/cocart-headless/cocart-php)
[![License](https://img.shields.io/github/license/jayanratna/resend-php?color=9cf&style=for-the-badge&labelColor=000000)](https://github.com/cocart-headless/cocart-php/blob/main/LICENSE)

---

The Official PHP SDK for the [CoCart REST API](https://docs.cocartapi.com).

> Supports API v2 for both **CoCart Basic** and the **legacy CoCart plugin** (`cart-rest-api-for-woocommerce` v4.x).

> [!IMPORTANT]
> This SDK is still in development and not yet ready for production use. Provide feedback if you experience a bug.

## TODO to complete the SDK

* [ ] Add SDK docs to documentation site
* [ ] Add support for Cart API extras
* [ ] Add Checkout API support
* [ ] Add Customers Account API support

---

## Requirements

- PHP 8.2 or higher
- CoCart Basic plugin installed on your WooCommerce store
- One of: Guzzle (recommended), cURL, or PHP Streams

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
| [Error Handling](docs/error-handling.md) | Exception hierarchy, catching errors, HTTP status mapping, response error helpers |
| [Utilities](docs/utilities.md) | Currency and timezone utilities helpers that operate on data already returned by the API. |

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
    'main_plugin'       => 'basic',                   // 'basic' (default) or 'legacy' for legacy CoCart plugin
    'etag'              => true,                     // ETag conditional requests (default true)
    'auto_storage'      => true,                     // Auto-persist cart key to $_SESSION
    'session_key'       => 'cocart_cart_key',        // Session key name
]);
```

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