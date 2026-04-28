<?php
declare(strict_types=1);

/**
 * Session Manager
 *
 * Helper class for managing cart sessions, especially useful for
 * tracking guest customer carts and handling the transition to authenticated users.
 *
 * @package CoCart\SDK
 */

namespace CoCart;

class SessionManager
{
    /**
     * Storage adapter for persisting cart key
     *
     * @var SessionStorageInterface|null
     */
    protected ?SessionStorageInterface $storage = null;

    /**
     * Storage key for the cart key
     *
     * @var string
     */
    protected string $storageKey = 'cocart_cart_key';

    /**
     * CoCart client instance
     *
     * @var CoCartInterface
     */
    protected CoCartInterface $client;

    /**
     * JWT Manager instance
     *
     * @var JwtManager|null
     */
    protected ?JwtManager $jwtManager = null;

    /**
     * Constructor
     *
     * @param CoCartInterface              $client  CoCart client instance
     * @param SessionStorageInterface|null $storage Storage adapter (optional)
     */
    public function __construct(CoCartInterface $client, ?SessionStorageInterface $storage = null)
    {
        $this->client = $client;
        $this->storage = $storage;

        // Restore cart key from storage if available
        if ($this->storage !== null) {
            $storedKey = $this->storage->get($this->storageKey);
            if ($storedKey !== null) {
                $this->client->setCartKey($storedKey);
            }
        }
    }

    /**
     * Set a custom storage key name
     *
     * @param string $key Storage key name
     * @return $this
     */
    public function setStorageKey(string $key): static
    {
        $this->storageKey = $key;
        return $this;
    }

    /**
     * Get the current cart key
     *
     * @return string|null
     */
    public function getCartKey(): ?string
    {
        return $this->client->getCartKey();
    }

    /**
     * Set the cart key manually
     *
     * @param string $cartKey Cart key
     * @return $this
     */
    public function setCartKey(string $cartKey): static
    {
        $this->client->setCartKey($cartKey);
        $this->persistCartKey($cartKey);
        return $this;
    }

    /**
     * Initialize a new cart session
     *
     * This will make a request to the cart endpoint to get a new cart key
     * for a guest customer.
     *
     * @return string|null The new cart key
     */
    public function initializeCart(): ?string
    {
        $response = $this->client->cart()->get();
        $cartKey = $response->getCartKey() ?? $this->client->getCartKey();

        if ($cartKey !== null) {
            $this->persistCartKey($cartKey);
        }

        return $cartKey;
    }

    /**
     * Login and optionally transfer guest cart to the customer
     *
     * @param string $username   Username, email, or phone
     * @param string $password   Password
     * @param bool   $mergeCart  Whether to merge the guest cart
     * @return Response
     */
    public function login(string $username, string $password, bool $mergeCart = true): Response
    {
        $guestCartKey = $this->client->getCartKey();

        // Set authentication
        $this->client->setAuth($username, $password);

        // Clear stored guest cart key
        $this->clearStoredCartKey();

        // If we have a guest cart and want to merge, pass it with the request
        if ($mergeCart && $guestCartKey !== null) {
            return $this->client->cart()->get(['cart_key' => $guestCartKey]);
        }

        return $this->client->cart()->get();
    }

    /**
     * Login with JWT token
     *
     * @param string $token JWT token
     * @return Response
     */
    public function loginWithToken(string $token): Response
    {
        $guestCartKey = $this->client->getCartKey();

        $this->client->setJwtToken($token);
        $this->clearStoredCartKey();

        // Merge guest cart if available
        if ($guestCartKey !== null) {
            return $this->client->cart()->get(['cart_key' => $guestCartKey]);
        }

        return $this->client->cart()->get();
    }

    /**
     * Get the JWT manager instance
     *
     * Creates one if not already set, sharing the same client and storage adapter.
     *
     * @param array $options Options passed to JwtManager constructor
     * @return JwtManager
     */
    public function jwt(array $options = []): JwtManager
    {
        if ($this->jwtManager === null) {
            $this->jwtManager = new JwtManager($this->client, $this->storage, $options);
        }
        return $this->jwtManager;
    }

    /**
     * Login with JWT authentication
     *
     * Acquires JWT tokens via the login endpoint, then optionally merges the guest cart.
     *
     * @param string $username  Username, email, or phone
     * @param string $password  Password
     * @param bool   $mergeCart Whether to merge the guest cart
     * @return Response The login response containing user profile
     * @throws Exceptions\CoCartException
     */
    public function loginWithJwt(string $username, string $password, bool $mergeCart = true): Response
    {
        $guestCartKey = $this->client->getCartKey();

        $loginResponse = $this->jwt()->login($username, $password);

        $this->clearStoredCartKey();

        if ($mergeCart && $guestCartKey !== null) {
            $this->client->cart()->get(['cart_key' => $guestCartKey]);
        }

        return $loginResponse;
    }

    /**
     * Complete JWT login after a 2FA challenge, optionally merging guest cart
     *
     * Call this after catching TwoFactorRequiredException from loginWithJwt().
     *
     * @param string      $username  Username, email, or phone
     * @param string      $password  Password
     * @param string      $code      The 2FA code from the user
     * @param string|null $provider  Provider name (e.g. 'email', 'totp'); omit to use server default
     * @param bool        $mergeCart Whether to merge the guest cart
     * @return Response
     * @throws Exceptions\CoCartException
     */
    public function loginWithJwt2fa(
        string $username,
        string $password,
        string $code,
        ?string $provider = null,
        bool $mergeCart = true
    ): Response {
        $guestCartKey = $this->client->getCartKey();

        $loginResponse = $this->jwt()->loginWith2fa($username, $password, $code, $provider);

        $this->clearStoredCartKey();

        if ($mergeCart && $guestCartKey !== null) {
            $this->client->cart()->get(['cart_key' => $guestCartKey]);
        }

        return $loginResponse;
    }

    /**
     * Logout and start a new guest session
     *
     * @return $this
     */
    public function logout(): static
    {
        if ($this->jwtManager !== null) {
            $this->jwtManager->clearTokens();
        }

        $this->client->clearSession();
        $this->clearStoredCartKey();
        return $this;
    }

    /**
     * Persist cart key to storage
     *
     * @param string $cartKey Cart key to store
     */
    protected function persistCartKey(string $cartKey): void
    {
        if ($this->storage !== null) {
            $this->storage->set($this->storageKey, $cartKey);
        }
    }

    /**
     * Clear stored cart key
     */
    protected function clearStoredCartKey(): void
    {
        if ($this->storage !== null) {
            $this->storage->delete($this->storageKey);
        }
    }

    /**
     * Check if the current session is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->client->isAuthenticated();
    }

    /**
     * Check if the current session is a guest session
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return $this->client->isGuest();
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
}
