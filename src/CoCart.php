<?php
declare(strict_types=1);

/**
 * CoCart PHP SDK
 * 
 * A frontend PHP SDK for interacting with the CoCart REST API.
 * Supports both guest customers (via cart_key) and authenticated users (via Basic Auth or JWT).
 * 
 * Supports multiple HTTP adapters:
 * - Guzzle (recommended, install via: composer require guzzlehttp/guzzle)
 * - cURL (built-in PHP extension)
 * - WordPress HTTP API (for WP plugins)
 * - PHP Streams (fallback)
 * 
 * @package CoCart\SDK
 * @version 1.0.0
 * @author  CoCart Headless, LLC
 * @license MIT
 */

use CoCart\Exceptions\CoCartException;
use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\ValidationException;
use CoCart\Endpoints\Cart;
use CoCart\Endpoints\Products;
use CoCart\Endpoints\Store;
use CoCart\Endpoints\Sessions;
use CoCart\Endpoints\Batch;
use CoCart\Http\HttpAdapterInterface;
use CoCart\Http\HttpAdapterFactory;
use CoCart\CoCartInterface;
use CoCart\Response;
use CoCart\JwtManager;

class CoCart implements CoCartInterface
{
    /**
     * SDK Version
     */
    const VERSION = '1.0.0';

    /**
     * API Version
     */
    const API_VERSION = 'v2';

    /**
     * Store URL
     *
     * @var string
     */
    protected string $storeUrl;

    /**
     * WordPress REST API prefix (default: wp-json)
     *
     * @var string
     */
    protected string $restPrefix = 'wp-json';

    /**
     * API namespace (default: cocart, can be white-labelled)
     *
     * @var string
     */
    protected string $namespace = 'cocart';

    /**
     * Session storage key for persisting cart key in $_SESSION
     *
     * @var string
     */
    protected string $sessionKey = 'cocart_cart_key';

    /**
     * Whether to automatically persist/restore cart key via $_SESSION
     *
     * @var bool
     */
    protected bool $autoStorage = true;

    /**
     * Cart key for guest sessions
     *
     * @var string|null
     */
    protected ?string $cartKey = null;

    /**
     * Authentication credentials
     *
     * @var array
     */
    protected array $auth = [];

    /**
     * JWT Token
     *
     * @var string|null
     */
    protected ?string $jwtToken = null;

    /**
     * JWT Refresh Token
     *
     * @var string|null
     */
    protected ?string $refreshToken = null;

    /**
     * JWT Manager for auto-refresh support
     *
     * @var JwtManager|null
     */
    protected ?JwtManager $jwtManager = null;

    /**
     * WooCommerce consumer key (for admin operations)
     *
     * @var string|null
     */
    protected ?string $consumerKey = null;

    /**
     * WooCommerce consumer secret (for admin operations)
     *
     * @var string|null
     */
    protected ?string $consumerSecret = null;

    /**
     * Maximum number of retries for transient failures
     *
     * @var int
     */
    protected int $maxRetries = 0;

    /**
     * HTTP timeout in seconds
     *
     * @var int
     */
    protected int $timeout = 30;

    /**
     * Whether to verify SSL certificates
     *
     * @var bool
     */
    protected bool $verifySsl = true;

    /**
     * Header name used for authentication (default: Authorization)
     *
     * Some hosting providers or reverse proxies strip the standard
     * Authorization header. This allows using an alternative header name.
     *
     * @var string
     */
    protected string $authHeader = 'Authorization';

    /**
     * Whether ETag conditional requests are enabled
     *
     * @var bool
     */
    protected bool $etagEnabled = true;

    /**
     * In-memory ETag cache (URL → ETag value)
     *
     * @var array<string, string>
     */
    protected array $etagCache = [];

    /**
     * Custom headers to send with requests
     *
     * @var array
     */
    protected array $customHeaders = [];

    /**
     * Last response from the API
     *
     * @var Response|null
     */
    protected ?Response $lastResponse = null;

    /**
     * HTTP adapter instance
     *
     * @var HttpAdapterInterface|null
     */
    protected ?HttpAdapterInterface $httpAdapter = null;

