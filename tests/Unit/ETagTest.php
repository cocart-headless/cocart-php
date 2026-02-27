<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use PHPUnit\Framework\TestCase;

class ETagTest extends TestCase
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

    // --- Response helpers ---

    public function testGetETagReturnsHeaderValue(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"abc123"'], '{}');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertSame('W/"abc123"', $response->getETag());
    }

    public function testGetETagReturnsNullWhenMissing(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertNull($response->getETag());
    }

    public function testIsNotModifiedReturnsTrueFor304(): void
    {
        $this->mockAdapter->queueResponse(304, ['ETag' => 'W/"abc123"'], '');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertTrue($response->isNotModified());
    }

    public function testIsNotModifiedReturnsFalseFor200(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertFalse($response->isNotModified());
    }

    public function testGetCacheStatusReadsHeader(): void
    {
        $this->mockAdapter->queueResponse(200, ['CoCart-Cache' => 'MISS'], '{}');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertSame('MISS', $response->getCacheStatus());
    }

    public function testGetCacheStatusHit(): void
    {
        $this->mockAdapter->queueResponse(304, ['CoCart-Cache' => 'HIT', 'ETag' => 'W/"abc"'], '');

        $client = $this->createClient();
        $response = $client->get('cart');

        $this->assertSame('HIT', $response->getCacheStatus());
    }

    // --- ETag caching and If-None-Match ---

    public function testETagStoredAndSentOnSubsequentGet(): void
    {
        // First request: server returns ETag
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"cart_hash_1"'], '{"items":[]}');
        // Second request: server returns 304
        $this->mockAdapter->queueResponse(304, ['ETag' => 'W/"cart_hash_1"', 'CoCart-Cache' => 'HIT'], '');

        $client = $this->createClient();

        // First request — no If-None-Match
        $client->get('cart');
        $firstRequest = $this->mockAdapter->getRequests()[0];
        $this->assertArrayNotHasKey('If-None-Match', $firstRequest['headers']);

        // Second request — should include If-None-Match
        $response = $client->get('cart');
        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertSame('W/"cart_hash_1"', $secondRequest['headers']['If-None-Match']);
        $this->assertTrue($response->isNotModified());
    }

    public function testETagNotSentWhenDisabled(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['etag' => false]);

        $client->get('cart');
        $client->get('cart');

        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertArrayNotHasKey('If-None-Match', $secondRequest['headers']);
    }

    public function testETagEnabledByDefault(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash2"'], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->get('cart');

        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertSame('W/"hash1"', $secondRequest['headers']['If-None-Match']);
    }

    public function testETagOnlySentForGetRequests(): void
    {
        // GET returns ETag
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        // POST should NOT include If-None-Match
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->post('cart/add-item', ['id' => '123', 'quantity' => '1']);

        $postRequest = $this->mockAdapter->getRequests()[1];
        $this->assertArrayNotHasKey('If-None-Match', $postRequest['headers']);
    }

    public function testETagUpdatedOnNewResponse(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash2"'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->get('cart'); // sends hash1, gets hash2
        $client->get('cart'); // should send hash2

        $thirdRequest = $this->mockAdapter->getRequests()[2];
        $this->assertSame('W/"hash2"', $thirdRequest['headers']['If-None-Match']);
    }

    public function testClearETagCache(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->clearETagCache();
        $client->get('cart');

        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertArrayNotHasKey('If-None-Match', $secondRequest['headers']);
    }

    public function testClearETagCacheIsFluent(): void
    {
        $client = $this->createClient();
        $result = $client->clearETagCache();
        $this->assertSame($client, $result);
    }

    public function testSetETagIsFluent(): void
    {
        $client = $this->createClient();
        $result = $client->setETag(false);
        $this->assertSame($client, $result);
    }

    public function test304DoesNotThrowException(): void
    {
        $this->mockAdapter->queueResponse(304, ['ETag' => 'W/"hash"'], '');

        $client = $this->createClient();
        $response = $client->get('cart');

        // 304 is not an error — should not throw
        $this->assertSame(304, $response->getStatusCode());
        $this->assertTrue($response->isNotModified());
        $this->assertFalse($response->isError());
    }

    public function testSkipCacheParamBypassesETag(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, ['CoCart-Cache' => 'SKIP'], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->get('cart', ['_skip_cache' => true]);

        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertArrayNotHasKey('If-None-Match', $secondRequest['headers']);
    }

    public function testDifferentUrlsHaveSeparateETags(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"cart_hash"'], '{}');
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"products_hash"'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->get('products');
        $client->get('cart');
        $client->get('products');

        $thirdRequest = $this->mockAdapter->getRequests()[2];
        $fourthRequest = $this->mockAdapter->getRequests()[3];
        $this->assertSame('W/"cart_hash"', $thirdRequest['headers']['If-None-Match']);
        $this->assertSame('W/"products_hash"', $fourthRequest['headers']['If-None-Match']);
    }

    public function testSetETagDisablesAfterCreation(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => 'W/"hash1"'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();

        $client->get('cart');
        $client->setETag(false);
        $client->get('cart');

        $secondRequest = $this->mockAdapter->getRequests()[1];
        $this->assertArrayNotHasKey('If-None-Match', $secondRequest['headers']);
    }
}
