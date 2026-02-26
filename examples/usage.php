<?php
/**
 * CoCart PHP SDK - Usage Examples
 * 
 * This file demonstrates various use cases for the CoCart PHP SDK.
 * These examples show how to work with both guest customers and 
 * authenticated users.
 */

require_once __DIR__ . '/../vendor/autoload.php';
// Or if not using Composer:
// require_once __DIR__ . '/../src/CoCart.php';
// ... require other files

use CoCart\CoCartInterface;
use CoCart\SessionManager;
use CoCart\JwtManager;
use CoCart\Storage\PhpSessionStorage;
use CoCart\Storage\CookieStorage;
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\ValidationException;

// Configuration
$storeUrl = 'https://your-woocommerce-store.com';

// =============================================================================
// EXAMPLE 1: Guest Customer Shopping
// =============================================================================

echo "=== Guest Customer Shopping ===\n\n";

// Initialize client
$client = new CoCart($storeUrl);

// Add first item - cart key will be automatically extracted from response headers
try {
    $response = $client->cart()->addItem(123, 2); // Product ID: 123, Quantity: 2
    
    echo "Item added!\n";
    echo "Cart Key: " . $client->getCartKey() . "\n";
    echo "Item Count: " . $response->getItemCount() . "\n";
    echo "Total: " . $response->get('totals.total') . "\n\n";
    
} catch (ValidationException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getErrorCode() . "\n";
}

