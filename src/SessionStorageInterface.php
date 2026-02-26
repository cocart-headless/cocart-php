<?php
declare(strict_types=1);

/**
 * Interface for session storage adapters
 *
 * @package CoCart\SDK
 */

namespace CoCart;

interface SessionStorageInterface
{
    /**
     * Get a value from storage
     *
     * @param string $key Storage key
     * @return string|null
     */
    public function get(string $key): ?string;

    /**
     * Set a value in storage
     *
     * @param string $key   Storage key
     * @param string $value Value to store
     * @return void
     */
    public function set(string $key, string $value): void;

    /**
     * Delete a value from storage
     *
     * @param string $key Storage key
     * @return void
     */
    public function delete(string $key): void;
}
