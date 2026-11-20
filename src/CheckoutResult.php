<?php
declare(strict_types=1);

/**
 * Checkout Result
 *
 * The outcome of a call that takes payment: placing an order, paying for an
 * unpaid order, returning from an off-site step, or changing a subscription's
 * payment method (CoCart Plus 2.0).
 *
 * Read from the response's `result`. A declined card is a result (isFailed()),
 * not an exception: exceptions are kept for requests the store refuses before
 * it tries to take payment.
 *
 * @package CoCart\SDK
 */

namespace CoCart;

class CheckoutResult
{
    /**
     * @param string        $code              result.code: order_placed, action_required, payment_pending,
     *                                         payment_failed, payment_method_changed, or another value
     * @param string|null   $object            The document returned: order or subscription
     * @param int|null      $orderId           The order's ID, when the document is an order
     * @param string|null   $orderKey          The order's key, when the document is an order
     * @param string|null   $orderNumber       The order number, for display
     * @param string|null   $orderStatus       The order's status code (processing, on-hold, pending…)
     * @param int|null      $subscriptionId    The subscription's ID, when the document is a subscription
     * @param string|null   $redirectUrl       Where to send the customer (https or http only)
     * @param string|null   $clientSecret      For an in-page step (3D Secure), or a payment made in the page
     * @param string|null   $intentType        payment_intent or setup_intent
     * @param string|null   $paymentMethodType The Stripe payment method to pay with when isAwaitingPayment()
     * @param string|null   $message           A message for the customer
     * @param string|null   $errorCode         CoCart's code for why the payment failed
     * @param string|null   $gatewayErrorCode  The gateway's own code
     * @param string|null   $gatewayMessage    The gateway's own message
     * @param string|null   $actionType        What the customer must do, as the gateway integration names it
     * @param array|null    $actionData        What the action needs
     * @param Response      $response          The raw response: the order, or the subscription
     */
    private function __construct(
        private string $code,
        private ?string $object,
        private ?int $orderId,
        private ?string $orderKey,
        private ?string $orderNumber,
        private ?string $orderStatus,
        private ?int $subscriptionId,
        private ?string $redirectUrl,
        private ?string $clientSecret,
        private ?string $intentType,
        private ?string $paymentMethodType,
        private ?string $message,
        private ?string $errorCode,
        private ?string $gatewayErrorCode,
        private ?string $gatewayMessage,
        private ?string $actionType,
        private ?array $actionData,
        private Response $response
    ) {
    }

    /**
     * Read the document a call that takes payment returns, with what the
     * payment did in `result`
     *
     * A missing `result.code` reads as `payment_failed`, never as complete.
     *
     * @param Response $response API response
     * @return self
     */
    public static function fromResponse(Response $response): self
    {
        $data       = $response->toArray();
        $result     = is_array($data['result'] ?? null) ? $data['result'] : [];
        $action     = is_array($result['action'] ?? null) ? $result['action'] : null;
        $actionData = is_array($action['data'] ?? null) ? $action['data'] : null;
        $error      = is_array($result['error'] ?? null) ? $result['error'] : null;
        $status     = is_array($data['status'] ?? null) ? $data['status'] : null;
        $object     = self::text($data['object'] ?? null);
        $isOrder    = $object !== 'subscription';
        $code       = self::text($result['code'] ?? null) ?? 'payment_failed';
        $number     = $data['number'] ?? null;
        $offSite    = self::webUrl($actionData['redirect'] ?? null);
        $resultUrl  = self::webUrl($result['redirect_url'] ?? null);

        return new self(
            code: $code,
            object: $object,
            orderId: $isOrder ? self::integer($data['id'] ?? null) : null,
            orderKey: $isOrder ? self::text($data['key'] ?? null) : null,
            orderNumber: $isOrder ? (is_int($number) ? (string) $number : self::text($number)) : null,
            orderStatus: $isOrder ? self::text($status['code'] ?? null) : null,
            subscriptionId: $isOrder ? null : self::integer($data['id'] ?? null),
            redirectUrl: $code === 'action_required' ? ($offSite ?? $resultUrl) : ($resultUrl ?? $offSite),
            clientSecret: self::text($actionData['client_secret'] ?? null),
            intentType: self::text($actionData['intent_type'] ?? null),
            paymentMethodType: self::text($actionData['payment_method_type'] ?? null),
            message: self::text($result['message'] ?? null),
            errorCode: self::text($error['code'] ?? null),
            gatewayErrorCode: self::text($error['gateway_code'] ?? null),
            gatewayMessage: self::text($error['gateway_message'] ?? null),
            actionType: self::text($action['type'] ?? null),
            actionData: $actionData,
            response: $response
        );
    }

    /**
     * Turn a `payment_data` map into the `{key, value}` pairs the API takes.
     * A list passes through as it is.
     *
     * @param array $data Map of the keys the gateway reads, or pairs
     * @return array<array{key: string, value: mixed}>
     */
    public static function toPaymentDataPairs(array $data): array
    {
        if (array_is_list($data)) {
            return $data;
        }

        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = ['key' => (string) $key, 'value' => $value];
        }

