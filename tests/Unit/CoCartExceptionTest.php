<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\CoCartException;
use PHPUnit\Framework\TestCase;

class CoCartExceptionTest extends TestCase
{
    // --- getCart() ---

    public function testGetCartReturnsTheCartSentWithItemNotInCart(): void
    {
        $cart = ['cart_key' => 't_1', 'items' => [['item_key' => 'abc']], 'totals' => ['total' => '3600']];
        $e = new CoCartException('Item specified does not exist in cart.', 404, 'cocart_item_not_in_cart', [
            'code' => 'cocart_item_not_in_cart',
            'data' => ['status' => 404, 'cart' => $cart],
        ]);

        $this->assertSame($cart, $e->getCart());
    }

    public function testGetCartIsNullWhenTheErrorHasNoCart(): void
    {
        $this->assertNull((new CoCartException('Nope', 404, 'cocart_coupon_not_exist', ['data' => ['status' => 404]]))->getCart());
        $this->assertNull((new CoCartException('Nope'))->getCart());
    }

    public function testGetCartWorksOnTheExceptionTheClientThrows(): void
    {
        $adapter = new MockHttpAdapter();
        $adapter->queueResponse(404, [], json_encode([
            'code' => 'cocart_item_not_in_cart',
            'message' => 'Item specified does not exist in cart.',
            'data' => ['status' => 404, 'cart' => ['cart_key' => 't_1', 'item_count' => 2]],
        ]));

        $client = new CoCart('https://store.example.com', ['http_adapter' => $adapter, 'auto_storage' => false]);

        try {
            $client->cart()->removeItem('missing');
            $this->fail('Expected a CoCartException');
        } catch (CoCartException $e) {
            $this->assertSame(2, $e->getCart()['item_count']);
        }
    }
}
