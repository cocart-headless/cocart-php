# Cart API

The Cart API handles all shopping cart operations.

- **Guest customers** — The first request creates a new guest session. The server returns a `Cart-Key` header which the SDK extracts and persists to the PHP session automatically. On the next page load, the SDK restores the cart key from the session — no manual setup needed.
- **Authenticated customers** — The server identifies the cart by the WordPress user account. No cart key is needed.

```php
$cart = $client->cart();
```

## Get Cart

```php
$response = $client->cart()->get();

// With parameters
$response = $client->cart()->get([
    '_fields' => 'items,totals', // Limit returned fields (also accepts 'fields')
    'thumb' => 'true',           // Include product thumbnails
    'default' => 'true',         // Return default cart data
]);
```

## Adding Items

### Add a Simple Product

```php
// Product ID 123, quantity 2
$response = $client->cart()->addItem(123, 2);

// Shorthand
$response = $client->cart()->add(123, 2);
```

### Add with Options

```php
$response = $client->cart()->addItem(123, 1, [
    'item_data' => [
        'gift_message' => 'Happy Birthday!',
        'engraving' => 'John',
    ],
    'email' => 'customer@email.com',
    'return_item' => true,  // Return only the added item details
]);
```

### Add a Variable Product

```php
$response = $client->cart()->addVariation(456, 1, [
    'attribute_pa_color' => 'blue',
    'attribute_pa_size' => 'large',
]);

// Or using addItem with variation option
$response = $client->cart()->addItem(456, 1, [
    'variation' => [
        'attribute_pa_color' => 'blue',
        'attribute_pa_size' => 'large',
    ],
]);
```

### Add Multiple Items at Once

```php
$response = $client->cart()->addItems([
    ['id' => '123', 'quantity' => '2'],
    ['id' => '456', 'quantity' => '1', 'variation' => [
        'attribute_pa_color' => 'red',
    ]],
    ['id' => '789', 'quantity' => '3'],
]);
```

## Updating Items

Update the quantity of a cart item using its item key:

```php
// Item keys are returned in cart responses
$response = $client->cart()->updateItem('abc123def456...', 5);

// With additional options
$response = $client->cart()->updateItem('abc123def456...', 3, [
    'item_data' => ['gift_wrap' => true],
]);
```

### Update Multiple Items at Once

```php
// Shorthand: item_key => quantity
$response = $client->cart()->updateItems([
    'abc123def456...' => 3,
    'def789ghi012...' => 1,
]);

// Full format with additional options
$response = $client->cart()->updateItems([
    ['item_key' => 'abc123def456...', 'quantity' => 3],
    ['item_key' => 'def789ghi012...', 'quantity' => 1],
]);
```

## Removing & Restoring Items

### Remove an Item

```php
$response = $client->cart()->removeItem('abc123def456...');
```

### Remove Multiple Items at Once

```php
$response = $client->cart()->removeItems([
    'abc123def456...',
    'def789ghi012...',
]);
```

### Restore a Removed Item

```php
$response = $client->cart()->restoreItem('abc123def456...');
```

### Get Removed Items

```php
$response = $client->cart()->getRemovedItems();
```

## Cart Management

### Clear Cart

```php
$response = $client->cart()->clear();

// Alias
$response = $client->cart()->empty();
```

### Calculate Totals

```php
$response = $client->cart()->calculate();
```

### Update Cart

```php
$response = $client->cart()->update([
    'customer_note' => 'Please gift wrap.',
]);
```

## Totals & Counts

### Get Totals

```php
// Raw values
$response = $client->cart()->getTotals();

// Formatted with currency (HTML)
$response = $client->cart()->getTotals(true);
```

### Get Item Count

```php
$response = $client->cart()->getItemCount();
```

## Coupons

> Requires the CoCart Plus plugin.

### Apply a Coupon

```php
$response = $client->cart()->applyCoupon('SUMMER20');
```

### Remove a Coupon

```php
$response = $client->cart()->removeCoupon('SUMMER20');
```

### Get Applied Coupons

