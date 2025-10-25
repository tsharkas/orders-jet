<?php
declare(strict_types=1);
/**
 * Orders Jet - Menu Service Class
 * Handles product and category data management for QR menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Menu_Service {
    
    /**
     * Get categories with their products
     * 
     * @param int|null $location_id WooFood location ID for filtering
     * @return array Categories with products
     */
    public function get_categories_with_products($location_id = null) {
        $categories = $this->get_menu_categories();
        $products = $this->get_products_by_location($location_id);
        
        // Group products by category
        $categorized_products = array();
        
        foreach ($categories as $category) {
            $category_products = array();
            
            foreach ($products as $product) {
                $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
                
                if (in_array($category->slug, $product_categories)) {
                    $category_products[] = $this->format_product_for_menu($product);
                }
            }
            
            if (!empty($category_products)) {
                $categorized_products[] = array(
                    'category' => $this->format_category($category),
                    'products' => $category_products
                );
            }
        }
        
        return $categorized_products;
    }
    
    /**
     * Get detailed product information
     * 
     * @param int $product_id The product ID
     * @return array Detailed product data
     */
    public function get_product_details($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Product not found', 'orders-jet'));
        }
        
        $product_data = $this->format_product_for_menu($product);
        
        // Add detailed information
        $product_data['description'] = $product->get_description();
        $product_data['short_description'] = $product->get_short_description();
        $product_data['variations'] = $this->get_product_variations($product);
        $product_data['addons'] = $this->get_product_addons($product_id);
        $product_data['gallery'] = $this->get_product_gallery($product);
        
        return $product_data;
    }
    
    /**
     * Get product add-ons from various sources
     * 
     * @param int $product_id The product ID
     * @return array Product add-ons
     */
    public function get_product_addons($product_id) {
        $addons = array();
        
        // Try WooFood add-ons first
        $woofood_addons = $this->get_woofood_addons($product_id);
        if (!empty($woofood_addons)) {
            $addons = array_merge($addons, $woofood_addons);
        }
        
        // Try WooCommerce Product Add-ons
        if (class_exists('WC_Product_Addons')) {
            $wc_addons = $this->get_wc_product_addons($product_id);
            if (!empty($wc_addons)) {
                $addons = array_merge($addons, $wc_addons);
            }
        }
        
        // Apply filters for extensibility
        return apply_filters('oj_product_addons', $addons, $product_id);
    }
    
    /**
     * Filter products by location
     * 
     * @param array $products Products array
     * @param int|null $location_id WooFood location ID
     * @return array Filtered products
     */
    public function filter_products_by_location($products, $location_id = null) {
        if (!$location_id) {
            return $products;
        }
        
        $filtered_products = array();
        
        foreach ($products as $product) {
            $product_locations = wp_get_post_terms($product->get_id(), 'exwoofood_loc', array('fields' => 'ids'));
            
            if (empty($product_locations) || in_array($location_id, $product_locations)) {
                $filtered_products[] = $product;
            }
        }
        
        return $filtered_products;
    }
    
    /**
     * Get menu categories
     * 
     * @return array Menu categories
     */
    private function get_menu_categories() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        return is_wp_error($categories) ? array() : $categories;
    }
    
    /**
     * Get products by location
     * 
     * @param int|null $location_id WooFood location ID
     * @return array Products
     */
    private function get_products_by_location($location_id = null) {
        $product_args = array(
            'limit' => -1,
            'status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );
        
        // Add location filter if specified
        if ($location_id) {
            $product_args['tax_query'] = array(
                array(
                    'taxonomy' => 'exwoofood_loc',
                    'field'    => 'term_id',
                    'terms'    => $location_id,
                )
            );
        }
        
        return wc_get_products($product_args);
    }
    
    /**
     * Format category for menu display
     * 
     * @param WP_Term $category Category term
     * @return array Formatted category data
     */
    private function format_category($category) {
        return array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => $category->count
        );
    }
    
    /**
     * Format product for menu display
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Formatted product data
     */
    private function format_product_for_menu($product) {
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'formatted_price' => wc_price($product->get_price()),
            'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'image_alt' => get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true),
            'short_description' => $product->get_short_description(),
            'in_stock' => $product->is_in_stock(),
            'stock_status' => $product->get_stock_status(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'))
        );
    }
    
    /**
     * Get product variations
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Product variations
     */
    private function get_product_variations($product) {
        if (!$product->is_type('variable')) {
            return array();
        }
        
        $variations = array();
        $available_variations = $product->get_available_variations();
        
        foreach ($available_variations as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if ($variation) {
                $variations[] = array(
                    'id' => $variation->get_id(),
                    'attributes' => $variation->get_variation_attributes(),
                    'price' => $variation->get_price(),
                    'formatted_price' => wc_price($variation->get_price()),
                    'in_stock' => $variation->is_in_stock(),
                    'image_url' => wp_get_attachment_image_url($variation->get_image_id(), 'medium')
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Get product gallery images
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Gallery images
     */
    private function get_product_gallery($product) {
        $gallery_ids = $product->get_gallery_image_ids();
        $gallery = array();
        
        foreach ($gallery_ids as $image_id) {
            $gallery[] = array(
                'id' => $image_id,
                'url' => wp_get_attachment_image_url($image_id, 'large'),
                'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
            );
        }
        
        return $gallery;
    }
    
    /**
     * Get WooFood add-ons
     * 
     * @param int $product_id Product ID
     * @return array WooFood add-ons
     */
    private function get_woofood_addons($product_id) {
        // Implementation depends on WooFood plugin structure
        // This is a placeholder for WooFood integration
        return array();
    }
    
    /**
     * Get WooCommerce Product Add-ons
     * 
     * @param int $product_id Product ID
     * @return array WC Product Add-ons
     */
    private function get_wc_product_addons($product_id) {
        // Implementation for WooCommerce Product Add-ons plugin
        // This is a placeholder for WC Product Add-ons integration
        return array();
    }
}
