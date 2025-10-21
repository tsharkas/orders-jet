<?php
declare(strict_types=1);
/**
 * Orders Jet - Order Method Service Class
 * Handles order method determination and related logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Order_Method_Service {
    
    /**
     * Get the order method (dinein, takeaway, delivery)
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string Order method: 'dinein', 'takeaway', or 'delivery'
     */
    public function get_order_method($order) {
        $order_method = $order->get_meta('exwf_odmethod');
        
        // If no exwf_odmethod, determine from other meta with better logic
        if (empty($order_method)) {
            $table_number_check = $order->get_meta('_oj_table_number');
            
            if (!empty($table_number_check)) {
                $order_method = 'dinein';
            } else {
                // Check if it's a delivery order by looking at shipping vs billing
                $billing_address = $order->get_billing_address_1();
                $shipping_address = $order->get_shipping_address_1();
                
                // If shipping address exists and differs from billing, likely delivery
                if (!empty($shipping_address) && $shipping_address !== $billing_address) {
                    $order_method = 'delivery';
                } else {
                    // Default to takeaway
                    $order_method = 'takeaway';
                }
            }
        }
        
        return $order_method;
    }
    
    /**
     * Get order method badge HTML for display
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string HTML for order method badge
     */
    public function get_order_method_badge($order) {
        $method = $this->get_order_method($order);
        
        switch ($method) {
            case 'dinein':
                return '<span class="oj-type-badge dinein">🍽️ ' . __('Dine-in', 'orders-jet') . '</span>';
            case 'takeaway':
                return '<span class="oj-type-badge takeaway">📦 ' . __('Takeaway', 'orders-jet') . '</span>';
            case 'delivery':
                return '<span class="oj-type-badge delivery">🚚 ' . __('Delivery', 'orders-jet') . '</span>';
            default:
                return '<span class="oj-type-badge takeaway">📦 ' . __('Takeaway', 'orders-jet') . '</span>';
        }
    }
    
    /**
     * Check if order is a table order (dine-in)
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return bool True if order is dine-in
     */
    public function is_table_order($order) {
        return $this->get_order_method($order) === 'dinein';
    }
    
    /**
     * Check if order is a pickup order (takeaway)
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return bool True if order is takeaway
     */
    public function is_pickup_order($order) {
        return $this->get_order_method($order) === 'takeaway';
    }
    
    /**
     * Check if order is a delivery order
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return bool True if order is delivery
     */
    public function is_delivery_order($order) {
        return $this->get_order_method($order) === 'delivery';
    }
}
