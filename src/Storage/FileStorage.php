<?php
declare(strict_types=1);

/**
 * File-based storage adapter
 *
 * @package CoCart\SDK\Storage
 */

namespace CoCart\Storage;

use CoCart\SessionStorageInterface;

class FileStorage implements SessionStorageInterface
{
    /**
     * Directory path for storage
     *
     * @var string
     */
    protected string $directory;

    /**
     * Constructor
     *
     * @param string $directory Directory path for storage files
     */
    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): ?string
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        return $contents !== false ? $contents : null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, string $value): void
    {
        file_put_contents($this->getFilePath($key), $value);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Get file path for a key
     *
     * @param string $key Storage key
     * @return string
     */
    protected function getFilePath(string $key): string
    {
        return $this->directory . '/' . md5($key) . '.txt';
    }
}
