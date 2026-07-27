# Account API

> **Note:** The Account API is fully supported by this SDK, but the Account API
> itself is not yet available in a released version of the CoCart plugin —
> it's coming in a future release. This documentation is ready for when it
> ships.

The Account endpoint gives an authenticated customer access to their own profile, password, order history, downloads, and reviews.

All methods require the customer to be authenticated (Basic Auth or JWT).

```php
$client = new CoCart('https://your-store.com', [
    'jwt_token' => 'eyJ...',
]);
```

### Get Profile

```php
$response = $client->account()->getProfile();
```

### Update Profile

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

### Get Order History

```php
$response = $client->account()->getOrders();

// With pagination
$response = $client->account()->getOrders(['page' => '2', 'per_page' => '10', 'order' => 'DESC']);
```

### Get a Single Order

```php
$response = $client->account()->getOrder(123);
```

### Get a Guest Order

Looks up an order placed as a guest, verified by billing email:

```php
$response = $client->account()->getGuestOrder(123, 'jane@example.com');
```

### Get Downloads for an Order

```php
$response = $client->account()->getOrderDownloads(123);

// For a guest order
$response = $client->account()->getGuestOrderDownloads(123, 'jane@example.com');
```

### Get All Downloads

```php
$response = $client->account()->getDownloads();
```

### Get Reviews

```php
$response = $client->account()->getReviews();
```

See [Error Handling](error-handling.md) for how a missing CoCart plugin/version is reported (a `cocart_plugin_required` error) when calling these methods.
