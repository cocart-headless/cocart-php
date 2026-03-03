<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\VersionException;
use CoCart\Exceptions\CoCartException;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
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

    private function createLegacyClient(array $options = []): CoCart
    {
        return $this->createClient(array_merge(['main_plugin' => 'legacy'], $options));
    }

    // --- Default main plugin ---

    public function testDefaultMainPluginIsBasic(): void
    {
        $client = $this->createClient();
        $this->assertSame('basic', $client->getMainPlugin());
    }

    // --- Fluent setter ---

    public function testSetMainPluginReturnsSelf(): void
    {
        $client = $this->createClient();
        $result = $client->setMainPlugin('legacy');
        $this->assertSame($client, $result);
        $this->assertSame('legacy', $client->getMainPlugin());
    }

    public function testConstructorAcceptsMainPluginOption(): void
    {
        $client = $this->createLegacyClient();
        $this->assertSame('legacy', $client->getMainPlugin());
    }

    // --- VersionException basics ---

    public function testVersionExceptionExtendsCoCartException(): void
    {
        $exception = new VersionException('test()->method');
        $this->assertInstanceOf(CoCartException::class, $exception);
    }

    public function testVersionExceptionMessageContainsMethodName(): void
    {
        $exception = new VersionException('products()->findBySlug');
        $this->assertStringContainsString('products()->findBySlug', $exception->getMessage());
        $this->assertStringContainsString('CoCart Basic', $exception->getMessage());
    }

    // --- Basic-only guards throw on legacy ---

    public function testCartCreateThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->cart()->create();
    }

    public function testProductsFindBySlugThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->findBySlug('test-product');
    }

    public function testProductsVariationThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->variation(1, 2);
    }

    public function testProductsCategoryThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->category(1);
    }

    public function testProductsTagThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->tag(1);
    }

    public function testProductsAttributeBySlugThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->attributeBySlug('color');
    }

    public function testProductsAttributeTermsBySlugThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->attributeTermsBySlug('color');
    }

    public function testProductsAttributeTermBySlugThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->attributeTermBySlug('color', 'blue');
    }

    public function testProductsBrandsThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->brands();
    }

    public function testProductsBrandThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->brand(1);
    }

    public function testProductsByBrandThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->byBrand('nike');
    }

    public function testProductsMyReviewsThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $this->expectException(VersionException::class);
        $client->products()->myReviews();
    }

    public function testBatchExecuteThrowsOnLegacy(): void
    {
        $client = $this->createLegacyClient();
        $client->batch()->get('/products');
        $this->expectException(VersionException::class);
        $client->batch()->execute();
    }

    // --- Methods that work on both versions ---

    public function testCartGetWorksOnLegacy(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');

        $client = $this->createLegacyClient();
        $response = $client->cart()->get();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProductsAllWorksOnLegacy(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createLegacyClient();
        $response = $client->products()->all();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProductsFindWorksOnLegacy(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"id":1}');

        $client = $this->createLegacyClient();
        $response = $client->products()->find(1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProductsCategoriesWorksOnLegacy(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createLegacyClient();
        $response = $client->products()->categories();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProductsTagsWorksOnLegacy(): void
    {
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createLegacyClient();
        $response = $client->products()->tags();

        $this->assertSame(200, $response->getStatusCode());
    }

    // --- Basic-only methods work on core ---

    public function testCartCreateWorksOnBasic(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"cart_key":"abc"}');

        $client = $this->createClient();
        $response = $client->cart()->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProductsFindBySlugWorksOnBasic(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"slug":"test"}');

        $client = $this->createClient();
        $response = $client->products()->findBySlug('test');

        $this->assertSame(200, $response->getStatusCode());
    }

    // --- Field parameter normalization ---

    public function testLegacyVersionConvertsUnderscoreFieldsToFields(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');

        $client = $this->createLegacyClient();
        $client->cart()->get(['_fields' => 'items,totals']);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('fields=', $request['url']);
        $this->assertStringNotContainsString('_fields=', $request['url']);
    }

    public function testBasicVersionConvertsFieldsToUnderscoreFields(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');

        $client = $this->createClient();
        $client->cart()->get(['fields' => 'items,totals']);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('_fields=', $request['url']);
        $this->assertStringNotContainsString('&fields=', $request['url']);
    }
}
