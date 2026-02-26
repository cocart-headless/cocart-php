<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $response = new Response(200, [], '{}');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIsSuccessful(): void
    {
        $this->assertTrue((new Response(200, [], '{}'))->isSuccessful());
        $this->assertTrue((new Response(201, [], '{}'))->isSuccessful());
        $this->assertFalse((new Response(400, [], '{}'))->isSuccessful());
        $this->assertFalse((new Response(500, [], '{}'))->isSuccessful());
    }

    public function testIsError(): void
    {
        $this->assertFalse((new Response(200, [], '{}'))->isError());
        $this->assertTrue((new Response(400, [], '{}'))->isError());
        $this->assertTrue((new Response(500, [], '{}'))->isError());
    }

    public function testToArray(): void
    {
        $response = new Response(200, [], '{"foo":"bar","num":42}');
        $this->assertSame(['foo' => 'bar', 'num' => 42], $response->toArray());
    }

    public function testToArrayCachesResult(): void
    {
        $response = new Response(200, [], '{"key":"value"}');
        $first = $response->toArray();
        $second = $response->toArray();
        $this->assertSame($first, $second);
    }

    public function testToArrayWithInvalidJson(): void
    {
        $response = new Response(200, [], 'not json');
        $this->assertSame([], $response->toArray());
    }

    public function testGetWithDotNotation(): void
    {
        $body = json_encode([
            'totals' => [
                'total' => '25.00',
                'subtotal' => '20.00',
            ],
            'items' => [
                ['name' => 'Widget'],
            ],
        ]);
        $response = new Response(200, [], $body);

        $this->assertSame('25.00', $response->get('totals.total'));
        $this->assertSame('20.00', $response->get('totals.subtotal'));
        $this->assertSame('Widget', $response->get('items.0.name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $response = new Response(200, [], '{"a":"b"}');
        $this->assertNull($response->get('missing'));
        $this->assertSame('default', $response->get('missing', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $response = new Response(200, [], '{"key":"value","nested":{"child":"yes"}}');
        $this->assertTrue($response->has('key'));
        $this->assertTrue($response->has('nested.child'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $response = new Response(200, [], '{"key":"value"}');
        $this->assertFalse($response->has('missing'));
        $this->assertFalse($response->has('key.deep'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $response = new Response(200, [], '{"field":null}');
        $this->assertTrue($response->has('field'));
    }

    public function testGetCartKeyFromHeader(): void
    {
        $response = new Response(200, ['Cart-Key' => 'abc123'], '{}');
        $this->assertSame('abc123', $response->getCartKey());
    }

    public function testGetCartKeyReturnsNullWhenMissing(): void
    {
        $response = new Response(200, [], '{}');
        $this->assertNull($response->getCartKey());
    }

    public function testGetCartKeyCaseInsensitive(): void
    {
        $response = new Response(200, ['cart-key' => 'xyz789'], '{}');
        $this->assertSame('xyz789', $response->getCartKey());
    }

    public function testGetHeaderCaseInsensitive(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');
        $this->assertSame('application/json', $response->getHeader('content-type'));
        $this->assertSame('application/json', $response->getHeader('CONTENT-TYPE'));
    }

    public function testGetHeaderDefaultValue(): void
    {
        $response = new Response(200, [], '{}');
        $this->assertNull($response->getHeader('missing'));
        $this->assertSame('fallback', $response->getHeader('missing', 'fallback'));
    }

    public function testGetItems(): void
    {
        $body = json_encode(['items' => [['id' => 1], ['id' => 2]]]);
        $response = new Response(200, [], $body);
        $this->assertCount(2, $response->getItems());
    }

    public function testGetTotals(): void
    {
        $body = json_encode(['totals' => ['total' => '50.00']]);
        $response = new Response(200, [], $body);
        $this->assertSame(['total' => '50.00'], $response->getTotals());
    }

    public function testGetItemCount(): void
    {
        $body = json_encode(['item_count' => 3]);
        $response = new Response(200, [], $body);
        $this->assertSame(3, $response->getItemCount());
    }

    public function testGetErrorCodeAndMessage(): void
    {
        $body = json_encode(['code' => 'not_found', 'message' => 'Item not found']);
        $response = new Response(404, [], $body);
        $this->assertSame('not_found', $response->getErrorCode());
        $this->assertSame('Item not found', $response->getErrorMessage());
    }

    public function testGetErrorCodeReturnsNullForSuccess(): void
    {
        $response = new Response(200, [], '{}');
        $this->assertNull($response->getErrorCode());
        $this->assertNull($response->getErrorMessage());
    }

    public function testMagicPropertyAccess(): void
    {
        $body = json_encode(['cart_hash' => 'abc', 'items' => []]);
        $response = new Response(200, [], $body);
        $this->assertSame('abc', $response->cart_hash);
        $this->assertSame([], $response->items);
    }

    public function testToJson(): void
    {
        $response = new Response(200, [], '{"a":1}');
        $json = $response->toJson(0);
        $this->assertSame('{"a":1}', $json);
    }
}
