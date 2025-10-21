<?php
declare(strict_types=1);
/**
 * Orders Jet - Kitchen Service Class
 * Handles kitchen management, order types, and readiness status
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Kitchen_Service {
    
    /**
     * Get the kitchen type for an order based on its items
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string Kitchen type: 'food', 'beverages', or 'mixed'
     */
    public function get_order_kitchen_type($order) {
        $kitchen_types = array();
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Check variation first, then fall back to main product
            $kitchen = '';
            if ($variation_id > 0) {
                $kitchen = get_post_meta($variation_id, 'Kitchen', true);
            }
            // Fall back to main product if variation doesn't have Kitchen field
            if (empty($kitchen)) {
                $kitchen = get_post_meta($product_id, 'Kitchen', true);
            }
            
            if (!empty($kitchen)) {
                $kitchen_types[] = strtolower(trim($kitchen));
            }
        }
        
        // Remove duplicates and determine final kitchen type
        $unique_types = array_unique($kitchen_types);
        
        if (count($unique_types) === 1) {
            return $unique_types[0];
        } elseif (count($unique_types) > 1) {
            return 'mixed';
        }
        
        // Default fallback to food if no kitchen field is set
        return 'food';
    }
    
    /**
     * Get kitchen readiness status for an order
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return array Kitchen readiness information
     */
    public function get_kitchen_readiness_status($order) {
        $kitchen_type = $order->get_meta('_oj_kitchen_type');
        if (empty($kitchen_type)) {
            // Determine and store kitchen type if not set
            $kitchen_type = $this->get_order_kitchen_type($order);
            $order->update_meta_data('_oj_kitchen_type', $kitchen_type);
            $order->save();
        }
        
        $order_status = $order->get_status();
        
        // Get readiness flags
        $food_ready = $order->get_meta('_oj_food_ready') === 'yes';
        $beverage_ready = $order->get_meta('_oj_beverage_ready') === 'yes';
        
        $status = array(
            'kitchen_type' => $kitchen_type,
            'food_ready' => $food_ready,
            'beverage_ready' => $beverage_ready,
            'all_ready' => false,
            'waiting_for' => array()
        );
        
        // Determine overall readiness based on kitchen type
        switch ($kitchen_type) {
            case 'food':
                $status['all_ready'] = $food_ready;
                if (!$food_ready) {
                    $status['waiting_for'][] = 'food';
                }
                break;
                
            case 'beverages':
                $status['all_ready'] = $beverage_ready;
                if (!$beverage_ready) {
                    $status['waiting_for'][] = 'beverages';
                }
                break;
                
            case 'mixed':
                $status['all_ready'] = $food_ready && $beverage_ready;
                if (!$food_ready) {
                    $status['waiting_for'][] = 'food';
                }
                if (!$beverage_ready) {
                    $status['waiting_for'][] = 'beverages';
                }
                break;
        }
        
        return $status;
    }
    
    /**
     * Get kitchen status badge HTML for display
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string HTML for kitchen status badge
     */
    public function get_kitchen_status_badge($order) {
        $status = $this->get_kitchen_readiness_status($order);
        $order_status = $order->get_status();
        
        if ($order_status === 'completed') {
            return '<span class="oj-status-badge completed">✅ ' . __('Completed', 'orders-jet') . '</span>';
        }
        
        if ($order_status === 'pending' && $status['all_ready']) {
            return '<span class="oj-status-badge ready">✅ ' . __('Ready', 'orders-jet') . '</span>';
        }
        
        if ($status['kitchen_type'] === 'mixed' && $order_status === 'processing') {
            if ($status['food_ready'] && !$status['beverage_ready']) {
                return '<span class="oj-status-badge partial">🍕✅ 🥤⏳ ' . __('Waiting for Bev.', 'orders-jet') . '</span>';
            } elseif (!$status['food_ready'] && $status['beverage_ready']) {
                return '<span class="oj-status-badge partial">🍕⏳ 🥤✅ ' . __('Waiting for Food', 'orders-jet') . '</span>';
            } else {
                return '<span class="oj-status-badge partial">🍕⏳ 🥤⏳ ' . __('Both Kitchens', 'orders-jet') . '</span>';
            }
        } elseif ($order_status === 'processing') {
            if ($status['kitchen_type'] === 'food') {
                return '<span class="oj-status-badge partial">🍕⏳ ' . __('Waiting for Food', 'orders-jet') . '</span>';
            } elseif ($status['kitchen_type'] === 'beverages') {
                return '<span class="oj-status-badge partial">🥤⏳ ' . __('Waiting for Bev.', 'orders-jet') . '</span>';
            }
        }
        
        return '<span class="oj-status-badge kitchen">⏳ ' . __('Kitchen', 'orders-jet') . '</span>';
    }
    
    /**
     * Get kitchen type badge for order cards
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string HTML for kitchen type badge
     */
    public function get_kitchen_type_badge($order) {
        $kitchen_type = $order->get_meta('_oj_kitchen_type');
        if (empty($kitchen_type)) {
            $kitchen_type = $this->get_order_kitchen_type($order);
        }
        
        switch ($kitchen_type) {
            case 'food':
                return '<span class="oj-kitchen-badge food">🍕 ' . __('Food', 'orders-jet') . '</span>';
            case 'beverages':
                return '<span class="oj-kitchen-badge beverages">🥤 ' . __('Beverages', 'orders-jet') . '</span>';
            case 'mixed':
                return '<span class="oj-kitchen-badge mixed">🍽️ ' . __('Mixed', 'orders-jet') . '</span>';
            default:
                return '<span class="oj-kitchen-badge food">🍕 ' . __('Food', 'orders-jet') . '</span>';
        }
    }
    
    /**
     * Mark specific kitchen type as ready for an order
     * 
     * @param WC_Order $order The order to update
     * @param string $kitchen_type The kitchen type to mark ready ('food' or 'beverages')
     * @return bool Success status
     */
    public function mark_kitchen_ready($order, $kitchen_type) {
        if (!in_array($kitchen_type, ['food', 'beverages'])) {
            error_log('Orders Jet Kitchen: Invalid kitchen type: ' . $kitchen_type);
            return false;
        }
        
        $meta_key = '_oj_' . $kitchen_type . '_ready';
        $order->update_meta_data($meta_key, 'yes');
        $order->update_meta_data('_oj_' . $kitchen_type . '_ready_time', current_time('mysql'));
        
        // Check if entire order is ready
        $status = $this->get_kitchen_readiness_status($order);
        if ($status['all_ready']) {
            $order->set_status('pending'); // Ready for pickup/delivery
            $order->add_order_note(sprintf(
                __('Order marked as ready - %s kitchen completed', 'orders-jet'),
                ucfirst($kitchen_type)
            ));
        } else {
            $order->add_order_note(sprintf(
                __('%s kitchen completed - waiting for %s', 'orders-jet'),
                ucfirst($kitchen_type),
                implode(', ', $status['waiting_for'])
            ));
        }
        
        $order->save();
        
        error_log('Orders Jet Kitchen: Marked ' . $kitchen_type . ' ready for order #' . $order->get_id());
        return true;
    }
    
    /**
     * Get kitchen summary for dashboard display
     * 
     * @param array $orders Array of WC_Order objects
     * @return array Kitchen summary statistics
     */
    public function get_kitchen_summary($orders) {
        $summary = array(
            'total_orders' => count($orders),
            'food_orders' => 0,
            'beverage_orders' => 0,
            'mixed_orders' => 0,
            'ready_orders' => 0,
            'waiting_orders' => 0,
            'food_ready' => 0,
            'beverage_ready' => 0
        );
        
        foreach ($orders as $order) {
            $kitchen_type = $this->get_order_kitchen_type($order);
            $status = $this->get_kitchen_readiness_status($order);
            
            // Count by kitchen type
            switch ($kitchen_type) {
                case 'food':
                    $summary['food_orders']++;
                    break;
                case 'beverages':
                    $summary['beverage_orders']++;
                    break;
                case 'mixed':
                    $summary['mixed_orders']++;
                    break;
            }
            
            // Count by readiness
            if ($status['all_ready']) {
                $summary['ready_orders']++;
            } else {
                $summary['waiting_orders']++;
            }
            
            // Count individual kitchen readiness
            if ($status['food_ready']) {
                $summary['food_ready']++;
            }
            if ($status['beverage_ready']) {
                $summary['beverage_ready']++;
            }
        }
        
        return $summary;
    }
    
    /**
     * Get orders filtered by kitchen type and status
     * 
     * @param string $kitchen_filter Kitchen type filter ('all', 'food', 'beverages', 'mixed')
     * @param string $status_filter Status filter ('all', 'waiting', 'ready')
     * @return array Filtered orders
     */
    public function get_filtered_kitchen_orders($kitchen_filter = 'all', $status_filter = 'all') {
        // Get orders that are in processing status (being prepared)
        $orders = wc_get_orders(array(
            'status' => 'processing',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        $filtered_orders = array();
        
        foreach ($orders as $order) {
            $kitchen_type = $this->get_order_kitchen_type($order);
            $status = $this->get_kitchen_readiness_status($order);
            
            // Apply kitchen filter
            if ($kitchen_filter !== 'all' && $kitchen_type !== $kitchen_filter) {
                continue;
            }
            
            // Apply status filter
            if ($status_filter === 'waiting' && $status['all_ready']) {
                continue;
            }
            if ($status_filter === 'ready' && !$status['all_ready']) {
                continue;
            }
            
            $filtered_orders[] = $order;
        }
        
        return $filtered_orders;
    }
}
