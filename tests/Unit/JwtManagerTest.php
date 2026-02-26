<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\CoCart;
use CoCart\JwtManager;
use CoCart\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

class JwtManagerTest extends TestCase
{
    private MockHttpAdapter $mockAdapter;

    protected function setUp(): void
    {
        $this->mockAdapter = new MockHttpAdapter();
    }

    private function createClient(array $options = []): CoCart
    {
        return new CoCart('https://store.example.com', array_merge(
            ['http_adapter' => $this->mockAdapter],
            $options
        ));
    }

    private function loginResponseBody(): string
    {
        return json_encode([
            'user_id' => '123',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'display_name' => 'john',
            'role' => 'Customer',
            'email' => 'john@example.com',
            'extras' => [
                'jwt_token' => 'eyJ.test.token',
                'jwt_refresh' => 'refresh_token_hash_abc123',
            ],
        ]);
    }

    private function refreshResponseBody(string $token = 'eyJ.new.token', string $refresh = 'new_refresh_hash'): string
    {
        return json_encode([
            'token' => $token,
            'refresh_token' => $refresh,
        ]);
    }

    // --- Login tests ---

    public function testLoginExtractsTokens(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->loginResponseBody());

        $client = $this->createClient();
        $jwt = new JwtManager($client);

        $response = $jwt->login('john@example.com', 'password123');

