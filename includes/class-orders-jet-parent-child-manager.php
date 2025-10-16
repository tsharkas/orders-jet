<?php
/**
 * Orders Jet - Parent-Child Order Manager
 * Handles parent-child order relationships for table orders
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Parent_Child_Manager {
    
    public function __construct() {
        // Initialize hooks
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Only initialize if feature flag is enabled
        if (!$this->is_parent_child_enabled()) {
            return;
        }
        
        error_log('Orders Jet: Parent-Child order system enabled');
    }
    
    /**
     * Check if parent-child order system is enabled
     */
    public function is_parent_child_enabled() {
        return get_option('oj_enable_parent_child_orders', false);
    }
    
    /**
     * Enable parent-child order system
     */
    public function enable_parent_child_orders() {
        update_option('oj_enable_parent_child_orders', true);
        error_log('Orders Jet: Parent-Child order system ENABLED');
    }
    
    /**
     * Disable parent-child order system (fallback to legacy)
     */
    public function disable_parent_child_orders() {
        update_option('oj_enable_parent_child_orders', false);
        error_log('Orders Jet: Parent-Child order system DISABLED - using legacy mode');
    }
    
    /**
     * Get or create parent order for a table
     */
    public function get_or_create_parent_order($table_number) {
        // Check if parent order already exists for this table session
        $existing_parent = $this->get_active_parent_order($table_number);
        
        if ($existing_parent) {
            error_log('Orders Jet: Found existing parent order #' . $existing_parent->get_id() . ' for table ' . $table_number);
            return $existing_parent;
        }
        
        // Create new parent order
        $parent_order = wc_create_order();
        
        if (is_wp_error($parent_order)) {
            error_log('Orders Jet: Failed to create parent order: ' . $parent_order->get_error_message());
            return false;
        }
        
        // Set parent order properties
        $parent_order->set_billing_first_name('Table ' . $table_number);
        $parent_order->set_billing_last_name('Combined Invoice');
        $parent_order->set_billing_phone('N/A');
        $parent_order->set_billing_email('table' . $table_number . '@restaurant.local');
        
        // Set parent order meta
        $parent_order->update_meta_data('_oj_table_number', $table_number);
        $parent_order->update_meta_data('_oj_order_type', 'parent');
        $parent_order->update_meta_data('_oj_order_method', 'dinein');
        $parent_order->update_meta_data('_oj_session_start', current_time('mysql'));
        $parent_order->update_meta_data('_oj_child_orders', array());
        
        // Set status to pending (will be completed when table is closed)
        $parent_order->set_status('pending');
        
        $parent_order->save();
        
        error_log('Orders Jet: Created new parent order #' . $parent_order->get_id() . ' for table ' . $table_number);
        
        return $parent_order;
    }
    
    /**
     * Get active parent order for a table
     */
    public function get_active_parent_order($table_number) {
        $parent_orders = wc_get_orders(array(
            'status' => array('pending', 'processing'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_order_type',
                    'value' => 'parent',
                    'compare' => '='
                )
            ),
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        return !empty($parent_orders) ? $parent_orders[0] : false;
    }
    
    /**
     * Add child order to parent order
     */
    public function add_child_to_parent($parent_order, $child_order) {
        if (!$parent_order || !$child_order) {
            return false;
        }
        
        // Link child to parent
        $child_order->update_meta_data('_oj_parent_order_id', $parent_order->get_id());
        $child_order->update_meta_data('_oj_order_type', 'child');
        $child_order->save();
        
        // Add child order details to parent
        $child_orders_data = $parent_order->get_meta('_oj_child_orders') ?: array();
        
        $child_order_data = array(
            'child_order_id' => $child_order->get_id(),
            'order_number' => $child_order->get_order_number(),
            'order_time' => $child_order->get_date_created()->format('H:i'),
            'order_date' => $child_order->get_date_created()->format('Y-m-d H:i:s'),
            'customer_name' => $child_order->get_billing_first_name(),
            'subtotal' => $child_order->get_subtotal(),
            'items' => array()
        );
        
        // Store individual items with full details
        foreach ($child_order->get_items() as $item) {
            $child_order_data['items'][] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_subtotal() / $item->get_quantity(),
                'total' => $item->get_subtotal(),
                'variations' => $this->get_item_variations($item),
                'addons' => $item->get_meta('_oj_item_addons'),
                'notes' => $item->get_meta('_oj_item_notes')
            );
        }
        
        $child_orders_data[] = $child_order_data;
        $parent_order->update_meta_data('_oj_child_orders', $child_orders_data);
        
        // Add all child order items to parent order for tax calculation
        foreach ($child_order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $parent_order->add_product($product, $item->get_quantity(), array(
                    'totals' => array(
                        'subtotal' => $item->get_subtotal(),
                        'total' => $item->get_total(),
                    )
                ));
            }
        }
        
        // Recalculate parent order totals (this will calculate taxes)
        $parent_order->calculate_totals();
        $parent_order->save();
        
        error_log('Orders Jet: Added child order #' . $child_order->get_id() . ' to parent order #' . $parent_order->get_id());
        
        return true;
    }
    
    /**
     * Get child orders for a parent order
     */
    public function get_child_orders($parent_order_id) {
        return wc_get_orders(array(
            'meta_key' => '_oj_parent_order_id',
            'meta_value' => $parent_order_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Close table using parent-child system
     */
    public function close_table_parent_child($table_number, $payment_method) {
        $parent_order = $this->get_active_parent_order($table_number);
        
        if (!$parent_order) {
            error_log('Orders Jet: No active parent order found for table ' . $table_number);
            return false;
        }
        
        // Get all child orders
        $child_orders = $this->get_child_orders($parent_order->get_id());
        
        // Complete all child orders
        foreach ($child_orders as $child_order) {
            $child_order->set_status('completed');
            $child_order->update_meta_data('_oj_payment_method', $payment_method);
            $child_order->update_meta_data('_oj_table_closed', current_time('mysql'));
            $child_order->save();
            
            error_log('Orders Jet: Completed child order #' . $child_order->get_id());
        }
        
        // Complete parent order (taxes already calculated)
        $parent_order->set_status('completed');
        $parent_order->update_meta_data('_oj_payment_method', $payment_method);
        $parent_order->update_meta_data('_oj_table_closed', current_time('mysql'));
        $parent_order->update_meta_data('_oj_tax_method', 'combined_invoice');
        
        // Add completion note
        $parent_order->add_order_note(sprintf(
            __('Table %s closed - Combined invoice with %d child orders - Payment: %s (Subtotal: %s, Tax: %s, Total: %s)', 'orders-jet'),
            $table_number,
            count($child_orders),
            $payment_method,
            wc_price($parent_order->get_subtotal()),
            wc_price($parent_order->get_total_tax()),
            wc_price($parent_order->get_total())
        ));
        
        $parent_order->save();
        
        error_log('Orders Jet: Completed parent order #' . $parent_order->get_id() . ' for table ' . $table_number);
        
        return $parent_order;
    }
    
    /**
     * Get item variations as string
     */
    private function get_item_variations($item) {
        $variations = array();
        $item_meta = $item->get_meta_data();
        
        foreach ($item_meta as $meta) {
            $meta_key = $meta->key;
            $meta_value = $meta->value;
            
            // Check for variation attributes
            if (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0) {
                $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                $attribute_label = wc_attribute_label($attribute_name);
                $variations[] = $attribute_label . ': ' . $meta_value;
            }
        }
        
        return implode(', ', $variations);
    }
    
    /**
     * Check if order is legacy (pre-parent-child) or new system
     */
    public function is_legacy_order($order) {
        $parent_order_id = $order->get_meta('_oj_parent_order_id');
        $child_orders = $order->get_meta('_oj_child_orders');
        $order_type = $order->get_meta('_oj_order_type');
        
        // Legacy order: has table number but no parent/child relationship
        return !empty($order->get_meta('_oj_table_number')) && 
               empty($parent_order_id) && 
               empty($child_orders) &&
               $order_type !== 'parent' &&
               $order_type !== 'child';
    }
    
    /**
     * Get effective orders for table (works with both legacy and new systems)
     */
    public function get_table_orders($table_number) {
        // First, try to get parent order (new system)
        $parent_order = $this->get_active_parent_order($table_number);
        
        if ($parent_order) {
            // New system: return parent order and its children
            $child_orders = $this->get_child_orders($parent_order->get_id());
            return array(
                'system' => 'parent_child',
                'parent' => $parent_order,
                'children' => $child_orders
            );
        }
        
        // Fallback: legacy system
        $legacy_orders = wc_get_orders(array(
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'status' => array('processing', 'pending'),
            'limit' => -1
        ));
        
        // Filter out any parent/child orders to avoid conflicts
        $legacy_orders = array_filter($legacy_orders, array($this, 'is_legacy_order'));
        
        return array(
            'system' => 'legacy',
            'orders' => $legacy_orders
        );
    }
}
