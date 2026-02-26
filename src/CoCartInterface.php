<?php
declare(strict_types=1);

/**
 * CoCart Client Interface
 *
 * Defines the public API contract for the CoCart client. Type-hint against
 * this interface (instead of the concrete CoCart class) to enable mocking
 * in unit tests and support alternative implementations.
 *
 * @package CoCart\SDK
 */

namespace CoCart;

use CoCart\Endpoints\Cart;
use CoCart\Endpoints\Products;
use CoCart\Endpoints\Store;
use CoCart\Endpoints\Sessions;
use CoCart\Http\HttpAdapterInterface;
use CoCart\Exceptions\CoCartException;

interface CoCartInterface
{
    // --- HTTP methods ---

    /**
     * Make a GET request
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function get(string $endpoint, array $params = []): Response;

    /**
     * Make a POST request
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function post(string $endpoint, array $data = [], array $params = []): Response;

    /**
     * Make a PUT request
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function put(string $endpoint, array $data = [], array $params = []): Response;

    /**
     * Make a DELETE request
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function delete(string $endpoint, array $params = []): Response;

    /**
     * Make an HTTP request to the API
     *
     * @param string     $method   HTTP method
     * @param string     $endpoint API endpoint
     * @param array      $params   Query parameters
     * @param array|null $data     Request body data
     * @return Response
     * @throws CoCartException
     */
    public function request(string $method, string $endpoint, array $params = [], ?array $data = null): Response;

    /**
     * Make an HTTP request using a full REST route (relative to wp-json/)
     *
     * @param string     $method HTTP method
     * @param string     $route  Full REST route relative to wp-json/
     * @param array      $params Query parameters
     * @param array|null $data   Request body data
     * @return Response
     * @throws CoCartException
     */
    public function requestRaw(string $method, string $route, array $params = [], ?array $data = null): Response;

    // --- Cart key ---

    /**
     * Set the cart key for guest session tracking
     *
     * @param string $cartKey The cart key
     * @return static
     */
    public function setCartKey(string $cartKey): static;

    /**
     * Get the current cart key
     *
     * @return string|null
     */
    public function getCartKey(): ?string;

    // --- Authentication ---

    /**
     * Set Basic Auth credentials for customer authentication
     *
     * @param string $username Username, email, or phone number
     * @param string $password Password
     * @return static
     */
    public function setAuth(string $username, string $password): static;

    /**
     * Set JWT token for authentication
     *
     * @param string $token JWT token
     * @return static
     */
    public function setJwtToken(string $token): static;

    /**
     * Get the current JWT token
     *
     * @return string|null
     */
    public function getJwtToken(): ?string;

    /**
     * Set the JWT refresh token
     *
     * @param string $token Refresh token
     * @return static
     */
    public function setRefreshToken(string $token): static;

    /**
     * Get the current refresh token
     *
     * @return string|null
     */
    public function getRefreshToken(): ?string;

    /**
     * Check if a JWT token is set
     *
     * @return bool
     */
    public function hasJwtToken(): bool;

    /**
     * Clear JWT and refresh tokens without affecting cart key
     *
     * @return static
     */
    public function clearJwtToken(): static;

    /**
     * Set the JWT Manager for auto-refresh support
     *
     * @param JwtManager $jwtManager JWT Manager instance
     * @return static
     */
    public function setJwtManager(JwtManager $jwtManager): static;

    /**
     * Set WooCommerce REST API credentials (for admin operations like Sessions)
     *
     * @param string $consumerKey    WooCommerce consumer key
     * @param string $consumerSecret WooCommerce consumer secret
     * @return static
     */
    public function setWooCommerceCredentials(string $consumerKey, string $consumerSecret): static;

    /**
     * Get or create the JWT Manager instance
     *
     * @return JwtManager
     */
    public function jwt(): JwtManager;

    /**
     * Login with username and password
     *
     * Uses JWT authentication if the CoCart JWT plugin is installed,
     * otherwise falls back to Basic Auth automatically.
     *
     * @param string $username Username, email, or phone
     * @param string $password Password
     * @return Response
     * @throws CoCartException
     */
    public function login(string $username, string $password): Response;

    /**
     * Logout — clear all JWT tokens
     *
     * @return static
     */
    public function logout(): static;

    /**
     * Check if authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * Check if using guest session
     *
     * @return bool
     */
    public function isGuest(): bool;

    // --- Session ---

    /**
     * Clear authentication and cart key
     *
     * @return static
     */
    public function clearSession(): static;

    // --- Configuration ---

    /**
     * Set HTTP timeout
     *
     * @param int $seconds Timeout in seconds
     * @return static
     */
    public function setTimeout(int $seconds): static;

    /**
     * Set the maximum number of retries for transient failures (429, 503, timeouts)
     *
     * @param int $retries Maximum retry attempts (0 to disable)
     * @return static
     */
    public function setMaxRetries(int $retries): static;

    /**
     * Set SSL verification
     *
     * @param bool $verify Whether to verify SSL
     * @return static
     */
    public function setVerifySsl(bool $verify): static;

    /**
     * Set the WordPress REST API prefix
     *
     * @param string $prefix REST prefix (e.g. 'wp-json', 'api')
     * @return static
     */
    public function setRestPrefix(string $prefix): static;

    /**
     * Get the WordPress REST API prefix
     *
     * @return string
     */
    public function getRestPrefix(): string;

    /**
     * Set the API namespace (for white-labelling)
     *
     * @param string $namespace API namespace (e.g. 'cocart', 'mystore')
     * @return static
     */
    public function setNamespace(string $namespace): static;

    /**
     * Get the API namespace
     *
     * @return string
     */
    public function getNamespace(): string;

    /**
     * Add custom header
     *
     * @param string $name  Header name
     * @param string $value Header value
     * @return static
     */
    public function addHeader(string $name, string $value): static;

    /**
     * Set the HTTP adapter to use
     *
     * @param HttpAdapterInterface|string $adapter Adapter instance or name (guzzle, curl, wordpress, stream)
     * @return static
     */
    public function setHttpAdapter(HttpAdapterInterface|string $adapter): static;

    /**
     * Get the name of the current HTTP adapter
     *
     * @return string
     */
    public function getHttpAdapterName(): string;

    // --- Accessors ---

    /**
     * Get the store URL
     *
     * @return string
     */
    public function getStoreUrl(): string;

    /**
     * Get the last response
     *
     * @return Response|null
     */
    public function getLastResponse(): ?Response;

    // --- Cart transfer ---

    /**
     * Transfer cart from guest to authenticated user
     *
     * @param string $username Username, email, or phone
     * @param string $password Password
     * @return Response
     * @throws CoCartException
     */
    public function transferCartToCustomer(string $username, string $password): Response;

    // --- Endpoints ---

    /**
     * Get Cart endpoint
     *
     * @return Cart
     */
    public function cart(): Cart;

    /**
     * Get Products endpoint
     *
     * @return Products
     */
    public function products(): Products;

    /**
     * Get Store endpoint
     *
     * @return Store
     */
    public function store(): Store;

    /**
     * Get Sessions endpoint (requires admin credentials)
     *
     * @return Sessions
     */
    public function sessions(): Sessions;
}
