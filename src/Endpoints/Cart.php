<?php
declare(strict_types=1);

/**
 * Cart Endpoint
 * 
 * Handles all cart-related API operations including adding items,
 * updating quantities, removing items, and managing the cart session.
 * 
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Response;

class Cart extends Endpoint
{
    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = 'cart';

    /**
     * Get the cart contents
     *
     * @param array $params Query parameters
     *                      - cart_key: string (for guest sessions)
     *                      - fields: string (comma-separated list of fields to return)
     *                      - default: bool (return default cart data)
     *                      - thumb: bool (include product thumbnails)
     * @return Response
     */
    public function get(string $path = '', array $params = []): Response
    {
        if ($path !== '') {
            return parent::get($path, $params);
        }

        return $this->client->get($this->endpoint, $params);
    }

    /**
     * Create a new guest cart session
     *
     * Generates a fresh cart_key without adding any items.
     * Only available for non-authenticated (guest) users.
     *
     * @return Response
     */
    public function create(): Response
    {
        $this->client->requiresBasic('cart()->create');
        return $this->post('');
    }

    /**
     * Get all items in the cart
     *
     * Returns only the items array (lighter than fetching the full cart).
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function getItems(array $params = []): Response
    {
        return parent::get('items', $params);
    }

    /**
     * Get a single item from the cart by its item key
     *
     * @param string $itemKey The cart item key
     * @param array  $params  Query parameters
     * @return Response
     */
    public function getItem(string $itemKey, array $params = []): Response
    {
        return parent::get("item/{$itemKey}", $params);
    }

    /**
     * Add an item to the cart
     *
     * @param string|int $productId   Product ID or variation ID
     * @param int        $quantity    Quantity to add
     * @param array      $options     Additional options:
     *                                - variation: array (variation attributes)
     *                                - item_data: array (custom item data)
     *                                - email: string (customer billing email)
     *                                - phone: string (customer billing phone)
     *                                - price: string (override price)
     *                                - return_item: bool (return only item details)
     * @return Response
     */
    public function addItem($productId, int $quantity = 1, array $options = []): Response
    {
        $data = array_merge([
            'id' => (string) $productId,
            'quantity' => (string) $quantity,
        ], $options);

        return $this->post('add-item', $data);
    }

    /**
     * Add multiple items to the cart in a single request
     *
     * @param array $items Array of items, each with:
     *                     - id: string (product/variation ID)
     *                     - quantity: string
     *                     - variation: array (optional)
     *                     - item_data: array (optional)
     * @return Response
     */
    public function addItems(array $items): Response
    {
        // Ensure quantity is a string as required by the API
        foreach ($items as &$item) {
            if (isset($item['quantity'])) {
                $item['quantity'] = (string) $item['quantity'];
            }
            if (isset($item['id'])) {
                $item['id'] = (string) $item['id'];
            }
        }

        return $this->post('add-items', ['items' => $items]);
    }

    /**
     * Update an item in the cart
     *
     * @param string $itemKey  The cart item key
     * @param int    $quantity New quantity
     * @param array  $options  Additional options
     * @return Response
     */
    public function updateItem(string $itemKey, int $quantity, array $options = []): Response
    {
        $data = array_merge([
            'quantity' => (string) $quantity,
        ], $options);

        return $this->post("item/{$itemKey}", $data);
    }

    /**
     * Update multiple items in a single request
     *
     * @param array $items Associative array of item_key => quantity, or
     *                     array of ['item_key' => string, 'quantity' => int, ...options]
     * @return Response
     */
    public function updateItems(array $items): Response
    {
        $formatted = [];

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                // Full format: ['item_key' => '...', 'quantity' => 2, ...]
                $value['quantity'] = (string) ($value['quantity'] ?? 1);
                $formatted[] = $value;
            } else {
                // Shorthand: item_key => quantity
                $formatted[] = [
                    'item_key' => $key,
                    'quantity' => (string) $value,
                ];
            }
        }

        return $this->post('update', ['items' => $formatted]);
    }

    /**
     * Remove an item from the cart
     *
     * @param string $itemKey The cart item key
     * @return Response
     */
    public function removeItem(string $itemKey): Response
    {
        return $this->delete("item/{$itemKey}");
    }

    /**
     * Remove multiple items from the cart
     *
     * @param array $itemKeys Array of cart item keys to remove
     * @return Response
     */
    public function removeItems(array $itemKeys): Response
    {
        // Set quantity to 0 for each item to remove them
        $items = [];
        foreach ($itemKeys as $key) {
            $items[] = [
                'item_key' => $key,
                'quantity' => '0',
            ];
        }

        return $this->post('update', ['items' => $items]);
    }

    /**
     * Restore a removed item to the cart
     *
     * @param string $itemKey The cart item key
     * @return Response
     */
    public function restoreItem(string $itemKey): Response
    {
        return $this->put("item/{$itemKey}");
    }

    /**
     * Clear all items from the cart
     *
     * @return Response
     */
    public function clear(): Response
    {
        return $this->post('clear');
    }

    /**
     * Calculate cart totals
     *
     * @param array $params Additional parameters
     * @return Response
     */
    public function calculate(array $params = []): Response
    {
        return $this->post('calculate', $params);
    }

    /**
     * Get cart totals
     *
     * @param bool $html Whether to return formatted HTML values
     * @return Response
     */
    public function getTotals(bool $html = false): Response
    {
        $params = $html ? ['html' => 'true'] : [];
        return $this->client->get('cart/totals', $params);
    }

    /**
     * Get count of items in cart
     *
     * @return Response
     */
    public function getItemCount(): Response
    {
        return $this->client->get('cart/items/count');
    }

    /**
     * Update the entire cart
     *
     * @param array $data Cart update data
     * @return Response
     */
    public function update(array $data): Response
    {
        return $this->post('update', $data);
    }

    /**
     * Apply a coupon to the cart
     *
     * @param string $couponCode The coupon code
     * @return Response
     */
    public function applyCoupon(string $couponCode): Response
    {
        return $this->post('apply-coupon', ['coupon' => $couponCode]);
    }

    /**
     * Remove a coupon from the cart
     *
     * @param string $couponCode The coupon code
     * @return Response
     */
    public function removeCoupon(string $couponCode): Response
    {
        return $this->delete("coupons/{$couponCode}");
    }

    /**
     * Get applied coupons
     *
     * @return Response
     */
    public function getCoupons(): Response
    {
        return parent::get('', ['_fields' => 'coupons']);
    }

    /**
     * Update customer details
     *
     * @param array $billing  Billing address fields
     * @param array $shipping Shipping address fields
     * @return Response
     */
    public function updateCustomer(array $billing = [], array $shipping = []): Response
    {
        $data = [];

        if (!empty($billing)) {
            foreach ($billing as $key => $value) {
                $data["billing_{$key}"] = $value;
            }
        }

        if (!empty($shipping)) {
            foreach ($shipping as $key => $value) {
                $data["shipping_{$key}"] = $value;
            }
        }

        return $this->post('update', $data);
    }

    /**
     * Get customer details from cart
     *
     * @return Response
     */
    public function getCustomer(): Response
    {
        return parent::get('', ['_fields' => 'customer']);
    }

    /**
     * Get shipping methods available for the cart
     *
     * @return Response
     */
    public function getShippingMethods(): Response
    {
        return parent::get('', ['_fields' => 'shipping']);
    }

    /**
     * Set shipping method for the cart
     *
     * @param string $methodKey Shipping method key (e.g., 'flat_rate:1')
     * @return Response
     */
    public function setShippingMethod(string $methodKey): Response
    {
        return $this->post('set-shipping-method', ['method_key' => $methodKey]);
    }

    /**
     * Calculate shipping for the cart
     *
     * @param array $address Shipping address
     * @return Response
     */
    public function calculateShipping(array $address): Response
    {
        return $this->post('calculate/shipping', $address);
    }

    /**
     * Get cart fees
     *
     * @return Response
     */
    public function getFees(): Response
    {
        return parent::get('', ['_fields' => 'fees']);
    }

    /**
     * Add a fee to the cart
     *
     * @param string $name    Fee name
     * @param float  $amount  Fee amount
     * @param bool   $taxable Whether the fee is taxable
     * @return Response
     */
    public function addFee(string $name, float $amount, bool $taxable = false): Response
    {
        return $this->post('add-fee', [
            'name' => $name,
            'amount' => $amount,
            'taxable' => $taxable,
        ]);
    }

    /**
     * Remove all fees from the cart
     *
     * @return Response
     */
    public function removeFees(): Response
    {
        return $this->post('remove-fees');
    }

    /**
     * Get cross-sell products for the cart
     *
     * @return Response
     */
    public function getCrossSells(): Response
    {
        return parent::get('', ['_fields' => 'cross_sells']);
    }

    /**
     * Get removed items that can be restored
     *
     * @return Response
     */
    public function getRemovedItems(): Response
    {
        return parent::get('', ['_fields' => 'removed_items']);
    }

    /**
     * Check if coupons applied are still valid
     *
     * @return Response
     */
    public function checkCoupons(): Response
    {
        return $this->get('coupons/validate');
    }

    /**
     * Shorthand: Add a simple product to cart
     *
     * @param int $productId Product ID
     * @param int $quantity  Quantity
     * @return Response
     */
    public function add(int $productId, int $quantity = 1): Response
    {
        return $this->addItem($productId, $quantity);
    }

    /**
     * Shorthand: Add a variable product to cart
     *
     * @param int   $variationId Variation ID
     * @param int   $quantity    Quantity
     * @param array $attributes  Variation attributes (e.g., ['attribute_pa_color' => 'blue'])
     * @return Response
     */
    public function addVariation(int $variationId, int $quantity = 1, array $attributes = []): Response
    {
        return $this->addItem($variationId, $quantity, [
            'variation' => $attributes,
        ]);
    }

    /**
     * Shorthand: Empty the cart (alias for clear)
     *
     * @return Response
     */
    public function empty(): Response
    {
        return $this->clear();
    }
}
