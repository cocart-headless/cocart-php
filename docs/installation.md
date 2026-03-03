# Installation

## Requirements

- PHP 8.2 or higher
- One of the following HTTP clients:
  - **Guzzle** (recommended): `composer require guzzlehttp/guzzle`
  - **cURL extension**: Built into most PHP installations
  - **PHP Streams**: Works with `allow_url_fopen` enabled (fallback)
- CoCart plugin installed on your WooCommerce store
- [CoCart JWT Authentication](https://wordpress.org/plugins/cocart-jwt-authentication/) plugin for JWT features (optional)

## Via Composer (Recommended)

```bash
# Install the SDK
composer require cocart/sdk

# Install Guzzle for best performance (recommended)
composer require guzzlehttp/guzzle
```

## Manual Installation

1. Download or clone this repository
2. Include the autoloader:

```php
require_once 'path/to/cocart-sdk/autoload.php';
```

## HTTP Adapters

The SDK automatically selects the best available HTTP adapter in this order:

| Priority | Adapter | Package | Notes |
|----------|---------|---------|-------|
| 1 | **Guzzle** | `guzzlehttp/guzzle` | Most feature-rich and performant |
| 2 | **cURL** | Built-in PHP extension | Widely available, good performance |
| 3 | **WordPress HTTP API** | WordPress core | For WordPress plugin/theme development |
| 4 | **PHP Streams** | Built-in PHP | Fallback — limited features |

### Specifying an Adapter

```php

use CoCart\Http\GuzzleAdapter;

// Auto-detect best available adapter (default)
$client = new CoCart('https://your-store.com');

// Specify adapter by name
$client = new CoCart('https://your-store.com', [
    'http_adapter' => 'guzzle'  // or 'curl', 'wordpress', 'stream'
]);

// Or inject a custom adapter instance
$client = new CoCart('https://your-store.com', [
    'http_adapter' => new GuzzleAdapter()
]);

// Change adapter at runtime
$client->setHttpAdapter('curl');

// Check which adapter is being used
echo $client->getHttpAdapterName(); // 'guzzle', 'curl', etc.

// List all available adapters
print_r(CoCart::getAvailableHttpAdapters());
```

### Configuring Guzzle

```php

use CoCart\Http\GuzzleAdapter;
use GuzzleHttp\Client;

// Use default Guzzle configuration
$client = new CoCart('https://your-store.com', [
    'http_adapter' => 'guzzle'
]);

// Or configure Guzzle with custom options
$guzzle = new Client([
    'proxy' => 'http://proxy.example.com:8080',
    'allow_redirects' => true,
    'debug' => true,
]);

$adapter = new GuzzleAdapter();
$adapter->setClient($guzzle);

$client = new CoCart('https://your-store.com', [
    'http_adapter' => $adapter
]);
```

### Using in WordPress Plugins

When building a WordPress plugin, the SDK will automatically detect and use WordPress's built-in HTTP API:

```php
// In a WordPress environment, this will auto-detect wp_remote_request
$client = new CoCart('https://your-store.com');

// Or explicitly use WordPress adapter
$client = new CoCart('https://your-store.com', [
    'http_adapter' => 'wordpress'
]);
```

### Environments Without cURL

If your server doesn't have cURL:

**Option 1: Install Guzzle (recommended)**
```bash
composer require guzzlehttp/guzzle
```

**Option 2: Use PHP Streams** (no installation needed)
```php
$client = new CoCart('https://your-store.com', [
    'http_adapter' => 'stream'
]);
```

> **Note**: The stream adapter has limitations — less performant, may have SSL issues on some configurations, and limited timeout control.

## Configuration Options

```php
$client = new CoCart('https://your-store.com', [
    // Guest session
    'cart_key' => 'existing_cart_key',

    // Basic Auth
    'username' => 'customer@email.com',
    'password' => 'password',

    // JWT Auth
    'jwt_token' => 'your-jwt-token',
    'jwt_refresh_token' => 'your-refresh-token',

    // Admin (Sessions API)
    'consumer_key' => 'ck_xxxxx',
    'consumer_secret' => 'cs_xxxxx',

    // HTTP settings
    'timeout' => 30,
    'verify_ssl' => true,

    // Custom auth header (for reverse proxies that strip Authorization)
    'auth_header' => 'Authorization',

    // REST API prefix (default: 'wp-json')
    'rest_prefix' => 'wp-json',

    // API namespace (default: 'cocart')
    'namespace' => 'cocart',

    // CoCart main plugin: 'basic' (default) or 'legacy'
    'main_plugin' => 'basic',

    // Retry transient failures (429, 503, timeouts)
    'max_retries' => 2,

    // ETag conditional requests (default: true)
    'etag' => true,

    // Cart key persistence (default: true, uses $_SESSION)
    'auto_storage' => true,
    'session_key' => 'cocart_cart_key',
]);
```

### Fluent Configuration

```php
$client = CoCart::create('https://your-store.com')
    ->setTimeout(60)
    ->setVerifySsl(false)
    ->setMaxRetries(2)
    ->setRestPrefix('api')
    ->setNamespace('mystore')
    ->setAuthHeader('X-Authorization')
    ->setETag(true)
    ->setMainPlugin('legacy')
    ->addHeader('X-Custom-Header', 'value');
```

## Legacy Plugin Support

The SDK supports both **CoCart Basic** and the **legacy CoCart plugin** (`cart-rest-api-for-woocommerce` v4.x). By default, the SDK targets CoCart Basic.

To use the SDK with the legacy plugin, set `main_plugin` to `'legacy'`:

```php
$client = new CoCart('https://your-store.com', [
    'main_plugin' => 'legacy',
]);

// Or use the fluent setter
$client->setMainPlugin('legacy');
```

### What changes in legacy mode

**Basic-only methods throw immediately.** Methods that require CoCart Basic will throw a `VersionException` before making any HTTP request, with a clear message indicating which method requires an upgrade:

```php
use CoCart\Exceptions\VersionException;

$client = new CoCart('https://your-store.com', ['main_plugin' => 'legacy']);

try {
    $client->products()->findBySlug('blue-hoodie');
} catch (VersionException $e) {
    // "products()->findBySlug() requires CoCart Basic. Please upgrade..."
}
```

Basic-only methods include:
- `cart()->create()`
- `products()->findBySlug()`, `variation()`, `category()`, `tag()`
- `products()->attributeBySlug()`, `attributeTermsBySlug()`, `attributeTermBySlug()`
- `products()->brands()`, `brand()`, `byBrand()`
- `products()->myReviews()`
- `batch()->execute()`

**Field filtering uses `fields` instead of `_fields`.** The legacy plugin uses CoCart's custom `fields` query parameter, while CoCart Basic uses the WordPress standard `_fields`. The SDK handles this automatically — methods like `getCoupons()`, `getCustomer()`, and `getShippingMethods()` will send the correct parameter based on the configured main plugin.
