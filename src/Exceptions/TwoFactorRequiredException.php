<?php
declare(strict_types=1);

/**
 * Two Factor Authentication Required Exception for CoCart SDK
 *
 * Thrown when the server requires 2FA verification before completing login.
 * Contains the available providers and challenge metadata from the API response.
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class TwoFactorRequiredException extends AuthenticationException
{
    /**
     * Get the 2FA providers available for this user
     *
     * @return string[] e.g. ['email', 'totp']
     */
    public function getAvailableProviders(): array
    {
        return $this->responseData['data']['available_providers'] ?? [];
    }

    /**
     * Get the default 2FA provider the server will use if none is specified
     *
     * @return string|null
     */
    public function getDefaultProvider(): ?string
    {
        return $this->responseData['data']['default_provider'] ?? null;
    }

    /**
     * Whether the server already sent a verification code via email
     *
     * @return bool
     */
    public function isEmailSent(): bool
    {
        return (bool) ($this->responseData['data']['email_sent'] ?? false);
    }
}