    /**
     * Preferred HTTP adapter name
     *
     * @var string|null
     */
    protected ?string $preferredAdapter = null;

    /**
     * Cart endpoint instance
     *
     * @var Cart|null
     */
    protected ?Cart $cart = null;

    /**
     * Products endpoint instance
     *
     * @var Products|null
     */
    protected ?Products $products = null;

    /**
     * Store endpoint instance
     *
     * @var Store|null
     */
    protected ?Store $store = null;

    /**
     * Sessions endpoint instance
     *
     * @var Sessions|null
     */
    protected ?Sessions $sessions = null;

    /**
     * Batch endpoint instance
     *
     * @var Batch|null
     */
    protected ?Batch $batch = null;

    /**
     * Constructor
     *
     * @param string $storeUrl The WooCommerce store URL
     * @param array  $options  Configuration options:
     *                         - cart_key: string (existing cart key for guest)
     *                         - username: string (for Basic Auth)
     *                         - password: string (for Basic Auth)
     *                         - jwt_token: string (for JWT Auth)
     *                         - jwt_refresh_token: string (JWT refresh token)
     *                         - consumer_key: string (WC API key for admin)
     *                         - consumer_secret: string (WC API secret for admin)
     *                         - timeout: int (HTTP timeout in seconds)
     *                         - verify_ssl: bool (verify SSL certificates)
     *                         - rest_prefix: string (WordPress REST prefix, default 'wp-json')
     *                         - namespace: string (API namespace, default 'cocart')
     *                         - auth_header: string (custom auth header name, default 'Authorization')
     *                         - headers: array (custom headers)
     *                         - http_adapter: string|HttpAdapterInterface (guzzle, curl, wordpress, stream, or instance)
     *                         - session_key: string (PHP session key for cart key storage, default 'cocart_cart_key')
     *                         - auto_storage: bool (auto-persist cart key to $_SESSION, default true)
     *                         - max_retries: int (retry transient failures up to N times, default 0)
     *                         - etag: bool (enable ETag conditional requests, default true)
     */
    public function __construct(string $storeUrl, array $options = [])
    {
        $this->storeUrl = rtrim($storeUrl, '/');

        // Set options
        if (isset($options['cart_key'])) {
            $this->cartKey = $options['cart_key'];
        }

        if (isset($options['username']) && isset($options['password'])) {
            $this->auth = [
                'username' => $options['username'],
                'password' => $options['password'],
            ];
        }

        if (isset($options['jwt_token'])) {
            $this->jwtToken = $options['jwt_token'];
        }

        if (isset($options['jwt_refresh_token'])) {
            $this->refreshToken = $options['jwt_refresh_token'];
        }

        if (isset($options['consumer_key']) && isset($options['consumer_secret'])) {
            $this->consumerKey = $options['consumer_key'];
            $this->consumerSecret = $options['consumer_secret'];
        }

        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }

        if (isset($options['verify_ssl'])) {
            $this->verifySsl = (bool) $options['verify_ssl'];
        }

        if (isset($options['rest_prefix'])) {
            $this->restPrefix = trim($options['rest_prefix'], '/');
        }

        if (isset($options['namespace'])) {
            $this->namespace = trim($options['namespace'], '/');
        }

        if (isset($options['auth_header'])) {
            $this->authHeader = $options['auth_header'];
        }

        if (isset($options['headers'])) {
            $this->customHeaders = $options['headers'];
        }

        if (isset($options['session_key'])) {
            $this->sessionKey = $options['session_key'];
        }

        if (isset($options['auto_storage'])) {
            $this->autoStorage = (bool) $options['auto_storage'];
        }

        if (isset($options['max_retries'])) {
            $this->maxRetries = max(0, (int) $options['max_retries']);
        }

        if (isset($options['etag'])) {
            $this->etagEnabled = (bool) $options['etag'];
        }

        // Set HTTP adapter
        if (isset($options['http_adapter'])) {
            if ($options['http_adapter'] instanceof HttpAdapterInterface) {
                $this->httpAdapter = $options['http_adapter'];
            } else {
                $this->preferredAdapter = $options['http_adapter'];
            }
        }

