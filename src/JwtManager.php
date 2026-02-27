<?php
declare(strict_types=1);

/**
 * JWT Manager
 *
 * Handles JWT token lifecycle: acquisition via login, refresh, validation,
 * and optional persistence using a storage adapter.
 *
 * @package CoCart\SDK
 */

namespace CoCart;

use CoCart\Exceptions\AuthenticationException;
use CoCart\Exceptions\CoCartException;

class JwtManager
{
    /**
     * CoCart client instance
     *
     * @var CoCartInterface
     */
    protected CoCartInterface $client;

    /**
     * Storage adapter for persisting tokens
     *
     * @var SessionStorageInterface|null
     */
    protected ?SessionStorageInterface $storage;

    /**
     * Storage key for the JWT access token
     *
     * @var string
     */
    protected string $tokenStorageKey = 'cocart_jwt_token';

    /**
     * Storage key for the JWT refresh token
     *
     * @var string
     */
    protected string $refreshTokenStorageKey = 'cocart_jwt_refresh_token';

    /**
     * Whether to automatically refresh on authentication errors
     *
     * @var bool
     */
    protected bool $autoRefresh = false;

    /**
     * Guard flag to prevent recursive refresh attempts
     *
     * @var bool
     */
    protected bool $isRefreshing = false;

    /**
     * Constructor
     *
     * @param CoCartInterface              $client  CoCart client instance
     * @param SessionStorageInterface|null $storage Storage adapter for token persistence
     * @param array                        $options Options:
     *                                              - auto_refresh: bool (default false)
     *                                              - token_storage_key: string
     *                                              - refresh_token_storage_key: string
     */
    public function __construct(
        CoCartInterface $client,
        ?SessionStorageInterface $storage = null,
        array $options = []
    ) {
        $this->client = $client;
        $this->storage = $storage;

        if (isset($options['auto_refresh'])) {
            $this->autoRefresh = (bool) $options['auto_refresh'];
        }

        if (isset($options['token_storage_key'])) {
            $this->tokenStorageKey = $options['token_storage_key'];
        }

        if (isset($options['refresh_token_storage_key'])) {
            $this->refreshTokenStorageKey = $options['refresh_token_storage_key'];
        }

        $this->restoreTokensFromStorage();
    }

    /**
     * Login with username and password to acquire JWT tokens
     *
     * Calls POST {namespace}/v2/login and extracts jwt_token and
     * jwt_refresh from the extras field in the response.
     *
     * Throws AuthenticationException if the JWT token is not present
     * in the response (e.g. JWT Authentication plugin not installed).
     *
     * @param string $username Username, email, or phone
     * @param string $password Password
     * @return Response The full login response (contains user profile data)
     * @throws CoCartException
     */
    public function login(string $username, string $password): Response
    {
        $response = $this->client->post('login', [
            'username' => $username,
            'password' => $password,
        ]);

        $data = $response->toArray();

        $jwtToken = $data['extras']['jwt_token'] ?? null;
        $refreshToken = $data['extras']['jwt_refresh'] ?? null;

        if ($jwtToken === null) {
            throw new AuthenticationException(
                'JWT token not found in login response. Is the CoCart JWT Authentication plugin installed?',
                0,
                'cocart_jwt_missing'
            );
        }

        $this->client->setJwtToken($jwtToken);

        if ($refreshToken !== null) {
            $this->client->setRefreshToken($refreshToken);
        }

        $this->persistTokens();

        return $response;
    }

    /**
     * Refresh the JWT access token using the refresh token
     *
     * Calls POST {namespace}/jwt/refresh-token.
     * Updates both the access token and refresh token on success.
     *
     * @param string|null $refreshToken Refresh token (uses stored one if null)
     * @return Response
     * @throws AuthenticationException If no refresh token is available
     * @throws CoCartException
     */
    public function refresh(?string $refreshToken = null): Response
    {
        $refreshToken = $refreshToken ?? $this->client->getRefreshToken();

        if ($refreshToken === null || $refreshToken === '') {
            throw new AuthenticationException(
                'No refresh token available. Please login first.',
                0,
                'cocart_jwt_no_refresh_token'
            );
        }

        $route = $this->client->getNamespace() . '/jwt/refresh-token';
        $response = $this->client->requestRaw('POST', $route, [], [
            'refresh_token' => $refreshToken,
        ]);

        $data = $response->toArray();

        $newToken = $data['token'] ?? null;
        $newRefreshToken = $data['refresh_token'] ?? null;

        if ($newToken !== null) {
            $this->client->setJwtToken($newToken);
        }

        if ($newRefreshToken !== null) {
            $this->client->setRefreshToken($newRefreshToken);
        }

        $this->persistTokens();

        return $response;
    }

