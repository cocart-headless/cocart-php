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

    public function testChangePasswordPostsOnlyThePasswordFieldsToMyAccountInOneRequest(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"object":"customer","result":{"code":"account_updated","message":"Account details changed successfully."}}');

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $response = $client->account()->changePassword('old-password', 'new-password', 'new-password');

        $this->assertCount(1, $this->mockAdapter->getRequests());
        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/my-account', $request['url']);
        $this->assertSame([
            'password_current' => 'old-password',
            'password_1'       => 'new-password',
            'password_2'       => 'new-password',
        ], json_decode($request['body'], true));
        $this->assertSame('account_updated', $response->get('result.code'));
    }

    // --- updateAddress ---

    public function testUpdateAddressPostsToMyAccountAddress(): void
    {
        $data = ['billing_address' => ['first_name' => 'Jane', 'city' => 'Paris']];

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->updateAddress($data);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/my-account/address', $request['url']);
        $this->assertSame($data, json_decode($request['body'], true));
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

    public function testGetOrdersSendsNoQueryWithoutParams(): void
    {
        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->getOrders();

        $this->assertSame(
            'https://store.example.com/wp-json/cocart/v2/my-account/orders',
            $this->mockAdapter->getLastRequest()['url']
        );
    }

    public function testGetOrdersSendsTheFiltersJoiningAListOfStatusesAndMetaKeysWithCommas(): void
    {
        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->getOrders([
            'status'                  => ['processing', 'on-hold'],
            'after'                   => '2026-01-01T00:00:00',
            'before'                  => '2026-12-31T23:59:59',
            'subscription_order_type' => 'renewal',
            'formatted'               => true,
            'meta_keys'               => ['gift_message', '_configuration'],
        ]);

        $url = $this->mockAdapter->getLastRequest()['url'];
        $this->assertStringStartsWith('https://store.example.com/wp-json/cocart/v2/my-account/orders?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame([
            'status'                  => 'processing,on-hold',
            'after'                   => '2026-01-01T00:00:00',
            'before'                  => '2026-12-31T23:59:59',
            'subscription_order_type' => 'renewal',
            'formatted'               => 'true',
            'meta_keys'               => 'gift_message,_configuration',
        ], $query);
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

    public function testDocumentReadsTakeFormattedAndMetaKeys(): void
    {
        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $account = $client->account();

        $account->getProfile(['formatted' => false]);
        $account->getOrder(42, ['meta_keys' => ['gift_message']]);
        $account->getGuestOrder(7, 'guest@example.com', ['formatted' => true]);
        $account->getSubscription(9, null, ['meta_keys' => ['a', 'b']]);

        $base = 'https://store.example.com/wp-json/cocart/v2/my-account';
        $this->assertSame([
            "{$base}?formatted=false",
            "{$base}/orders/42?meta_keys=gift_message",
            "{$base}/orders/7?formatted=true&email=guest%40example.com",
            "{$base}/subscriptions/9?meta_keys=a%2Cb",
        ], array_map(fn ($r) => $r['url'], $this->mockAdapter->getRequests()));
    }

    // --- getGuestOrder ---

    public function testGetGuestOrderSendsEmailParam(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"order_id":7}');

        $client = $this->createClient();
        $client->account()->getGuestOrder(7, 'guest@example.com');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringContainsString('cocart/v2/my-account/orders/7', $request['url']);
        $this->assertStringContainsString('email=guest%40example.com', $request['url']);
    }

    // --- cancelOrder ---

    public function testCancelOrderPostsToMyAccountOrdersIdCancel(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"success":true,"order_id":42,"order_status":"cancelled"}');

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->cancelOrder(42);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/my-account/orders/42/cancel', $request['url']);
        $this->assertNull($request['body']);
    }

    // --- getOrderDownloads ---

    public function testGetOrderDownloadsCallsTheOrdersDownloadsRoute(): void
    {
        $body = ['downloads' => [[
            'product_name'        => 'Album',
            'download_name'       => 'MP3',
            'downloads_remaining' => '3',
        ]]];
        $this->mockAdapter->queueResponse(200, [], json_encode($body));

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $response = $client->account()->getOrderDownloads(123);

        $this->assertCount(1, $this->mockAdapter->getRequests());
        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertSame('https://store.example.com/wp-json/cocart/v2/my-account/orders/123/downloads', $request['url']);
        $this->assertSame($body, $response->toArray());
    }

    // --- getGuestOrderDownloads ---

    public function testGetGuestOrderDownloadsSendsTheBillingEmailToTheSameRoute(): void
    {
        $this->mockAdapter->queueResponse(200, [], '{"downloads":[]}');

        $client = $this->createClient();
        $response = $client->account()->getGuestOrderDownloads(123, 'jane@example.com');

        $this->assertSame(
            'https://store.example.com/wp-json/cocart/v2/my-account/orders/123/downloads?email=jane%40example.com',
            $this->mockAdapter->getLastRequest()['url']
        );
        $this->assertSame(['downloads' => []], $response->toArray());
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

    public function testGetReviewsUsesProductsReviewsMine(): void
    {
        // There is no my-account/reviews route.
        $this->mockAdapter->queueResponse(200, [], '[]');

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->getReviews();

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/products/reviews/mine', $request['url']);
    }

    // --- email verification ---

    public function testEmailVerificationMethodsUseVerifyEmailAndVerifyOrderEmail(): void
    {
        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $client->account()->verifyEmail('k1', 7);
        $client->account()->sendOrderEmailVerification();
        $client->account()->verifyOrderEmail('k2', 7);

        $calls = array_map(fn ($r) => $r['method'] . ' ' . $r['url'], $this->mockAdapter->getRequests());
        $this->assertSame([
            'GET https://store.example.com/wp-json/cocart/v2/my-account/verify-email?key=k1&user_id=7',
            'POST https://store.example.com/wp-json/cocart/v2/my-account/verify-order-email',
            'GET https://store.example.com/wp-json/cocart/v2/my-account/verify-order-email?key=k2&user_id=7',
        ], $calls);
    }

    // --- subscriptions ---

    public function testSubscriptionMethodsMapToTheMyAccountSubscriptionsRoutes(): void
    {
        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $account = $client->account();

        $account->getSubscriptions(['page' => 2, 'status' => 'active']);
        $account->getSubscription(9);
        $account->suspendSubscription(9);
        $account->cancelSubscription(9);
        $account->reactivateSubscription(9);
        $account->setSubscriptionAutoRenew(9, false);
        $account->updateSubscriptionPaymentMethod(9, ['payment_method' => 'stripe', 'token_id' => 3]);
        $account->resubscribe(9);
        $account->cancelResubscribe(9);

        $base = 'https://store.example.com/wp-json/cocart/v2/my-account/subscriptions';
        $calls = array_map(
            fn ($r) => [$r['method'], $r['url'], $r['body'] === null ? null : json_decode($r['body'], true)],
            $this->mockAdapter->getRequests()
        );
        $this->assertSame([
            ['GET', "{$base}?page=2&status=active", null],
            ['GET', "{$base}/9", null],
            ['POST', "{$base}/9/suspend", null],
            ['POST', "{$base}/9/cancel", null],
            ['POST', "{$base}/9/reactivate", null],
            ['POST', "{$base}/9/auto-renew", ['enabled' => false]],
            ['POST', "{$base}/9/payment-method", ['payment_method' => 'stripe', 'token_id' => 3]],
            ['POST', "{$base}/9/resubscribe", null],
            ['DELETE', "{$base}/9/resubscribe", null],
        ], $calls);
    }

    public function testUpdateSubscriptionPaymentMethodReturnsTheSubscriptionResult(): void
    {
        $this->mockAdapter->queueResponse(200, [], json_encode([
            'object' => 'subscription',
            'result' => ['code' => 'payment_method_changed', 'message' => 'Changed.', 'action' => null, 'redirect_url' => null, 'error' => null],
            'id'     => 9,
            'status' => ['code' => 'active', 'label' => 'Active'],
        ]));

        $client = $this->createClient(['username' => 'user', 'password' => 'pass']);
        $result = $client->account()->updateSubscriptionPaymentMethod(9, [
            'payment_method' => 'stripe',
            'payment_data'   => ['wc-stripe-payment-method' => 'pm_card_visa'],
        ]);

        $this->assertSame(
            ['payment_method' => 'stripe', 'payment_data' => [['key' => 'wc-stripe-payment-method', 'value' => 'pm_card_visa']]],
            json_decode($this->mockAdapter->getLastRequest()['body'], true)
        );
        $this->assertTrue($result->isComplete());
        $this->assertSame('subscription', $result->getObject());
        $this->assertSame(9, $result->getSubscriptionId());
        $this->assertNull($result->getOrderId());
    }

    public function testGetSubscriptionPassesTheEmailForAGuest(): void
    {
        $client = $this->createClient();
        $client->account()->getSubscription(9, 'jane@example.com');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertStringEndsWith('/my-account/subscriptions/9?email=jane%40example.com', $request['url']);
    }

    // --- requestOrderWithdrawal ---

    public function testRequestOrderWithdrawalPostsToOrderWithdrawal(): void
    {
        $data = [
            'first_name'         => 'Jane',
            'last_name'          => 'Doe',
            'email'              => 'j@example.com',
            'email_confirmation' => 'j@example.com',
            'order_number'       => '1234',
        ];

        $client = $this->createClient();
        $client->account()->requestOrderWithdrawal($data);

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/order-withdrawal', $request['url']);
        $this->assertSame($data, json_decode($request['body'], true));
    }

    // --- register ---

    public function testRegisterPostsRequestedUsernameAndPasswordToRegister(): void
    {
        $this->mockAdapter->queueResponse(201, [], '{"object":"customer","result":{"code":"account_created","message":"Your account was created."},"id":5,"email":"jane@example.com"}');

        $client = $this->createClient();
        $response = $client->account()->register('jane@example.com', 'jane', 's3cret!');

        $request = $this->mockAdapter->getLastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/wp-json/cocart/v2/register', $request['url']);
        // Not `username`/`password`: CoCart treats those as a Basic Auth attempt.
        $this->assertSame(
            ['email' => 'jane@example.com', 'requested_username' => 'jane', 'requested_password' => 's3cret!'],
            json_decode($request['body'], true)
        );
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(5, $response->get('id'));
        $this->assertSame('account_created', $response->get('result.code'));
    }

    public function testRegisterSendsOnlyTheEmailWhenNothingElseIsGiven(): void
    {
        $client = $this->createClient();
        $client->account()->register('jane@example.com');

        $this->assertSame(['email' => 'jane@example.com'], json_decode($this->mockAdapter->getLastRequest()['body'], true));
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
