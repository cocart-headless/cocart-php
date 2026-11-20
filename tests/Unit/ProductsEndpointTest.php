<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use PHPUnit\Framework\TestCase;

class ProductsEndpointTest extends TestCase
{
    private MockHttpAdapter $mockAdapter;

    protected function setUp(): void
    {
        $this->mockAdapter = new MockHttpAdapter();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function createClient(array $options = []): CoCart
    {
        return new CoCart('https://store.example.com', array_merge(
            ['http_adapter' => $this->mockAdapter, 'auto_storage' => false],
            $options
        ));
    }

    // --- find ---

    public function testFindSendsCorrectRequestForNumericId(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":278}');

        $client = $this->createClient();
        $client->products()->find(278);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/278', $request['url']);
    }

    public function testFindAcceptsSkuString(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"sku":"PCT-2024"}');

        $client = $this->createClient();
        $client->products()->find('PCT-2024');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/products/PCT-2024', $request['url']);
    }

    // --- findBySlug ---

    public function testFindBySlugSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":123,"slug":"blue-hoodie"}');

        $client = $this->createClient();
        $response = $client->products()->findBySlug('blue-hoodie');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/blue-hoodie', $request['url']);
        $this->assertSame('blue-hoodie', $response->get('slug'));
    }

    // --- attributeTerm (by ID) ---

    public function testAttributeTermSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":5,"name":"Blue"}');

        $client = $this->createClient();
        $client->products()->attributeTerm(1, 5);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/attributes/1/terms/5', $request['url']);
    }

    // --- attributeBySlug ---

    public function testAttributeBySlugSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":1,"slug":"color"}');

        $client = $this->createClient();
        $client->products()->attributeBySlug('color');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/attributes/color', $request['url']);
    }

    // --- attributeTermsBySlug ---

    public function testAttributeTermsBySlugSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient();
        $client->products()->attributeTermsBySlug('color');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/attributes/color/terms', $request['url']);
    }

    // --- attributeTermBySlug ---

    public function testAttributeTermBySlugSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"slug":"blue"}');

        $client = $this->createClient();
        $client->products()->attributeTermBySlug('color', 'blue');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/attributes/color/terms/blue', $request['url']);
    }

    // --- myReviews ---

    public function testMyReviewsSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient([
            'username' => 'customer@example.com',
            'password' => 'pass',
        ]);
        $client->products()->myReviews();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/products/reviews/mine', $request['url']);
    }

    // --- shippingRates() (CoCart Plus) ---

    public function testShippingRatesSendsQuantityAndVariationWithoutTheCartKeyTheRouteRefuses(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"packages":{}}');

        $client = $this->createClient(['cart_key' => 't_guest']);
        $client->products()->shippingRates(134, ['quantity' => 2, 'variation' => ['attribute_pa_color' => 'blue']]);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertSame('/wp-json/cocart/v2/products/134/shipping-rates', parse_url($request['url'], PHP_URL_PATH));
        parse_str((string) parse_url($request['url'], PHP_URL_QUERY), $query);
        $this->assertSame(['quantity' => '2', 'variation' => ['attribute_pa_color' => 'blue']], $query);
        $this->assertArrayNotHasKey('Cart-Key', $request['headers']);

        // Other requests still carry the key.
        $client->cart()->get();
        $this->assertSame('t_guest', $this->mockAdapter->getLastRequest()['headers']['Cart-Key']);
    }

    public function testShippingRatesSendsItemDataAndEncodesASku(): void
    {
        $client = $this->createClient();
        $client->products()->shippingRates('Woo beanie/1', ['item_data' => ['engraving' => 'JD']]);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('/wp-json/cocart/v2/products/Woo%20beanie%2F1/shipping-rates', parse_url($request['url'], PHP_URL_PATH));
        $this->assertStringEndsWith('?item_data%5Bengraving%5D=JD', $request['url']);
    }

    public function testShippingRatesUsesTheConfiguredNamespace(): void
    {
        $client = $this->createClient(['namespace' => 'mystore']);
        $client->products()->shippingRates(134);

        $this->assertStringEndsWith('/wp-json/mystore/v2/products/134/shipping-rates', $this->mockAdapter->getLastRequest()['url']);
    }

    // --- createReview ---

    public function testCreateReviewPostsToProductsReviews(): void
    {
        $this->mockAdapter->queueResponse(201, [], '{}');
        $data = ['product_id' => 154, 'review' => 'Warm.', 'rating' => 5, 'reviewer' => 'Jane', 'reviewer_email' => 'jane@example.com'];

        $client = $this->createClient();
        $client->products()->createReview($data);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST /wp-json/cocart/v2/products/reviews', $request['method'] . ' ' . parse_url($request['url'], PHP_URL_PATH));
        $this->assertSame($data, json_decode($request['body'], true));
    }
}
