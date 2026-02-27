<?php
declare(strict_types=1);

/**
 * Products Endpoint
 * 
 * Handles all product-related API operations.
 * Products API is publicly accessible without authentication.
 * 
 * @package CoCart\SDK\Endpoints
 */

namespace CoCart\Endpoints;

use CoCart\Response;

class Products extends Endpoint
{
    /**
     * Endpoint prefix
     *
     * @var string
     */
    protected string $endpoint = 'products';

    /**
     * Get all products
     *
     * @param array $params Query parameters for filtering:
     *                      - page: int
     *                      - per_page: int
     *                      - search: string
     *                      - category: string (category slug)
     *                      - tag: string (tag slug)
     *                      - status: string
     *                      - featured: bool
     *                      - on_sale: bool
     *                      - min_price: string
     *                      - max_price: string
     *                      - stock_status: string
     *                      - orderby: string
     *                      - order: string (asc/desc)
     * @return Response
     */
    public function all(array $params = []): Response
    {
        return $this->get('', $params);
    }

    /**
     * Get a single product
     *
     * @param int   $productId Product ID
     * @param array $params    Query parameters
     * @return Response
     */
    public function find(int $productId, array $params = []): Response
    {
        return $this->get((string) $productId, $params);
    }

    /**
     * Search products
     *
     * @param string $term   Search term
     * @param array  $params Additional parameters
     * @return Response
     */
    public function search(string $term, array $params = []): Response
    {
        $params['search'] = $term;
        return $this->all($params);
    }

    /**
     * Get products by category
     *
     * @param string $categorySlug Category slug
     * @param array  $params       Additional parameters
     * @return Response
     */
    public function byCategory(string $categorySlug, array $params = []): Response
    {
        $params['category'] = $categorySlug;
        return $this->all($params);
    }

    /**
     * Get products by tag
     *
     * @param string $tagSlug Tag slug
     * @param array  $params  Additional parameters
     * @return Response
     */
    public function byTag(string $tagSlug, array $params = []): Response
    {
        $params['tag'] = $tagSlug;
        return $this->all($params);
    }

    /**
     * Get featured products
     *
     * @param array $params Additional parameters
     * @return Response
     */
    public function featured(array $params = []): Response
    {
        $params['featured'] = true;
        return $this->all($params);
    }

    /**
     * Get products on sale
     *
     * @param array $params Additional parameters
     * @return Response
     */
    public function onSale(array $params = []): Response
    {
        $params['on_sale'] = true;
        return $this->all($params);
    }

    /**
     * Get products within a price range
     *
     * @param float|null $minPrice Minimum price
     * @param float|null $maxPrice Maximum price
     * @param array      $params   Additional parameters
     * @return Response
     */
    public function byPriceRange(?float $minPrice = null, ?float $maxPrice = null, array $params = []): Response
    {
        if ($minPrice !== null) {
            $params['min_price'] = (string) $minPrice;
        }
        if ($maxPrice !== null) {
            $params['max_price'] = (string) $maxPrice;
        }
        return $this->all($params);
    }

    /**
     * Get products sorted by a field
     *
     * @param string $field Field to sort by (date, id, title, slug, price, popularity, rating)
     * @param string $order Sort direction (asc or desc)
     * @param array  $params Additional parameters
     * @return Response
     */
    public function sortBy(string $field, string $order = 'asc', array $params = []): Response
    {
        $params['orderby'] = $field;
        $params['order'] = $order;
        return $this->all($params);
    }

    /**
     * Get products by stock status
     *
     * @param string $status Stock status (instock, outofstock, onbackorder)
     * @param array  $params Additional parameters
     * @return Response
     */
    public function byStockStatus(string $status, array $params = []): Response
    {
        $params['stock_status'] = $status;
        return $this->all($params);
    }

    /**
     * Get a specific page of products
     *
     * @param int   $page    Page number
     * @param int   $perPage Products per page
     * @param array $params  Additional parameters
     * @return Response
     */
    public function paginate(int $page = 1, int $perPage = 10, array $params = []): Response
    {
        $params['page'] = $page;
        $params['per_page'] = $perPage;
        return $this->all($params);
    }

