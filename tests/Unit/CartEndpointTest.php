<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
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
}