        // Restore cart key from PHP session if not explicitly provided
        if ($this->autoStorage && $this->cartKey === null) {
            $this->ensureSessionStarted();
            if (isset($_SESSION[$this->sessionKey])) {
                $this->cartKey = $_SESSION[$this->sessionKey];
            }
        }
    }

    /**
     * Create a new instance with fluent interface
     *
     * @param string $storeUrl The WooCommerce store URL
     * @return static
     */
    public static function create(string $storeUrl): static
    {
        return new static($storeUrl);
    }

    /**
     * Set the cart key for guest session tracking
     *
     * @param string $cartKey The cart key
     * @return $this
     */
    public function setCartKey(string $cartKey): static
    {
        $this->cartKey = $cartKey;
        return $this;
    }

    /**
     * Get the current cart key
     *
     * @return string|null
     */
    public function getCartKey(): ?string
    {
        return $this->cartKey;
    }

    /**
     * Set Basic Auth credentials for customer authentication
     *
     * @param string $username Username, email, or phone number
     * @param string $password Password
     * @return $this
     */
    public function setAuth(string $username, string $password): static
    {
        $this->auth = [
            'username' => $username,
            'password' => $password,
        ];
        // Clear JWT when using Basic Auth
        $this->jwtToken = null;
        return $this;
    }

    /**
     * Set JWT token for authentication
     *
     * @param string $token JWT token
     * @return $this
     */
    public function setJwtToken(string $token): static
    {
        $this->jwtToken = $token;
        // Clear Basic Auth when using JWT
        $this->auth = [];
        return $this;
    }

    /**
     * Get the current JWT token
     *
     * @return string|null
     */
    public function getJwtToken(): ?string
    {
        return $this->jwtToken;
    }

    /**
     * Set the JWT refresh token
     *
     * @param string $token Refresh token
     * @return $this
     */
    public function setRefreshToken(string $token): static
    {
        $this->refreshToken = $token;
        return $this;
    }

    /**
     * Get the current refresh token
     *
     * @return string|null
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Check if a JWT token is set
     *
     * @return bool
     */
    public function hasJwtToken(): bool
    {
        return $this->jwtToken !== null && $this->jwtToken !== '';
    }

    /**
     * Clear JWT and refresh tokens without affecting cart key
     *
     * @return $this
     */
    public function clearJwtToken(): static
    {
        $this->jwtToken = null;
        $this->refreshToken = null;
        return $this;
    }

    /**
     * Set the JWT Manager for auto-refresh support
     *
     * @param JwtManager $jwtManager JWT Manager instance
     * @return $this
     */
    public function setJwtManager(JwtManager $jwtManager): static
    {
        $this->jwtManager = $jwtManager;
        return $this;
    }

    /**
     * Set WooCommerce REST API credentials (for admin operations like Sessions)
     *
     * @param string $consumerKey    WooCommerce consumer key
     * @param string $consumerSecret WooCommerce consumer secret
     * @return $this
     */
    public function setWooCommerceCredentials(string $consumerKey, string $consumerSecret): static
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        return $this;
    }

    /**
     * Set HTTP timeout
     *
     * @param int $seconds Timeout in seconds
     * @return $this
     */
    public function setTimeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set the maximum number of retries for transient failures (429, 503, timeouts)
     *
     * @param int $retries Maximum retry attempts (0 to disable)
     * @return $this
     */
    public function setMaxRetries(int $retries): static
    {
        $this->maxRetries = max(0, $retries);
        return $this;
    }

    /**
     * Set SSL verification
     *
     * @param bool $verify Whether to verify SSL
     * @return $this
     */
    public function setVerifySsl(bool $verify): static
    {
        $this->verifySsl = $verify;
        return $this;
    }

    /**
     * Set the WordPress REST API prefix
     *
     * @param string $prefix REST prefix (e.g. 'wp-json', 'api')
     * @return $this
     */
    public function setRestPrefix(string $prefix): static
    {
        $this->restPrefix = trim($prefix, '/');
        return $this;
    }

    /**
     * Get the WordPress REST API prefix
     *
     * @return string
     */
    public function getRestPrefix(): string
    {
        return $this->restPrefix;
    }

    /**
     * Set the API namespace (for white-labelling)
     *
     * @param string $namespace API namespace (e.g. 'cocart', 'mystore')
     * @return $this
     */
    public function setNamespace(string $namespace): static
    {
        $this->namespace = trim($namespace, '/');
        return $this;
    }

    /**
     * Get the API namespace
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Set the authentication header name
     *
     * Use this when the standard Authorization header is stripped by a
     * reverse proxy or hosting provider (e.g. Cloudflare, Nginx, Apache).
     *
     * @param string $header Header name (e.g. 'X-Authorization')
     * @return $this
     */
    public function setAuthHeader(string $header): static
    {
        $this->authHeader = $header;
        return $this;
    }

    /**
     * Get the authentication header name
     *
     * @return string
     */
    public function getAuthHeader(): string
    {
        return $this->authHeader;
    }

    /**
     * Add custom header
     *
     * @param string $name  Header name
     * @param string $value Header value
     * @return $this
     */
    public function addHeader(string $name, string $value): static
    {
        $this->customHeaders[$name] = $value;
        return $this;
    }

    /**
     * Enable or disable ETag conditional requests
     *
     * When enabled, the SDK automatically stores ETags from responses
     * and sends If-None-Match on subsequent GET requests.
     *
     * @param bool $enabled Whether to enable ETag support
     * @return $this
     */
    public function setETag(bool $enabled): static
    {
        $this->etagEnabled = $enabled;
        return $this;
    }

    /**
     * Clear all cached ETags
     *
     * @return $this
     */
    public function clearETagCache(): static
    {
        $this->etagCache = [];
        return $this;
    }

    /**
     * Set the HTTP adapter to use
     *
     * @param HttpAdapterInterface|string $adapter Adapter instance or name (guzzle, curl, wordpress, stream)
     * @return $this
     */
    public function setHttpAdapter(HttpAdapterInterface|string $adapter): static
    {
        if ($adapter instanceof HttpAdapterInterface) {
            $this->httpAdapter = $adapter;
        } else {
            $this->preferredAdapter = $adapter;
            $this->httpAdapter = null; // Reset to force recreation
        }
        return $this;
    }

    /**
     * Get the HTTP adapter instance
     *
     * @return HttpAdapterInterface
     * @throws CoCartException
     */
    protected function getHttpAdapter(): HttpAdapterInterface
    {
        if ($this->httpAdapter === null) {
            $this->httpAdapter = HttpAdapterFactory::create($this->preferredAdapter);
        }
        return $this->httpAdapter;
    }

    /**
     * Get the name of the current HTTP adapter
     *
     * @return string
     */
    public function getHttpAdapterName(): string
    {
        return $this->getHttpAdapter()::getName();
    }

    /**
     * Get list of available HTTP adapters
     *
     * @return array
     */
    public static function getAvailableHttpAdapters(): array
    {
        return HttpAdapterFactory::getAvailableAdapters();
    }

    // --- JWT convenience methods ---

    /**
     * Get or create the JWT Manager instance
     *
     * @return JwtManager
     */
    public function jwt(): JwtManager
    {
        if ($this->jwtManager === null) {
            $this->jwtManager = new JwtManager($this, null, [
                'auto_refresh' => true,
            ]);
        }
        return $this->jwtManager;
    }

    /**
     * Login with username and password
     *
     * Uses JWT authentication if the CoCart JWT plugin is installed,
     * otherwise falls back to Basic Auth automatically.
     *
     * @param string $username Username, email, or phone
     * @param string $password Password
     * @return Response The login response (contains user profile data)
     * @throws CoCartException
     */
    public function login(string $username, string $password): Response
    {
        return $this->jwt()->login($username, $password);
    }

    /**
     * Logout — clear all JWT tokens
     *
     * @return $this
     */
    public function logout(): static
    {
        // Call the server logout endpoint to invalidate the session
        try {
            $this->post('logout');
        } catch (\Throwable $e) {
            // Continue even if server call fails — always clear local tokens
        }

        $this->jwt()->clearTokens();
        return $this;
    }

    /**
     * Check if authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return !empty($this->auth) || !empty($this->jwtToken);
    }

    /**
     * Check if using guest session
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return !$this->isAuthenticated();
    }

    /**
     * Get Cart endpoint
     *
     * @return Cart
     */
    public function cart(): Cart
    {
        if ($this->cart === null) {
            $this->cart = new Cart($this);
        }
        return $this->cart;
    }

    /**
     * Get Products endpoint
     *
     * @return Products
     */
    public function products(): Products
    {
        if ($this->products === null) {
            $this->products = new Products($this);
        }
        return $this->products;
    }

    /**
     * Get Store endpoint
     *
     * @return Store
     */
    public function store(): Store
    {
        if ($this->store === null) {
            $this->store = new Store($this);
        }
        return $this->store;
    }

    /**
     * Get Sessions endpoint (requires admin credentials)
     *
     * @return Sessions
     */
    public function sessions(): Sessions
    {
        if ($this->sessions === null) {
            $this->sessions = new Sessions($this);
        }
        return $this->sessions;
    }

    /**
     * Get Batch endpoint (requires CoCart Plus)
     *
     * @return Batch
     */
    public function batch(): Batch
    {
        if ($this->batch === null) {
            $this->batch = new Batch($this);
        }
        return $this->batch;
    }

    /**
     * Make a GET request
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function get(string $endpoint, array $params = []): Response
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function post(string $endpoint, array $data = [], array $params = []): Response
    {
        return $this->request('POST', $endpoint, $params, $data);
    }

    /**
     * Make a PUT request
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function put(string $endpoint, array $data = [], array $params = []): Response
    {
        return $this->request('PUT', $endpoint, $params, $data);
    }

    /**
     * Make a DELETE request
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return Response
     * @throws CoCartException
     */
    public function delete(string $endpoint, array $params = []): Response
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    /**
     * Make an HTTP request to the API
     *
     * If a JwtManager with auto-refresh is attached and the request fails
     * with an authentication error, the token will be refreshed and the
     * request retried once.
     *
     * @param string     $method   HTTP method
     * @param string     $endpoint API endpoint
     * @param array      $params   Query parameters
     * @param array|null $data     Request body data
     * @return Response
     * @throws CoCartException
     */
    public function request(string $method, string $endpoint, array $params = [], ?array $data = null): Response
    {
        try {
            return $this->executeRequest($method, $endpoint, $params, $data);
        } catch (AuthenticationException $e) {
            if ($this->jwtManager !== null
                && $this->jwtManager->isAutoRefreshEnabled()
                && $this->refreshToken !== null
            ) {
                try {
                    $this->jwtManager->refresh();
                    return $this->executeRequest($method, $endpoint, $params, $data);
                } catch (\Throwable) {
                    throw $e;
                }
            }
            throw $e;
        }
    }

    /**
     * Make an HTTP request using a full REST route (relative to the REST prefix)
     *
     * Unlike request(), this does NOT prepend the namespace/version prefix.
     * Used for endpoints outside the standard versioned namespace (e.g., jwt/*).
     *
     * @param string     $method HTTP method
     * @param string     $route  Full REST route relative to the REST prefix
     * @param array      $params Query parameters
     * @param array|null $data   Request body data
     * @return Response
     * @throws CoCartException
     */
    public function requestRaw(string $method, string $route, array $params = [], ?array $data = null): Response
    {
        $url = sprintf('%s/%s/%s', $this->storeUrl, $this->restPrefix, ltrim($route, '/'));

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = $this->buildHeaders();
        $body = $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : null;

        $httpResponse = $this->getHttpAdapter()->request(
            $method,
            $url,
            $headers,
            $body,
            [
                'timeout' => $this->timeout,
                'verify_ssl' => $this->verifySsl,
            ]
        );

        $this->lastResponse = new Response(
            $httpResponse->statusCode,
            $httpResponse->headers,
            $httpResponse->body
        );

        $this->extractCartKeyFromHeaders($this->lastResponse);

        if ($httpResponse->statusCode >= 400) {
            $this->handleErrorResponse($this->lastResponse);
        }

        return $this->lastResponse;
    }

    /**
     * Execute an HTTP request (internal, no auto-refresh)
     *
     * @param string     $method   HTTP method
     * @param string     $endpoint API endpoint
     * @param array      $params   Query parameters
     * @param array|null $data     Request body data
     * @return Response
     * @throws CoCartException
     */
    protected function executeRequest(string $method, string $endpoint, array $params = [], ?array $data = null): Response
    {
        $url = $this->buildUrl($endpoint, $params);
        $headers = $this->buildHeaders();
        $body = $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : null;
        $adapterOptions = [
            'timeout' => $this->timeout,
            'verify_ssl' => $this->verifySsl,
        ];

        // Add If-None-Match header for GET requests when ETag is cached
        $skipCache = isset($params['_skip_cache']) && $params['_skip_cache'];
        if ($this->etagEnabled && $method === 'GET' && !$skipCache && isset($this->etagCache[$url])) {
            $headers['If-None-Match'] = $this->etagCache[$url];
        }

        $attempt = 0;

        while (true) {
            try {
                $httpResponse = $this->getHttpAdapter()->request(
                    $method,
                    $url,
                    $headers,
                    $body,
                    $adapterOptions
                );
            } catch (CoCartException $e) {
                // Retry on timeout/connection errors
                if ($attempt < $this->maxRetries && $this->isTransientError($e)) {
                    $attempt++;
                    $this->retrySleep($attempt);
                    continue;
                }
                throw $e;
            }

            $this->lastResponse = new Response(
                $httpResponse->statusCode,
                $httpResponse->headers,
                $httpResponse->body
            );

            // Extract cart key from response headers for guest sessions
            $this->extractCartKeyFromHeaders($this->lastResponse);

            // Store ETag from response for future conditional requests
            if ($this->etagEnabled && $method === 'GET') {
                $etag = $this->lastResponse->getETag();
                if ($etag !== null) {
                    $this->etagCache[$url] = $etag;
                }
            }

            // Retry on transient HTTP status codes (429, 503)
            if ($attempt < $this->maxRetries && $this->isRetryableStatus($httpResponse->statusCode)) {
                $attempt++;
                $this->retrySleep($attempt, $this->lastResponse);
                continue;
            }

            // Handle errors
            if ($httpResponse->statusCode >= 400) {
                $this->handleErrorResponse($this->lastResponse);
            }

            return $this->lastResponse;
        }
    }

    /**
     * Check if an exception represents a transient error worth retrying
     *
     * @param CoCartException $e The exception
     * @return bool
     */
    protected function isTransientError(CoCartException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connection');
    }

    /**
     * Check if an HTTP status code is retryable
     *
     * @param int $statusCode HTTP status code
     * @return bool
     */
    protected function isRetryableStatus(int $statusCode): bool
    {
        return $statusCode === 429 || $statusCode === 503;
    }

    /**
     * Sleep before retrying with exponential backoff
     *
     * @param int           $attempt  Current attempt number (1-based)
     * @param Response|null $response Last response (to check Retry-After header)
     */
    protected function retrySleep(int $attempt, ?Response $response = null): void
    {
        // Honor Retry-After header if present
        if ($response !== null) {
            $retryAfter = $response->getHeader('Retry-After');
            if ($retryAfter !== null && is_numeric($retryAfter)) {
                sleep(min((int) $retryAfter, 60));
                return;
            }
        }

        // Exponential backoff: 1s, 2s, 4s, ...
        sleep(min((int) pow(2, $attempt - 1), 30));
    }

    /**
     * Build the full API URL
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return string
     */
    protected function buildUrl(string $endpoint, array $params = []): string
    {
        // Add cart_key to params if set and not authenticated
        if ($this->cartKey && !$this->isAuthenticated()) {
            $params['cart_key'] = $this->cartKey;
        }

        // Normalize 'fields' to '_fields' (WordPress REST API standard)
        if (isset($params['fields']) && !isset($params['_fields'])) {
            $params['_fields'] = $params['fields'];
            unset($params['fields']);
        }

        $url = sprintf(
            '%s/%s/%s/%s/%s',
            $this->storeUrl,
            $this->restPrefix,
            $this->namespace,
            self::API_VERSION,
            ltrim($endpoint, '/')
        );

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Build request headers
     *
     * @return array
     */
    protected function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'CoCart-PHP-SDK/' . self::VERSION,
        ];

        // Add authentication
        if (!empty($this->jwtToken)) {
            $headers[$this->authHeader] = 'Bearer ' . $this->jwtToken;
        } elseif (!empty($this->auth)) {
            $credentials = base64_encode($this->auth['username'] . ':' . $this->auth['password']);
            $headers[$this->authHeader] = 'Basic ' . $credentials;
        } elseif (!empty($this->consumerKey) && !empty($this->consumerSecret)) {
            $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);
            $headers[$this->authHeader] = 'Basic ' . $credentials;
        }

        // Add cart key header (alternative to query param)
        if ($this->cartKey && !$this->isAuthenticated()) {
            $headers['Cart-Key'] = $this->cartKey;
        }

        // Add custom headers
        return array_merge($headers, $this->customHeaders);
    }

    /**
     * Extract cart key from response headers
     *
     * @param Response $response The response object
     */
    protected function extractCartKeyFromHeaders(Response $response): void
    {
        // Check for Cart-Key header (current API version)
        $cartKey = $response->getHeader('Cart-Key');

        if ($cartKey !== null) {
            $this->cartKey = $cartKey;

            if ($this->autoStorage) {
                $this->ensureSessionStarted();
                $_SESSION[$this->sessionKey] = $cartKey;
            }
        }
    }

    /**
     * Ensure a PHP session is started
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Handle error responses from the API
     *
     * @param Response $response The response object
     * @throws CoCartException
     */
    protected function handleErrorResponse(Response $response): void
    {
        $data = $response->toArray();
        $code = $data['code'] ?? 'unknown_error';
        $message = $data['message'] ?? 'An unknown error occurred';
        $httpCode = $response->getStatusCode();

        // Authentication errors (401, 403 with auth codes)
        if ($httpCode === 401 || $httpCode === 403 || str_contains($code, 'authenticat')) {
            throw new AuthenticationException($message, $httpCode, $code, $data);
        }

        // Validation errors
        if ($httpCode === 400 || str_contains($code, 'invalid') || str_contains($code, 'missing')) {
            throw new ValidationException($message, $httpCode, $code, $data);
        }

        throw new CoCartException($message, $httpCode, $code, $data);
    }

    /**
     * Get the last response
     *
     * @return Response|null
     */
    public function getLastResponse(): ?Response
    {
        return $this->lastResponse;
    }

    /**
     * Get the store URL
     *
     * @return string
     */
    public function getStoreUrl(): string
    {
        return $this->storeUrl;
    }

    /**
     * Clear authentication and cart key
     *
     * @return $this
     */
    public function clearSession(): static
    {
        $this->auth = [];
        $this->jwtToken = null;
        $this->refreshToken = null;
        $this->cartKey = null;

        if ($this->autoStorage) {
            $this->ensureSessionStarted();
            unset($_SESSION[$this->sessionKey]);
        }

        return $this;
    }

    /**
     * Transfer cart from guest to authenticated user
     * 
     * This should be called when a guest customer logs in to merge their cart.
     *
     * @param string $username Username, email, or phone
     * @param string $password Password
     * @return Response
     * @throws CoCartException
     */
    public function transferCartToCustomer(string $username, string $password): Response
    {
        $guestCartKey = $this->cartKey;
        
        // Set authentication
        $this->setAuth($username, $password);
        
        // Get cart with the guest cart key to merge
        if ($guestCartKey) {
            return $this->cart()->get(['cart_key' => $guestCartKey]);
        }
        
        return $this->cart()->get();
    }
}
