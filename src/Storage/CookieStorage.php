<?php
declare(strict_types=1);

/**
 * Cookie storage adapter
 *
 * @package CoCart\SDK\Storage
 */

namespace CoCart\Storage;

use CoCart\SessionStorageInterface;

class CookieStorage implements SessionStorageInterface
{
    /**
     * Cookie expiration time in seconds
     *
     * @var int
     */
    protected int $expiration;

    /**
     * Cookie path
     *
     * @var string
     */
    protected string $path;

    /**
     * Cookie domain
     *
     * @var string
     */
    protected string $domain;

    /**
     * Whether cookie should be secure (HTTPS only)
     *
     * @var bool
     */
    protected bool $secure;

    /**
     * Whether cookie should be HTTP only
     *
     * @var bool
     */
    protected bool $httpOnly;

    /**
     * Constructor
     *
     * @param int    $expiration Cookie expiration in seconds (default 7 days)
     * @param string $path       Cookie path
     * @param string $domain     Cookie domain
     * @param bool   $secure     Whether cookie should be secure
     * @param bool   $httpOnly   Whether cookie should be HTTP only
     */
    public function __construct(
        int $expiration = 604800,
        string $path = '/',
        string $domain = '',
        bool $secure = true,
        bool $httpOnly = true
    ) {
        $this->expiration = $expiration;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): ?string
    {
        return $_COOKIE[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, string $value): void
    {
        setcookie(
            $key,
            $value,
            time() + $this->expiration,
            $this->path,
            $this->domain,
            $this->secure,
            $this->httpOnly
        );
        $_COOKIE[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        setcookie(
            $key,
            '',
            time() - 3600,
            $this->path,
            $this->domain,
            $this->secure,
            $this->httpOnly
        );
        unset($_COOKIE[$key]);
    }
}
