# Sessions API

## Admin Sessions Endpoint

The Sessions endpoint is for administrators to manage cart sessions server-side. It requires WooCommerce REST API credentials.

```php
$client = new CoCart('https://your-store.com', [
    'consumer_key' => 'ck_xxxxx',
    'consumer_secret' => 'cs_xxxxx',
]);
```

### List All Sessions

```php
$response = $client->sessions()->all();

// With parameters
$response = $client->sessions()->all(['per_page' => '50']);
```

### Find a Session

```php
// By cart key
$response = $client->sessions()->find('guest_abc123');

// By customer ID
$response = $client->sessions()->byCustomer(123);
```

### Get Session Items

```php
$response = $client->sessions()->getItems('guest_abc123');
```

### Delete a Session

```php
// By cart key
$response = $client->sessions()->destroy('guest_abc123');

// By customer ID
$response = $client->sessions()->destroyByCustomer(123);
```

---

## SessionManager

The `SessionManager` class handles cart sessions for frontend applications — tracking guest carts, persisting cart keys, and managing the guest-to-authenticated transition.

### Basic Setup

```php
use CoCart\SessionManager;
use CoCart\Storage\PhpSessionStorage;

$client = new CoCart('https://your-store.com');
$storage = new PhpSessionStorage();
$session = new SessionManager($client, $storage);
```

### Initialize a Cart

Creates a guest cart and persists the cart key:

```php
$cartKey = $session->initializeCart();
echo $cartKey; // 'guest_abc123...'

// The cart key is now stored in PHP session
// On the next page load, SessionManager restores it automatically
```

### Login with Basic Auth

```php
// Guest adds items first
$client->cart()->addItem(123, 2);

// Login and merge guest cart into customer cart
$response = $session->login('customer@email.com', 'password', mergeCart: true);

// Or login without merging (starts fresh customer cart)
$response = $session->login('customer@email.com', 'password', mergeCart: false);
```

### Login with JWT

```php
// Guest adds items
$client->cart()->addItem(123, 2);

// Login via JWT and merge cart
$response = $session->loginWithJwt('customer@email.com', 'password', mergeCart: true);

// Access JWT manager for token operations
$session->jwt()->validate();
$session->jwt()->refresh();
```

### Login with Existing JWT Token

```php
$response = $session->loginWithToken('eyJ...');
```

### Logout

```php
$session->logout();

// Start a new guest session
$session->initializeCart();
```

### Session Status

```php
$session->isAuthenticated(); // true if Basic Auth or JWT is set
$session->isGuest();         // true if no auth credentials
$session->getCartKey();      // current cart key or null
```

### Custom Storage Key

```php
$session->setStorageKey('my_app_cart_key');
```

---

## Storage Adapters

Storage adapters implement `SessionStorageInterface` and are used by both `SessionManager` (for cart keys) and `JwtManager` (for JWT tokens).

### PHP Session Storage

Stores data in PHP's `$_SESSION`:

```php
use CoCart\Storage\PhpSessionStorage;

$storage = new PhpSessionStorage();
```

### Cookie Storage

Stores data in HTTP cookies:

```php
use CoCart\Storage\CookieStorage;

$storage = new CookieStorage(
    expiration: 604800,           // 7 days (seconds)
    path: '/',
    domain: 'your-store.com',
    secure: true,                 // HTTPS only
    httpOnly: true                // Not accessible via JavaScript
);
```

### File Storage

Stores data in the filesystem:

```php
use CoCart\Storage\FileStorage;

$storage = new FileStorage('/tmp/cocart-sessions');
```

### Custom Storage

Implement `SessionStorageInterface` for any storage backend:

```php
use CoCart\SessionStorageInterface;

class RedisStorage implements SessionStorageInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value === false ? null : $value;
    }

    public function set(string $key, string $value): void
    {
        $this->redis->set($key, $value);
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }
}
```

Usage:

```php
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RedisStorage($redis);
$session = new SessionManager($client, $storage);
$jwt = new JwtManager($client, $storage);
```

---

## Cart Transfer on Login

A common flow for headless stores: the guest browses and adds items, then logs in and their cart transfers to their account.

```php
use CoCart\SessionManager;
use CoCart\Storage\PhpSessionStorage;

$client = new CoCart('https://your-store.com');
$storage = new PhpSessionStorage();
$session = new SessionManager($client, $storage);

// 1. Initialize guest session
$session->initializeCart();

// 2. Guest browses and adds items
$client->cart()->addItem(123, 2);
$client->cart()->addItem(456, 1);

// 3. Guest decides to log in
$session->loginWithJwt('customer@email.com', 'password', mergeCart: true);

// 4. Guest cart items are now in the customer's cart
$cart = $client->cart()->get();
$items = $cart->getItems(); // Contains items 123 and 456

// 5. Later, customer logs out
$session->logout();
$session->initializeCart(); // Fresh guest session
```

See [Authentication](authentication.md) for more on JWT and Basic Auth setup.
