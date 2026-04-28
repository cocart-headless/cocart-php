<?php
declare(strict_types=1);

/**
 * Account Endpoint
 *
 * Handles all my-account API operations: profile, orders, downloads, and reviews.
 *
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Exceptions\CoCartException;
use CoCart\Response;

class Account extends Endpoint
{
    /**
     * Endpoint prefix (unused — all calls use raw routing)
     *
     * @var string
     */
    protected string $endpoint = '';

    // MARK: - Helpers

    private function rawPath(string $sub = ''): string
    {
        return $sub === '' ? 'cocart/v2/my-account' : 'cocart/v2/my-account/' . ltrim($sub, '/');
    }

    private function getRaw(string $sub = '', array $params = []): Response
    {
        try {
            return $this->client->requestRaw('GET', $this->rawPath($sub), $params);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    private function postRaw(string $sub = '', array $data = []): Response
    {
        try {
            return $this->client->requestRaw('POST', $this->rawPath($sub), [], $data);
        } catch (CoCartException $e) {
            $this->handleNoRoute($e);
        }
    }

    // MARK: - Profile

    /**
     * Get the authenticated user's account profile
     *
     * @return Response
     * @throws CoCartException
     */
    public function getProfile(): Response
    {
        return $this->getRaw();
    }

    /**
     * Update the authenticated user's profile
     *
     * @param array $data Profile fields (account_first_name, account_last_name, account_display_name, account_email)
     * @return Response
     * @throws CoCartException
     */
    public function updateProfile(array $data): Response
    {
        return $this->postRaw('', $data);
    }

    /**
     * Change the authenticated user's password
     *
     * @param string $current  Current password
     * @param string $password New password
     * @param string $confirm  Confirm new password
     * @return Response
     * @throws CoCartException
     */
    public function changePassword(string $current, string $password, string $confirm): Response
    {
        return $this->postRaw('change-password', [
            'password_current' => $current,
            'password_1'       => $password,
            'password_2'       => $confirm,
        ]);
    }

    // MARK: - Orders

    /**
     * Get the user's order history
     *
     * @param array $params Query params: page, per_page, order (ASC|DESC)
     * @return Response
     * @throws CoCartException
     */
    public function getOrders(array $params = []): Response
    {
        return $this->getRaw('orders', $params);
    }

    /**
     * Get a single order by ID
     *
     * @param int $id Order ID
     * @return Response
     * @throws CoCartException
     */
    public function getOrder(int $id): Response
    {
        return $this->getRaw("orders/{$id}");
    }

    /**
     * Get a single guest order by ID and billing email
     *
     * @param int    $id    Order ID
     * @param string $email Billing email used when placing the order
     * @return Response
     * @throws CoCartException
     */
    public function getGuestOrder(int $id, string $email): Response
    {
        return $this->getRaw("orders/{$id}", ['email' => $email]);
    }

    // MARK: - Downloads

    /**
     * Get downloadable files for a specific order
     *
     * @param int $id Order ID
     * @return Response
     * @throws CoCartException
     */
    public function getOrderDownloads(int $id): Response
    {
        return $this->getRaw("orders/{$id}/downloads");
    }

    /**
     * Get downloadable files for a specific guest order
     *
     * @param int    $id    Order ID
     * @param string $email Billing email used when placing the order
     * @return Response
     * @throws CoCartException
     */
    public function getGuestOrderDownloads(int $id, string $email): Response
    {
        return $this->getRaw("orders/{$id}/downloads", ['email' => $email]);
    }

    /**
     * Get all downloadable files available to the authenticated user
     *
     * @return Response
     * @throws CoCartException
     */
    public function getDownloads(): Response
    {
        return $this->getRaw('downloads');
    }

    // MARK: - Reviews

    /**
     * Get the authenticated user's product reviews
     *
     * @return Response
     * @throws CoCartException
     */
    public function getReviews(): Response
    {
        return $this->getRaw('reviews');
    }
}
