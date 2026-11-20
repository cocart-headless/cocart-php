<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\CheckoutResult;
use CoCart\Response;
use PHPUnit\Framework\TestCase;

/**
 * CheckoutResult reads `result` from the document a payment call returns
 * (CoCart Plus 2.0).
 */
class CheckoutResultTest extends TestCase
{
    private function read(array $body, int $status = 200): CheckoutResult
    {
        return CheckoutResult::fromResponse(new Response($status, [], json_encode($body, JSON_THROW_ON_ERROR)));
    }

    private function order(array $result): array
    {
        return [
            'object' => 'order',
            'result' => $result + ['message' => 'Message.', 'action' => null, 'redirect_url' => null, 'error' => null],
            'id'     => 217,
            'number' => '217',
            'key'    => 'wc_order_secretkey',
            'status' => ['code' => 'pending', 'label' => 'Pending payment'],
        ];
    }

    public function testAMissingCodeReadsAsFailedNeverComplete(): void
    {
        $result = $this->read(['object' => 'order', 'id' => 217]);

        $this->assertSame('payment_failed', $result->getCode());
        $this->assertTrue($result->isFailed());
        $this->assertFalse($result->isComplete());
    }

    public function testAnOrderPlacedWithAnActionIsAwaitingPaymentNotComplete(): void
    {
        $result = $this->read($this->order([
            'code'   => 'order_placed',
            'action' => ['type' => 'stripe_voucher_payment', 'data' => ['client_secret' => 'pi_x_secret_y', 'payment_method_type' => 'boleto']],
        ]));

        $this->assertFalse($result->isComplete());
        $this->assertTrue($result->isAwaitingPayment());
        $this->assertSame('boleto', $result->getPaymentMethodType());
    }

    public function testWithActionRequiredTheOffSiteStepComesBeforeTheResultUrl(): void
    {
        $result = $this->read($this->order([
            'code'         => 'action_required',
            'action'       => ['type' => 'gateway_redirect_required', 'data' => ['redirect' => 'https://bank.example.com/3ds']],
            'redirect_url' => 'https://shop.example.com/order-received/217',
        ]));

        $this->assertTrue($result->needsRedirect());
        $this->assertSame('https://bank.example.com/3ds', $result->getRedirectUrl());
    }

    public function testOtherwiseTheResultUrlComesFirst(): void
    {
        $result = $this->read($this->order([
            'code'         => 'order_placed',
            'action'       => ['type' => 'gateway_redirect_required', 'data' => ['redirect' => 'https://bank.example.com/pay']],
            'redirect_url' => 'https://shop.example.com/order-received/217',
        ]));

        $this->assertSame('https://shop.example.com/order-received/217', $result->getRedirectUrl());
    }

    public function testAPendingPaymentIsPending(): void
    {
        $result = $this->read($this->order(['code' => 'payment_pending']));

        $this->assertTrue($result->isPending());
        $this->assertFalse($result->isComplete());
        $this->assertFalse($result->isFailed());
        $this->assertSame('pending', $result->getOrderStatus());
    }

    public function testDumpingAResultHidesTheClientSecretAndTheOrderKey(): void
    {
        $result = $this->read($this->order([
            'code'         => 'action_required',
            'action'       => ['type' => 'stripe_confirm_payment', 'data' => ['client_secret' => 'pi_1_secret_hidden', 'intent_type' => 'payment_intent']],
            'redirect_url' => 'https://shop.example.com/order-received/217?key=wc_order_secretkey',
        ]));

        ob_start();
        var_dump($result);
        $dumped = (string) ob_get_clean() . print_r($result, true);

        $this->assertStringNotContainsString('pi_1_secret_hidden', $dumped);
        $this->assertStringNotContainsString('wc_order_secretkey', $dumped);
        $this->assertStringContainsString('action_required', $dumped);
        $this->assertStringContainsString('key=***', $dumped);

        // The getters still give them to the code that needs them.
        $this->assertSame('pi_1_secret_hidden', $result->getClientSecret());
        $this->assertSame('wc_order_secretkey', $result->getOrderKey());
    }
}
