<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart;
use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class CoCartTest extends TestCase
{
    private MockHttpAdapter $mockAdapter;

    protected function setUp(): void
    {
        $this->mockAdapter = new MockHttpAdapter();

        // Ensure session is available for session tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Clean session state before each test
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

    // --- Cart key header tests (the critical bug fix) ---

    public function testCartKeyExtractedFromCartKeyHeader(): void
    {
        $this->mockAdapter->queueResponse(200, ['Cart-Key' => 'guest_abc123'], '{}');

        $client = $this->createClient();
        $client->get('cart');

        $this->assertSame('guest_abc123', $client->getCartKey());
    }

    public function testCartKeySentAsCartKeyHeader(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['cart_key' => 'existing_key']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertArrayHasKey('Cart-Key', $request['headers']);
        $this->assertSame('existing_key', $request['headers']['Cart-Key']);
    }

    public function testCartKeyNotSentWhenAuthenticated(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'cart_key' => 'some_key',
            'username' => 'user',
            'password' => 'pass',
        ]);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertArrayNotHasKey('Cart-Key', $request['headers']);
    }

    public function testCartKeyAlsoSentAsQueryParam(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['cart_key' => 'qp_key']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cart_key=qp_key', $request['url']);
    }

    public function testCartKeyExtractedCaseInsensitive(): void
    {
        $this->mockAdapter->queueResponse(200, ['cart-key' => 'lower_case_key'], '{}');

        $client = $this->createClient();
        $client->get('cart');

        $this->assertSame('lower_case_key', $client->getCartKey());
    }

    // --- URL construction tests ---

    public function testBuildUrlWithEndpoint(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/wp-json/cocart/v2/cart', $request['url']);
    }

    public function testBuildUrlWithQueryParams(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->get('products', ['search' => 'widget', 'per_page' => '10']);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('search=widget', $request['url']);
        $this->assertStringContainsString('per_page=10', $request['url']);
    }

    public function testStoreUrlTrailingSlashNormalized(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = new CoCart('https://store.example.com/', ['http_adapter' => $this->mockAdapter]);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/wp-json/', $request['url']);
        $this->assertStringNotContainsString('example.com//wp-json', $request['url']);
    }

    // --- Authentication header tests ---

    public function testBasicAuthHeader(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['username' => 'admin', 'password' => 'secret']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $expected = 'Basic ' . base64_encode('admin:secret');
        $this->assertSame($expected, $request['headers']['Authorization']);
    }

    public function testJwtAuthHeader(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['jwt_token' => 'my.jwt.token']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('Bearer my.jwt.token', $request['headers']['Authorization']);
    }

    public function testJwtTakesPriorityOverBasicAuth(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'username' => 'user',
            'password' => 'pass',
            'jwt_token' => 'jwt.token',
        ]);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('Bearer ', $request['headers']['Authorization']);
    }

    public function testConsumerKeyAuthHeader(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);
        $client->get('sessions');

        $request = $this->mockAdapter->getLastRequest();
        $expected = 'Basic ' . base64_encode('ck_test:cs_test');
        $this->assertSame($expected, $request['headers']['Authorization']);
    }

    public function testCustomAuthHeaderWithBasicAuth(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'username' => 'admin',
            'password' => 'secret',
            'auth_header' => 'X-Authorization',
        ]);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $expected = 'Basic ' . base64_encode('admin:secret');
        $this->assertSame($expected, $request['headers']['X-Authorization']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
    }

    public function testCustomAuthHeaderWithJwt(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient([
            'jwt_token' => 'my.jwt.token',
            'auth_header' => 'X-Authorization',
        ]);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('Bearer my.jwt.token', $request['headers']['X-Authorization']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
    }

    public function testSetAuthHeaderFluent(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $result = $client->setAuthHeader('X-Auth');
        $this->assertSame($client, $result);

        $client->get('cart');
        $request = $this->mockAdapter->getLastRequest();
        $this->assertArrayHasKey('X-Auth', $request['headers']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
    }

    public function testSetAuthClearsJwt(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['jwt_token' => 'old_token']);
        $client->setAuth('user', 'pass');
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('Basic ', $request['headers']['Authorization']);
    }

    public function testSetJwtClearsBasicAuth(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->setJwtToken('new.token');
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('Bearer new.token', $request['headers']['Authorization']);
    }

    // --- Standard headers ---

    public function testRequestIncludesStandardHeaders(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('application/json', $request['headers']['Accept']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertStringStartsWith('CoCart-PHP-SDK/', $request['headers']['User-Agent']);
    }

    public function testCustomHeaders(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->addHeader('X-Custom', 'value');
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('value', $request['headers']['X-Custom']);
    }

    // --- HTTP methods ---

    public function testGetRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->get('cart');

        $this->assertSame('GET', $this->mockAdapter->getLastRequest()['method']);
    }

    public function testPostRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->post('cart/add-item', ['id' => '123', 'quantity' => '1']);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('"id":"123"', $request['body']);
    }

    public function testPutRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->put('cart/item/abc', ['quantity' => '2']);

        $this->assertSame('PUT', $this->mockAdapter->getLastRequest()['method']);
    }

    public function testDeleteRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $client->delete('cart/item/abc');

        $this->assertSame('DELETE', $this->mockAdapter->getLastRequest()['method']);
    }

    // --- Error handling ---

    public function testThrowsAuthenticationExceptionOn401(): void
    {
        $body = json_encode(['code' => 'rest_forbidden', 'message' => 'Not authorized']);
        $this->mockAdapter->queueResponse(401, [], $body);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Not authorized');

        $client = $this->createClient();
        $client->get('cart');
    }

    public function testThrowsValidationExceptionOn400(): void
    {
        $body = json_encode(['code' => 'invalid_product', 'message' => 'Product not found']);
        $this->mockAdapter->queueResponse(400, [], $body);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Product not found');

        $client = $this->createClient();
        $client->get('cart');
    }

    public function testThrowsCoCartExceptionOn500(): void
    {
        $body = json_encode(['code' => 'server_error', 'message' => 'Internal error']);
        $this->mockAdapter->queueResponse(500, [], $body);

        $this->expectException(CoCartException::class);

        $client = $this->createClient();
        $client->get('cart');
    }

    // --- Fluent interface ---

    public function testFluentCreate(): void
    {
        $client = CoCart::create('https://example.com')
            ->setHttpAdapter($this->mockAdapter)
            ->setTimeout(60)
            ->setVerifySsl(false)
            ->addHeader('X-Test', 'yes');

        $this->assertInstanceOf(CoCart::class, $client);
    }

    // --- Session management ---

    public function testIsAuthenticatedWithBasicAuth(): void
    {
        $client = $this->createClient(['username' => 'u', 'password' => 'p']);
        $this->assertTrue($client->isAuthenticated());
        $this->assertFalse($client->isGuest());
    }

    public function testIsGuestWithNoAuth(): void
    {
        $client = $this->createClient();
        $this->assertFalse($client->isAuthenticated());
        $this->assertTrue($client->isGuest());
    }

    public function testClearSession(): void
    {
        $client = $this->createClient([
            'username' => 'user',
            'password' => 'pass',
            'cart_key' => 'key',
        ]);

        $client->clearSession();

        $this->assertFalse($client->isAuthenticated());
        $this->assertNull($client->getCartKey());
    }

    // --- Lazy endpoint instantiation ---

    public function testEndpointLazyLoading(): void
    {
        $client = $this->createClient();

        $cart1 = $client->cart();
        $cart2 = $client->cart();
        $this->assertSame($cart1, $cart2);

        $products1 = $client->products();
        $products2 = $client->products();
        $this->assertSame($products1, $products2);
    }

    // --- Custom REST prefix and namespace ---

    public function testDefaultRestPrefixAndNamespace(): void
    {
        $client = $this->createClient();
        $this->assertSame('wp-json', $client->getRestPrefix());
        $this->assertSame('cocart', $client->getNamespace());
    }

    public function testCustomRestPrefixViaConstructor(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['rest_prefix' => 'api']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/api/cocart/v2/cart', $request['url']);
        $this->assertStringNotContainsString('wp-json', $request['url']);
    }

    public function testCustomNamespaceViaConstructor(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['namespace' => 'mystore']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/wp-json/mystore/v2/cart', $request['url']);
        $this->assertStringNotContainsString('/cocart/', $request['url']);
    }

    public function testCustomRestPrefixAndNamespaceTogether(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['rest_prefix' => 'api', 'namespace' => 'mystore']);
        $client->get('cart');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/api/mystore/v2/cart', $request['url']);
    }

    public function testSetRestPrefixFluent(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $result = $client->setRestPrefix('api');
        $this->assertSame($client, $result);

        $client->get('cart');
        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/api/', $request['url']);
    }

    public function testSetNamespaceFluent(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient();
        $result = $client->setNamespace('mystore');
        $this->assertSame($client, $result);

        $client->get('cart');
        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('/mystore/', $request['url']);
    }

    public function testCustomRestPrefixInRequestRaw(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['rest_prefix' => 'api']);
        $client->requestRaw('POST', 'cocart/jwt/validate-token');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringStartsWith('https://store.example.com/api/', $request['url']);
        $this->assertStringNotContainsString('wp-json', $request['url']);
    }

    public function testPrefixSlashesAreTrimmed(): void
    {
        $client = $this->createClient(['rest_prefix' => '/api/', 'namespace' => '/mystore/']);
        $this->assertSame('api', $client->getRestPrefix());
        $this->assertSame('mystore', $client->getNamespace());
    }

    // --- Auto-storage (cart key persistence via $_SESSION) ---

    public function testCartKeyRestoredFromSession(): void
    {
        $_SESSION['cocart_cart_key'] = 'restored_key';

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => true,
        ]);

        $this->assertSame('restored_key', $client->getCartKey());
    }

    public function testCartKeyPersistedToSessionOnExtraction(): void
    {
        $this->mockAdapter->queueResponse(200, ['Cart-Key' => 'new_guest_key'], '{}');

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => true,
        ]);
        $client->get('cart');

        $this->assertSame('new_guest_key', $_SESSION['cocart_cart_key']);
    }

    public function testExplicitCartKeyTakesPriorityOverSession(): void
    {
        $_SESSION['cocart_cart_key'] = 'session_key';

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => true,
            'cart_key' => 'explicit_key',
        ]);

        $this->assertSame('explicit_key', $client->getCartKey());
    }

    public function testClearSessionRemovesFromSession(): void
    {
        $this->mockAdapter->queueResponse(200, ['Cart-Key' => 'temp_key'], '{}');

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => true,
        ]);
        $client->get('cart');

        $this->assertSame('temp_key', $_SESSION['cocart_cart_key']);

        $client->clearSession();

        $this->assertArrayNotHasKey('cocart_cart_key', $_SESSION);
        $this->assertNull($client->getCartKey());
    }

    public function testAutoStorageDisabledDoesNotTouchSession(): void
    {
        $this->mockAdapter->queueResponse(200, ['Cart-Key' => 'ignored_key'], '{}');

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => false,
        ]);
        $client->get('cart');

        // Cart key is extracted in memory but not persisted to session
        $this->assertSame('ignored_key', $client->getCartKey());
        $this->assertArrayNotHasKey('cocart_cart_key', $_SESSION ?? []);
    }

    public function testCustomSessionKey(): void
    {
        $_SESSION['my_store_cart'] = 'custom_key';

        $client = new CoCart('https://store.example.com', [
            'http_adapter' => $this->mockAdapter,
            'auto_storage' => true,
            'session_key' => 'my_store_cart',
        ]);

        $this->assertSame('custom_key', $client->getCartKey());
    }

    // --- Logout ---

    public function testLogoutCallsServerEndpoint(): void
    {
        // Queue response for the POST logout call
        $this->mockAdapter->queueResponse(200, [], '{}');

        $client = $this->createClient(['jwt_token' => 'my.jwt.token']);
        $client->logout();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/logout', $request['url']);
    }

    public function testLogoutClearsTokensEvenIfServerFails(): void
    {
        // Queue a server error for the logout call
        $this->mockAdapter->queueResponse(500, [], '{"code":"server_error","message":"fail"}');

        $client = $this->createClient(['jwt_token' => 'my.jwt.token']);
        $client->logout();

        // Tokens should still be cleared locally
        $this->assertFalse($client->isAuthenticated());
    }
}
