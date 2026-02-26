<?php
declare(strict_types=1);

/**
 * PHP Session storage adapter
 *
 * @package CoCart\SDK\Storage
 */

namespace CoCart\Storage;

use CoCart\SessionStorageInterface;

class PhpSessionStorage implements SessionStorageInterface
{
    /**
     * Constructor - ensures session is started
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): ?string
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, string $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