        $this->assertSame('eyJ.test.token', $client->getJwtToken());
        $this->assertSame('refresh_token_hash_abc123', $client->getRefreshToken());
        $this->assertTrue($response->isSuccessful());
    }

    public function testLoginReturnsUserProfile(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->loginResponseBody());

        $client = $this->createClient();
        $jwt = new JwtManager($client);

        $response = $jwt->login('john@example.com', 'pass');

        $this->assertSame('123', $response->get('user_id'));
        $this->assertSame('John', $response->get('first_name'));
    }

    public function testLoginThrowsWhenNoJwtInResponse(): void
    {
        $body = json_encode(['user_id' => '123', 'extras' => []]);
        $this->mockAdapter->queueResponse(200, [], $body);

        $client = $this->createClient();
        $jwt = new JwtManager($client);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('JWT token');

        $jwt->login('user', 'pass');
    }

    public function testLoginSendsCorrectRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->loginResponseBody());

        $client = $this->createClient();
        $jwt = new JwtManager($client);
        $jwt->login('myuser', 'mypass');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/wp-json/cocart/v2/login', $request['url']);

        $body = json_decode($request['body'], true);
        $this->assertSame('myuser', $body['username']);
        $this->assertSame('mypass', $body['password']);
    }

    // --- Refresh tests ---

    public function testRefreshUpdatesTokens(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());

        $client = $this->createClient([
            'jwt_token' => 'old.jwt.token',
            'jwt_refresh_token' => 'old_refresh',
        ]);
        $jwt = new JwtManager($client);

        $jwt->refresh();

        $this->assertSame('eyJ.new.token', $client->getJwtToken());
        $this->assertSame('new_refresh_hash', $client->getRefreshToken());
    }

    public function testRefreshThrowsWithoutRefreshToken(): void
    {
        $client = $this->createClient(['jwt_token' => 'some.jwt']);
        $jwt = new JwtManager($client);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No refresh token');

        $jwt->refresh();
    }

    public function testRefreshUsesCorrectEndpoint(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());

        $client = $this->createClient([
            'jwt_token' => 'token',
            'jwt_refresh_token' => 'refresh_abc',
        ]);
        $jwt = new JwtManager($client);
        $jwt->refresh();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        // Must use {namespace}/jwt/ route, NOT {namespace}/v2/
        $this->assertStringContainsString('/cocart/jwt/refresh-token', $request['url']);
        $this->assertStringNotContainsString('/v2/', $request['url']);

        $body = json_decode($request['body'], true);
        $this->assertSame('refresh_abc', $body['refresh_token']);
    }

    public function testRefreshAcceptsExplicitToken(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());

        $client = $this->createClient(['jwt_token' => 'token']);
        $jwt = new JwtManager($client);

        // No refresh token on client, but passing one explicitly
        $jwt->refresh('explicit_refresh_token');

        $request = $this->mockAdapter->getLastRequest();
        $body = json_decode($request['body'], true);
        $this->assertSame('explicit_refresh_token', $body['refresh_token']);
    }

    // --- Validate tests ---

    public function testValidateReturnsTrueOnSuccess(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"message":"Token is valid."}');

        $client = $this->createClient(['jwt_token' => 'valid.jwt.token']);
        $jwt = new JwtManager($client);

        $this->assertTrue($jwt->validate());
    }

    public function testValidateReturnsFalseOnAuthError(): void
    {
        $body = json_encode(['code' => 'cocart_authentication_error', 'message' => 'Authentication failed.']);
        $this->mockAdapter->queueResponse(403, [], $body);

        $client = $this->createClient(['jwt_token' => 'expired.jwt']);
        $jwt = new JwtManager($client);

        $this->assertFalse($jwt->validate());
    }

    public function testValidateReturnsFalseWhenNoToken(): void
    {
        $client = $this->createClient();
        $jwt = new JwtManager($client);

        // Should return false without making any request
        $this->assertFalse($jwt->validate());
        $this->assertEmpty($this->mockAdapter->getRequests());
    }

    public function testValidateUsesCorrectEndpoint(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"message":"Token is valid."}');

        $client = $this->createClient(['jwt_token' => 'token']);
        $jwt = new JwtManager($client);
        $jwt->validate();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/wp-json/cocart/jwt/validate-token', $request['url']);
    }

    // --- withAutoRefresh tests ---

    public function testWithAutoRefreshRetriesOnce(): void
    {
        // First call: auth error, second call (after refresh): refresh response, third call (retry): success
        $authError = json_encode(['code' => 'cocart_authentication_error', 'message' => 'Auth failed.']);
        $this->mockAdapter->queueResponse(403, [], $authError);
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');

        $client = $this->createClient([
            'jwt_token' => 'expired.token',
            'jwt_refresh_token' => 'valid_refresh',
        ]);
        $jwt = new JwtManager($client);

        $response = $jwt->withAutoRefresh(function (CoCart $client) {
            return $client->cart()->get();
        });

        $this->assertTrue($response->isSuccessful());
        $this->assertCount(3, $this->mockAdapter->getRequests());
    }

    public function testWithAutoRefreshThrowsWithoutRefreshToken(): void
    {
        $authError = json_encode(['code' => 'cocart_authentication_error', 'message' => 'Auth failed.']);
        $this->mockAdapter->queueResponse(403, [], $authError);

        $client = $this->createClient(['jwt_token' => 'expired.token']);
        $jwt = new JwtManager($client);

        $this->expectException(AuthenticationException::class);

        $jwt->withAutoRefresh(function (CoCart $client) {
            return $client->cart()->get();
        });
    }

    // --- Storage tests ---

    public function testTokensPersistedToStorage(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->loginResponseBody());

        $storage = new InMemoryStorage();
        $client = $this->createClient();
        $jwt = new JwtManager($client, $storage);

        $jwt->login('user', 'pass');

        $this->assertSame('eyJ.test.token', $storage->get('cocart_jwt_token'));
        $this->assertSame('refresh_token_hash_abc123', $storage->get('cocart_jwt_refresh_token'));
    }

    public function testTokensRestoredFromStorage(): void
    {
        $storage = new InMemoryStorage();
        $storage->set('cocart_jwt_token', 'stored.jwt.token');
        $storage->set('cocart_jwt_refresh_token', 'stored_refresh');

        $client = $this->createClient();
        $jwt = new JwtManager($client, $storage);

        $this->assertSame('stored.jwt.token', $client->getJwtToken());
        $this->assertSame('stored_refresh', $client->getRefreshToken());
    }

    public function testClearTokensRemovesFromStorage(): void
    {
        $storage = new InMemoryStorage();
        $storage->set('cocart_jwt_token', 'token');
        $storage->set('cocart_jwt_refresh_token', 'refresh');

        $client = $this->createClient();
        $jwt = new JwtManager($client, $storage);
        $jwt->clearTokens();

        $this->assertNull($storage->get('cocart_jwt_token'));
        $this->assertNull($storage->get('cocart_jwt_refresh_token'));
        $this->assertNull($client->getJwtToken());
        $this->assertNull($client->getRefreshToken());
    }

    public function testRefreshPersistsNewTokensToStorage(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody('new.jwt', 'new_refresh'));

        $storage = new InMemoryStorage();
        $storage->set('cocart_jwt_token', 'old.jwt');
        $storage->set('cocart_jwt_refresh_token', 'old_refresh');

        $client = $this->createClient();
        $jwt = new JwtManager($client, $storage);
        $jwt->refresh();

        $this->assertSame('new.jwt', $storage->get('cocart_jwt_token'));
        $this->assertSame('new_refresh', $storage->get('cocart_jwt_refresh_token'));
    }

    // --- Auto-refresh in request() tests ---

    public function testAutoRefreshInRequest(): void
    {
        // First request: auth error, refresh call: success, retry: success
        $authError = json_encode(['code' => 'cocart_authentication_error', 'message' => 'Expired.']);
        $this->mockAdapter->queueResponse(403, [], $authError);
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());
        $this->mockAdapter->queueResponse(200, [], '{"items":[]}');

        $client = $this->createClient([
            'jwt_token' => 'expired.token',
            'jwt_refresh_token' => 'valid_refresh',
        ]);
        $jwt = new JwtManager($client, null, ['auto_refresh' => true]);
        $client->setJwtManager($jwt);

        $response = $client->get('cart');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('eyJ.new.token', $client->getJwtToken());
    }

    public function testAutoRefreshDisabledByDefault(): void
    {
        $authError = json_encode(['code' => 'cocart_authentication_error', 'message' => 'Expired.']);
        $this->mockAdapter->queueResponse(403, [], $authError);

        $client = $this->createClient([
            'jwt_token' => 'expired.token',
            'jwt_refresh_token' => 'valid_refresh',
        ]);
        $jwt = new JwtManager($client);
        $client->setJwtManager($jwt);

        // auto_refresh is false by default, so should throw without retry
        $this->expectException(AuthenticationException::class);
        $client->get('cart');
    }

    // --- Utility method tests ---

    public function testHasTokens(): void
    {
        $client = $this->createClient();
        $jwt = new JwtManager($client);

        $this->assertFalse($jwt->hasTokens());

        $client->setJwtToken('some.token');
        $this->assertTrue($jwt->hasTokens());
    }

    public function testSetAutoRefresh(): void
    {
        $client = $this->createClient();
        $jwt = new JwtManager($client);

        $this->assertFalse($jwt->isAutoRefreshEnabled());

        $jwt->setAutoRefresh(true);
        $this->assertTrue($jwt->isAutoRefreshEnabled());
    }

    // --- Custom namespace tests ---

    public function testRefreshUsesCustomNamespace(): void
    {
        $this->mockAdapter->queueResponse(200, [], $this->refreshResponseBody());

        $client = $this->createClient([
            'jwt_token' => 'token',
            'jwt_refresh_token' => 'refresh_abc',
            'namespace' => 'mystore',
        ]);
        $jwt = new JwtManager($client);
        $jwt->refresh();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/mystore/jwt/refresh-token', $request['url']);
        $this->assertStringNotContainsString('/cocart/', $request['url']);
    }

    public function testValidateUsesCustomNamespace(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"message":"Token is valid."}');

        $client = $this->createClient([
            'jwt_token' => 'valid.jwt.token',
            'namespace' => 'mystore',
        ]);
        $jwt = new JwtManager($client);
        $jwt->validate();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/mystore/jwt/validate-token', $request['url']);
        $this->assertStringNotContainsString('/cocart/', $request['url']);
    }
}

/**
 * Simple in-memory storage for testing
 */
class InMemoryStorage implements \CoCart\SessionStorageInterface
{
    /** @var array<string, string> */
    private array $data = [];

    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->data[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }
}
