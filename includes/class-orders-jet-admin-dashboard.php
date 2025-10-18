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
        // Add Manager Screen (Parent Menu) - available to managers and admins
        if (current_user_can('access_oj_manager_dashboard') || current_user_can('manage_options')) {
            add_menu_page(
                __('Manager Screen', 'orders-jet'),
                __('Manager Screen', 'orders-jet'),
                'manage_options', // Use WordPress admin capability as fallback
                'manager-overview', // Point directly to overview
                array($this, 'render_manager_overview'), // Default to overview
                'dashicons-businessman',
                3
            );
            
            // Manager Screen Child Pages - Overview becomes the first item
            add_submenu_page(
                'manager-overview',
                __('Overview', 'orders-jet'),
                __('Overview', 'orders-jet'),
                'manage_options',
                'manager-overview',
                array($this, 'render_manager_overview')
            );
            
            add_submenu_page(
                'manager-overview',
                __('Orders Management', 'orders-jet'),
                __('Orders Management', 'orders-jet'),
                'manage_options',
                'manager-orders',
                array($this, 'render_manager_orders')
            );
            
            add_submenu_page(
                'manager-overview',
                __('Tables Management', 'orders-jet'),
                __('Tables Management', 'orders-jet'),
                'manage_options',
                'manager-tables',
                array($this, 'render_manager_tables')
            );
            
            add_submenu_page(
                'manager-overview',
                __('Staff Management', 'orders-jet'),
                __('Staff Management', 'orders-jet'),
                'manage_options',
                'manager-staff',
                array($this, 'render_manager_staff')
            );
            
            add_submenu_page(
                'manager-overview',
                __('Reports', 'orders-jet'),
                __('Reports', 'orders-jet'),
                'manage_options',
                'manager-reports',
                array($this, 'render_manager_reports')
            );
            
            add_submenu_page(
                'manager-overview',
                __('Settings', 'orders-jet'),
                __('Settings', 'orders-jet'),
                'manage_options',
                'manager-settings',
                array($this, 'render_manager_settings')
            );
            
            // Invoice page (hidden from menu)
            add_submenu_page(
                null, // No parent menu (hidden)
                __('Order Invoice', 'orders-jet'),
                __('Order Invoice', 'orders-jet'),
                'read', // Minimal capability - anyone who can read
                'orders-jet-invoice',
                array($this, 'render_invoice_page')
            );
        }
        
        // Add Kitchen Dashboard (available to kitchen staff and admins)
        if (current_user_can('access_oj_kitchen_dashboard') || current_user_can('manage_options')) {
            add_menu_page(
                __('Kitchen Display', 'orders-jet'),
                __('Kitchen Display', 'orders-jet'),
                'manage_options', // Use WordPress admin capability as fallback
                'orders-jet-kitchen',
                array($this, 'render_kitchen_dashboard'),
                'dashicons-food',
                3
            );
        }
        
        // Add Waiter Dashboard (available to waiters and admins)
        if (current_user_can('access_oj_waiter_dashboard') || current_user_can('manage_options')) {
            add_menu_page(
                __('My Tables', 'orders-jet'),
                __('My Tables', 'orders-jet'),
                'manage_options', // Use WordPress admin capability as fallback
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
        // Manager Screen pages
        $manager_pages = array(
            'toplevel_page_manager-overview',
            'manager-overview_page_manager-orders',
            'manager-overview_page_manager-tables',
            'manager-overview_page_manager-staff',
            'manager-overview_page_manager-reports',
            'manager-overview_page_manager-settings'
        );
        
        // Other dashboard pages
        $other_pages = array(
            'toplevel_page_orders-jet-kitchen',
            'toplevel_page_orders-jet-waiter'
        );
        
        // Only load on our dashboard pages
        if (!in_array($hook, array_merge($manager_pages, $other_pages))) {
            return;
        }
        
        // Enqueue admin CSS and JS for classic WordPress admin pages
        wp_enqueue_style(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        wp_enqueue_script(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('orders-jet-admin', 'OrdersJetAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_dashboard_nonce'),
            'userRole' => oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : ''),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
            'isRTL' => is_rtl(), // Add RTL detection for JavaScript
            'language' => get_user_locale(), // Add current language
        ));
    }
    
    /**
     * Render manager overview (main dashboard)
     */
    public function render_manager_overview() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the overview template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-overview.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Create a simple placeholder
            $this->render_manager_placeholder('Overview', 'A comprehensive dashboard showing restaurant performance, quick actions, and key metrics.');
        }
    }
    
    /**
     * Render manager orders (current orders management)
     */
    public function render_manager_orders() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the orders management template (our current manager dashboard)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-manager-orders.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Orders Management template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render manager tables
     */
    public function render_manager_tables() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the tables management template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-tables.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Tables Management', 'Manage restaurant tables, assignments, and seating arrangements.');
        }
    }
    
    /**
     * Render manager staff
     */
    public function render_manager_staff() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the staff management template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-staff.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Staff Management', 'Manage restaurant staff, roles, schedules, and performance.');
        }
    }
    
    /**
     * Render manager reports
     */
    public function render_manager_reports() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the reports template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-reports.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Reports', 'View detailed analytics, sales reports, and business insights.');
        }
    }
    
    /**
     * Render manager settings
     */
    public function render_manager_settings() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the settings template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-settings.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Settings', 'Configure restaurant settings, preferences, and system options.');
        }
    }
    
    /**
     * Render invoice page
     */
    public function render_invoice_page() {
        // Get order ID from URL
        $order_id = intval($_GET['order_id'] ?? 0);
        
        if (empty($order_id)) {
            wp_die(__('Order ID is required', 'orders-jet'));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'orders-jet'));
        }
        
        // Check if it's a table order or individual order
        $table_number = $order->get_meta('_oj_table_number');
        
        if (!empty($table_number)) {
            // Table order - redirect to table invoice
            $invoice_url = site_url('/wp-content/plugins/orders-jet-integration/table-invoice.php?table=' . urlencode($table_number) . '&payment_method=cash');
        } else {
            // Individual order - redirect to individual order invoice
            $invoice_url = site_url('/wp-content/plugins/orders-jet-integration/table-invoice.php?order_id=' . $order_id . '&payment_method=cash');
        }
        
        // Check if print parameter is set
        if (isset($_GET['print']) && $_GET['print'] == '1') {
            $invoice_url .= '&print=1';
        }
        
        // Redirect to the invoice
        wp_redirect($invoice_url);
        exit;
    }
    
    /**
     * Render placeholder page for manager sections
     */
    private function render_manager_placeholder($title, $description) {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-businessman" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
                <?php echo esc_html($title); ?>
            </h1>
            <hr class="wp-header-end">
            
            <div class="notice notice-info">
                <p><strong><?php _e('Coming Soon!', 'orders-jet'); ?></strong></p>
                <p><?php echo esc_html($description); ?></p>
                <p><?php _e('This section will be developed in the upcoming phases of the Manager Screen system.', 'orders-jet'); ?></p>
            </div>
            
            <div class="manager-placeholder" style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; margin-top: 20px;">
                <div style="font-size: 64px; color: #ddd; margin-bottom: 20px;">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <h2 style="color: #666; margin-bottom: 10px;"><?php echo esc_html($title); ?></h2>
                <p style="color: #999; font-size: 16px; max-width: 500px; margin: 0 auto;"><?php echo esc_html($description); ?></p>
                
                <div style="margin-top: 30px;">
                    <a href="?page=manager-orders" class="button button-primary" style="margin-right: 10px;">
                        <?php _e('Go to Orders Management', 'orders-jet'); ?>
                    </a>
                    <a href="?page=manager-screen" class="button">
                        <?php _e('Back to Overview', 'orders-jet'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render kitchen dashboard
     */
    public function render_kitchen_dashboard() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the simple dashboard template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-kitchen.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Dashboard template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render waiter dashboard
     */
    public function render_waiter_dashboard() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the simple dashboard template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-waiter.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Dashboard template not found.', 'orders-jet'));
        }
    }
    
    // React dashboard function removed - using PHP templates instead
    
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
        
        // Kitchen sees only processing orders (simplified)
        if ($user_role === 'oj_kitchen') {
            $args['status'] = array('processing');
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
            $order_type = oj_get_order_type($order);
            
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
                'order_type' => $order_type,
                'order_type_label' => oj_get_order_type_label($order_type),
                'delivery_address' => ($order_type === 'delivery') ? $order->get_meta('_oj_delivery_address') ?: $order->get_meta('_exwf_delivery_address') : '',
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


