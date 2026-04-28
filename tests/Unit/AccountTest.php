<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\CoCartException;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
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

    // --- getProfile ---

    public function testGetProfileSendsGetRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"user":{"id":1}}');

        $client = $this->createClient();
        $client->account()->getProfile();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('cocart/v2/my-account', $request['url']);
    }

    // --- updateProfile ---

    public function testUpdateProfileSendsPostRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"user":{"email":"new@example.com"}}');

        $client = $this->createClient();
        $client->account()->updateProfile(['account_email' => 'new@example.com']);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('cocart/v2/my-account', $request['url']);
        $this->assertStringContainsString('new@example.com', $request['body']);
    }

    // --- changePassword ---

    public function testChangePasswordRemapsFieldNames(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->account()->changePassword('oldpass', 'newpass', 'newpass');

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('oldpass', $body['password_current']);
        $this->assertSame('newpass', $body['password_1']);
        $this->assertSame('newpass', $body['password_2']);
        $this->assertStringContainsString('change-password', $request['url']);
    }

    // --- getOrders ---

    public function testGetOrdersSendsGetToOrdersRoute(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"orders":[]}');

        $client = $this->createClient();
        $client->account()->getOrders(['per_page' => 5]);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('cocart/v2/my-account/orders', $request['url']);
        $this->assertStringContainsString('per_page=5', $request['url']);
    }

    // --- getOrder ---

    public function testGetOrderSendsCorrectPath(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"order_id":42}');

        $client = $this->createClient();
        $client->account()->getOrder(42);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/orders/42', $request['url']);
    }

    // --- getGuestOrder ---

    public function testGetGuestOrderSendsEmailParam(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"order_id":7}');

        $client = $this->createClient();
        $client->account()->getGuestOrder(7, 'guest@example.com');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/orders/7', $request['url']);
        $this->assertStringContainsString('email=guest%40example.com', urldecode($request['url']));
    }

    // --- getOrderDownloads ---

    public function testGetOrderDownloadsSendsCorrectPath(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient();
        $client->account()->getOrderDownloads(3);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/orders/3/downloads', $request['url']);
    }

    // --- getGuestOrderDownloads ---

    public function testGetGuestOrderDownloadsSendsEmailParam(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient();
        $client->account()->getGuestOrderDownloads(3, 'g@x.com');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/orders/3/downloads', $request['url']);
        $this->assertStringContainsString('email=', $request['url']);
    }

    // --- getDownloads ---

    public function testGetDownloadsSendsCorrectPath(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient();
        $client->account()->getDownloads();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/downloads', $request['url']);
    }

    // --- getReviews ---

    public function testGetReviewsSendsCorrectPath(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient();
        $client->account()->getReviews();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/reviews', $request['url']);
    }

    // --- rest_no_route handling ---

    public function testRestNoRouteBecomesPluginRequired(): void
    {
        $this->mockAdapter->queueResponse(404, [], json_encode([
            'code'    => 'rest_no_route',
            'message' => 'No route was found.',
            'data'    => ['status' => 404],
        ]));

        $this->expectException(CoCartException::class);
        $this->expectExceptionCode(404);

        $client = $this->createClient();
        $client->account()->getProfile();
    }
}
