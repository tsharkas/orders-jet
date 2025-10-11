<?php
/**
 * Orders Jet - Helpers Class
 * Helper functions for the Orders Jet system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Helpers {
    
    /**
     * Send order notification to staff
     */
    public static function send_order_notification($order) {
        // Get restaurant email (you can customize this)
        $restaurant_email = get_option('admin_email');
        $table_number = $order->get_meta('_oj_table_number');
        
        $subject = sprintf(__('New Order from Table %s', 'orders-jet'), $table_number);
        $message = sprintf(__('A new order has been placed from Table %s. Order #%s', 'orders-jet'), $table_number, $order->get_order_number());
        
        // Add order details
        $message .= "\n\n" . __('Order Details:', 'orders-jet') . "\n";
        $message .= __('Order Number:', 'orders-jet') . ' ' . $order->get_order_number() . "\n";
        $message .= __('Table:', 'orders-jet') . ' ' . $table_number . "\n";
        $message .= __('Total:', 'orders-jet') . ' ' . $order->get_formatted_order_total() . "\n";
        
        if ($order->get_customer_note()) {
            $message .= __('Special Requests:', 'orders-jet') . ' ' . $order->get_customer_note() . "\n";
        }
        
        $message .= "\n" . __('View Order:', 'orders-jet') . ' ' . admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        
        wp_mail($restaurant_email, $subject, $message);
    }
    
    /**
     * Get table ID by number
     */
    public static function get_table_id_by_number($table_number) {
        $posts = get_posts(array(
            'post_type' => 'oj_table',
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        return !empty($posts) ? $posts[0]->ID : false;
    }
    
    /**
     * Get current order for table
     */
    public static function get_current_table_order($table_number) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold'),
            'limit' => 1,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        return !empty($orders) ? $orders[0] : false;
    }
    
    /**
     * Format currency for display
     */
    public static function format_currency($amount, $currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        return wc_price($amount, array('currency' => $currency));
    }
    
    /**
     * Get table status options
     */
    public static function get_table_status_options() {
        return array(
            'available' => __('Available', 'orders-jet'),
            'occupied' => __('Occupied', 'orders-jet'),
            'reserved' => __('Reserved', 'orders-jet'),
            'maintenance' => __('Maintenance', 'orders-jet')
        );
    }
    
    /**
     * Check if table is available for ordering
     */
    public static function is_table_available($table_id) {
        $status = get_post_meta($table_id, '_oj_table_status', true);
        return in_array($status, array('available', 'occupied'));
    }
    
    /**
     * Get table orders for admin
     */
    public static function get_table_orders_for_admin($table_number, $limit = 10) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold', 'completed'),
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        return $orders;
    }
    
    /**
     * Update table status
     */
    public static function update_table_status($table_id, $status) {
        $valid_statuses = array_keys(self::get_table_status_options());
        
        if (in_array($status, $valid_statuses)) {
            update_post_meta($table_id, '_oj_table_status', $status);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get restaurant settings
     */
    public static function get_restaurant_settings() {
        return array(
            'name' => get_option('blogname'),
            'email' => get_option('admin_email'),
            'phone' => get_option('woocommerce_store_phone'),
            'address' => get_option('woocommerce_store_address'),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol()
        );
    }
    
    /**
     * Log debug information
     */
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet [' . strtoupper($level) . ']: ' . $message);
        }
    }
    
    /**
     * Sanitize table number
     */
    public static function sanitize_table_number($table_number) {
        return sanitize_text_field($table_number);
    }
    
    /**
     * Validate table number format
     */
    public static function validate_table_number($table_number) {
        // Allow alphanumeric table numbers (e.g., T01, A12, 5)
        return preg_match('/^[A-Za-z0-9]+$/', $table_number);
    }
}

// Global helper functions for backward compatibility
function oj_send_order_notification($order) {
    return Orders_Jet_Helpers::send_order_notification($order);
}

function oj_get_table_id_by_number($table_number) {
    return Orders_Jet_Helpers::get_table_id_by_number($table_number);
}

function oj_get_current_table_order($table_number) {
    return Orders_Jet_Helpers::get_current_table_order($table_number);
}

function oj_get_table_orders_for_admin($table_number, $limit = 10) {
    return Orders_Jet_Helpers::get_table_orders_for_admin($table_number, $limit);
}

