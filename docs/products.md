# Products API

The Products API is publicly accessible and does not require authentication.

```php
$products = $client->products();
```

## List Products

```php
$response = $client->products()->all();
$response = $client->products()->all(['per_page' => '20', 'page' => '1']);
```

## Parameters Reference

All list methods accept an optional `$params` array with these query parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number (default: 1) |
| `per_page` | int | Items per page (default: 10, max: 100) |
| `search` | string | Search term |
| `category` | string | Filter by category slug |
| `tag` | string | Filter by tag slug |
| `status` | string | Product status |
| `featured` | bool | Show only featured products |
| `on_sale` | bool | Show only products on sale |
| `min_price` | string | Minimum price |
| `max_price` | string | Maximum price |
| `stock_status` | string | Stock status (`instock`, `outofstock`, `onbackorder`) |
| `orderby` | string | Sort field (`date`, `id`, `title`, `slug`, `price`, `popularity`, `rating`) |
| `order` | string | Sort direction (`asc`, `desc`) |

## Filtering

### By Category

```php
$response = $client->products()->byCategory('electronics');

// With additional params
$response = $client->products()->byCategory('electronics', [
    'per_page' => '20',
    'orderby' => 'price',
    'order' => 'asc',
]);
```

### By Tag

```php
$response = $client->products()->byTag('new-arrival');
```

### Featured Products

```php
$response = $client->products()->featured();
$response = $client->products()->featured(['per_page' => '4']);
```

### Products on Sale

```php
$response = $client->products()->onSale();
```

### By Price Range

```php
// Products between $10 and $50
$response = $client->products()->byPriceRange(10.00, 50.00);

// Products under $25
$response = $client->products()->byPriceRange(null, 25.00);

// Products over $100
$response = $client->products()->byPriceRange(100.00);
```

### Search

```php
$response = $client->products()->search('wireless headphones');

// Search within a category
$response = $client->products()->search('headphones', [
    'category' => 'electronics',
]);
```

### Combining Filters

```php
$response = $client->products()->all([
    'category' => 'clothing',
    'on_sale' => true,
    'min_price' => '20',
    'max_price' => '100',
    'orderby' => 'popularity',
    'order' => 'desc',
    'per_page' => '12',
]);
```

### By Stock Status

```php
$response = $client->products()->byStockStatus('instock');
$response = $client->products()->byStockStatus('outofstock');
$response = $client->products()->byStockStatus('onbackorder');
```

## Pagination & Sorting

### Paginate Helper

```php
// Page 1, 12 products per page
$response = $client->products()->paginate(1, 12);

// Page 2
$response = $client->products()->paginate(2, 12);
```

### Sort Helper

```php
// Cheapest first
$response = $client->products()->sortBy('price');

// Most expensive first
$response = $client->products()->sortBy('price', 'desc');

// Newest first
$response = $client->products()->sortBy('date', 'desc');

// Most popular
$response = $client->products()->sortBy('popularity', 'desc');

// Highest rated
$response = $client->products()->sortBy('rating', 'desc');

// Combine with other filters
$response = $client->products()->sortBy('price', 'asc', [
    'category' => 'electronics',
    'on_sale' => true,
]);
```

### Using Parameters Directly

```php
$response = $client->products()->all([
    'page' => '1',
    'per_page' => '10',
    'orderby' => 'price',
    'order' => 'asc',
]);
```

### Paginated Loop

```php
$page = 1;
$perPage = 20;

do {
    $response = $client->products()->paginate($page, $perPage);
    $products = $response->toArray();
    $totalPages = $response->getTotalPages();

    foreach ($products as $product) {
        echo $product['name'] . ' - $' . $product['price'] . "\n";
    }

    $page++;
} while ($page <= $totalPages);
```

## Single Product

```php
$response = $client->products()->find(123);

$data = $response->toArray();
echo $data['name'];
echo $data['price'];
echo $data['description'];
```

## Variations

### List All Variations

```php
$response = $client->products()->variations(123);

foreach ($response->toArray() as $variation) {
    echo $variation['id'] . ': ' . $variation['price'] . "\n";
}
```

### Get a Specific Variation

```php
$response = $client->products()->variation(123, 456);
```

## Categories

### List All Categories

```php
$response = $client->products()->categories();
$response = $client->products()->categories(['per_page' => '50']);
```

### Get a Single Category

```php
$response = $client->products()->category(15);
```

## Tags

### List All Tags

```php
$response = $client->products()->tags();
```

### Get a Single Tag

```php
$response = $client->products()->tag(8);
```

## Attributes

### List All Attributes

```php
$response = $client->products()->attributes();
```

### Get a Single Attribute

```php
$response = $client->products()->attribute(1);
```

### Get Attribute Terms

```php
// Get all terms for attribute ID 1 (e.g., all colors)
$response = $client->products()->attributeTerms(1);
```

## Brands

### List All Brands

```php
$response = $client->products()->brands();
$response = $client->products()->brands(['per_page' => '50']);
```

### Get a Single Brand

```php
$response = $client->products()->brand(5);
```

### Filter Products by Brand

```php
$response = $client->products()->byBrand('nike');

// With additional params
$response = $client->products()->byBrand('nike', [
    'per_page' => '20',
    'orderby' => 'price',
    'order' => 'asc',
]);
```

## Reviews

### List All Reviews

```php
$response = $client->products()->reviews();
```

### Reviews for a Specific Product

```php
$response = $client->products()->productReviews(123);
```

## Working with Responses

All methods return a `Response` object:

```php
$response = $client->products()->all(['per_page' => '5']);

// As array
$products = $response->toArray();

// As object
$products = $response->toObject();

// Check success
if ($response->isSuccessful()) {
    foreach ($response->toArray() as $product) {
        echo $product['name'] . "\n";
    }
}

// Access nested data with dot notation
$response = $client->products()->find(123);
echo $response->get('name');
echo $response->get('price');
echo $response->get('categories.0.name');
```

See [Error Handling](error-handling.md) for handling API errors.
