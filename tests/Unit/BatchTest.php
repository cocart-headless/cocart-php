<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class BatchTest extends TestCase
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

    public function testExecuteSendsPostToBatchEndpoint(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/wp-json/cocart/batch', $request['url']);
    }

    public function testRequestsIncludeVersionedPaths(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('/cocart/v2/cart/add-item', $body['requests'][0]['path']);
    }

    public function testCustomNamespaceInPaths(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient(['namespace' => 'mystore']);
        $client->batch()
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('/mystore/v2/cart/add-item', $body['requests'][0]['path']);
        $this->assertStringContainsString('/mystore/batch', $request['url']);
    }

    public function testMultipleRequestsQueued(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->add('cart/add-item', ['id' => '123', 'quantity' => '2'])
            ->add('cart/add-item', ['id' => '456', 'quantity' => '1'])
            ->add('cart/apply-coupon', ['coupon' => 'SAVE10'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertCount(3, $body['requests']);
        $this->assertSame('POST', $body['requests'][0]['method']);
        $this->assertSame(['id' => '123', 'quantity' => '2'], $body['requests'][0]['body']);
    }

    public function testPutAndDeleteMethods(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->update('cart/item/abc123', ['quantity' => '5'])
            ->remove('cart/item/def456')
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('PUT', $body['requests'][0]['method']);
        $this->assertSame(['quantity' => '5'], $body['requests'][0]['body']);
        $this->assertSame('DELETE', $body['requests'][1]['method']);
        $this->assertArrayNotHasKey('body', $body['requests'][1]);
    }

    public function testValidationMode(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->setValidation('require-all-validate')
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('require-all-validate', $body['validation']);
    }

    public function testDefaultValidationIsNormal(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $client->batch()
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->execute();

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);

        $this->assertSame('normal', $body['validation']);
    }

    public function testExecuteThrowsWhenQueueEmpty(): void
    {
        $client = $this->createClient();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No requests queued');

        $client->batch()->execute();
    }

    public function testQueueClearedAfterExecute(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"responses":[]}');

        $client = $this->createClient();
        $batch = $client->batch();
        $batch->add('cart/add-item', ['id' => '123', 'quantity' => '1']);

        $this->assertSame(1, $batch->count());

        $batch->execute();

        $this->assertSame(0, $batch->count());
    }

    public function testClearWithoutExecuting(): void
    {
        $client = $this->createClient();
        $batch = $client->batch();

        $batch->add('cart/add-item', ['id' => '123', 'quantity' => '1']);
        $batch->add('cart/add-item', ['id' => '456', 'quantity' => '2']);
        $this->assertSame(2, $batch->count());

        $result = $batch->clear();
        $this->assertSame($batch, $result); // Fluent
        $this->assertSame(0, $batch->count());
    }

    public function testMaxRequestsLimit(): void
    {
        $client = $this->createClient();
        $batch = $client->batch();

        for ($i = 0; $i < 25; $i++) {
            $batch->add('cart/add-item', ['id' => (string) $i, 'quantity' => '1']);
        }

        $this->assertSame(25, $batch->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Maximum of 25');

        $batch->add('cart/add-item', ['id' => '26', 'quantity' => '1']);
    }

    public function testFluentInterface(): void
    {
        $client = $this->createClient();
        $batch = $client->batch();

        $result = $batch
            ->add('cart/add-item', ['id' => '123', 'quantity' => '1'])
            ->update('cart/item/abc', ['quantity' => '3'])
            ->remove('cart/item/def');

        $this->assertSame($batch, $result);
        $this->assertSame(3, $batch->count());
    }

    public function testBatchEndpointLazyLoading(): void
    {
        $client = $this->createClient();

        $batch1 = $client->batch();
        $batch2 = $client->batch();
        $this->assertSame($batch1, $batch2);
    }
}
