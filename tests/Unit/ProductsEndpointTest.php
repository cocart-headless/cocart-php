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
}
