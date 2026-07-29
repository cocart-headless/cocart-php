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

use CoCart\Exceptions\ValidationException;
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
     * Add an item to the cart.
     *
     * `productId` accepts a numeric product/variation ID or a SKU — the
     * server resolves a non-numeric `id` before falling back to a 404,
     * so this SDK doesn't restrict the type here either.
     *
     * @param string|int $productId   Product/variation ID (number or numeric string), or a SKU
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
     * Add multiple children of a WooCommerce Grouped Product to the cart in
     * a single request, via the dedicated `add-items` endpoint.
     *
     * This is NOT a generic "add several unrelated products" call — the
     * server requires a single grouped product ID plus a map of that
     * group's child product IDs to quantities. For adding unrelated
     * products in one request, use `$client->batch()` instead.
     *
     * @param int|string $groupedProductId The parent grouped product's ID
     * @param array      $items            Map of childId => quantity (shorthand),
     *                                      or an array of ['id' => ..., 'quantity' => ...] entries
     * @return Response
     * @throws ValidationException If no items are given
     */
    public function addItems(int|string $groupedProductId, array $items): Response
    {
        $entries = $this->normalizeAddItemsEntries($items);

        if (empty($entries)) {
            throw new ValidationException('addItems() requires at least one item.', 400, 'cocart_batch_empty');
        }

        $quantity = [];
        foreach ($entries as $childId => $qty) {
            $quantity[(string) $childId] = (string) $qty;
        }

        return $this->post('add-items', [
            'id' => (string) $groupedProductId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Normalize the addItems() items argument into a childId => quantity map
     *
     * @param array $items Map of childId => quantity, or array of ['id' => ..., 'quantity' => ...]
     * @return array<string, int|string>
     */
    private function normalizeAddItemsEntries(array $items): array
    {
        if (array_is_list($items)) {
            $entries = [];
            foreach ($items as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $entries[(string) $item['id']] = $item['quantity'] ?? 1;
                }
            }
            return $entries;
        }

        return $items;
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
     * Update multiple items' quantities, one request per item, sequentially
     *
     * There is no real bulk endpoint for this, so each item is sent as its
     * own `updateItem()` request, one after another, and the response from
     * the last update (reflecting the fully-updated cart) is returned. For a
     * true single round trip, see `batchUpdateItems()`.
     *
     * @param array $items Associative array of item_key => quantity, or
     *                     array of ['item_key' => string, 'quantity' => int, ...options]
     * @return Response
     * @throws ValidationException If no items are given
     */
    public function updateItems(array $items): Response
    {
        $entries = $this->normalizeItemEntries($items);

        if (empty($entries)) {
            throw new ValidationException('updateItems() requires at least one item.', 400, 'cocart_batch_empty');
        }

        $response = null;
        foreach ($entries as $itemKey => $quantity) {
            $response = $this->updateItem((string) $itemKey, (int) $quantity);
        }

        return $response;
    }

    /**
     * Update multiple items' quantities in a single request via the batch
     * endpoint (requires CoCart Plus)
     *
     * Unlike `updateItems()`, this is a true single round trip instead of
     * one sequential request per item. Accepts the same shorthand/full
     * formats as `updateItems()`.
     *
     * @param array $items Associative array of item_key => quantity, or
     *                     array of ['item_key' => string, 'quantity' => int, ...options]
     * @return Response
     * @throws ValidationException If no items are given
     */
    public function batchUpdateItems(array $items): Response
    {
        $entries = $this->normalizeItemEntries($items);

        if (empty($entries)) {
            throw new ValidationException('batchUpdateItems() requires at least one item.', 400, 'cocart_batch_empty');
        }

        // Note: uses add() (POST), not update() (PUT) — updateItem() itself
        // posts to `item/{itemKey}` (see above), so the batched sub-requests
        // must match that method to hit the same controller.
        $batch = $this->client->batch();
        foreach ($entries as $itemKey => $quantity) {
            $batch->add("cart/item/{$itemKey}", ['quantity' => (string) $quantity]);
        }

        return $batch->execute();
    }

    /**
     * Normalize the shorthand (item_key => quantity) or full array format
     * into an item_key => quantity map
     *
     * @param array $items
     * @return array<string, int|string>
     */
    private function normalizeItemEntries(array $items): array
    {
        if (!array_is_list($items)) {
            return $items;
        }

        $entries = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['item_key'])) {
                $entries[(string) $item['item_key']] = $item['quantity'] ?? 1;
            }
        }

        return $entries;
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
     * Remove multiple items from the cart, one request per item, sequentially
     *
     * There is no real bulk endpoint for this, so each item is sent as its
     * own `removeItem()` request, one after another, and the response from
     * the last removal (reflecting the fully-updated cart) is returned. For
     * a true single round trip, see `batchRemoveItems()`.
     *
     * @param array $itemKeys Array of cart item keys to remove
     * @return Response
     * @throws ValidationException If no item keys are given
     */
    public function removeItems(array $itemKeys): Response
    {
        if (empty($itemKeys)) {
            throw new ValidationException('removeItems() requires at least one item key.', 400, 'cocart_batch_empty');
        }

        $response = null;
        foreach ($itemKeys as $itemKey) {
            $response = $this->removeItem((string) $itemKey);
        }

        return $response;
    }

    /**
     * Remove multiple items in a single request via the batch endpoint
     * (requires CoCart Plus)
     *
     * Unlike `removeItems()`, this is a true single round trip instead of
     * one sequential request per item.
     *
     * @param array $itemKeys Array of cart item keys to remove
     * @return Response
     * @throws ValidationException If no item keys are given
     */
    public function batchRemoveItems(array $itemKeys): Response
    {
        if (empty($itemKeys)) {
            throw new ValidationException('batchRemoveItems() requires at least one item key.', 400, 'cocart_batch_empty');
        }

        $batch = $this->client->batch();
        foreach ($itemKeys as $itemKey) {
            $batch->remove("cart/item/{$itemKey}");
        }

        return $batch->execute();
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
     * Update customer billing (and optionally shipping) address on the cart
     *
     * Posts to the `update-customer` callback on `POST /cart/update` —
     * billing fields are sent unprefixed (`first_name`, `address_1`, ...)
     * and shipping fields are sent `s_`-prefixed (`s_first_name`,
     * `s_address_1`, ...), which the server requires for any address field
     * the destination country marks required, independent of whether
     * `ship_to_different_address` is set. If `$shipping` is omitted/empty,
     * billing is mirrored into the `s_` fields so that check passes and the
     * shipping address matches billing, the same as leaving "ship to a
     * different address" unchecked at a normal WooCommerce checkout.
     *
     * @param array $billing  Billing address fields (unprefixed, e.g. first_name, address_1, city, postcode, country, email, phone)
     * @param array $shipping Shipping address fields, if different from billing. Omit/empty to mirror billing.
     * @return Response
     */
    public function updateCustomer(array $billing = [], array $shipping = []): Response
    {
        $hasDistinctShipping = !empty($shipping);
        $shipTo = $hasDistinctShipping ? $shipping : $billing;

        $data = ['namespace' => 'update-customer'];

        foreach ($billing as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($shipTo as $key => $value) {
            $data["s_{$key}"] = $value;
        }

        if ($hasDistinctShipping) {
            $data['ship_to_different_address'] = true;
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
     * Select a shipping rate for a package (requires CoCart Plus)
     *
     * Posts `rate_id` (and optional `package_id`) to `set-shipping-method`.
     * Omit `$packageId` to apply the rate to every package.
     *
     * @param string      $rateId    The chosen rate's key, e.g. 'flat_rate:2' (see a shipping package's 'rates' map)
     * @param string|null $packageId Restrict the selection to one package. Omit to apply to all packages.
     * @return Response
     */
    public function setShippingMethod(string $rateId, ?string $packageId = null): Response
    {
        $data = ['rate_id' => $rateId];

        if ($packageId !== null && $packageId !== '') {
            $data['package_id'] = $packageId;
        }

        return $this->post('set-shipping-method', $data);
    }

    /**
     * Calculate shipping for the cart
     *
     * @deprecated There is no address-taking shipping-calculation endpoint
     * in the CoCart REST API — `POST /cart/calculate/shipping` (what this
     * method used to call) does not exist. To calculate shipping, call
     * `updateCustomer()` with the destination address first (the server
     * recalculates totals as part of that request); this method now just
     * delegates to `calculate()`, ignoring `$address`. Prefer `calculate()`
     * directly.
     *
     * @param array $address Unused; kept for backwards compatibility
     * @return Response
     */
    public function calculateShipping(array $address = []): Response
    {
        return $this->calculate();
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
     * @param int|string $variationId Variation ID, or a SKU
     * @param int        $quantity    Quantity
     * @param array      $attributes  Variation attributes (e.g., ['attribute_pa_color' => 'blue'])
     * @return Response
     */
    public function addVariation(int|string $variationId, int $quantity = 1, array $attributes = []): Response
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
