<?php
/**
 * Orders Jet - Admin Dashboard Class
 * Main dashboard controller with role-based views
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Admin_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        
        // AJAX handlers for dashboard
        add_action('wp_ajax_oj_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_oj_get_table_updates', array($this, 'get_table_updates'));
        add_action('wp_ajax_oj_get_order_updates', array($this, 'get_order_updates'));
        add_action('wp_ajax_oj_get_notifications', array($this, 'get_notifications'));
    }
    
    /**
     * Register admin menu pages based on user role
     */
    public function register_admin_pages() {
        $user_role = oj_get_user_role();
        
        // Manager Dashboard
        if (current_user_can('access_oj_manager_dashboard')) {
            add_menu_page(
                __('Manager Dashboard', 'orders-jet'),
                __('Manager Dashboard', 'orders-jet'),
                'access_oj_manager_dashboard',
                'orders-jet-manager',
                array($this, 'render_manager_dashboard'),
                'dashicons-businessman',
                3
            );
        }
        
        // Kitchen Dashboard
        if (current_user_can('access_oj_kitchen_dashboard')) {
            add_menu_page(
                __('Kitchen Display', 'orders-jet'),
                __('Kitchen Display', 'orders-jet'),
                'access_oj_kitchen_dashboard',
                'orders-jet-kitchen',
                array($this, 'render_kitchen_dashboard'),
                'dashicons-food',
                3
            );
        }
        
        // Waiter Dashboard
        if (current_user_can('access_oj_waiter_dashboard')) {
            add_menu_page(
                __('My Tables', 'orders-jet'),
                __('My Tables', 'orders-jet'),
                'access_oj_waiter_dashboard',
                'orders-jet-waiter',
                array($this, 'render_waiter_dashboard'),
                'dashicons-tickets-alt',
                3
            );
        }
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        // Only load on our dashboard pages
        if (!in_array($hook, array('toplevel_page_orders-jet-manager', 'toplevel_page_orders-jet-kitchen', 'toplevel_page_orders-jet-waiter'))) {
            return;
        }
        
        // Enqueue dashboard CSS
        wp_enqueue_style(
            'orders-jet-admin-dashboard',
            ORDERS_JET_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        // Enqueue dashboard JavaScript
        wp_enqueue_script(
            'orders-jet-admin-dashboard',
            ORDERS_JET_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('orders-jet-admin-dashboard', 'OrdersJetDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_dashboard_nonce'),
            'userId' => get_current_user_id(),
            'userRole' => oj_get_user_role(),
            'pollInterval' => 5000, // 5 seconds
            'soundEnabled' => get_user_meta(get_current_user_id(), '_oj_sound_alerts_enabled', true) !== 'no',
            'strings' => array(
                'newOrder' => __('New Order!', 'orders-jet'),
                'orderReady' => __('Order Ready!', 'orders-jet'),
                'paymentDue' => __('Payment Required', 'orders-jet'),
                'connectionLost' => __('Connection lost. Trying to reconnect...', 'orders-jet'),
                'connected' => __('Connected', 'orders-jet'),
            )
        ));
    }
    
    /**
     * Render manager dashboard
     */
    public function render_manager_dashboard() {
        if (!current_user_can('access_oj_manager_dashboard')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-manager.php';
    }
    
    /**
     * Render kitchen dashboard
     */
    public function render_kitchen_dashboard() {
        if (!current_user_can('access_oj_kitchen_dashboard')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-kitchen.php';
    }
    
    /**
     * Render waiter dashboard
     */
    public function render_waiter_dashboard() {
        if (!current_user_can('access_oj_waiter_dashboard')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-waiter.php';
    }
    
    /**
     * Get dashboard data (AJAX)
     */
    public function get_dashboard_data() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $data = array(
            'timestamp' => current_time('timestamp'),
            'tables' => $this->get_tables_data($user_role, $user_id),
            'orders' => $this->get_orders_data($user_role, $user_id),
            'notifications' => $this->get_notifications_data($user_role, $user_id),
        );
        
        // Add role-specific data
        if ($user_role === 'oj_manager') {
            $data['staff'] = $this->get_staff_activity();
            $data['metrics'] = $this->get_performance_metrics();
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get tables data
     */
    private function get_tables_data($user_role, $user_id) {
        $args = array(
            'post_type' => 'oj_table',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );
        
        // Waiters only see their assigned tables
        if ($user_role === 'oj_waiter') {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => $user_id,
                    'compare' => '=',
                ),
                array(
                    'key' => '_oj_assigned_waiter',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }
        
        $tables = get_posts($args);
        $table_data = array();
        
        foreach ($tables as $table) {
            $table_number = get_post_meta($table->ID, '_oj_table_number', true);
            $table_status = get_post_meta($table->ID, '_oj_table_status', true);
            $assigned_waiter_id = get_post_meta($table->ID, '_oj_assigned_waiter', true);
            $session_start = get_post_meta($table->ID, '_oj_session_start', true);
            $session_orders = get_post_meta($table->ID, '_oj_session_orders', true);
            $session_total = get_post_meta($table->ID, '_oj_session_total', true);
            
            $waiter_name = '';
            if ($assigned_waiter_id) {
                $waiter = get_userdata($assigned_waiter_id);
                $waiter_name = $waiter ? $waiter->display_name : '';
            }
            
            $table_data[] = array(
                'id' => $table->ID,
                'number' => $table_number,
                'name' => $table->post_title,
                'status' => $table_status ?: 'available',
                'assigned_waiter_id' => $assigned_waiter_id,
                'assigned_waiter_name' => $waiter_name,
                'session_start' => $session_start,
                'session_orders_count' => is_array($session_orders) ? count($session_orders) : 0,
                'session_total' => $session_total ? floatval($session_total) : 0,
                'can_claim' => $user_role === 'oj_waiter' && !$assigned_waiter_id,
                'is_mine' => $assigned_waiter_id == $user_id,
            );
        }
        
        return $table_data;
    }
    
    /**
     * Get orders data
     */
    private function get_orders_data($user_role, $user_id) {
        $args = array(
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Kitchen sees orders in preparing/ready status
        if ($user_role === 'oj_kitchen') {
            $args['meta_query'] = array(
                array(
                    'key' => '_oj_order_status',
                    'value' => array('received', 'preparing', 'ready'),
                    'compare' => 'IN',
                ),
            );
        }
        
        // Waiters see orders assigned to them
        if ($user_role === 'oj_waiter') {
            $args['meta_query'] = array(
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => $user_id,
                    'compare' => '=',
                ),
            );
        }
        
        // Manager sees all active orders
        if ($user_role === 'oj_manager') {
            $args['status'] = array('pending', 'processing', 'on-hold');
        }
        
        $orders = wc_get_orders($args);
        $order_data = array();
        
        foreach ($orders as $order) {
            $order_status = $order->get_meta('_oj_order_status') ?: 'placed';
            $table_number = $order->get_meta('_oj_table_number');
            $assigned_waiter_id = $order->get_meta('_oj_assigned_waiter');
            $received_time = $order->get_meta('_oj_received_time');
            $preparing_time = $order->get_meta('_oj_preparing_time');
            
            $waiter_name = '';
            if ($assigned_waiter_id) {
                $waiter = get_userdata($assigned_waiter_id);
                $waiter_name = $waiter ? $waiter->display_name : '';
            }
            
            // Calculate preparation time
            $prep_minutes = 0;
            if ($preparing_time) {
                $prep_minutes = floor((current_time('timestamp') - intval($preparing_time)) / 60);
            } elseif ($received_time) {
                $prep_minutes = floor((current_time('timestamp') - intval($received_time)) / 60);
            }
            
            $order_data[] = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'table_number' => $table_number,
                'status' => $order_status,
                'wc_status' => $order->get_status(),
                'assigned_waiter_id' => $assigned_waiter_id,
                'assigned_waiter_name' => $waiter_name,
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'date_formatted' => $order->get_date_created()->format('M d, H:i'),
                'prep_minutes' => $prep_minutes,
                'items' => $this->get_order_items_data($order),
                'special_instructions' => $order->get_customer_note(),
            );
        }
        
        return $order_data;
    }
    
    /**
     * Get order items data
     */
    private function get_order_items_data($order) {
        $items_data = array();
        
        foreach ($order->get_items() as $item) {
            $items_data[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'total_formatted' => wc_price($item->get_total()),
                'addons' => $item->get_meta('_oj_item_addons'),
                'notes' => $item->get_meta('_oj_item_notes'),
            );
        }
        
        return $items_data;
    }
    
    /**
     * Get notifications data
     */
    private function get_notifications_data($user_role, $user_id) {
        $notifications = array();
        
        // This will be populated by real-time checks
        // For now, return empty array
        
        return $notifications;
    }
    
    /**
     * Get staff activity (for managers)
     */
    private function get_staff_activity() {
        $staff = oj_get_staff_users();
        $activity = array();
        
        foreach ($staff as $member) {
            $assigned_tables = get_user_meta($member->ID, '_oj_assigned_tables', true);
            
            $activity[] = array(
                'id' => $member->ID,
                'name' => $member->display_name,
                'role' => $member->oj_role_name,
                'assigned_tables_count' => is_array($assigned_tables) ? count($assigned_tables) : 0,
                'active' => true, // TODO: Implement activity tracking
            );
        }
        
        return $activity;
    }
    
    /**
     * Get performance metrics (for managers)
     */
    private function get_performance_metrics() {
        // Get today's metrics
        $today_start = strtotime('today');
        
        $args = array(
            'limit' => -1,
            'date_created' => '>=' . $today_start,
        );
        
        $orders = wc_get_orders($args);
        
        $total_orders = count($orders);
        $total_revenue = 0;
        $avg_prep_time = 0;
        $completed_orders = 0;
        
        foreach ($orders as $order) {
            $total_revenue += $order->get_total();
            
            if ($order->get_status() === 'completed') {
                $completed_orders++;
            }
        }
        
        return array(
            'today_orders' => $total_orders,
            'today_revenue' => wc_price($total_revenue),
            'completed_orders' => $completed_orders,
            'pending_orders' => $total_orders - $completed_orders,
            'avg_prep_time' => $avg_prep_time, // TODO: Calculate from timestamps
        );
    }
    
    /**
     * Get table updates (AJAX)
     */
    public function get_table_updates() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : 0;
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $tables = $this->get_tables_data($user_role, $user_id);
        
        wp_send_json_success(array(
            'tables' => $tables,
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get order updates (AJAX)
     */
    public function get_order_updates() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : 0;
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $orders = $this->get_orders_data($user_role, $user_id);
        
        // Find new orders since last check
        $new_orders = array();
        foreach ($orders as $order) {
            $order_time = strtotime($order['date']);
            if ($order_time > $last_check) {
                $new_orders[] = $order;
            }
        }
        
        wp_send_json_success(array(
            'orders' => $orders,
            'new_orders' => $new_orders,
            'new_count' => count($new_orders),
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get notifications (AJAX)
     */
    public function get_notifications() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $notifications = $this->get_notifications_data($user_role, $user_id);
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'count' => count($notifications),
        ));
    }
}


