<?php
declare(strict_types=1);

/**
 * Store Endpoint
 *
 * Handles store information API operations.
 *
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Response;

class Store extends Endpoint
{
    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = 'store';

    /**
     * Get store information
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function info(array $params = []): Response
    {
        return $this->get('', $params);
    }
}
