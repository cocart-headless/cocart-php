<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use PHPUnit\Framework\TestCase;

class ProductsSeoTest extends TestCase
{
    private MockHttpAdapter $mockAdapter;

    protected function setUp(): void
    {
        $this->mockAdapter = new MockHttpAdapter();
    }

    private function createClient(array $options = []): CoCart
    {
        return new CoCart('https://store.example.com', array_merge(
            ['http_adapter' => $this->mockAdapter, 'auto_storage' => false],
            $options
        ));
    }

    public function testSeoByIdBuildsCorrectUrl(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"provider":"yoast","meta_data":{},"schema":{}}');

        $client = $this->createClient();
        $client->products()->seo(123);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/cocart/v2/products/123/seo', $request['url']);
    }

    public function testSeoBySlugBuildsCorrectUrl(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"provider":"yoast","meta_data":{},"schema":{}}');

        $client = $this->createClient();
        $client->products()->seoBySlug('premium-t-shirt');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringContainsString('/cocart/v2/products/premium-t-shirt/seo', $request['url']);
    }

    public function testSeoResponseDataAccessible(): void
    {
        $body = json_encode([
            'provider' => 'yoast',
            'meta_data' => [
                'meta_title' => 'Buy Premium T-Shirt',
                'meta_description' => 'Shop our premium t-shirt.',
                'opengraph' => [
                    'title' => 'Premium T-Shirt',
                    'image' => 'https://example.com/image.jpg',
                ],
                'robots' => [
                    'index' => true,
                    'follow' => true,
                ],
            ],
            'schema' => [
                '@type' => 'Product',
                'name' => 'Premium T-Shirt',
            ],
        ]);
        $this->mockAdapter->queueResponse(200, [], $body);

        $client = $this->createClient();
        $response = $client->products()->seo(123);

        $this->assertSame('yoast', $response->get('provider'));
        $this->assertSame('Buy Premium T-Shirt', $response->get('meta_data.meta_title'));
        $this->assertSame('https://example.com/image.jpg', $response->get('meta_data.opengraph.image'));
        $this->assertTrue($response->get('meta_data.robots.index'));
        $this->assertSame('Product', $response->get('schema.@type'));
    }

    public function testSeoWithCustomNamespace(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"provider":"default","meta_data":{},"schema":{}}');

        $client = $this->createClient(['namespace' => 'mystore']);
        $client->products()->seo(456);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/mystore/v2/products/456/seo', $request['url']);
    }
}
