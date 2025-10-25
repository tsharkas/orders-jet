<?php
declare(strict_types=1);
/**
 * Orders Jet - Cart Service Class
 * Handles cart state and session management for QR menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Cart_Service {
    
    private $session_service;
    
    public function __construct($session_service = null) {
        $this->session_service = $session_service;
    }
    
    /**
     * Initialize cart session for table
     * 
     * @param string $table_number The table number
     * @return array Empty cart structure
     */
    public function initialize_cart_session($table_number) {
        $cart_data = array(
            'items' => array(),
            'totals' => array(
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0
            ),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $this->save_cart_state($table_number, $cart_data);
        
        return $cart_data;
    }
    
    /**
     * Save cart state to storage
     * 
     * @param string $table_number The table number
     * @param array $cart_data Cart data to save
     * @return bool Success status
     */
    public function save_cart_state($table_number, $cart_data) {
        // Update timestamp
        $cart_data['updated_at'] = current_time('mysql');
        
        // Use WordPress transients for now (24 hour expiry)
        // This can be replaced with database storage or Redis later
        $transient_key = $this->get_cart_transient_key($table_number);
        
        return set_transient($transient_key, $cart_data, 24 * HOUR_IN_SECONDS);
    }
    
    /**
     * Load cart state from storage
     * 
     * @param string $table_number The table number
     * @return array Cart data or empty cart if not found
     */
    public function load_cart_state($table_number) {
        $transient_key = $this->get_cart_transient_key($table_number);
        $cart_data = get_transient($transient_key);
        
        if (!$cart_data || !is_array($cart_data)) {
            return $this->initialize_cart_session($table_number);
        }
        
        // Validate cart structure
        if (!isset($cart_data['items']) || !isset($cart_data['totals'])) {
            return $this->initialize_cart_session($table_number);
        }
        
        return $cart_data;
    }
    
    /**
     * Calculate cart totals
     * 
     * @param array $cart_items Cart items array
     * @return array Calculated totals
     */
    public function calculate_cart_totals($cart_items) {
        $subtotal = 0;
        $tax_total = 0;
        $total = 0;
        
        foreach ($cart_items as $item) {
            $item_subtotal = floatval($item['base_price'] ?? 0) * intval($item['quantity'] ?? 1);
            $addon_total = floatval($item['addon_total'] ?? 0) * intval($item['quantity'] ?? 1);
            $item_total = $item_subtotal + $addon_total;
            
            $subtotal += $item_total;
            
            // Calculate tax if applicable
            $tax_rate = $this->get_product_tax_rate($item['product_id'] ?? 0);
            $item_tax = $item_total * ($tax_rate / 100);
            $tax_total += $item_tax;
        }
        
        $total = $subtotal + $tax_total;
        
        return array(
            'subtotal' => $subtotal,
            'tax' => $tax_total,
            'total' => $total,
            'formatted_subtotal' => wc_price($subtotal),
            'formatted_tax' => wc_price($tax_total),
            'formatted_total' => wc_price($total),
            'item_count' => count($cart_items)
        );
    }
    
    /**
     * Add item to cart
     * 
     * @param string $table_number The table number
     * @param array $item_data Item data to add
     * @return array Updated cart data
     */
    public function add_item_to_cart($table_number, $item_data) {
        $cart_data = $this->load_cart_state($table_number);
        
        // Check if item already exists (same product, variation, and add-ons)
        $existing_index = $this->find_existing_item($cart_data['items'], $item_data);
        
        if ($existing_index !== false) {
            // Update quantity of existing item
            $cart_data['items'][$existing_index]['quantity'] += intval($item_data['quantity'] ?? 1);
        } else {
            // Add new item
            $cart_data['items'][] = $item_data;
        }
        
        // Recalculate totals
        $cart_data['totals'] = $this->calculate_cart_totals($cart_data['items']);
        
        // Save updated cart
        $this->save_cart_state($table_number, $cart_data);
        
        return $cart_data;
    }
    
    /**
     * Update item quantity in cart
     * 
     * @param string $table_number The table number
     * @param int $item_index Item index in cart
     * @param int $quantity New quantity
     * @return array Updated cart data
     */
    public function update_item_quantity($table_number, $item_index, $quantity) {
        $cart_data = $this->load_cart_state($table_number);
        
        if (!isset($cart_data['items'][$item_index])) {
            throw new Exception(__('Cart item not found', 'orders-jet'));
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            unset($cart_data['items'][$item_index]);
            $cart_data['items'] = array_values($cart_data['items']); // Reindex
        } else {
            // Update quantity
            $cart_data['items'][$item_index]['quantity'] = $quantity;
        }
        
        // Recalculate totals
        $cart_data['totals'] = $this->calculate_cart_totals($cart_data['items']);
        
        // Save updated cart
        $this->save_cart_state($table_number, $cart_data);
        
        return $cart_data;
    }
    
    /**
     * Remove item from cart
     * 
     * @param string $table_number The table number
     * @param int $item_index Item index to remove
     * @return array Updated cart data
     */
    public function remove_item_from_cart($table_number, $item_index) {
        return $this->update_item_quantity($table_number, $item_index, 0);
    }
    
    /**
     * Clear entire cart
     * 
     * @param string $table_number The table number
     * @return array Empty cart data
     */
    public function clear_cart($table_number) {
        return $this->initialize_cart_session($table_number);
    }
    
    /**
     * Get cart summary for display
     * 
     * @param string $table_number The table number
     * @return array Cart summary
     */
    public function get_cart_summary($table_number) {
        $cart_data = $this->load_cart_state($table_number);
        
        return array(
            'item_count' => count($cart_data['items']),
            'total' => $cart_data['totals']['total'],
            'formatted_total' => $cart_data['totals']['formatted_total'] ?? wc_price($cart_data['totals']['total']),
            'is_empty' => empty($cart_data['items'])
        );
    }
    
    /**
     * Validate cart before checkout
     * 
     * @param string $table_number The table number
     * @return array Validation result
     */
    public function validate_cart($table_number) {
        $cart_data = $this->load_cart_state($table_number);
        $errors = array();
        
        if (empty($cart_data['items'])) {
            $errors[] = __('Cart is empty', 'orders-jet');
        }
        
        // Validate each item
        foreach ($cart_data['items'] as $index => $item) {
            $product = wc_get_product($item['product_id'] ?? 0);
            
            if (!$product) {
                $errors[] = sprintf(__('Product in cart item %d not found', 'orders-jet'), $index + 1);
                continue;
            }
            
            if (!$product->is_in_stock()) {
                $errors[] = sprintf(__('Product "%s" is out of stock', 'orders-jet'), $product->get_name());
            }
            
            if ($item['quantity'] <= 0) {
                $errors[] = sprintf(__('Invalid quantity for product "%s"', 'orders-jet'), $product->get_name());
            }
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get cart transient key
     * 
     * @param string $table_number The table number
     * @return string Transient key
     */
    private function get_cart_transient_key($table_number) {
        return 'oj_cart_' . sanitize_key($table_number);
    }
    
    /**
     * Find existing item in cart
     * 
     * @param array $cart_items Current cart items
     * @param array $new_item New item to check
     * @return int|false Item index if found, false otherwise
     */
    private function find_existing_item($cart_items, $new_item) {
        foreach ($cart_items as $index => $item) {
            // Check if same product and variation
            if ($item['product_id'] === $new_item['product_id'] && 
                ($item['variation_id'] ?? 0) === ($new_item['variation_id'] ?? 0)) {
                
                // Check if add-ons are the same
                $item_addons = json_encode($item['add_ons'] ?? array());
                $new_addons = json_encode($new_item['add_ons'] ?? array());
                
                if ($item_addons === $new_addons) {
                    return $index;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get product tax rate
     * 
     * @param int $product_id Product ID
     * @return float Tax rate percentage
     */
    private function get_product_tax_rate($product_id) {
        // This is a simplified tax calculation
        // In a real implementation, you'd use WooCommerce tax classes and rates
        return 0; // No tax for now
    }
}
