<?php
declare(strict_types=1);
/**
 * Orders Jet - QR Order History Handler Class
 * Handles customer order history for QR menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_QR_History_Handler {
    
    public function __construct() {
        // Constructor for future service injection
    }
    
    /**
     * Get table order history for QR menu display
     * 
     * @param string $table_number The table number
     * @return array Order history data
     */
    public function get_table_order_history($table_number) {
        if (empty($table_number)) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        // Get orders for this table (only pending/processing for guest privacy)
        $orders = $this->fetch_table_orders($table_number);
        
        $order_data = array();
        $total_amount = 0;
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) {
                continue;
            }
            
            // Process order items
            $order_items = $this->process_order_items($order);
            
            $order_info = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'formatted_total' => wc_price($order->get_total()),
                'date' => $order->get_date_created()->format('M j, Y g:i A'),
                'items' => $order_items,
                'payment_method' => $order->get_meta('_oj_payment_method') ?: 'cash'
            );
            
            $order_data[] = $order_info;
            $total_amount += $order->get_total();
        }
        
        return array(
            'orders' => $order_data,
            'total_amount' => $total_amount,
            'formatted_total' => wc_price($total_amount),
            'order_count' => count($order_data)
        );
    }
    
    /**
     * Format order for display in QR menu
     * 
     * @param WC_Order $order WooCommerce order object
     * @return array Formatted order data
     */
    public function format_order_for_display($order) {
        $order_items = $this->process_order_items($order);
        
        return array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_name' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'formatted_total' => wc_price($order->get_total()),
            'date_created' => $order->get_date_created()->format('M j, Y g:i A'),
            'items' => $order_items,
            'item_count' => count($order_items),
            'customer_notes' => $order->get_customer_note(),
            'payment_method' => $order->get_meta('_oj_payment_method') ?: 'cash'
        );
    }
    
    /**
     * Get order totals for table
     * 
     * @param string $table_number The table number
     * @return array Order totals data
     */
    public function get_order_totals($table_number) {
        $history_data = $this->get_table_order_history($table_number);
        
        $totals = array(
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => $history_data['total_amount'],
            'formatted_total' => $history_data['formatted_total'],
            'order_count' => $history_data['order_count']
        );
        
        // Calculate subtotal and tax from orders
        foreach ($history_data['orders'] as $order_data) {
            $order = wc_get_order($order_data['order_id']);
            if ($order) {
                $totals['subtotal'] += $order->get_subtotal();
                $totals['tax_total'] += $order->get_total_tax();
            }
        }
        
        $totals['formatted_subtotal'] = wc_price($totals['subtotal']);
        $totals['formatted_tax'] = wc_price($totals['tax_total']);
        
        return $totals;
    }
    
    /**
     * Check if table has any pending orders
     * 
     * @param string $table_number The table number
     * @return bool True if table has pending orders
     */
    public function has_pending_orders($table_number) {
        $orders = $this->fetch_table_orders($table_number);
        return !empty($orders);
    }
    
    /**
     * Fetch table orders using multiple methods for reliability
     * 
     * @param string $table_number The table number
     * @return array Order posts
     */
    private function fetch_table_orders($table_number) {
        // For guest order history, only show pending/processing orders (exclude completed)
        $post_statuses = array(
            'wc-pending',
            'wc-processing'
        );
        
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => $post_statuses,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $orders = get_posts($args);
        
        // Fallback method: WooCommerce native wc_get_orders
        if (empty($orders) && function_exists('wc_get_orders')) {
            $wc_orders = wc_get_orders(array(
                'status' => array('pending', 'processing'),
                'meta_key' => '_oj_table_number',
                'meta_value' => $table_number,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            // Convert WC_Order objects to post objects for consistency
            $orders = array();
            foreach ($wc_orders as $wc_order) {
                $post = get_post($wc_order->get_id());
                if ($post) {
                    $orders[] = $post;
                }
            }
        }
        
        return $orders;
    }
    
    /**
     * Process order items and extract detailed information
     * 
     * @param WC_Order $order WooCommerce order object
     * @return array Processed order items
     */
    private function process_order_items($order) {
        $order_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            
            // Get basic item info
            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => $quantity,
                'total' => $item->get_total(),
                'formatted_total' => wc_price($item->get_total()),
                'unit_price' => $item->get_total() / $quantity,
                'formatted_unit_price' => wc_price($item->get_total() / $quantity),
                'base_price' => 0,
                'variations' => array(),
                'addons' => array(),
                'notes' => ''
            );
            
            // Get base price from stored meta
            $stored_base_price = $item->get_meta('_oj_base_price');
            if ($stored_base_price) {
                $item_data['base_price'] = floatval($stored_base_price);
                $item_data['formatted_base_price'] = wc_price($stored_base_price);
            }
            
            // Get variations
            if ($product && $product->is_type('variation')) {
                $variation_attributes = $product->get_variation_attributes();
                if (!empty($variation_attributes)) {
                    foreach ($variation_attributes as $name => $value) {
                        $attribute_name = ucfirst(str_replace('pa_', '', $name));
                        $item_data['variations'][$attribute_name] = $value;
                    }
                }
            }
            
            // Get add-ons from stored data
            $addons_data = $item->get_meta('_oj_addons_data');
            if ($addons_data && is_array($addons_data)) {
                $item_data['addons'] = $addons_data;
            } else {
                // Fallback to string format
                $addons_string = $item->get_meta('_oj_item_addons');
                if ($addons_string) {
                    $addon_parts = explode(', ', $addons_string);
                    foreach ($addon_parts as $addon_part) {
                        $item_data['addons'][] = array(
                            'name' => strip_tags($addon_part),
                            'price' => 0
                        );
                    }
                }
            }
            
            // Get notes
            $notes = $item->get_meta('_oj_item_notes');
            if ($notes) {
                $item_data['notes'] = $notes;
            }
            
            $order_items[] = $item_data;
        }
        
        return $order_items;
    }
}
