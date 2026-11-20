<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\CheckoutOutcomeUnknownException;
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\ValidationException;
use CoCart\Http\HttpAdapterInterface;
use CoCart\Http\HttpResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * placeOrder() sends a checkout_id, and when no answer comes back (or another
 * payment is still running) it says so with that ID, for getStatus() to tell
 * how the checkout ended.
 */
class CheckoutOutcomeTest extends TestCase
{
    private const ORDER = ['payment_method' => 'cod', 'billing_address' => ['email' => 'jane@example.com']];

    /**
     * An adapter that answers each request from a queue: an int is a status
     * code, null a timeout. It keeps the bodies it was sent.
     *
     * @param array<int|null> $answers
     */
    private function adapter(array $answers): HttpAdapterInterface
    {
        return new class ($answers) implements HttpAdapterInterface {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            /** @param array<int|null> $answers */
            public function __construct(private array $answers)
            {
            }

            public function request(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): HttpResponse
            {
                $this->sent[] = ['url' => $url, 'body' => $body === null ? null : json_decode($body, true)];
                $answer = array_shift($this->answers);
                if ($answer === null) {
                    throw new CoCartException('Connection timed out after 30000 milliseconds');
                }
                if ($answer === 504) {
                    return new HttpResponse(504, [], '<html>Gateway Time-out</html>');
                }
                if ($answer === 409) {
                    return new HttpResponse(409, [], '{"code":"cocart_payment_in_progress","message":"This order is already being placed.","data":{"status":409}}');
                }
                if ($answer >= 400) {
                    return new HttpResponse($answer, [], '{"code":"cocart_cant_checkout_empty_cart","message":"Cannot checkout with empty cart.","data":{"status":400}}');
                }
                return new HttpResponse($answer, [], '{"object":"order","result":{"code":"order_placed","message":"Thank you.","action":null,"redirect_url":null,"error":null},"id":217}');
            }

            public static function isAvailable(): bool
            {
                return true;
            }

            public static function getName(): string
            {
                return 'checkout-outcome-test';
            }
        };
    }

    private function client(HttpAdapterInterface $adapter): CoCart
    {
        return new CoCart('https://store.example.com', ['http_adapter' => $adapter, 'auto_storage' => false]);
    }

    public function testEachOrderIsSentWithANewRandomCheckoutId(): void
    {
        $adapter = $this->adapter([200, 200]);
        $checkout = $this->client($adapter)->checkout();

        $checkout->placeOrder(self::ORDER);
        $checkout->placeOrder(self::ORDER);

        $first = $adapter->sent[0]['body']['checkout_id'];
        $second = $adapter->sent[1]['body']['checkout_id'];
        $this->assertMatchesRegularExpression('/^chk_[0-9a-f]{32}$/', $first);
        $this->assertMatchesRegularExpression('/^chk_[0-9a-f]{32}$/', $second);
        $this->assertNotSame($first, $second);
    }

    public function testTheCheckoutIdGivenIsSent(): void
    {
        $adapter = $this->adapter([200]);

        $this->client($adapter)->checkout()->placeOrder(self::ORDER + ['checkout_id' => 'chk_mine_00000000001']);

        $this->assertSame('chk_mine_00000000001', $adapter->sent[0]['body']['checkout_id']);
    }

    public function testATimeoutSaysTheOutcomeIsUnknownWithTheCheckoutId(): void
    {
        $adapter = $this->adapter([null]);

        try {
            $this->client($adapter)->checkout()->placeOrder(self::ORDER);
            $this->fail('The timeout should reach the caller.');
        } catch (CheckoutOutcomeUnknownException $e) {
            $this->assertSame($adapter->sent[0]['body']['checkout_id'], $e->getCheckoutId());
            $this->assertStringContainsString('timed out', $e->getMessage());
            $this->assertSame(0, $e->getHttpCode());
        }
        $this->assertCount(1, $adapter->sent, 'The order is not sent again.');
    }

    public function testAProxyThatGaveUpSaysTheOutcomeIsUnknown(): void
    {
        $adapter = $this->adapter([504]);

        try {
            $this->client($adapter)->checkout()->placeOrder(self::ORDER);
            $this->fail('The 504 should reach the caller.');
        } catch (CheckoutOutcomeUnknownException $e) {
            $this->assertSame(504, $e->getHttpCode());
            $this->assertSame($adapter->sent[0]['body']['checkout_id'], $e->getCheckoutId());
        }
    }

    public function testAPaymentStillInProgressSaysTheOutcomeIsUnknown(): void
    {
        $adapter = $this->adapter([409]);

        try {
            $this->client($adapter)->checkout()->placeOrder(self::ORDER);
            $this->fail('The 409 should reach the caller.');
        } catch (CheckoutOutcomeUnknownException $e) {
            $this->assertSame(409, $e->getHttpCode());
            $this->assertSame('cocart_payment_in_progress', $e->getErrorCode());
            $this->assertSame($adapter->sent[0]['body']['checkout_id'], $e->getCheckoutId());
        }
        $this->assertCount(1, $adapter->sent, 'The order is not sent again.');
    }

    public function testAPaymentInProgressOnPayForOrderIsAnOrdinaryError(): void
    {
        $adapter = $this->adapter([409]);

        try {
            $this->client($adapter)->checkout()->payForOrder(217, 'wc_order_abc', ['payment_method' => 'cod']);
            $this->fail('The 409 should reach the caller.');
        } catch (CoCartException $e) {
            $this->assertNotInstanceOf(CheckoutOutcomeUnknownException::class, $e);
            $this->assertNotInstanceOf(ValidationException::class, $e);
            $this->assertSame('cocart_payment_in_progress', $e->getErrorCode());
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedCheckoutIds(): array
    {
        return [
            'too short' => ['chk_short'],
            'too long' => [str_repeat('a', 65)],
            'a space' => ['chk_has a space_0001'],
            'a slash' => ['chk_has/a/slash_0001'],
            'not a string' => [1234567890123456789],
        ];
    }

    #[DataProvider('malformedCheckoutIds')]
    public function testAMalformedCheckoutIdIsRefusedBeforeSending(mixed $checkoutId): void
    {
        $adapter = $this->adapter([200]);

        try {
            $this->client($adapter)->checkout()->placeOrder(self::ORDER + ['checkout_id' => $checkoutId]);
            $this->fail('The checkout_id should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame('checkout_invalid_checkout_id', $e->getErrorCode());
            $this->assertSame(0, $e->getHttpCode());
        }
        $this->assertCount(0, $adapter->sent, 'Nothing is sent.');
    }

    public function testCheckoutIdsAtTheLimitsAreSent(): void
    {
        $adapter = $this->adapter([200, 200]);
        $checkout = $this->client($adapter)->checkout();

        $checkout->placeOrder(self::ORDER + ['checkout_id' => str_repeat('a', 16)]);
        $checkout->placeOrder(self::ORDER + ['checkout_id' => str_repeat('Z-_9', 16)]);

        $this->assertCount(2, $adapter->sent);
    }

    public function testAnErrorTheStoreAnsweredWithIsKept(): void
    {
        $adapter = $this->adapter([400]);

        try {
            $this->client($adapter)->checkout()->placeOrder(self::ORDER);
            $this->fail('The error should reach the caller.');
        } catch (CoCartException $e) {
            $this->assertNotInstanceOf(CheckoutOutcomeUnknownException::class, $e);
            $this->assertSame('cocart_cant_checkout_empty_cart', $e->getErrorCode());
        }
    }
}
