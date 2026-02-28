<?php
declare(strict_types=1);

/**
 * Version Exception for CoCart SDK
 *
 * Thrown when a method requires CoCart Basic but the SDK
 * is configured for the legacy CoCart plugin.
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class VersionException extends CoCartException
{
    /**
     * Constructor
     *
     * @param string $method The method that requires CoCart Basic
     */
    public function __construct(string $method)
    {
        parent::__construct(
            "{$method}() requires CoCart Basic. Please upgrade from the legacy CoCart plugin to use this feature.",
            0,
            'cocart_version_required'
        );
    }
}
