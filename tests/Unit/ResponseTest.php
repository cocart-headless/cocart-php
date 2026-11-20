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

    // --- Cart shapes CoCart sends (recorded from CoCart Starter 5) ---

    private function recordedCart(): Response
    {
        return new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/cart.json'));
    }

    public function testGetShippingMethodsListsThePackagesFromShippingPackages(): void
    {
        $response = $this->recordedCart();

        $packages = $response->getShippingMethods();
        $this->assertCount(1, $packages);
        $this->assertTrue(array_is_list($packages));
        $this->assertSame('Shipment', $packages[0]['package_name']);
        $this->assertArrayHasKey('flat_rate:1', $packages[0]['rates']);
    }

    public function testGetShippingReturnsTheWholeShippingField(): void
    {
        $shipping = $this->recordedCart()->getShipping();

        $this->assertSame(2, $shipping['total_packages']);
        $this->assertTrue($shipping['has_calculated_shipping']);
        $this->assertArrayHasKey('default', $shipping['packages']);
    }

    public function testGetShippingMethodsAcceptsAPlainListAndIsEmptyWithoutShipping(): void
    {
        $this->assertCount(1, (new Response(200, [], json_encode(['shipping' => [['package_name' => 'A']]])))->getShippingMethods());
        $this->assertSame([], (new Response(200, [], '{"items":[]}'))->getShippingMethods());
        $this->assertNull((new Response(200, [], '{"items":[]}'))->getShipping());
    }

    public function testGetFeesListsFeesKeyedByFeeIdAndIsEmptyForEmptyMaps(): void
    {
        $keyed = ['fees' => ['gift-wrap' => ['name' => 'Gift wrap', 'fee' => '500'], 'handling' => ['name' => 'Handling', 'fee' => '150']]];

        $this->assertSame([
            ['name' => 'Gift wrap', 'fee' => '500'],
            ['name' => 'Handling', 'fee' => '150'],
        ], (new Response(200, [], json_encode($keyed)))->getFees());
        $this->assertSame([], (new Response(200, [], '{"fees":[]}'))->getFees());
        $this->assertSame([], $this->recordedCart()->getFees());
    }

    public function testGetNoticesReturnsNoticesKeyedByType(): void
    {
        $response = new Response(200, [], '{"notices":{"success":["Item added"]}}');

        $this->assertSame(['success' => ['Item added']], $response->getNotices());
    }

    public function testGetNoticesIsEmptyWhenThereAreNone(): void
    {
        // CoCart Starter 5 sends {}, CoCart 4.x sends [].
        $this->assertSame([], $this->recordedCart()->getNotices());
        $this->assertSame([], (new Response(200, [], '{"notices":[]}'))->getNotices());
        $this->assertSame([], (new Response(200, [], '{"items":[]}'))->getNotices());
    }

    public function testGetExtensionsReturnsTheCartExtensionsAndIsEmptyForEmptyOrNone(): void
    {
        $recurring = ['abc' => ['totals' => ['period' => '/ month', 'subtotal' => '1000', 'total' => '1000']]];

        $this->assertSame($recurring, (new Response(200, [], json_encode(['extensions' => ['recurring_totals' => $recurring]])))->getExtensions()['recurring_totals']);
        $this->assertSame([], (new Response(200, [], '{"extensions":[]}'))->getExtensions());
        $this->assertSame([], (new Response(200, [], '{"items":[]}'))->getExtensions());
        $this->assertSame([], $this->recordedCart()->getExtensions());
    }

    // --- getRateLimit() ---

    public function testGetRateLimitReadsTheRateLimitHeaders(): void
    {
        $response = new Response(200, ['RateLimit-Limit' => '25', 'ratelimit-remaining' => '24', 'RateLimit-Reset' => '1791108883'], '{}');

        $this->assertSame(['limit' => 25, 'remaining' => 24, 'reset' => 1791108883, 'retry_after' => null], $response->getRateLimit());
    }

    public function testGetRateLimitIsAllNullWithoutTheHeaders(): void
    {
        $this->assertSame(
            ['limit' => null, 'remaining' => null, 'reset' => null, 'retry_after' => null],
            (new Response(200, [], '{}'))->getRateLimit()
        );
    }

    // --- getTaxes() / hasTaxes() ---

    public function testGetTaxesReturnsArrayAsIs(): void
    {
        $taxes = [
            ['key' => 'US-US-1', 'name' => 'State Tax', 'price' => '5.00'],
        ];
        $response = new Response(200, [], json_encode(['taxes' => $taxes]));

        $this->assertSame($taxes, $response->getTaxes());
    }

    public function testGetTaxesNormalizesLegacyObjectShape(): void
    {
        $body = json_encode([
            'taxes' => [
                'US-US-1' => ['name' => 'State Tax', 'price' => '5.00'],
                'US-US-2' => ['name' => 'City Tax', 'price' => '1.50'],
            ],
        ]);
        $response = new Response(200, [], $body);

        $taxes = $response->getTaxes();

        $this->assertCount(2, $taxes);
        $this->assertSame(['key' => 'US-US-1', 'name' => 'State Tax', 'price' => '5.00'], $taxes[0]);
        $this->assertSame(['key' => 'US-US-2', 'name' => 'City Tax', 'price' => '1.50'], $taxes[1]);
    }

    public function testGetTaxesReturnsEmptyArrayWhenMissing(): void
    {
        $response = new Response(200, [], '{}');

        $this->assertSame([], $response->getTaxes());
    }

    public function testHasTaxesTrueWhenTaxesPresent(): void
    {
        $response = new Response(200, [], json_encode(['taxes' => [['key' => 'a', 'name' => 'Tax', 'price' => '1.00']]]));

        $this->assertTrue($response->hasTaxes());
    }

    public function testHasTaxesFalseWhenEmpty(): void
    {
        $response = new Response(200, [], '{}');

        $this->assertFalse($response->hasTaxes());
    }

    public function testHasTaxesFalseWhenLegacyObjectEmpty(): void
    {
        $response = new Response(200, [], json_encode(['taxes' => new \stdClass()]));

        $this->assertFalse($response->hasTaxes());
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
