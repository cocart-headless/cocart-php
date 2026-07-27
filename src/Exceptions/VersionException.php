<?php
declare(strict_types=1);

/**
 * Version Exception for CoCart SDK
 *
 * Thrown when a method requires CoCart Starter but the SDK
 * is configured for the CoCart Community plugin.
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class VersionException extends CoCartException
{
    /**
     * Constructor
     *
     * @param string $method The method that requires CoCart Starter
     */
    public function __construct(string $method)
    {
        parent::__construct(
            "{$method}() requires CoCart Starter. Please upgrade from the CoCart Community plugin to use this feature.",
            0,
            'cocart_version_required'
        );
    }
}
