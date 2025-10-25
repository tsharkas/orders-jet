<?php
declare(strict_types=1);
/**
 * Orders Jet - QR Cart Handler Class
 * Handles cart operations and state management for QR menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_QR_Cart_Handler {
    
    private $cart_service;
    
    public function __construct($cart_service = null) {
        $this->cart_service = $cart_service;
    }
    
    /**
     * Add item to cart
     * 
     * @param array $product_data Product data from AJAX request
     * @return array Success response with cart data
     * @throws Exception On validation or processing errors
     */
    public function add_to_cart($product_data) {
        // Validate required fields
        $this->validate_product_data($product_data);
        
        $table_number = sanitize_text_field($product_data['table_number']);
        $product_id = intval($product_data['product_id']);
        $variation_id = intval($product_data['variation_id'] ?? 0);
        $quantity = intval($product_data['quantity'] ?? 1);
        $notes = sanitize_textarea_field($product_data['notes'] ?? '');
        $add_ons = $product_data['add_ons'] ?? array();
        
        // Get product to validate
        $product = $variation_id > 0 ? wc_get_product($variation_id) : wc_get_product($product_id);
        if (!$product) {
            throw new Exception(__('Product not found', 'orders-jet'));
        }
        
        // Calculate pricing
        $base_price = floatval($product->get_price());
        $addon_total = $this->calculate_addon_total($add_ons);
        $item_total = ($base_price + $addon_total) * $quantity;
        
        // Create cart item
        $cart_item = array(
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'name' => $product->get_name(),
            'quantity' => $quantity,
            'base_price' => $base_price,
            'addon_total' => $addon_total,
            'item_total' => $item_total,
            'notes' => $notes,
            'add_ons' => $add_ons,
            'added_at' => current_time('mysql')
        );
        
        // Add to cart (this will use cart service when implemented)
        $cart = $this->get_cart_contents($table_number);
        $cart[] = $cart_item;
        $this->save_cart($table_number, $cart);
        
        return array(
            'cart_item' => $cart_item,
            'cart_total' => $this->calculate_cart_total($cart),
            'cart_count' => count($cart)
        );
    }
    
    /**
     * Update cart item quantity
     * 
     * @param array $request_data Request data with item_index and quantity
     * @return array Updated cart data
     * @throws Exception On validation errors
     */
    public function update_cart_item($request_data) {
        $table_number = sanitize_text_field($request_data['table_number']);
        $item_index = intval($request_data['item_index']);
        $quantity = intval($request_data['quantity']);
        
        if ($quantity < 1) {
            throw new Exception(__('Quantity must be at least 1', 'orders-jet'));
        }
        
        $cart = $this->get_cart_contents($table_number);
        
        if (!isset($cart[$item_index])) {
            throw new Exception(__('Cart item not found', 'orders-jet'));
        }
        
        // Update quantity and recalculate total
        $cart[$item_index]['quantity'] = $quantity;
        $base_price = $cart[$item_index]['base_price'];
        $addon_total = $cart[$item_index]['addon_total'];
        $cart[$item_index]['item_total'] = ($base_price + $addon_total) * $quantity;
        
        $this->save_cart($table_number, $cart);
        
        return array(
            'cart' => $cart,
            'cart_total' => $this->calculate_cart_total($cart),
            'cart_count' => count($cart)
        );
    }
    
    /**
     * Remove item from cart
     * 
     * @param array $request_data Request data with item_index
     * @return array Updated cart data
     * @throws Exception On validation errors
     */
    public function remove_from_cart($request_data) {
        $table_number = sanitize_text_field($request_data['table_number']);
        $item_index = intval($request_data['item_index']);
        
        $cart = $this->get_cart_contents($table_number);
        
        if (!isset($cart[$item_index])) {
            throw new Exception(__('Cart item not found', 'orders-jet'));
        }
        
        // Remove item and reindex array
        unset($cart[$item_index]);
        $cart = array_values($cart);
        
        $this->save_cart($table_number, $cart);
        
        return array(
            'cart' => $cart,
            'cart_total' => $this->calculate_cart_total($cart),
            'cart_count' => count($cart)
        );
    }
    
    /**
     * Clear entire cart
     * 
     * @param string $table_number The table number
     * @return array Empty cart data
     */
    public function clear_cart($table_number) {
        $this->save_cart($table_number, array());
        
        return array(
            'cart' => array(),
            'cart_total' => 0,
            'cart_count' => 0
        );
    }
    
    /**
     * Get cart contents for table
     * 
     * @param string $table_number The table number
     * @return array Cart items
     */
    public function get_cart_contents($table_number) {
        // For now, use WordPress transients
        // This will be replaced with cart service
        $cart = get_transient('oj_cart_' . $table_number);
        return is_array($cart) ? $cart : array();
    }
    
    /**
     * Get formatted cart data for display
     * 
     * @param string $table_number The table number
     * @return array Formatted cart data
     */
    public function get_formatted_cart_data($table_number) {
        $cart = $this->get_cart_contents($table_number);
        
        return array(
            'items' => $cart,
            'total' => $this->calculate_cart_total($cart),
            'count' => count($cart),
            'formatted_total' => wc_price($this->calculate_cart_total($cart))
        );
    }
    
    /**
     * Validate product data for add to cart
     * 
     * @param array $product_data Product data to validate
     * @throws Exception On validation errors
     */
    private function validate_product_data($product_data) {
        if (empty($product_data['table_number'])) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        if (empty($product_data['product_id'])) {
            throw new Exception(__('Product ID is required', 'orders-jet'));
        }
        
        $quantity = intval($product_data['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new Exception(__('Quantity must be at least 1', 'orders-jet'));
        }
    }
    
    /**
     * Calculate total price for add-ons
     * 
     * @param array $add_ons Add-ons array
     * @return float Total add-on price
     */
    private function calculate_addon_total($add_ons) {
        $total = 0;
        
        if (!empty($add_ons) && is_array($add_ons)) {
            foreach ($add_ons as $addon) {
                $price = floatval($addon['price'] ?? 0);
                $quantity = intval($addon['quantity'] ?? 1);
                $total += $price * $quantity;
            }
        }
        
        return $total;
    }
    
    /**
     * Calculate total cart value
     * 
     * @param array $cart Cart items
     * @return float Total cart value
     */
    private function calculate_cart_total($cart) {
        $total = 0;
        
        foreach ($cart as $item) {
            $total += floatval($item['item_total'] ?? 0);
        }
        
        return $total;
    }
    
    /**
     * Save cart to storage
     * 
     * @param string $table_number The table number
     * @param array $cart Cart items
     */
    private function save_cart($table_number, $cart) {
        // For now, use WordPress transients (24 hour expiry)
        // This will be replaced with cart service
        set_transient('oj_cart_' . $table_number, $cart, 24 * HOUR_IN_SECONDS);
    }
}
