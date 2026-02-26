<?php
declare(strict_types=1);

/**
 * Validation Exception for CoCart SDK
 *
 * @package CoCart\SDK\Exceptions
 */

namespace CoCart\Exceptions;

class ValidationException extends CoCartException
{
    /**
     * Get field-level validation errors from the API response
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->responseData['data']['params']
            ?? $this->responseData['additional_errors']
            ?? [];
    }
}
