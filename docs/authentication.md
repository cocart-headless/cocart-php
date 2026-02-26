# Authentication

CoCart supports multiple authentication methods depending on the use case.

## Guest Customers

No authentication is needed for guest cart operations. The SDK automatically manages the cart session for you:

1. **First request** — No cart key exists yet. The CoCart server creates a new guest session and returns a `Cart-Key` header in the response.
2. **SDK extracts it** — The SDK reads the `Cart-Key` header and stores it on the client instance automatically.
3. **Subsequent requests within the same script** — The stored cart key is sent as both a `Cart-Key` header and a `cart_key` query parameter, so the server knows which cart to use.

```php
$client = new CoCart('https://your-store.com');

// First page load — add item, cart key is persisted to PHP session automatically
$client->cart()->addItem(123, 2);

echo $client->getCartKey(); // 'guest_abc123...'

// Next page load — cart key is restored from PHP session automatically
$client = new CoCart('https://your-store.com');
$cart = $client->cart()->get(); // Same cart as before
```

The SDK uses PHP sessions (`$_SESSION`) behind the scenes to persist and restore the cart key. No extra setup is needed.

### Disabling Auto-Storage

In CLI scripts or test environments where PHP sessions are not available, disable auto-storage:

```php
$client = new CoCart('https://your-store.com', ['auto_storage' => false]);
```

### Custom Session Key

For multi-store setups, use separate session keys to avoid collisions:

```php
$clientA = new CoCart('https://store-a.com', ['session_key' => 'store_a_cart']);
$clientB = new CoCart('https://store-b.com', ['session_key' => 'store_b_cart']);
```

### Resuming with a Known Cart Key

If you already have a cart key (e.g. stored in your own database), pass it directly. This takes priority over any value in the session:

```php
$client = new CoCart('https://your-store.com', [
    'cart_key' => 'existing_cart_key',
]);
```

## Basic Auth

For authenticated customers using WordPress username/password:

```php
$client = new CoCart('https://your-store.com', [
    'username' => 'customer@email.com',
    'password' => 'customer_password',
]);

// Or set at runtime
$client = new CoCart('https://your-store.com');
$client->setAuth('customer@email.com', 'password');

// Check auth status
$client->isAuthenticated(); // true
$client->isGuest();         // false
```

## JWT Authentication

