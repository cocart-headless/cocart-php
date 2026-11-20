<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\CoCartException;
use CoCart\Http\HttpAdapterInterface;
use CoCart\Http\HttpResponse;
use CoCart\Response;
use PHPUnit\Framework\TestCase;

/**
 * Retries never send a change twice: after a timeout or a 503, a POST may
 * already have run on the store, so only a GET is sent again.
 */
class RetrySafetyTest extends TestCase
{
    /**
     * An adapter that answers each request from a queue: an int is a status
     * code, null a timeout.
     *
     * @param array<int|null> $answers
     */
    private function adapter(array $answers): HttpAdapterInterface
    {
        return new class ($answers) implements HttpAdapterInterface {
            public int $calls = 0;

            /** @param array<int|null> $answers */
            public function __construct(private array $answers)
            {
            }

            public function request(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): HttpResponse
            {
                $this->calls++;
                $answer = array_shift($this->answers);
                if ($answer === null) {
                    throw new CoCartException('Connection timed out after 30000 milliseconds');
                }
                return new HttpResponse($answer, [], $answer >= 400 ? '{"code":"error","message":"Error"}' : '{"order_id":217}');
            }

            public static function isAvailable(): bool
            {
                return true;
            }

            public static function getName(): string
            {
                return 'retry-test';
            }
        };
    }

    private function client(HttpAdapterInterface $adapter): CoCart
    {
        return new class ('https://store.example.com', ['http_adapter' => $adapter, 'auto_storage' => false, 'max_retries' => 2]) extends CoCart {
            protected function retrySleep(int $attempt, ?Response $response = null): void
            {
            }
        };
    }

    public function testGetThatTimesOutIsSentAgain(): void
    {
        $adapter = $this->adapter([null, 200]);
        $this->client($adapter)->get('cart');
        $this->assertSame(2, $adapter->calls);
    }

    public function testPostThatTimesOutIsNotSentAgain(): void
    {
        $adapter = $this->adapter([null, 200]);
        try {
            $this->client($adapter)->post('checkout', ['payment_method' => 'stripe']);
            $this->fail('The timeout should reach the caller.');
        } catch (CoCartException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }
        $this->assertSame(1, $adapter->calls);
    }

    public function testPostAnswered503IsNotSentAgain(): void
    {
        $adapter = $this->adapter([503, 200]);
        try {
            $this->client($adapter)->post('checkout', ['payment_method' => 'stripe']);
            $this->fail('The 503 should reach the caller.');
        } catch (CoCartException) {
        }
        $this->assertSame(1, $adapter->calls);
    }

    public function testPostAnswered429IsSentAgain(): void
    {
        $adapter = $this->adapter([429, 200]);
        $response = $this->client($adapter)->post('checkout', ['payment_method' => 'stripe']);
        $this->assertSame(2, $adapter->calls);
        $this->assertSame(217, $response->get('order_id'));
    }

    public function testGetAnswered503IsSentAgain(): void
    {
        $adapter = $this->adapter([503, 200]);
        $this->client($adapter)->get('cart');
        $this->assertSame(2, $adapter->calls);
    }
}