```php
$response = $client->cart()->getCoupons();
```

### Validate Applied Coupons

```php
$response = $client->cart()->checkCoupons();
```

## Customer Details

### Update Customer

```php
// Update billing address
$response = $client->cart()->updateCustomer(
    billing: [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'address_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postcode' => '10001',
        'country' => 'US',
    ]
);

// Update shipping address
$response = $client->cart()->updateCustomer(
    shipping: [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_1' => '456 Oak Ave',
        'city' => 'Los Angeles',
        'state' => 'CA',
        'postcode' => '90001',
        'country' => 'US',
    ]
);

// Update both at once
$response = $client->cart()->updateCustomer(
    billing: ['email' => 'john@example.com'],
    shipping: ['address_1' => '456 Oak Ave']
);
```

### Get Customer Details

```php
$response = $client->cart()->getCustomer();
```

## Shipping

### Get Available Shipping Methods

```php
$response = $client->cart()->getShippingMethods();
```

### Set Shipping Method

> Requires the CoCart Plus plugin.

```php
$response = $client->cart()->setShippingMethod('flat_rate:1');
```

### Calculate Shipping

```php
$response = $client->cart()->calculateShipping([
    'country' => 'US',
    'state' => 'CA',
    'postcode' => '90001',
    'city' => 'Los Angeles',
]);
```

## Fees

> Requires the CoCart Plus plugin.

### Get Cart Fees

```php
$response = $client->cart()->getFees();
```

### Add a Fee

```php
// Non-taxable fee
$response = $client->cart()->addFee('Rush Processing', 9.99);

// Taxable fee
$response = $client->cart()->addFee('Gift Wrapping', 4.99, true);
```

### Remove All Fees

```php
$response = $client->cart()->removeFees();
```

## Cross-Sells

Get cross-sell product recommendations based on cart contents:

```php
$response = $client->cart()->getCrossSells();
```

## Batch Requests

> Requires the CoCart Plus plugin.

Submit multiple write operations in a single HTTP request for better performance. Up to 25 operations per batch.

```php
$response = $client->batch()
    ->add('cart/add-item', ['id' => '123', 'quantity' => '2'])
    ->add('cart/add-item', ['id' => '456', 'quantity' => '1'])
    ->add('cart/apply-coupon', ['coupon' => 'SAVE10'])
    ->execute();
```

For cart-only requests, the server returns the final cart state with all notices merged.

### Available Methods

```php
// Queue a POST request (default)
$client->batch()->add('cart/add-item', ['id' => '123', 'quantity' => '1']);

// Queue a PUT request
$client->batch()->update('cart/item/abc123', ['quantity' => '5']);

// Queue a DELETE request
$client->batch()->remove('cart/item/abc123');
```

### Validation Mode

```php
// Require all requests to pass validation before any are executed
$response = $client->batch()
    ->setValidation('require-all-validate')
    ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
    ->add('cart/add-item', ['id' => '456', 'quantity' => '2'])
    ->execute();
```

### Managing the Queue

```php
$batch = $client->batch();
$batch->add('cart/add-item', ['id' => '123', 'quantity' => '1']);

echo $batch->count(); // 1

$batch->clear(); // Clear without executing
echo $batch->count(); // 0
```

## Working with Responses

All cart methods return a `Response` object with cart-specific helpers:

```php
$response = $client->cart()->get();

// Cart items
$items = $response->getItems();

// Cart totals
$totals = $response->getTotals();

// Item count
$count = $response->getItemCount();

// Cart key (from headers)
$cartKey = $response->getCartKey();

// Cart hash
$hash = $response->getCartHash();

// Notices
$notices = $response->getNotices();

// Dot-notation access
$subtotal = $response->get('totals.subtotal');
$firstItemName = $response->get('items.0.name');

// Check if key exists
if ($response->has('totals.discount_total')) {
    echo 'Discount applied!';
}

// Full data
$data = $response->toArray();
$json = $response->toJson();
```

See [Error Handling](error-handling.md) for handling API errors.