If the [CoCart JWT Authentication](https://wordpress.org/plugins/cocart-jwt-authentication/) plugin (v3.0+) is installed, `login()` acquires JWT tokens automatically. If not, the SDK falls back to Basic Auth — so `login()` always works regardless of your server setup.

### Login

```php
$client = new CoCart('https://your-store.com');

// Login — uses JWT if available, falls back to Basic Auth
$response = $client->login('customer@email.com', 'password');

echo $response->get('display_name'); // 'john'
echo $response->get('user_id');      // '123'

// Subsequent requests automatically use the acquired credentials
$cart = $client->cart()->get();
```

### Logout

```php
$client->logout(); // Clears JWT and refresh tokens
```

### Refresh an Expired Token

```php
$client->jwt()->refresh();
```

### Validate a Token

```php
if ($client->jwt()->validate()) {
    echo 'Token is valid';
} else {
    echo 'Token is expired or invalid';
}
```

### Check Token Expiry

Check if the token is expired locally without making an API call:

```php
// Check if expired (with 30-second leeway by default)
if ($client->jwt()->isTokenExpired()) {
    $client->jwt()->refresh();
}

// Custom leeway (e.g., refresh 5 minutes before expiry)
if ($client->jwt()->isTokenExpired(300)) {
    $client->jwt()->refresh();
}

// Get the expiry timestamp
$expiry = $client->jwt()->getTokenExpiry();
if ($expiry !== null) {
    echo 'Token expires at: ' . date('Y-m-d H:i:s', $expiry);
}
```

### Auto-Refresh

Expired tokens are automatically refreshed and retried. This is enabled by default when using `$client->login()`. If you set a JWT token manually, you can enable it explicitly:

```php
$client->setJwtToken('eyJ...');
$client->setRefreshToken('refresh_hash_...');
$client->jwt()->setAutoRefresh(true);

// Expired tokens are refreshed and retried automatically
$cart = $client->cart()->get();
```

### Persisting Tokens Across Requests

Pass a storage adapter to the JWT Manager for automatic persistence between page loads:

```php
use CoCart\JwtManager;
use CoCart\Storage\PhpSessionStorage;

$storage = new PhpSessionStorage();
$jwt = new JwtManager($client, $storage);
$client->setJwtManager($jwt);

// Tokens are saved to storage after login/refresh
$client->login('user@example.com', 'password');

// On subsequent page loads, tokens are restored automatically
$client2 = new CoCart('https://your-store.com');
$jwt2 = new JwtManager($client2, $storage);
$client2->setJwtManager($jwt2);
// $client2 now has the stored JWT token — no need to login again
```

### JWT Utility Methods

```php
$client->jwt()->hasTokens();            // true if a JWT token is set
$client->jwt()->isTokenExpired();       // true if token is expired (local check)
$client->jwt()->getTokenExpiry();       // unix timestamp of token expiry
$client->jwt()->isAutoRefreshEnabled(); // check auto-refresh status
$client->jwt()->setAutoRefresh(true);   // enable/disable at runtime
```

## Consumer Keys (Admin)

For admin-only endpoints like Sessions API, use WooCommerce REST API credentials:

```php
$client = new CoCart('https://your-store.com', [
    'consumer_key' => 'ck_xxxxx',
    'consumer_secret' => 'cs_xxxxx',
]);

$sessions = $client->sessions()->all();
```

## Authentication Priority

When multiple auth credentials are configured, the SDK uses this priority:

1. **JWT Token** (`jwt_token`) — Bearer token
2. **Basic Auth** (`username` / `password`) — Basic auth header
3. **Consumer Keys** (`consumer_key` / `consumer_secret`) — Basic auth header

### Switching Auth at Runtime

```php
// Start with JWT
$client = new CoCart('https://your-store.com', [
    'jwt_token' => 'eyJ...',
]);

// Switch to Basic Auth (clears JWT)
$client->setAuth('user', 'pass');

// Switch to JWT (clears Basic Auth)
$client->setJwtToken('new.jwt.token');

// Clear everything
$client->clearSession();
```

## White-Labelling / Custom REST Prefix

If your WordPress site uses a custom REST URL prefix (via `rest_url_prefix` filter) or CoCart has been white-labelled with a different namespace:

```php
// Custom REST prefix (site uses /api/ instead of /wp-json/)
$client = new CoCart('https://your-store.com', [
    'rest_prefix' => 'api',
]);
// Requests go to: https://your-store.com/api/cocart/v2/cart

// White-labelled namespace
$client = new CoCart('https://your-store.com', [
    'namespace' => 'mystore',
]);
// Requests go to: https://your-store.com/wp-json/mystore/v2/cart

// Both together
$client = new CoCart('https://your-store.com', [
    'rest_prefix' => 'api',
    'namespace' => 'mystore',
]);
// Requests go to: https://your-store.com/api/mystore/v2/cart

// Or set at runtime
$client->setRestPrefix('api')->setNamespace('mystore');
```

JWT endpoints also respect the namespace automatically:

```php
// Refresh calls: {rest_prefix}/{namespace}/jwt/refresh-token
// Validate calls: {rest_prefix}/{namespace}/jwt/validate-token
```

## Custom Auth Header

Some hosting providers or reverse proxies (Cloudflare, Nginx, Apache) strip or block the standard `Authorization` header. You can configure the SDK to use an alternative header name:

```php
// Via constructor
$client = new CoCart('https://your-store.com', [
    'username' => 'customer@email.com',
    'password' => 'password',
    'auth_header' => 'X-Authorization',
]);

// Or at runtime
$client->setAuthHeader('X-Authorization');
```

The SDK will send credentials using the custom header instead:

```http
X-Authorization: Basic dXNlcjpwYXNz
X-Authorization: Bearer eyJ...
```

Your WordPress server must be configured to read the custom header. For example, in `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:X-Authorization} ^(.+)$
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:X-Authorization}]
```

## Testing with Mocks

Type-hint your classes against `CoCartInterface` for easy mocking:

```php
use CoCart\CoCartInterface;

class MyCartService
{
    public function __construct(
        private CoCartInterface $client
    ) {}

    public function addProduct(int $productId, int $quantity): void
    {
        $this->client->cart()->addItem($productId, $quantity);
    }
}
```

In your tests:

```php
use CoCart\CoCartInterface;
use CoCart\Response;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testAddToCart(): void
    {
        $client = $this->createMock(CoCartInterface::class);

        $response = new Response(200, [], '{"items_count": 1}');
        $client->method('cart')->willReturn(/* your cart mock */);

        $service = new MyCartService($client);
        $service->addProduct(123, 2);
    }
}
```
