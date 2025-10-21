<?php
declare(strict_types=1);
/**
 * Orders Jet - Table Query Handler Class
 * Handles complex table order querying logic extracted from AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Table_Query_Handler {
    
    /**
     * Get orders for a specific table
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function get_orders($post_data) {
        $table_number = sanitize_text_field($post_data['table_number']);
        
        error_log('Orders Jet: Getting orders for table: ' . $table_number);
        
        if (empty($table_number)) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        // Get orders using multiple methods for reliability
        $orders = $this->fetch_table_orders($table_number);
        
        // Process orders and build response data
        $order_data = array();
        $total_amount = 0;
        
        error_log('Orders Jet: Found ' . count($orders) . ' orders for table ' . $table_number);
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) continue;
            
            $order_items = $this->process_order_items($order);
            
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => wc_price($order->get_total()),
                'items' => $order_items,
                'date' => $order->get_date_created()->format('Y-m-d H:i:s')
            );
            
            $total_amount += $order->get_total();
        }
        
        // Generate debug information
        $debug_info = $this->generate_debug_info($table_number);
        
        return array(
            'orders' => $order_data,
            'total' => $total_amount,
            'debug' => array(
                'searched_table' => $table_number,
                'recent_orders' => $debug_info
            )
        );
    }
    
    /**
     * Fetch table orders using multiple methods for reliability
     */
    private function fetch_table_orders($table_number) {
        // For guest order history, only show pending/processing orders (exclude completed)
        // This prevents guests from seeing completed orders from previous sessions
        $post_statuses = array(
            'wc-pending',
            'wc-processing'
        );
        
        error_log('Orders Jet: Showing only pending/processing orders for guest privacy');
        
        // Primary method: get_posts
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
        
        error_log('Orders Jet: Query args: ' . print_r($args, true));
        
        $orders = get_posts($args);
        
        error_log('Orders Jet: Main query found ' . count($orders) . ' orders for table ' . $table_number);
        
        // Fallback method: WooCommerce native wc_get_orders
        if (count($orders) == 0 && function_exists('wc_get_orders')) {
            error_log('Orders Jet: Trying WooCommerce native wc_get_orders method...');
            
            // Convert post statuses to WooCommerce statuses for fallback query
            $wc_statuses = array();
            foreach ($post_statuses as $status) {
                $wc_statuses[] = str_replace('wc-', '', $status);
            }
            
            $wc_orders = wc_get_orders(array(
                'status' => $wc_statuses,
                'meta_key' => '_oj_table_number',
                'meta_value' => $table_number,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            error_log('Orders Jet: WooCommerce native method found ' . count($wc_orders) . ' orders');
            
            if (count($wc_orders) > 0) {
                // Convert WC_Order objects to post objects for consistency
                $orders = array();
                foreach ($wc_orders as $wc_order) {
                    $post = get_post($wc_order->get_id());
                    if ($post) {
                        $orders[] = $post;
                    }
                }
                error_log('Orders Jet: Converted ' . count($orders) . ' WooCommerce orders to post objects');
            }
        }
        
        return $orders;
    }
    
    /**
     * Process order items and extract detailed information
     */
    private function process_order_items($order) {
        $order_items = array();
        
        foreach ($order->get_items() as $item) {
            // Get basic item info
            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => wc_price($item->get_total()),
                'unit_price' => wc_price($item->get_total() / $item->get_quantity()),
                'base_price' => 0, // Will be set below for variant products
                'variations' => array(),
                'addons' => array(),
                'notes' => ''
            );
            
            // Process variations and metadata
            $this->process_item_variations($item, $item_data);
            $this->process_item_metadata($item, $item_data);
            $this->calculate_base_price($item, $item_data);
            
            error_log('Orders Jet: Final base_price: ' . $item_data['base_price']);
            
            $order_items[] = $item_data;
        }
        
        return $order_items;
    }
    
    /**
     * Process item variations using WooCommerce native methods
     */
    private function process_item_variations($item, &$item_data) {
        $product = $item->get_product();
        if ($product && $product->is_type('variation')) {
            // For variation products, get variation attributes directly
            $variation_attributes = $product->get_variation_attributes();
            foreach ($variation_attributes as $attribute_name => $attribute_value) {
                if (!empty($attribute_value)) {
                    // Clean attribute name and get proper label
                    $clean_attribute_name = str_replace('attribute_', '', $attribute_name);
                    $attribute_label = wc_attribute_label($clean_attribute_name);
                    $item_data['variations'][$attribute_label] = $attribute_value;
                }
            }
        }
    }
    
    /**
     * Process item metadata for add-ons, notes, and custom variations
     */
    private function process_item_metadata($item, &$item_data) {
        $item_meta = $item->get_meta_data();
        foreach ($item_meta as $meta) {
            $meta_key = $meta->key;
            $meta_value = $meta->value;
            
            // Get add-ons (prefer structured data if available)
            if ($meta_key === '_oj_addons_data' && is_array($meta_value)) {
                $item_data['addons'] = array_map(function($addon) {
                    return $addon['name'] . ' (+' . wc_price($addon['price']) . ')';
                }, $meta_value);
            } elseif ($meta_key === '_oj_item_addons' && empty($item_data['addons'])) {
                $addons = explode(', ', $meta_value);
                $item_data['addons'] = array_map(function($addon) {
                    return strip_tags($addon);
                }, $addons);
            }
            
            // Get custom variations (for non-variation products)
            if ($meta_key === '_oj_variations_data' && is_array($meta_value) && empty($item_data['variations'])) {
                foreach ($meta_value as $variation) {
                    $item_data['variations'][$variation['name']] = $variation['value'] ?? $variation['name'];
                }
            } elseif ($meta_key === '_oj_item_variations' && empty($item_data['variations'])) {
                // Parse the old format as fallback
                $variations = explode(', ', $meta_value);
                foreach ($variations as $variation_string) {
                    if (preg_match('/^(.+?)\s*\(\+/', $variation_string, $matches)) {
                        $item_data['variations'][$matches[1]] = $matches[1];
                    }
                }
            }
            
            // Also check for standard WooCommerce variation attributes in meta (fallback)
            if (empty($item_data['variations']) && (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0)) {
                $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                $attribute_label = wc_attribute_label($attribute_name);
                $item_data['variations'][$attribute_label] = $meta_value;
            }
            
            // Get notes
            if ($meta_key === '_oj_item_notes') {
                $item_data['notes'] = $meta_value;
            }
        }
    }
    
    /**
     * Calculate base price for the item
     */
    private function calculate_base_price($item, &$item_data) {
        $base_price_found = false;
        
        // Check if we stored the original variant price in meta data
        $stored_base_price = $item->get_meta('_oj_base_price');
        if ($stored_base_price) {
            $item_data['base_price'] = floatval($stored_base_price);
            $base_price_found = true;
            error_log('Orders Jet: Using stored base price: ' . $item_data['base_price']);
        }
        
        // If no stored price, try to calculate from current data
        if (!$base_price_found) {
            $product = $item->get_product();
            
            // Debug logging
            error_log('Orders Jet: Product ID: ' . ($product ? $product->get_id() : 'null'));
            error_log('Orders Jet: Product type: ' . ($product ? $product->get_type() : 'null'));
            error_log('Orders Jet: Item total: ' . $item->get_total());
            error_log('Orders Jet: Item quantity: ' . $item->get_quantity());
            
            // Check if this is a variation product
            if ($product && $product->is_type('variation')) {
                $variation_price = $product->get_price();
                $item_data['base_price'] = $variation_price;
                $base_price_found = true;
                error_log('Orders Jet: Variation product, price: ' . $variation_price);
                
                // Get variation attributes
                $variation_attributes = $product->get_variation_attributes();
                foreach ($variation_attributes as $attribute_name => $attribute_value) {
                    if (!empty($attribute_value)) {
                        $attribute_label = wc_attribute_label($attribute_name);
                        $item_data['variations'][$attribute_label] = $attribute_value;
                    }
                }
            } else {
                // For non-variation products, calculate base price by subtracting add-ons
                $addon_total = $this->calculate_addon_total($item_data['addons'], $item->get_quantity());
                
                $item_total = $item->get_total();
                $base_price = ($item_total - $addon_total) / $item->get_quantity();
                $item_data['base_price'] = $base_price;
                $base_price_found = true;
                
                error_log('Orders Jet: Calculated base price: ' . $base_price);
                error_log('Orders Jet: Item total: ' . $item_total);
                error_log('Orders Jet: Addon total: ' . $addon_total);
            }
        }
    }
    
    /**
     * Calculate total add-on cost
     */
    private function calculate_addon_total($addons, $quantity) {
        $addon_total = 0;
        if (!empty($addons)) {
            foreach ($addons as $addon_string) {
                // Extract price from add-on string like "Extra 2 (+100.00 EGP)"
                preg_match('/\(([^)]+)\)/', $addon_string, $matches);
                if (isset($matches[1])) {
                    $price_string = $matches[1];
                    preg_match('/[\d,]+\.?\d*/', $price_string, $price_matches);
                    if (isset($price_matches[0])) {
                        $addon_price = floatval(str_replace(',', '.', $price_matches[0]));
                        $addon_total += $addon_price * $quantity;
                    }
                }
            }
        }
        return $addon_total;
    }
    
    /**
     * Generate debug information for troubleshooting
     */
    private function generate_debug_info($table_number) {
        // Get recent orders for debugging
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array(
                'wc-pending',
                'wc-processing', 
                'wc-pending',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed'
            ),
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        error_log('Orders Jet: Recent orders count: ' . count($recent_orders));
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $table_meta = $order->get_meta('_oj_table_number');
                $total = $order->get_total();
                $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                error_log('Orders Jet: Recent order #' . $order->get_id() . ' - Table: "' . $table_meta . '" - Status: ' . $order->get_status() . ' - Total: ' . $total . ' - Billing: ' . $billing_name);
            }
        }
        
        // Specifically check for order ID 214 (debug case)
        $test_order = wc_get_order(214);
        if ($test_order) {
            error_log('Orders Jet: Found order 214 - Status: ' . $test_order->get_status() . ' - Table: "' . $test_order->get_meta('_oj_table_number') . '" - Total: ' . $test_order->get_total());
        } else {
            error_log('Orders Jet: Order 214 NOT FOUND');
        }
        
        // Prepare debug information
        $debug_info = array();
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $debug_info[] = array(
                    'id' => $order->get_id(),
                    'table' => $order->get_meta('_oj_table_number'),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'billing' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
                );
            }
        }
        
        return $debug_info;
    }
}
