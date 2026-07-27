<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class CartEndpointTest extends TestCase
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

    // --- cart()->create() ---

    public function testCreateSendsPostToCart(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"cart_key":"guest_new123"}');

        $client = $this->createClient();
        $response = $client->cart()->create();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/cocart/v2/cart', $request['url']);
        $this->assertSame('guest_new123', $response->get('cart_key'));
    }

    // --- cart()->getItems() ---

    public function testGetItemsSendsGetToCartItems(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[{"id":"123","quantity":2}]');

        $client = $this->createClient();
        $client->cart()->getItems();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/cart/items', $request['url']);
    }

    // --- cart()->getItem() ---

    public function testGetItemSendsGetToCartItemKey(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":"123","quantity":2}');

        $client = $this->createClient();
        $client->cart()->getItem('abc_item_key');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/cart/item/abc_item_key', $request['url']);
    }

    // --- cart()->addItems() ---

    public function testAddItemsPostsGroupedProductWithMapShorthand(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->addItems(100, ['200' => 2, '300' => 1]);

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/cart/add-items', $request['url']);
        $this->assertSame('100', $body['id']);
        $this->assertSame(['200' => '2', '300' => '1'], $body['quantity']);
    }

    public function testAddItemsAcceptsArrayOfIdQuantityEntries(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->addItems(100, [
            ['id' => 200, 'quantity' => 2],
            ['id' => 300, 'quantity' => 1],
        ]);

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('100', $body['id']);
        $this->assertSame(['200' => '2', '300' => '1'], $body['quantity']);
    }

    public function testAddItemsThrowsWhenEmpty(): void
    {
        $client = $this->createClient();

        $this->expectException(ValidationException::class);
        $client->cart()->addItems(100, []);
    }

    // --- cart()->updateItems() / batchUpdateItems() ---

    public function testUpdateItemsSendsOneRequestPerItemSequentially(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"item_count":1}');
        $this->mockAdapter->queueResponse(200, [], '{"item_count":2}');

        $client = $this->createClient();
        $response = $client->cart()->updateItems([
            'abc123' => 3,
            'def456' => 1,
        ]);

        $requests = $this->mockAdapter->getRequests();
        $this->assertCount(2, $requests);
        $this->assertStringContainsString('/cart/item/abc123', $requests[0]['url']);
        $this->assertStringContainsString('/cart/item/def456', $requests[1]['url']);
        $this->assertSame(2, $response->get('item_count'));
    }

    public function testUpdateItemsAcceptsFullArrayFormat(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->updateItems([
            ['item_key' => 'abc123', 'quantity' => 5],
        ]);

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertStringContainsString('/cart/item/abc123', $request['url']);
        $this->assertSame('5', $body['quantity']);
    }

    public function testUpdateItemsThrowsWhenEmpty(): void
    {
        $client = $this->createClient();

        $this->expectException(ValidationException::class);
        $client->cart()->updateItems([]);
    }

    public function testBatchUpdateItemsSendsSingleBatchRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->cart()->batchUpdateItems([
            'abc123' => 3,
            'def456' => 1,
        ]);

        $requests = $this->mockAdapter->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertStringContainsString('/batch', $request['url']);

        $body = json_decode($request['body'], true);
        $this->assertCount(2, $body['requests']);
        $this->assertSame('POST', $body['requests'][0]['method']);
        $this->assertSame('/cocart/v2/cart/item/abc123', $body['requests'][0]['path']);
        $this->assertSame(['quantity' => '3'], $body['requests'][0]['body']);
    }

    // --- cart()->removeItems() / batchRemoveItems() ---

    public function testRemoveItemsSendsOneRequestPerItemSequentially(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"item_count":1}');
        $this->mockAdapter->queueResponse(200, [], '{"item_count":0}');

        $client = $this->createClient();
        $response = $client->cart()->removeItems(['abc123', 'def456']);

        $requests = $this->mockAdapter->getRequests();
        $this->assertCount(2, $requests);
        $this->assertSame('DELETE', $requests[0]['method']);
        $this->assertStringContainsString('/cart/item/abc123', $requests[0]['url']);
        $this->assertStringContainsString('/cart/item/def456', $requests[1]['url']);
        $this->assertSame(0, $response->get('item_count'));
    }

    public function testRemoveItemsThrowsWhenEmpty(): void
    {
        $client = $this->createClient();

        $this->expectException(ValidationException::class);
        $client->cart()->removeItems([]);
    }

    public function testBatchRemoveItemsSendsSingleBatchRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->cart()->batchRemoveItems(['abc123', 'def456']);

        $requests = $this->mockAdapter->getRequests();
        $this->assertCount(1, $requests);

        $body = json_decode($requests[0]['body'], true);
        $this->assertCount(2, $body['requests']);
        $this->assertSame('DELETE', $body['requests'][0]['method']);
        $this->assertSame('/cocart/v2/cart/item/abc123', $body['requests'][0]['path']);
        $this->assertArrayNotHasKey('body', $body['requests'][0]);
    }

    // --- cart()->updateCustomer() ---

    public function testUpdateCustomerSendsUnprefixedBillingAndMirrorsShipping(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->updateCustomer([
            'first_name' => 'John',
            'address_1' => '123 Main St',
        ]);

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('update-customer', $body['namespace']);
        $this->assertSame('John', $body['first_name']);
        $this->assertSame('123 Main St', $body['address_1']);
        // Mirrored into s_ fields since no distinct shipping was given
        $this->assertSame('John', $body['s_first_name']);
        $this->assertSame('123 Main St', $body['s_address_1']);
        $this->assertArrayNotHasKey('ship_to_different_address', $body);
    }

    public function testUpdateCustomerSendsPrefixedShippingWhenDistinct(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->updateCustomer(
            ['first_name' => 'John', 'address_1' => '123 Main St'],
            ['first_name' => 'Jane', 'address_1' => '456 Oak Ave']
        );

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('John', $body['first_name']);
        $this->assertSame('Jane', $body['s_first_name']);
        $this->assertSame('456 Oak Ave', $body['s_address_1']);
        $this->assertTrue($body['ship_to_different_address']);
    }

    // --- cart()->setShippingMethod() ---

    public function testSetShippingMethodPostsRateId(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->setShippingMethod('flat_rate:2');

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertStringContainsString('/cart/set-shipping-method', $request['url']);
        $this->assertSame('flat_rate:2', $body['rate_id']);
        $this->assertArrayNotHasKey('package_id', $body);
    }

    public function testSetShippingMethodPostsRateIdAndPackageId(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->setShippingMethod('flat_rate:2', 'package_1');

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('flat_rate:2', $body['rate_id']);
        $this->assertSame('package_1', $body['package_id']);
    }

    // --- cart()->calculateShipping() (deprecated) ---

    public function testCalculateShippingDelegatesToCalculate(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->cart()->calculateShipping(['country' => 'US']);

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertStringContainsString('/cart/calculate', $request['url']);
        $this->assertStringNotContainsString('/cart/calculate/shipping', $request['url']);
        $this->assertArrayNotHasKey('country', $body ?? []);
    }
}
