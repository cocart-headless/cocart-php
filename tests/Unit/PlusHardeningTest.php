<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\ValidationException;
use CoCart\Http\HttpAdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Client hardening for CoCart Plus 2.0: the retry wait it asks for, responses
 * the ETag cache mustn't keep, how errors map, and the fields an error carries.
 */
class PlusHardeningTest extends TestCase
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

    /**
     * A client that records how long it would wait before each retry, instead of waiting
     */
    private function recordingClient(HttpAdapterInterface $adapter): CoCart
    {
        return new class ('https://store.example.com', ['http_adapter' => $adapter, 'auto_storage' => false, 'max_retries' => 1]) extends CoCart {
            /** @var int[] */
            public array $waits = [];

            protected function pause(int $seconds): void
            {
                $this->waits[] = $seconds;
            }
        };
    }

    private function caught(callable $call): CoCartException
    {
        try {
            $call();
        } catch (CoCartException $e) {
            return $e;
        }
        $this->fail('Expected a CoCartException');
    }

    // --- Retry delay ---

    public function testWaitsAsLongAsRateLimitRetryAfterSaysBeforeRetryingA429(): void
    {
        $this->mockAdapter->queueResponse(429, ['RateLimit-Retry-After' => '7'], '{"code":"cocart_rate_limited","message":"Slow down"}');
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');
        $client = $this->recordingClient($this->mockAdapter);

        $client->cart()->get();

        $this->assertSame([7], $client->waits);
    }

    public function testStillReadsRetryAfterWhenThatIsAllAProxySends(): void
    {
        $this->mockAdapter->queueResponse(429, ['Retry-After' => '3'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');
        $client = $this->recordingClient($this->mockAdapter);

        $client->cart()->get();

        $this->assertSame([3], $client->waits);
    }

    public function testTheWaitIsCappedAtSixtySeconds(): void
    {
        $this->mockAdapter->queueResponse(429, ['RateLimit-Retry-After' => '3600'], '{}');
        $this->mockAdapter->queueResponse(200, [], '{}');
        $client = $this->recordingClient($this->mockAdapter);

        $client->cart()->get();

        $this->assertSame([60], $client->waits);
    }

    public function testARawRequestIsNeverSentAgain(): void
    {
        $this->mockAdapter->queueResponse(429, ['RateLimit-Retry-After' => '1'], '{"code":"cocart_rate_limited","message":"Slow down"}');
        $client = $this->recordingClient($this->mockAdapter);

        $e = $this->caught(fn () => $client->requestRaw('POST', 'cocart/v2/checkout', [], ['payment_method' => 'cod']));

        $this->assertSame(429, $e->getHttpCode());
        $this->assertCount(1, $this->mockAdapter->getRequests());
        $this->assertSame([], $client->waits);
    }

    // --- ETag cache ---

    public function testDoesNotKeepANoStoreResponseInTheETagCache(): void
    {
        $headers = ['ETag' => '"abc"', 'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0, private'];
        $this->mockAdapter->queueResponse(200, $headers, '{"object":"order"}');
        $this->mockAdapter->queueResponse(200, $headers, '{"object":"order"}');
        $client = $this->createClient();

        $client->get('cart');
        $client->get('cart');

        $this->assertArrayNotHasKey('If-None-Match', $this->mockAdapter->getLastRequest()['headers']);
    }

    public function testDoesNotKeepAPrivateResponseInTheETagCache(): void
    {
        $headers = ['ETag' => '"abc"', 'Cache-Control' => 'private, max-age=0'];
        $this->mockAdapter->queueResponse(200, $headers, '{}');
        $this->mockAdapter->queueResponse(200, $headers, '{}');
        $client = $this->createClient();

        $client->get('cart');
        $client->get('cart');

        $this->assertArrayNotHasKey('If-None-Match', $this->mockAdapter->getLastRequest()['headers']);
    }

    public function testStillUsesTheETagCacheForAResponseThatMayBeStored(): void
    {
        $this->mockAdapter->queueResponse(200, ['ETag' => '"abc"', 'Cache-Control' => 'public, max-age=60'], '{"items":[]}');
        $this->mockAdapter->queueResponse(304, [], '');
        $client = $this->createClient();

        $client->get('products');
        $client->get('products');

        $this->assertSame('"abc"', $this->mockAdapter->getLastRequest()['headers']['If-None-Match']);
    }

    // --- Error mapping ---

    public function testA409IsNeverAValidationErrorEvenWhenItsCodeSaysInvalid(): void
    {
        $this->mockAdapter->queueResponse(409, [], '{"code":"cocart_order_invalid_status","message":"Paid already.","data":{"status":409}}');
        $client = $this->createClient();

        $e = $this->caught(fn () => $client->requestRaw('POST', 'cocart/v2/order-received/12/pay'));

        $this->assertNotInstanceOf(ValidationException::class, $e);
        $this->assertSame(409, $e->getHttpCode());
        $this->assertSame('cocart_order_invalid_status', $e->getErrorCode());
    }

    public function testA429AndA5xxAreNeverValidationErrors(): void
    {
        $this->mockAdapter->queueResponse(429, [], '{"code":"cocart_missing_rate","message":"Slow down"}');
        $this->mockAdapter->queueResponse(500, [], '{"code":"cocart_invalid_state","message":"Oops"}');
        $client = $this->createClient();

        $rateLimited = $this->caught(fn () => $client->requestRaw('GET', 'cocart/v2/checkout'));
        $serverError = $this->caught(fn () => $client->requestRaw('GET', 'cocart/v2/checkout'));

        $this->assertNotInstanceOf(ValidationException::class, $rateLimited);
        $this->assertNotInstanceOf(ValidationException::class, $serverError);
        $this->assertSame(500, $serverError->getHttpCode());
    }

    public function testA400IsStillAValidationErrorAndA401AnAuthenticationError(): void
    {
        $this->mockAdapter->queueResponse(400, [], '{"code":"cocart_field_required","message":"Missing.","data":{"status":400}}');
        $this->mockAdapter->queueResponse(401, [], '{"code":"cocart_rest_authentication_required","message":"Log in.","data":{"status":401}}');
        $client = $this->createClient();

        $this->assertInstanceOf(ValidationException::class, $this->caught(fn () => $client->requestRaw('POST', 'cocart/v2/checkout')));
        $this->assertInstanceOf(AuthenticationException::class, $this->caught(fn () => $client->requestRaw('GET', 'cocart/v2/my-account')));
    }

    // --- Error fields ---

    public function testReadsEveryFieldAtFaultTheFirstOneAndTheReasonFromAnError(): void
    {
        $this->mockAdapter->queueResponse(400, [], json_encode([
            'code'    => 'cocart_invalid_address_fields',
            'message' => 'Check the address.',
            'data'    => [
                'status' => 400,
                'field'  => 'billing.postcode',
                'fields' => ['billing.postcode' => 'Enter a valid postcode.', 'billing.phone' => 'Enter a phone number.'],
                'reason' => 'postcode_format',
            ],
        ]));
        $client = $this->createClient();

        $e = $this->caught(fn () => $client->requestRaw('PUT', 'cocart/v2/checkout'));

        $this->assertSame(['billing.postcode' => 'Enter a valid postcode.', 'billing.phone' => 'Enter a phone number.'], $e->getFields());
        $this->assertSame('billing.postcode', $e->getField());
        $this->assertSame('postcode_format', $e->getReason());
    }

    public function testAnErrorWithoutFieldDetailsGivesAnEmptyMapAndNulls(): void
    {
        $e = new CoCartException('Nope', 404, 'cocart_order_not_found', ['code' => 'cocart_order_not_found', 'data' => ['status' => 404]]);

        $this->assertSame([], $e->getFields());
        $this->assertNull($e->getField());
        $this->assertNull($e->getReason());
        $this->assertSame([], (new CoCartException('Nope'))->getFields());
    }
}
