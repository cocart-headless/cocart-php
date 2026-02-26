# CoCart PHP SDK

A comprehensive PHP SDK for the [CoCart REST API](https://docs.cocartapi.com), enabling frontend cart management for WooCommerce headless stores.

## Features

- Full cart management (add, update, remove, clear items)
- Guest customer support with automatic cart key tracking
- Authenticated user support (Basic Auth & JWT)
- JWT token lifecycle (login, refresh, validate, auto-refresh)
- Session management and cart transfer on login
- Products API integration
- Store information API
- Sessions management (admin)
- Multiple storage adapters for cart key and token persistence
- Multiple HTTP adapters (Guzzle, cURL, WordPress HTTP API, PHP Streams)
- `CoCartInterface` for easy mocking in tests
- Comprehensive error handling
- PSR-4 autoloading

## Requirements

- PHP 8.1 or higher
- CoCart plugin installed on your WooCommerce store
- One of: Guzzle (recommended), cURL, or PHP Streams

## Installation

```bash
composer require cocart/sdk

# Recommended: install Guzzle for best performance
composer require guzzlehttp/guzzle
```

See [Installation Guide](docs/installation.md) for manual install, HTTP adapter options, and full configuration reference.

## Quick Start

```php
use CoCart\CoCart;

// Guest customer — cart key is persisted to PHP session automatically
$client = new CoCart('https://your-store.com');
$client->cart()->addItem(123, 2);
$cart = $client->cart()->get();

// Authenticated customer
$client = new CoCart('https://your-store.com', [
    'username' => 'customer@email.com',
    'password' => 'password',
]);
$cart = $client->cart()->get();

// Fluent interface
$cart = CoCart::create('https://your-store.com')
    ->setAuth('customer@email.com', 'password')
    ->cart()
    ->get();
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

## Configuration

```php
$client = new CoCart('https://your-store.com', [
    'cart_key'        => 'existing_cart_key',      // Guest session
    'username'        => 'customer@email.com',     // Basic Auth
    'password'        => 'password',
    'jwt_token'       => 'your-jwt-token',         // JWT Auth
    'jwt_refresh_token' => 'your-refresh-token',
    'consumer_key'    => 'ck_xxxxx',               // Admin (Sessions API)
    'consumer_secret' => 'cs_xxxxx',
    'timeout'         => 30,                       // HTTP settings
    'verify_ssl'      => true,
    'rest_prefix'     => 'wp-json',                // Custom REST prefix
    'namespace'       => 'cocart',                 // Custom namespace
    'auto_storage'    => true,                     // Auto-persist cart key to $_SESSION
    'session_key'     => 'cocart_cart_key',        // Session key name
]);
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

## Support

- [CoCart Documentation](https://docs.cocartapi.com)
- [CoCart Discord Community](https://cocartapi.com/community)
- [GitHub Issues](https://github.com/cocart-headless/cocart-sdk-php/issues)