    /**
     * Get product variations
     *
     * @param int   $productId Product ID
     * @param array $params    Query parameters
     * @return Response
     */
    public function variations(int $productId, array $params = []): Response
    {
        return $this->get("{$productId}/variations", $params);
    }

    /**
     * Get a specific variation
     *
     * @param int   $productId   Product ID
     * @param int   $variationId Variation ID
     * @param array $params      Query parameters
     * @return Response
     */
    public function variation(int $productId, int $variationId, array $params = []): Response
    {
        return $this->get("{$productId}/variations/{$variationId}", $params);
    }

    /**
     * Get product categories
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function categories(array $params = []): Response
    {
        return $this->get('categories', $params);
    }

    /**
     * Get a single category
     *
     * @param int   $categoryId Category ID
     * @param array $params     Query parameters
     * @return Response
     */
    public function category(int $categoryId, array $params = []): Response
    {
        return $this->get("categories/{$categoryId}", $params);
    }

    /**
     * Get product tags
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function tags(array $params = []): Response
    {
        return $this->get('tags', $params);
    }

    /**
     * Get a single tag
     *
     * @param int   $tagId  Tag ID
     * @param array $params Query parameters
     * @return Response
     */
    public function tag(int $tagId, array $params = []): Response
    {
        return $this->get("tags/{$tagId}", $params);
    }

    /**
     * Get product attributes
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function attributes(array $params = []): Response
    {
        return $this->get('attributes', $params);
    }

    /**
     * Get a single attribute
     *
     * @param int   $attributeId Attribute ID
     * @param array $params      Query parameters
     * @return Response
     */
    public function attribute(int $attributeId, array $params = []): Response
    {
        return $this->get("attributes/{$attributeId}", $params);
    }

    /**
     * Get attribute terms
     *
     * @param int   $attributeId Attribute ID
     * @param array $params      Query parameters
     * @return Response
     */
    public function attributeTerms(int $attributeId, array $params = []): Response
    {
        return $this->get("attributes/{$attributeId}/terms", $params);
    }

    /**
     * Get product brands
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function brands(array $params = []): Response
    {
        return $this->get('brands', $params);
    }

    /**
     * Get a single brand
     *
     * @param int   $brandId Brand ID
     * @param array $params  Query parameters
     * @return Response
     */
    public function brand(int $brandId, array $params = []): Response
    {
        return $this->get("brands/{$brandId}", $params);
    }

    /**
     * Get products by brand
     *
     * @param string $brandSlug Brand slug
     * @param array  $params    Additional parameters
     * @return Response
     */
    public function byBrand(string $brandSlug, array $params = []): Response
    {
        $params['brand'] = $brandSlug;
        return $this->all($params);
    }

    /**
     * Get SEO data for a product by ID
     *
     * Returns SEO metadata, Open Graph, Twitter cards, and Schema.org
     * structured data. Requires the CoCart SEO Pack plugin.
     *
     * @param int $productId Product ID
     * @return Response
     */
    public function seo(int $productId): Response
    {
        return $this->get("{$productId}/seo");
    }

    /**
     * Get SEO data for a product by slug
     *
     * Returns SEO metadata, Open Graph, Twitter cards, and Schema.org
     * structured data. Requires the CoCart SEO Pack plugin.
     *
     * @param string $slug Product slug
     * @return Response
     */
    public function seoBySlug(string $slug): Response
    {
        return $this->get("{$slug}/seo");
    }

    /**
     * Get product reviews
     *
     * @param array $params Query parameters
     * @return Response
     */
    public function reviews(array $params = []): Response
    {
        return $this->get('reviews', $params);
    }

    /**
     * Get reviews for a specific product
     *
     * @param int   $productId Product ID
     * @param array $params    Query parameters
     * @return Response
     */
    public function productReviews(int $productId, array $params = []): Response
    {
        $params['product'] = $productId;
        return $this->reviews($params);
    }

}
