<?php
declare(strict_types=1);

/**
 * Sessions Endpoint
 * 
 * Handles cart session management for administrators.
 * Requires WooCommerce REST API credentials (consumer_key/consumer_secret).
 * 
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Response;
use CoCart\Exceptions\AuthenticationException;

class Sessions extends Endpoint
{
    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = 'sessions';

    /**
     * Get all cart sessions
     *
     * @param array $params Query parameters
     * @return Response
     * @throws AuthenticationException
     */
    public function all(array $params = []): Response
    {
        return $this->get('', $params);
    }

    /**
     * Get a specific cart session
     *
     * @param string $sessionKey Session key (cart_key or customer ID)
     * @param array  $params     Query parameters
     * @return Response
     */
    public function find(string $sessionKey, array $params = []): Response
    {
        // CoCart Core uses singular "session" for individual session routes
        return $this->client->get("session/{$sessionKey}", $params);
    }

    /**
     * Delete a cart session
     *
     * @param string $sessionKey Session key (cart_key or customer ID)
     * @return Response
     */
    public function destroy(string $sessionKey): Response
    {
        // CoCart Core uses singular "session" for individual session routes
        return $this->client->delete("session/{$sessionKey}");
    }

    /**
     * Get session items
     *
     * @param string $sessionKey Session key
     * @return Response
     */
    public function getItems(string $sessionKey): Response
    {
        // CoCart Core uses singular "session" for individual session routes
        return $this->client->get("session/{$sessionKey}/items");
    }

    /**
     * Get session by customer ID
     *
     * @param int $customerId Customer ID
     * @return Response
     */
    public function byCustomer(int $customerId): Response
    {
        return $this->find((string) $customerId);
    }

    /**
     * Delete session by customer ID
     *
     * @param int $customerId Customer ID
     * @return Response
     */
    public function destroyByCustomer(int $customerId): Response
    {
        return $this->destroy((string) $customerId);
    }
}