// Add a variable product (t-shirt with size and color)
try {
    $response = $client->cart()->addVariation(456, 1, [
        'attribute_pa_size' => 'large',
        'attribute_pa_color' => 'blue'
    ]);
    
    echo "Variable product added!\n";
    echo "Items in cart: " . $response->getItemCount() . "\n\n";
    
} catch (CoCartException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Get the full cart
$cart = $client->cart()->get();
echo "Full Cart:\n";
print_r($cart->toArray());
echo "\n";

// =============================================================================
// EXAMPLE 2: Using Session Manager for Persistent Cart
// =============================================================================

echo "=== Using Session Manager ===\n\n";

// Use PHP sessions to persist cart key
session_start();
$storage = new PhpSessionStorage();
$client2 = new CoCart($storeUrl);
$session = new SessionManager($client2, $storage);

// Initialize or resume cart
$cartKey = $session->initializeCart();
echo "Cart initialized with key: " . $cartKey . "\n";

// Add items (cart key is automatically included in requests)
$client2->cart()->addItem(789, 1);
echo "Item added to persistent cart!\n\n";

// =============================================================================
// EXAMPLE 3: Customer Login with Cart Transfer
// =============================================================================

echo "=== Customer Login with Cart Transfer ===\n\n";

// Guest shopping first
$guestClient = new CoCart($storeUrl);
$guestSession = new SessionManager($guestClient, new PhpSessionStorage());
$guestSession->initializeCart();

// Guest adds items
$guestClient->cart()->addItem(111, 3);
$guestClient->cart()->addItem(222, 1);
echo "Guest has " . $guestClient->cart()->get()->getItemCount() . " items\n";

// Guest decides to login - cart is automatically merged
try {
    $response = $guestSession->login('customer@example.com', 'customer_password', mergeCart: true);
    echo "Logged in! Cart now has " . $response->getItemCount() . " items\n";
    echo "Cart merged successfully!\n\n";
} catch (AuthenticationException $e) {
    echo "Login failed: " . $e->getMessage() . "\n";
}

// =============================================================================
// EXAMPLE 4: JWT Authentication
// =============================================================================

echo "=== JWT Authentication ===\n\n";

$jwtClient = new CoCart($storeUrl, [
    'jwt_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
]);

// Or set it later
$jwtClient = new CoCart($storeUrl);
$jwtClient->setJwtToken('your_jwt_token_here');

try {
    $cart = $jwtClient->cart()->get();
    echo "JWT Auth successful! Items: " . $cart->getItemCount() . "\n\n";
} catch (AuthenticationException $e) {
    echo "JWT Auth failed: " . $e->getMessage() . "\n\n";
}

// =============================================================================
// EXAMPLE 5: Full Cart Management
// =============================================================================

echo "=== Full Cart Management ===\n\n";

$client = new CoCart($storeUrl);

// Add items
$response = $client->cart()->addItem(100, 2);
$itemKey = array_key_first($response->getItems()); // Get the item key

// Update quantity
$client->cart()->updateItem($itemKey, 5);
echo "Quantity updated to 5\n";

// Apply coupon
try {
    $client->cart()->applyCoupon('SUMMER20');
    echo "Coupon applied!\n";
} catch (ValidationException $e) {
    echo "Invalid coupon: " . $e->getMessage() . "\n";
}

// Get totals
$totals = $client->cart()->getTotals(true); // true = formatted with currency
echo "Totals:\n";
print_r($totals->toArray());

// Update customer billing info
$client->cart()->updateCustomer(
    billing: [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '+1234567890',
        'address_1' => '123 Main Street',
        'city' => 'New York',
        'state' => 'NY',
        'postcode' => '10001',
        'country' => 'US'
    ]
);
echo "Customer details updated!\n";

// Calculate shipping
$client->cart()->calculateShipping([
    'country' => 'US',
    'state' => 'NY',
    'postcode' => '10001',
    'city' => 'New York'
]);

// Get shipping methods
$methods = $client->cart()->getShippingMethods();
echo "Available shipping methods:\n";
print_r($methods->toArray());

// Set shipping method
$client->cart()->setShippingMethod('flat_rate:1');
echo "Shipping method set!\n";

// Calculate totals
$client->cart()->calculate();

// Remove item
$client->cart()->removeItem($itemKey);
echo "Item removed!\n";

// Restore item
$client->cart()->restoreItem($itemKey);
echo "Item restored!\n";

// Clear cart
$client->cart()->clear();
echo "Cart cleared!\n\n";

// =============================================================================
// EXAMPLE 6: Products API
// =============================================================================

echo "=== Products API ===\n\n";

$client = new CoCart($storeUrl);

// Get all products
$products = $client->products()->all(['per_page' => 10]);
echo "Found " . count($products->toArray()) . " products\n";

// Search products
$results = $client->products()->search('headphones');
echo "Search results: " . count($results->toArray()) . " products\n";

// Get products by category
$electronics = $client->products()->byCategory('electronics');

// Get featured products
$featured = $client->products()->featured();

// Get products on sale
$sales = $client->products()->onSale();

// Get products in price range
$affordable = $client->products()->byPriceRange(10.00, 50.00);

// Get single product
$product = $client->products()->find(123);
echo "Product: " . $product->get('name') . "\n";

// Get product variations
$variations = $client->products()->variations(123);
echo "Variations: " . count($variations->toArray()) . "\n";

// Get categories
$categories = $client->products()->categories();
echo "Categories available\n";

// Get product reviews
$reviews = $client->products()->productReviews(123);
echo "Reviews loaded\n\n";

// =============================================================================
// EXAMPLE 7: Store Information
// =============================================================================

echo "=== Store Information ===\n\n";

$client = new CoCart($storeUrl);

// Get store info
$info = $client->store()->info();
echo "Store info loaded\n";

echo "\n";

// =============================================================================
// EXAMPLE 8: Admin Session Management
// =============================================================================

echo "=== Admin Session Management ===\n\n";

// Requires WooCommerce REST API credentials
$adminClient = new CoCart($storeUrl, [
    'consumer_key' => 'ck_your_consumer_key',
    'consumer_secret' => 'cs_your_consumer_secret'
]);

try {
    // Get all cart sessions
    $sessions = $adminClient->sessions()->all();
    echo "Total sessions: " . count($sessions->toArray()) . "\n";
    
    // Get specific session
    $session = $adminClient->sessions()->find('some_cart_key');
    
    // Get session by customer ID
    $customerSession = $adminClient->sessions()->byCustomer(123);
    
    // Delete a session
    // $adminClient->sessions()->destroy('cart_key_to_delete');
    
} catch (AuthenticationException $e) {
    echo "Admin auth required: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// EXAMPLE 9: Working with Response Objects
// =============================================================================

echo "=== Working with Responses ===\n\n";

$client = new CoCart($storeUrl);
$response = $client->cart()->get();

// Check status
echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Is Successful: " . ($response->isSuccessful() ? 'Yes' : 'No') . "\n";

// Get headers
$headers = $response->getHeaders();
echo "Cart Key from Header: " . $response->getCartKey() . "\n";

// Access data
echo "Cart Hash: " . $response->get('cart_hash') . "\n";
echo "Item Count: " . $response->getItemCount() . "\n";

// Nested access with dot notation
echo "Subtotal: " . $response->get('totals.subtotal') . "\n";
echo "Currency: " . $response->get('currency.currency_code') . "\n";

// Get items
$items = $response->getItems();
foreach ($items as $itemKey => $item) {
    echo "- {$item['name']} x {$item['quantity']['value']}\n";
}

// Get notices
$notices = $response->getNotices();
if (!empty($notices['success'])) {
    foreach ($notices['success'] as $notice) {
        echo "Notice: $notice\n";
    }
}

// Convert to different formats
$json = $response->toJson();
$array = $response->toArray();
$object = $response->toObject();

echo "\n";

// =============================================================================
// EXAMPLE 10: Error Handling Best Practices
// =============================================================================

echo "=== Error Handling ===\n\n";

$client = new CoCart($storeUrl);

try {
    // Try to add a non-existent product
    $response = $client->cart()->addItem(999999, 1);
    
} catch (ValidationException $e) {
    echo "Validation Error!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getErrorCode() . "\n";
    echo "HTTP Code: " . $e->getHttpCode() . "\n";
    
} catch (AuthenticationException $e) {
    echo "Authentication Error!\n";
    echo "Message: " . $e->getMessage() . "\n";
    // Redirect to login page or refresh token
    
} catch (CoCartException $e) {
    echo "General API Error!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    
} catch (\Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// EXAMPLE 11: Cookie-Based Session Storage (for web apps)
// =============================================================================

echo "=== Cookie-Based Storage ===\n\n";

$cookieStorage = new CookieStorage(
    expiration: 604800,  // 7 days
    path: '/',
    domain: '',          // Current domain
    secure: true,        // HTTPS only
    httpOnly: true       // Not accessible via JavaScript
);

$client = new CoCart($storeUrl);
$session = new SessionManager($client, $cookieStorage);

// Cart key will be stored in a secure HTTP-only cookie
$session->initializeCart();
$client->cart()->addItem(123, 1);

echo "Cart key stored in cookie!\n\n";

// =============================================================================
// EXAMPLE 12: Adding Items with Custom Data
// =============================================================================

echo "=== Custom Item Data ===\n\n";

$client = new CoCart($storeUrl);

// Add item with gift wrapping option
$response = $client->cart()->addItem(123, 1, [
    'item_data' => [
        'gift_wrap' => true,
        'gift_message' => 'Happy Birthday!',
        'custom_engraving' => 'To John, From Jane'
    ]
]);

// Add item with custom price (requires server-side validation)
$response = $client->cart()->addItem(123, 1, [
    'price' => '29.99'  // Override price
]);

// Add item and set customer email at the same time
$response = $client->cart()->addItem(123, 1, [
    'email' => 'customer@example.com',
    'phone' => '+1234567890'
]);

echo "Items with custom data added!\n\n";

// =============================================================================
// EXAMPLE 13: Batch Adding Items
// =============================================================================

echo "=== Batch Adding Items ===\n\n";

$client = new CoCart($storeUrl);

$response = $client->cart()->addItems([
    [
        'id' => '123',
        'quantity' => '2'
    ],
    [
        'id' => '456',
        'quantity' => '1',
        'variation' => [
            'attribute_pa_color' => 'red',
            'attribute_pa_size' => 'medium'
        ]
    ],
    [
        'id' => '789',
        'quantity' => '3',
        'item_data' => [
            'custom_option' => 'value'
        ]
    ]
]);

echo "Added " . $response->getItemCount() . " items in single request!\n\n";

// =============================================================================
// EXAMPLE 14: Complete Checkout Flow
// =============================================================================

echo "=== Complete Checkout Flow ===\n\n";

$client = new CoCart($storeUrl);
$session = new SessionManager($client, new PhpSessionStorage());
$session->initializeCart();

// Step 1: Add items
$client->cart()->addItem(123, 2);
$client->cart()->addItem(456, 1);
echo "Step 1: Items added\n";

// Step 2: Apply coupon (optional)
try {
    $client->cart()->applyCoupon('WELCOME10');
    echo "Step 2: Coupon applied\n";
} catch (ValidationException $e) {
    echo "Step 2: No valid coupon\n";
}

// Step 3: Update customer details
$client->cart()->updateCustomer(
    billing: [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'address_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postcode' => '10001',
        'country' => 'US'
    ],
    shipping: [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postcode' => '10001',
        'country' => 'US'
    ]
);
echo "Step 3: Customer details updated\n";

// Step 4: Calculate shipping
$client->cart()->calculateShipping([
    'country' => 'US',
    'state' => 'NY',
    'postcode' => '10001',
    'city' => 'New York'
]);
echo "Step 4: Shipping calculated\n";

// Step 5: Get shipping methods and select one
$shippingMethods = $client->cart()->getShippingMethods();
// Select first available method
$client->cart()->setShippingMethod('flat_rate:1');
echo "Step 5: Shipping method selected\n";

// Step 6: Calculate final totals
$client->cart()->calculate();
$cart = $client->cart()->get();
echo "Step 7: Final totals calculated\n";

echo "\nOrder Summary:\n";
echo "Items: " . $cart->getItemCount() . "\n";
echo "Subtotal: " . $cart->get('totals.subtotal') . "\n";
echo "Shipping: " . $cart->get('totals.shipping_total') . "\n";
echo "Tax: " . $cart->get('totals.total_tax') . "\n";
echo "Total: " . $cart->get('totals.total') . "\n";

// Step 8: Proceed to payment gateway
// At this point, you would redirect to your payment processor
// or handle payment via their SDK

echo "\nReady for payment!\n";
echo "Cart Key for order: " . $client->getCartKey() . "\n";

// The cart key can be used to load this cart on the native WooCommerce
// checkout if needed for payment processing

// =============================================================================
// EXAMPLE 15: JWT Authentication
// =============================================================================

echo "=== JWT Authentication ===\n\n";

$client = new CoCart($storeUrl);

// --- Basic JWT login ---
$jwt = new JwtManager($client);

try {
    $response = $jwt->login('customer@example.com', 'customer_password');

    echo "Logged in as: " . $response->get('display_name') . "\n";
    echo "JWT Token: " . substr($client->getJwtToken(), 0, 20) . "...\n";
    echo "Refresh Token: " . substr($client->getRefreshToken(), 0, 20) . "...\n";

    // All subsequent requests use the JWT token automatically
    $cart = $client->cart()->get();
    echo "Cart items: " . $cart->getItemCount() . "\n";

} catch (AuthenticationException $e) {
    echo "Login failed: " . $e->getMessage() . "\n";
}

// --- Validate token ---
if ($jwt->validate()) {
    echo "Token is valid\n";
} else {
    echo "Token is expired or invalid\n";
}

// --- Refresh token ---
try {
    $jwt->refresh();
    echo "Token refreshed successfully\n";
} catch (AuthenticationException $e) {
    echo "Refresh failed: " . $e->getMessage() . "\n";
}

// --- Auto-refresh wrapper ---
try {
    $response = $jwt->withAutoRefresh(function ($client) {
        return $client->cart()->get();
    });
    echo "Cart retrieved with auto-refresh: " . $response->getItemCount() . " items\n";
} catch (CoCartException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// --- Auto-refresh enabled globally ---
$client2 = new CoCart($storeUrl);
$jwt2 = new JwtManager($client2, null, ['auto_refresh' => true]);
$client2->setJwtManager($jwt2);

// Now any request will auto-refresh the token on auth failure
// $cart = $client2->cart()->get();

// --- JWT with storage persistence ---
$storage = new PhpSessionStorage();
$client3 = new CoCart($storeUrl);
$jwt3 = new JwtManager($client3, $storage);

// Login — tokens are automatically saved to storage
$jwt3->login('customer@example.com', 'password');

// On subsequent page loads, tokens are restored from storage:
// $jwt4 = new JwtManager(new CoCart($storeUrl), $storage);
// No need to login again — tokens are loaded from the session

// --- JWT via SessionManager (with cart merging) ---
$client4 = new CoCart($storeUrl);
$session = new SessionManager($client4, new PhpSessionStorage());
$session->initializeCart();

// Guest adds items
$client4->cart()->addItem(123, 2);

// Login with JWT — guest cart is merged automatically
try {
    $loginResponse = $session->loginWithJwt('customer@example.com', 'password', mergeCart: true);
    echo "JWT login + cart merge successful\n";

    // Access JWT manager via session
    $session->jwt()->validate();

} catch (CoCartException $e) {
    echo "JWT login failed: " . $e->getMessage() . "\n";
}

// Clear tokens on logout
$jwt->clearTokens();
echo "Tokens cleared\n\n";

// =============================================================================
// EXAMPLE 16: Testing with CoCartInterface
// =============================================================================

echo "=== Testing with CoCartInterface ===\n\n";

// The SDK provides CoCartInterface so you can mock the client in tests.
// Type-hint your classes against CoCartInterface instead of the concrete CoCart class.

// Example service class:
//
// class CartService
// {
//     public function __construct(
//         private CoCartInterface $client
//     ) {}
//
//     public function addProduct(int $productId, int $quantity): Response
//     {
//         return $this->client->cart()->addItem($productId, $quantity);
//     }
//
//     public function getTotal(): string
//     {
//         $cart = $this->client->cart()->get();
//         return $cart->get('totals.total');
//     }
// }

// In your PHPUnit test:
//
// class CartServiceTest extends TestCase
// {
//     public function testGetTotal(): void
//     {
//         $client = $this->createMock(CoCartInterface::class);
//         // Set up mock expectations...
//
//         $service = new CartService($client);
//         $total = $service->getTotal();
//         $this->assertSame('25.00', $total);
//     }
// }

echo "See README.md for full testing documentation.\n\n";

// =============================================================================
// EXAMPLE 17: White-Labelling / Custom REST Prefix
// =============================================================================

echo "=== White-Labelling / Custom REST Prefix ===\n\n";

// If your WordPress site uses a custom REST URL prefix
$client = new CoCart($storeUrl, [
    'rest_prefix' => 'api',  // instead of default 'wp-json'
]);
echo "REST prefix: " . $client->getRestPrefix() . "\n";
// Requests go to: https://your-store.com/api/cocart/v2/...

// If CoCart has been white-labelled with a different namespace
$client = new CoCart($storeUrl, [
    'namespace' => 'mystore',  // instead of default 'cocart'
]);
echo "Namespace: " . $client->getNamespace() . "\n";
// Requests go to: https://your-store.com/wp-json/mystore/v2/...

// Both together
$client = new CoCart($storeUrl, [
    'rest_prefix' => 'api',
    'namespace' => 'mystore',
]);
// Requests go to: https://your-store.com/api/mystore/v2/...

// Or change at runtime via fluent setters
$client = new CoCart($storeUrl);
$client->setRestPrefix('api')->setNamespace('mystore');
echo "Updated prefix: " . $client->getRestPrefix() . "\n";
echo "Updated namespace: " . $client->getNamespace() . "\n\n";

echo "\n=== Examples Complete ===\n";
