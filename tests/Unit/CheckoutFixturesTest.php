<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\CheckoutResult;
use CoCart\Exceptions\CheckoutOutcomeUnknownException;
use CoCart\Exceptions\CoCartException;
use CoCart\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Replays the shared checkout fixtures (tests/fixtures/checkout, copied from
 * cocart-js's packages/checkout/tests/fixtures): the same request/response data
 * every CoCart SDK runs, so all of them are tested the same way.
 */
class CheckoutFixturesTest extends TestCase
{
    private const STORE = 'https://store.example.com/wp-json/';

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    public static function fixtures(): array
    {
        $cases = [];
        foreach (glob(__DIR__ . '/../fixtures/checkout/*.json') as $file) {
            $cases[basename($file, '.json')] = [json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)];
        }
        ksort($cases);
        return $cases;
    }

    public function testRunsEveryFixture(): void
    {
        $this->assertCount(53, self::fixtures());
    }

    #[DataProvider('fixtures')]
    public function testFixture(array $fixture): void
    {
        $adapter = new MockHttpAdapter();
        foreach ($fixture['exchanges'] as $exchange) {
            $adapter->queueResponse(
                $exchange['response']['status'],
                [],
                json_encode($exchange['response']['body'], JSON_THROW_ON_ERROR)
            );
        }

        $client = new CoCart('https://store.example.com', ['http_adapter' => $adapter, 'auto_storage' => false]);

        $outcome = null;
        $error = null;
        try {
            $outcome = $this->call($client, $fixture['call']);
        } catch (CoCartException $e) {
            $error = $e;
        }

        $this->assertEquals(
            array_map(fn ($exchange) => $exchange['request'], $fixture['exchanges']),
            array_map([$this, 'seen'], $adapter->getRequests()),
            'requests'
        );

        $expect = $fixture['expect'];

        if (isset($expect['error'])) {
            $this->assertInstanceOf(CoCartException::class, $error);
            $this->assertSame($expect['error']['code'], $error->getErrorCode());
            // The checkout-outcome-unknown error carries the checkout_id to ask getStatus() about.
            if (isset($expect['error']['checkoutId'])) {
                $this->assertInstanceOf(CheckoutOutcomeUnknownException::class, $error);
                $this->assertSame($expect['error']['checkoutId'], $error->getCheckoutId());
            }
            return;
        }
        if ($error !== null) {
            throw $error;
        }

        if (isset($expect['result'])) {
            $this->assertInstanceOf(CheckoutResult::class, $outcome);
            // null in a fixture is a field the result leaves out.
            foreach ($expect['result'] as $key => $value) {
                $getter = method_exists($outcome, $key) ? $key : 'get' . ucfirst($key);
                if (is_array($value)) {
                    $this->assertEquals($value, $outcome->$getter(), $key);
                } else {
                    $this->assertSame($value, $outcome->$getter(), $key);
                }
            }
        }
        if (array_key_exists('value', $expect)) {
            $value = $expect['value'];
            $this->assertEquals(array_is_list($value) ? array_map([self::class, 'snakeKeys'], $value) : self::snakeKeys($value), $outcome);
        }
        if (array_key_exists('responseBody', $expect)) {
            $response = $outcome instanceof CheckoutResult ? $outcome->getResponse() : $outcome;
            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals($expect['responseBody'], $response->toArray());
        }
    }

    /**
     * Calls the fixture's method with this SDK's names and parameters.
     */
    private function call(CoCart $client, array $call): mixed
    {
        $args = $call['args'];

        if ($call['target'] === 'account') {
            return $client->account()->{$call['method']}(...$args);
        }

        $checkout = $client->checkout();

        return match ($call['method']) {
            'getCheckout' => $checkout->get($args[0] ?? []),
            'updateCheckout' => $checkout->update($args[0]),
            'getCheckoutConfig' => $checkout->getConfig(),
            'getCheckoutStatus' => $checkout->getStatus($args[0]),
            'searchAddresses' => $checkout->searchAddresses(
                $args[0]['query'],
                $args[0]['country'] ?? null,
                $args[0]['type'] ?? null,
                $args[0]['provider'] ?? null
            ),
            'getAddressDetails' => $checkout->getAddressDetails($args[0]['address_id'], $args[0]['provider']),
            'confirmReturn' => $checkout->confirmReturn($args[0], $args[1]['orderId'] ?? null, $args[1]['orderKey'] ?? null),
            default => $checkout->{$call['method']}(...$args),
        };
    }

    /**
     * A recorded request in the fixture's shape.
     */
    private function seen(array $request): array
    {
        $this->assertStringStartsWith(self::STORE, $request['url']);
        $parts = explode('?', substr($request['url'], strlen(self::STORE)), 2);
        parse_str($parts[1] ?? '', $query);

        return [
            'method' => $request['method'],
            'path' => $parts[0],
            'query' => $query,
            'body' => $request['body'] === null ? null : json_decode($request['body'], true),
        ];
    }

    /**
     * A value with this SDK's keys (gatewayId → gateway_id, checkoutId → checkout_id).
     */
    private static function snakeKeys(array $card): array
    {
        $snake = [];
        foreach ($card as $key => $value) {
            $snake[strtolower((string) preg_replace('/[A-Z]/', '_$0', $key))] = $value;
        }
        return $snake;
    }
}
