<?php
declare(strict_types=1);

/**
 * Authentication Exception for CoCart SDK
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class AuthenticationException extends CoCartException
{
    /**
     * Check if the error indicates an expired token that could be refreshed
     *
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        $code = $this->getErrorCode();

        return $code !== null && (
            str_contains($code, 'expired') ||
            str_contains($code, 'jwt_auth_invalid_token')
        );
    }
}