        return $pairs;
    }

    /**
     * `result.code`: `order_placed`, `action_required`, `payment_pending`,
     * `payment_failed`, `payment_method_changed` for a subscription, or a value
     * a store or gateway adds
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Paid, nothing to pay, or held for review or offline payment: the order is
     * placed (or the subscription's payment method changed)
     */
    public function isComplete(): bool
    {
        return in_array($this->code, ['order_placed', 'payment_method_changed'], true) && $this->actionType === null;
    }

    /**
     * The order is placed but not paid yet: the customer pays outside the checkout
     *
     * With getClientSecret(), pay in the page with the gateway's SDK for
     * getPaymentMethodType() (Cash App Pay, WeChat Pay, a Boleto, OXXO or
     * Multibanco voucher); otherwise getActionType() says what to show
     * (`stripe_blik_confirmation_pending`: approve in the banking app; bank
     * transfer instructions…). The gateway's webhook marks the order paid, so
     * don't place the order again.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->code === 'order_placed' && $this->actionType !== null;
    }

    /** The card needs an in-page step (3D Secure): confirm getClientSecret() with the gateway's SDK, then make the same call again. */
    public function needsConfirmation(): bool
    {
        return $this->code === 'action_required' && $this->clientSecret !== null;
    }

    /** The payment needs an off-site step: send the customer to getRedirectUrl(), then call confirmReturn() when they come back. */
    public function needsRedirect(): bool
    {
        return $this->code === 'action_required'
            && $this->clientSecret === null
            && self::webUrl($this->actionData['redirect'] ?? null) !== null;
    }

    /** The gateway hasn't confirmed the payment yet: confirm again shortly, or check the order. */
    public function isPending(): bool
    {
        return $this->code === 'payment_pending';
    }

    /** The payment was declined or couldn't be taken: show getMessage(); the order can be paid again. */
    public function isFailed(): bool
    {
        return $this->code === 'payment_failed';
    }

    /** The document returned: `order`, or `subscription` after a subscription's payment method change. */
    public function getObject(): ?string
    {
        return $this->object;
    }

    /** The order's ID, when the document is an order. */
    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    /** The order's key, needed to view or pay for it as a guest. */
    public function getOrderKey(): ?string
    {
        return $this->orderKey;
    }

    /** The order number, for display. Can differ from the ID. */
    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    /** The order's status code, e.g. `processing`, `on-hold` or `pending`. */
    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }

    /** The subscription's ID, when the document is a subscription. */
    public function getSubscriptionId(): ?int
    {
        return $this->subscriptionId;
    }

    /**
     * Where to send the customer: the off-site step when needsRedirect(),
     * otherwise the page to show once the payment is done (the order-received
     * page, or the gateway's own). Only ever an https or http address.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /** For an in-page step (3D Secure), or a payment the customer makes in the page: give it to the gateway's SDK. */
    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /** `payment_intent` or `setup_intent`, alongside getClientSecret(). */
    public function getIntentType(): ?string
    {
        return $this->intentType;
    }

    /** For isAwaitingPayment(): `cashapp`, `wechat_pay`, `boleto`, `oxxo` or `multibanco`. */
    public function getPaymentMethodType(): ?string
    {
        return $this->paymentMethodType;
    }

    /** A message for the customer, for every code: why a payment failed, or that the order was received. Plain text. */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /** CoCart's code for why the payment failed: `card_declined`, `insufficient_funds`… or `payment_failed`. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** The gateway's own error code, for logging. */
    public function getGatewayErrorCode(): ?string
    {
        return $this->gatewayErrorCode;
    }

    /** The gateway's own message, for logging. Plain text. */
    public function getGatewayMessage(): ?string
    {
        return $this->gatewayMessage;
    }

    /** What the customer must do, as the gateway integration names it, e.g. `stripe_confirm_payment` or `stripe_voucher_payment`. */
    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    /** What the action needs, e.g. `client_secret` or `redirect`. */
    public function getActionData(): ?array
    {
        return $this->actionData;
    }

    /** The raw response: the order, or the subscription. */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * What var_dump() and print_r() show: the client secret, the order key
     * and query values are masked, and the raw response is left out, so a
     * dumped result doesn't put them in logs
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $actionData = $this->actionData;
        if (is_array($actionData)) {
            if (array_key_exists('client_secret', $actionData)) {
                $actionData['client_secret'] = '***';
            }
            if (is_string($actionData['redirect'] ?? null)) {
                $actionData['redirect'] = self::redactQuery($actionData['redirect']);
            }
        }

        return [
            'code' => $this->code,
            'object' => $this->object,
            'orderId' => $this->orderId,
            'orderKey' => $this->orderKey === null ? null : '***',
            'orderNumber' => $this->orderNumber,
            'orderStatus' => $this->orderStatus,
            'subscriptionId' => $this->subscriptionId,
            'redirectUrl' => $this->redirectUrl === null ? null : self::redactQuery($this->redirectUrl),
            'clientSecret' => $this->clientSecret === null ? null : '***',
            'intentType' => $this->intentType,
            'paymentMethodType' => $this->paymentMethodType,
            'message' => $this->message,
            'errorCode' => $this->errorCode,
            'gatewayErrorCode' => $this->gatewayErrorCode,
            'gatewayMessage' => $this->gatewayMessage,
            'actionType' => $this->actionType,
            'actionData' => $actionData,
            'response' => sprintf('Response(%d), see getResponse()', $this->response->getStatusCode()),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A web address the customer can be sent to: https or http only. Anything
     * else (javascript:, data:, another app's link, a relative path) is left
     * out, so a response can't make a page run script or open another app.
     */
    private static function webUrl(mixed $value): ?string
    {
        $url = self::text($value);
        return $url !== null && preg_match('#^https?://[^\s/?\#]#i', $url) === 1 ? $url : null;
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        return is_string($value) && $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * A URL with every query value replaced by `***` (the query can carry the order key)
     */
    private static function redactQuery(string $url): string
    {
        $i = strpos($url, '?');
        if ($i === false) {
            return $url;
        }

        $pairs = array_map(
            fn (string $pair) => str_contains($pair, '=') ? substr($pair, 0, (int) strpos($pair, '=')) . '=***' : $pair,
            explode('&', substr($url, $i + 1))
        );

        return substr($url, 0, $i + 1) . implode('&', $pairs);
    }
}
