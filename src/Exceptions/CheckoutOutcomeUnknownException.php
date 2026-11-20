<?php
declare(strict_types=1);

/**
 * Checkout Outcome Unknown Exception
 *
 * placeOrder() sent the order but no answer came back (the connection dropped,
 * the request timed out, or a proxy gave up waiting), or another request is
 * still taking payment for the cart (`409 cocart_payment_in_progress`, kept in
 * getErrorCode()), so the order may or may not have been placed. Don't place
 * it again blindly: ask checkout()->getStatus($e->getCheckoutId()) how it ended.
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class CheckoutOutcomeUnknownException extends CoCartException
{
    /**
     * @param CoCartException $cause      The error the request failed with
     * @param string          $checkoutId The `checkout_id` the order was sent with
     */
    public function __construct(CoCartException $cause, private string $checkoutId)
    {
        parent::__construct(
            $cause->getMessage(),
            $cause->getHttpCode(),
            $cause->getErrorCode(),
            $cause->getResponseData(),
            $cause
        );
    }

    /**
     * The `checkout_id` the order was sent with
     *
     * @return string
     */
    public function getCheckoutId(): string
    {
        return $this->checkoutId;
    }
}
