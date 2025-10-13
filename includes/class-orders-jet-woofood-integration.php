<?php
/**
 * WooFood Integration Class
 * 
 * Provides seamless integration between Orders Jet and WooFood plugin
 * 
 * @package Orders_Jet_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_WooFood_Integration {
    
    /**
     * WooFood instance
     */
    private $woofood_instance;
    
    /**
     * Integration status
     */
    private $is_woofood_active = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_integration'), 20);
        add_action('admin_notices', array($this, 'integration_notices'));
    }
    
    /**
     * Initialize integration
     */
    public function init_integration() {
        // Check if WooFood is active
        if (class_exists('EX_WooFood')) {
            $this->is_woofood_active = true;
            
            // Try to get WooFood instance - check different patterns
            if (method_exists('EX_WooFood', 'instance')) {
                $this->woofood_instance = EX_WooFood::instance();
            } elseif (method_exists('EX_WooFood', 'get_instance')) {
                $this->woofood_instance = EX_WooFood::get_instance();
            } else {
                // WooFood might not use singleton pattern
                $this->woofood_instance = null;
                error_log('Orders Jet: WooFood detected but no singleton method found');
            }
            
            $this->setup_hooks();
            $this->register_integration_hooks();
        }
    }
    
    /**
     * Setup integration hooks
     */
    private function setup_hooks() {
        // Order processing integration
        add_action('woocommerce_checkout_order_processed', array($this, 'process_woofood_order'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Product integration
        add_filter('oj_product_details', array($this, 'enhance_product_with_woofood_data'), 10, 2);
        add_filter('oj_cart_item_data', array($this, 'process_woofood_cart_data'), 10, 2);
        
        // Location integration
        add_filter('oj_available_locations', array($this, 'get_woofood_locations'));
        add_filter('oj_location_data', array($this, 'enhance_location_data'), 10, 2);
        
        // Menu integration
        add_filter('oj_menu_items', array($this, 'filter_menu_by_location'), 10, 2);
        
        // Admin integration
        add_action('oj_admin_dashboard_data', array($this, 'add_woofood_dashboard_data'));
        
        // WooFood location admin integration
        add_action('exwoofood_loc_edit_form', array($this, 'add_table_management_to_woofood_location'));
        add_action('exwoofood_loc_add_form_fields', array($this, 'add_table_management_to_woofood_location_new'));
    }
    
    /**
     * Register WooFood-specific hooks
     */
    private function register_integration_hooks() {
        // Hook into WooFood's order processing if available
        $woofood_hooks = array(
            'exwf_order_created' => 'handle_woofood_order_created',
            'exwf_order_status_changed' => 'handle_woofood_status_change',
            'exwf_delivery_assigned' => 'handle_delivery_assignment',
            'exwf_order_ready' => 'handle_order_ready_notification'
        );
        
        foreach ($woofood_hooks as $hook => $method) {
            if (has_action($hook)) {
                add_action($hook, array($this, $method), 10, 3);
            }
        }
    }
    
    /**
     * Process WooFood order integration
     */
    public function process_woofood_order($order_id, $posted_data, $order) {
        if (!$this->is_woofood_active) {
            return;
        }
        
        // Get WooFood order type
        $order_type = $order->get_meta('_exwf_order_type');
        $location_id = $order->get_meta('_exwf_location_id');
        
        // Process based on order type
        switch ($order_type) {
            case 'dine_in':
                $this->process_dine_in_order($order, $location_id);
                break;
            case 'pickup':
                $this->process_pickup_order($order, $location_id);
                break;
            case 'delivery':
                $this->process_delivery_order($order, $location_id);
                break;
            default:
                // Handle as regular order
                $this->process_regular_order($order);
        }
        
        // Log integration
        error_log("Orders Jet: WooFood order processed - ID: {$order_id}, Type: {$order_type}, Location: {$location_id}");
    }
    
    /**
     * Process dine-in order
     */
    private function process_dine_in_order($order, $location_id) {
        $table_number = $order->get_meta('_oj_table_number');
        
        if ($table_number) {
            // This is a table order - integrate with our table system
            $table_id = oj_get_table_id_by_number($table_number);
            
            if ($table_id) {
                // Update table status
                update_post_meta($table_id, '_oj_table_status', 'occupied');
                update_post_meta($table_id, '_oj_table_location_id', $location_id);
                
                // Link order to table
                $order->update_meta_data('_oj_table_id', $table_id);
                $order->update_meta_data('_oj_order_type', 'dine_in');
                $order->update_meta_data('_oj_order_location_id', $location_id);
                $order->save();
                
                // Notify kitchen and staff
                $this->notify_staff_new_table_order($order, $table_number);
            }
        } else {
            // Regular dine-in order without table
            $order->update_meta_data('_oj_order_type', 'dine_in');
            $order->update_meta_data('_oj_order_location_id', $location_id);
            $order->save();
        }
    }
    
    /**
     * Process pickup order
     */
    private function process_pickup_order($order, $location_id) {
        $pickup_time = $order->get_meta('_exwf_pickup_time');
        
        // Add Orders Jet meta
        $order->update_meta_data('_oj_order_type', 'pickup');
        $order->update_meta_data('_oj_order_location_id', $location_id);
        $order->update_meta_data('_oj_pickup_time', $pickup_time);
        $order->save();
        
        // Notify kitchen
        $this->notify_kitchen_pickup_order($order);
    }
    
    /**
     * Process delivery order
     */
    private function process_delivery_order($order, $location_id) {
        $delivery_address = $order->get_meta('_exwf_delivery_address');
        $delivery_time = $order->get_meta('_exwf_delivery_time');
        $delivery_fee = $order->get_meta('_exwf_delivery_fee');
        
        // Add Orders Jet meta
        $order->update_meta_data('_oj_order_type', 'delivery');
        $order->update_meta_data('_oj_order_location_id', $location_id);
        $order->update_meta_data('_oj_delivery_address', $delivery_address);
        $order->update_meta_data('_oj_delivery_time', $delivery_time);
        $order->update_meta_data('_oj_delivery_fee', $delivery_fee);
        $order->save();
        
        // Notify kitchen and delivery team
        $this->notify_kitchen_delivery_order($order);
    }
    
    /**
     * Process regular order (fallback)
     */
    private function process_regular_order($order) {
        // Default to pickup if no type specified
        $order->update_meta_data('_oj_order_type', 'pickup');
        $order->save();
    }
    
    /**
     * Handle order status changes
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if (!$this->is_woofood_active) {
            return;
        }
        
        $order_type = $order->get_meta('_oj_order_type');
        
        switch ($new_status) {
            case 'processing':
                $this->handle_order_processing($order, $order_type);
                break;
            case 'on-hold':
                $this->handle_order_ready($order, $order_type);
                break;
            case 'completed':
                $this->handle_order_completed($order, $order_type);
                break;
        }
    }
    
    /**
     * Handle order processing status
     */
    private function handle_order_processing($order, $order_type) {
        // Notify kitchen
        do_action('oj_order_sent_to_kitchen', $order->get_id(), $order_type);
        
        if ($order_type === 'dine_in') {
            $table_number = $order->get_meta('_oj_table_number');
            if ($table_number) {
                // Update table status to occupied
                $table_id = oj_get_table_id_by_number($table_number);
                if ($table_id) {
                    update_post_meta($table_id, '_oj_table_status', 'occupied');
                }
            }
        }
    }
    
    /**
     * Handle order ready status
     */
    private function handle_order_ready($order, $order_type) {
        // Notify staff and customer
        do_action('oj_order_ready_for_service', $order->get_id(), $order_type);
        
        switch ($order_type) {
            case 'dine_in':
                $this->notify_waiter_order_ready($order);
                break;
            case 'pickup':
                $this->notify_customer_pickup_ready($order);
                break;
            case 'delivery':
                $this->notify_delivery_team($order);
                break;
        }
    }
    
    /**
     * Handle order completed status
     */
    private function handle_order_completed($order, $order_type) {
        if ($order_type === 'dine_in') {
            $table_number = $order->get_meta('_oj_table_number');
            if ($table_number) {
                // Check if this is the last order for the table
                $table_orders = $this->get_active_table_orders($table_number);
                if (count($table_orders) <= 1) {
                    // This was the last order, table can be available
                    $table_id = oj_get_table_id_by_number($table_number);
                    if ($table_id) {
                        update_post_meta($table_id, '_oj_table_status', 'available');
                    }
                }
            }
        }
        
        do_action('oj_order_completed', $order->get_id(), $order_type);
    }
    
    /**
     * Enhance product details with WooFood data
     */
    public function enhance_product_with_woofood_data($product_data, $product_id) {
        if (!$this->is_woofood_active) {
            return $product_data;
        }
        
        // Get WooFood product data
        $woofood_data = get_post_meta($product_id, '_exwf_product_data', true);
        $locations = get_post_meta($product_id, '_exwf_locations', true);
        $preparation_time = get_post_meta($product_id, '_exwf_preparation_time', true);
        
        // Add WooFood data to product
        if ($woofood_data) {
            $product_data['woofood_data'] = $woofood_data;
        }
        
        if ($locations) {
            $product_data['available_locations'] = $locations;
        }
        
        if ($preparation_time) {
            $product_data['preparation_time'] = $preparation_time;
        }
        
        // Check if product supports different order types
        $product_data['supports_dine_in'] = get_post_meta($product_id, '_exwf_dine_in_enabled', true);
        $product_data['supports_pickup'] = get_post_meta($product_id, '_exwf_pickup_enabled', true);
        $product_data['supports_delivery'] = get_post_meta($product_id, '_exwf_delivery_enabled', true);
        
        return $product_data;
    }
    
    /**
     * Get WooFood locations
     */
    public function get_woofood_locations($locations = array()) {
        if (!$this->is_woofood_active) {
            return $locations;
        }
        
        // Get WooFood locations
        $woofood_locations = get_posts(array(
            'post_type' => 'exwf_location',
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        foreach ($woofood_locations as $location) {
            $location_data = array(
                'id' => $location->ID,
                'name' => $location->post_title,
                'address' => get_post_meta($location->ID, '_exwf_location_address', true),
                'phone' => get_post_meta($location->ID, '_exwf_location_phone', true),
                'operating_hours' => get_post_meta($location->ID, '_exwf_operating_hours', true),
                'delivery_zones' => get_post_meta($location->ID, '_exwf_delivery_zones', true),
                'minimum_order' => get_post_meta($location->ID, '_exwf_minimum_order', true),
                'delivery_fee' => get_post_meta($location->ID, '_exwf_delivery_fee', true),
                'source' => 'woofood'
            );
            
            $locations[] = $location_data;
        }
        
        return $locations;
    }
    
    /**
     * Filter menu items by location
     */
    public function filter_menu_by_location($menu_items, $location_id) {
        if (!$this->is_woofood_active || !$location_id) {
            return $menu_items;
        }
        
        // Filter products available at this location
        foreach ($menu_items as $key => $item) {
            $product_locations = get_post_meta($item['id'], '_exwf_locations', true);
            
            if ($product_locations && is_array($product_locations)) {
                if (!in_array($location_id, $product_locations)) {
                    unset($menu_items[$key]);
                }
            }
        }
        
        return array_values($menu_items);
    }
    
    /**
     * Get active table orders
     */
    private function get_active_table_orders($table_number) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold'),
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
     * Notification methods
     */
    private function notify_staff_new_table_order($order, $table_number) {
        do_action('oj_notify_staff', array(
            'type' => 'new_table_order',
            'order_id' => $order->get_id(),
            'table_number' => $table_number,
            'message' => sprintf(__('New order for table %s', 'orders-jet'), $table_number)
        ));
    }
    
    private function notify_kitchen_pickup_order($order) {
        do_action('oj_notify_kitchen', array(
            'type' => 'pickup_order',
            'order_id' => $order->get_id(),
            'pickup_time' => $order->get_meta('_oj_pickup_time'),
            'message' => __('New pickup order received', 'orders-jet')
        ));
    }
    
    private function notify_kitchen_delivery_order($order) {
        do_action('oj_notify_kitchen', array(
            'type' => 'delivery_order',
            'order_id' => $order->get_id(),
            'delivery_time' => $order->get_meta('_oj_delivery_time'),
            'message' => __('New delivery order received', 'orders-jet')
        ));
    }
    
    private function notify_waiter_order_ready($order) {
        $table_number = $order->get_meta('_oj_table_number');
        do_action('oj_notify_waiter', array(
            'type' => 'order_ready',
            'order_id' => $order->get_id(),
            'table_number' => $table_number,
            'message' => sprintf(__('Order ready for table %s', 'orders-jet'), $table_number)
        ));
    }
    
    private function notify_customer_pickup_ready($order) {
        do_action('oj_notify_customer', array(
            'type' => 'pickup_ready',
            'order_id' => $order->get_id(),
            'customer_phone' => $order->get_billing_phone(),
            'message' => __('Your order is ready for pickup', 'orders-jet')
        ));
    }
    
    private function notify_delivery_team($order) {
        do_action('oj_notify_delivery', array(
            'type' => 'delivery_ready',
            'order_id' => $order->get_id(),
            'delivery_address' => $order->get_meta('_oj_delivery_address'),
            'message' => __('Order ready for delivery', 'orders-jet')
        ));
    }
    
    /**
     * Integration notices
     */
    public function integration_notices() {
        if (!$this->is_woofood_active && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . __('Orders Jet Integration:', 'orders-jet') . '</strong> ';
            echo __('WooFood plugin not detected. Some features may be limited.', 'orders-jet');
            echo '</p></div>';
        }
    }
    
    /**
     * Check if WooFood is active
     */
    public function is_woofood_active() {
        return $this->is_woofood_active;
    }
    
    /**
     * Get WooFood instance
     */
    public function get_woofood_instance() {
        return $this->woofood_instance;
    }
    
    /**
     * Check if WooFood instance is available
     */
    public function has_woofood_instance() {
        return $this->woofood_instance !== null;
    }
    
    /**
     * Handle WooFood-specific hooks (if they exist)
     */
    public function handle_woofood_order_created($order_id, $order_data = null) {
        error_log("Orders Jet: WooFood order created hook fired - Order ID: {$order_id}");
        // Additional processing if needed
    }
    
    public function handle_woofood_status_change($order_id, $old_status, $new_status) {
        error_log("Orders Jet: WooFood status change - Order ID: {$order_id}, {$old_status} -> {$new_status}");
        // Additional processing if needed
    }
    
    public function handle_delivery_assignment($order_id, $driver_id) {
        error_log("Orders Jet: Delivery assigned - Order ID: {$order_id}, Driver: {$driver_id}");
        // Additional processing if needed
    }
    
    public function handle_order_ready_notification($order_id) {
        error_log("Orders Jet: WooFood order ready notification - Order ID: {$order_id}");
        // Additional processing if needed
    }
    
    /**
     * Add table management to WooFood location edit form
     */
    public function add_table_management_to_woofood_location($term) {
        $location_id = $term->term_id;
        $tables = oj_get_tables_by_woofood_location($location_id);
        $stats = oj_get_woofood_location_stats($location_id);
        
        echo '<tr class="form-field">';
        echo '<th scope="row"><label>' . __('Orders Jet Tables', 'orders-jet') . '</label></th>';
        echo '<td>';
        echo '<div class="oj-location-tables-management">';
        
        // Location statistics
        echo '<div class="oj-location-stats" style="background: #f0f9ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin-bottom: 20px;">';
        echo '<h4 style="margin-top: 0;">' . sprintf(__('Location Statistics for %s', 'orders-jet'), esc_html($term->name)) . '</h4>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">';
        echo '<div><strong>' . __('Total Tables:', 'orders-jet') . '</strong><br>' . $stats['total_tables'] . '</div>';
        echo '<div><strong>' . __('Available:', 'orders-jet') . '</strong><br>' . $stats['available_tables'] . '</div>';
        echo '<div><strong>' . __('Occupied:', 'orders-jet') . '</strong><br>' . $stats['occupied_tables'] . '</div>';
        echo '<div><strong>' . __('Active Orders:', 'orders-jet') . '</strong><br>' . $stats['active_orders'] . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tables list
        echo '<h4>' . __('Tables at this Location', 'orders-jet') . '</h4>';
        
        if ($tables) {
            echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 15px;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="width: 15%;">' . __('Table #', 'orders-jet') . '</th>';
            echo '<th style="width: 15%;">' . __('Capacity', 'orders-jet') . '</th>';
            echo '<th style="width: 15%;">' . __('Status', 'orders-jet') . '</th>';
            echo '<th style="width: 25%;">' . __('QR Code', 'orders-jet') . '</th>';
            echo '<th style="width: 30%;">' . __('Actions', 'orders-jet') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($tables as $table) {
                $table_number = get_post_meta($table->ID, '_oj_table_number', true);
                $capacity = get_post_meta($table->ID, '_oj_table_capacity', true);
                $status = get_post_meta($table->ID, '_oj_table_status', true);
                
                // Check for active orders
                $active_orders = oj_get_current_table_order($table_number);
                $display_status = ($active_orders && count($active_orders) > 0) ? 'occupied' : $status;
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($table_number) . '</strong></td>';
                echo '<td>' . esc_html($capacity) . ' ' . __('people', 'orders-jet') . '</td>';
                echo '<td>';
                
                $status_colors = array(
                    'available' => '#00a32a',
                    'occupied' => '#d63638',
                    'reserved' => '#dba617',
                    'maintenance' => '#666'
                );
                $status_labels = array(
                    'available' => __('Available', 'orders-jet'),
                    'occupied' => __('Occupied', 'orders-jet'),
                    'reserved' => __('Reserved', 'orders-jet'),
                    'maintenance' => __('Maintenance', 'orders-jet')
                );
                
                $color = isset($status_colors[$display_status]) ? $status_colors[$display_status] : '#666';
                $label = isset($status_labels[$display_status]) ? $status_labels[$display_status] : $display_status;
                
                echo '<span style="display: inline-block; padding: 3px 8px; border-radius: 3px; color: white; background: ' . $color . '; font-size: 11px;">';
                echo esc_html($label);
                echo '</span>';
                
                if ($active_orders && count($active_orders) > 0) {
                    echo '<br><small>' . sprintf(__('%d active orders', 'orders-jet'), count($active_orders)) . '</small>';
                }
                
                echo '</td>';
                echo '<td>';
                
                $qr_url = home_url('/table-menu/?table=' . urlencode($table_number));
                echo '<a href="' . esc_url($qr_url) . '" target="_blank" style="font-size: 11px;">';
                echo esc_html($table_number) . ' QR Menu';
                echo '</a>';
                
                echo '</td>';
                echo '<td>';
                
                echo '<a href="' . get_edit_post_link($table->ID) . '" class="button button-small">' . __('Edit', 'orders-jet') . '</a> ';
                echo '<a href="' . esc_url($qr_url) . '" target="_blank" class="button button-small">' . __('View Menu', 'orders-jet') . '</a>';
                
                if ($active_orders && count($active_orders) > 0) {
                    echo ' <a href="' . admin_url('admin.php?page=orders-jet-manager&table=' . urlencode($table_number)) . '" class="button button-small button-primary">';
                    echo __('View Orders', 'orders-jet') . '</a>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . __('No tables assigned to this location yet.', 'orders-jet') . '</p>';
            echo '</div>';
        }
        
        // Quick actions
        echo '<div class="oj-location-actions" style="border-top: 1px solid #ddd; padding-top: 15px;">';
        echo '<h4>' . __('Quick Actions', 'orders-jet') . '</h4>';
        echo '<p>';
        echo '<a href="' . admin_url('post-new.php?post_type=oj_table&woofood_location=' . $location_id) . '" class="button button-primary">';
        echo __('Add New Table', 'orders-jet') . '</a> ';
        echo '<a href="' . admin_url('edit.php?post_type=oj_table&woofood_location=' . $location_id) . '" class="button">';
        echo __('Manage All Tables', 'orders-jet') . '</a> ';
        echo '<a href="' . admin_url('admin.php?page=orders-jet-manager&location=' . $location_id) . '" class="button">';
        echo __('View Location Dashboard', 'orders-jet') . '</a>';
        echo '</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Add table management to WooFood location add form
     */
    public function add_table_management_to_woofood_location_new($taxonomy) {
        echo '<div class="form-field">';
        echo '<label>' . __('Orders Jet Integration', 'orders-jet') . '</label>';
        echo '<div style="background: #f0f9ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px;">';
        echo '<p><strong>' . __('Table Management Integration', 'orders-jet') . '</strong></p>';
        echo '<p>' . __('After creating this location, you can assign tables to it from the Orders Jet table management system.', 'orders-jet') . '</p>';
        echo '<p>' . __('Tables assigned to this location will automatically filter the menu to show only products available at this location.', 'orders-jet') . '</p>';
        echo '</div>';
        echo '</div>';
    }
}

// Initialize the integration
new Orders_Jet_WooFood_Integration();