    /**
     * Validate the current JWT token with the server
     *
     * Calls POST {namespace}/jwt/validate-token.
     *
     * @return bool True if token is valid
     * @throws CoCartException On network or server errors (not auth errors)
     */
    public function validate(): bool
    {
        if (!$this->client->hasJwtToken()) {
            return false;
        }

        try {
            $route = $this->client->getNamespace() . '/jwt/validate-token';
            $response = $this->client->requestRaw('POST', $route);
            return $response->isSuccessful();
        } catch (AuthenticationException) {
            return false;
        }
    }

    /**
     * Execute a callback with automatic token refresh on authentication error
     *
     * If the callback throws an AuthenticationException, this method will
     * attempt to refresh the token and retry once.
     *
     * @param callable $callback A callable that receives the CoCart client
     * @return Response
     * @throws CoCartException
     */
    public function withAutoRefresh(callable $callback): Response
    {
        try {
            return $callback($this->client);
        } catch (AuthenticationException $e) {
            if ($this->isRefreshing || $this->client->getRefreshToken() === null) {
                throw $e;
            }

            $this->isRefreshing = true;

            try {
                $this->refresh();
                $result = $callback($this->client);
                return $result;
            } catch (\Throwable) {
                throw $e;
            } finally {
                $this->isRefreshing = false;
            }
        }
    }

    /**
     * Clear all JWT tokens from client and storage
     *
     * @return $this
     */
    public function clearTokens(): static
    {
        $this->client->clearJwtToken();

        if ($this->storage !== null) {
            $this->storage->delete($this->tokenStorageKey);
            $this->storage->delete($this->refreshTokenStorageKey);
        }

        return $this;
    }

    /**
     * Check if tokens are available
     *
     * @return bool
     */
    public function hasTokens(): bool
    {
        return $this->client->hasJwtToken();
    }

    /**
     * Check if the current JWT token is expired by decoding its payload
     *
     * Returns true if the token is expired or cannot be decoded.
     * Returns false if the token has no expiry claim or is still valid.
     *
     * @param int $leeway Seconds of leeway before actual expiry (default 30)
     * @return bool
     */
    public function isTokenExpired(int $leeway = 30): bool
    {
        $token = $this->client->getJwtToken();
        if ($token === null || $token === '') {
            return true;
        }

        $payload = $this->decodeTokenPayload($token);
        if ($payload === null) {
            return true;
        }

        if (!isset($payload['exp'])) {
            return false;
        }

        return time() >= ($payload['exp'] - $leeway);
    }

    /**
     * Get the expiry timestamp of the current JWT token
     *
     * @return int|null Unix timestamp, or null if no token or no exp claim
     */
    public function getTokenExpiry(): ?int
    {
        $token = $this->client->getJwtToken();
        if ($token === null || $token === '') {
            return null;
        }

        $payload = $this->decodeTokenPayload($token);
        return $payload['exp'] ?? null;
    }

    /**
     * Decode the payload section of a JWT token without verification
     *
     * @param string $token JWT token
     * @return array|null Decoded payload, or null if malformed
     */
    protected function decodeTokenPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Set auto-refresh behavior
     *
     * @param bool $enabled Whether to enable auto-refresh
     * @return $this
     */
    public function setAutoRefresh(bool $enabled): static
    {
        $this->autoRefresh = $enabled;
        return $this;
    }

    /**
     * Check if auto-refresh is enabled
     *
     * @return bool
     */
    public function isAutoRefreshEnabled(): bool
    {
        return $this->autoRefresh;
    }

    /**
     * Get the CoCart client instance
     *
     * @return CoCartInterface
     */
    public function getClient(): CoCartInterface
    {
        return $this->client;
    }

    /**
     * Restore tokens from storage into the client
     */
    protected function restoreTokensFromStorage(): void
    {
        if ($this->storage === null) {
            return;
        }

        $storedToken = $this->storage->get($this->tokenStorageKey);
        $storedRefresh = $this->storage->get($this->refreshTokenStorageKey);

        if ($storedToken !== null && $storedToken !== '') {
            $this->client->setJwtToken($storedToken);
        }

        if ($storedRefresh !== null && $storedRefresh !== '') {
            $this->client->setRefreshToken($storedRefresh);
        }
    }

    /**
     * Persist current tokens to storage
     */
    protected function persistTokens(): void
    {
        if ($this->storage === null) {
            return;
        }

        $token = $this->client->getJwtToken();
        $refreshToken = $this->client->getRefreshToken();

        if ($token !== null && $token !== '') {
            $this->storage->set($this->tokenStorageKey, $token);
        }

        if ($refreshToken !== null && $refreshToken !== '') {
            $this->storage->set($this->refreshTokenStorageKey, $refreshToken);
        }
    }
}
