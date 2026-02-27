<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use PHPUnit\Framework\TestCase;

class SessionsTest extends TestCase
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

    // --- Routing: plural "sessions" for list, singular "session" for individual ---

    public function testAllUsesSessionsPlural(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->all();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/sessions', $request['url']);
        $this->assertStringNotContainsString('/sessions/', $request['url']);
    }

    public function testFindUsesSessionSingular(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->find('abc123');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/session/abc123', $request['url']);
        $this->assertStringNotContainsString('/sessions/abc123', $request['url']);
    }

    public function testDestroyUsesSessionSingular(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->destroy('abc123');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('DELETE', $request['method']);
        $this->assertStringContainsString('/session/abc123', $request['url']);
        $this->assertStringNotContainsString('/sessions/abc123', $request['url']);
    }

    public function testGetItemsUsesSessionSingular(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->getItems('abc123');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/session/abc123/items', $request['url']);
        $this->assertStringNotContainsString('/sessions/abc123/items', $request['url']);
    }

    public function testByCustomerUsesSessionSingular(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->byCustomer(42);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/session/42', $request['url']);
    }

    public function testDestroyByCustomerUsesSessionSingular(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->sessions()->destroyByCustomer(42);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('DELETE', $request['method']);
        $this->assertStringContainsString('/session/42', $request['url']);
    }
}
