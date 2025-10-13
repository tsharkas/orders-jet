<?php
/**
 * Orders Jet - REST API Class
 * Handles API endpoints for React dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_REST_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Dashboard data endpoint
        register_rest_route('orders-jet/v1', '/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_data'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Tables endpoint
        register_rest_route('orders-jet/v1', '/tables', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tables'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Update table status
        register_rest_route('orders-jet/v1', '/tables/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_table_status'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Orders endpoint
        register_rest_route('orders-jet/v1', '/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Update order status
        register_rest_route('orders-jet/v1', '/orders/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Staff endpoint
        register_rest_route('orders-jet/v1', '/staff', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_staff'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Analytics endpoint
        register_rest_route('orders-jet/v1', '/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Real-time updates
        register_rest_route('orders-jet/v1', '/tables/updates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_table_updates'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        register_rest_route('orders-jet/v1', '/orders/updates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_updates'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
        
        // Notifications endpoint
        register_rest_route('orders-jet/v1', '/notifications', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_notifications'),
            'permission_callback' => array($this, 'check_dashboard_permission'),
        ));
    }
    
    /**
     * Check if user has dashboard permission
     */
    public function check_dashboard_permission($request) {
        return current_user_can('access_oj_manager_dashboard') || 
               current_user_can('access_oj_kitchen_dashboard') || 
               current_user_can('access_oj_waiter_dashboard');
    }
    
    /**
     * Get dashboard data
     */
    public function get_dashboard_data($request) {
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $data = array(
            'timestamp' => current_time('timestamp'),
            'user' => array(
                'id' => $user_id,
                'role' => $user_role,
                'name' => wp_get_current_user()->display_name,
            ),
            'metrics' => $this->get_metrics(),
            'tables' => $this->get_tables_data(),
            'orders' => $this->get_orders_data(),
            'staff' => $this->get_staff_data(),
        );
        
        return rest_ensure_response($data);
    }
    
    /**
     * Get tables data
     */
    public function get_tables($request) {
        $tables = $this->get_tables_data();
        return rest_ensure_response($tables);
    }
    
    /**
     * Update table status
     */
    public function update_table_status($request) {
        $table_id = $request['id'];
        $status = $request['status'];
        
        // Validate status
        $valid_statuses = array('available', 'occupied', 'cleaning', 'maintenance');
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', __('Invalid table status', 'orders-jet'), array('status' => 400));
        }
        
        // Update table status
        $updated = update_post_meta($table_id, '_oj_table_status', $status);
        
        if ($updated) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Table status updated successfully', 'orders-jet'),
                'table_id' => $table_id,
                'status' => $status,
            ));
        } else {
            return new WP_Error('update_failed', __('Failed to update table status', 'orders-jet'), array('status' => 500));
        }
    }
    
    /**
     * Get orders data
     */
    public function get_orders($request) {
        $orders = $this->get_orders_data();
        return rest_ensure_response($orders);
    }
    
    /**
     * Update order status
     */
    public function update_order_status($request) {
        $order_id = $request['id'];
        $status = $request['status'];
        
        // Validate status
        $valid_statuses = array('placed', 'received', 'preparing', 'ready', 'delivered', 'completed', 'cancelled');
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', __('Invalid order status', 'orders-jet'), array('status' => 400));
        }
        
        // Update order status
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('Order not found', 'orders-jet'), array('status' => 404));
        }
        
        $updated = $order->update_meta_data('_oj_order_status', $status);
        $order->save();
        
        if ($updated) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Order status updated successfully', 'orders-jet'),
                'order_id' => $order_id,
                'status' => $status,
            ));
        } else {
            return new WP_Error('update_failed', __('Failed to update order status', 'orders-jet'), array('status' => 500));
        }
    }
    
    /**
     * Get staff data
     */
    public function get_staff($request) {
        $staff = $this->get_staff_data();
        return rest_ensure_response($staff);
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics($request) {
        $date_range = $request->get_param('date_range') ?: 'today';
        
        $analytics = array(
            'revenue' => $this->get_revenue_analytics($date_range),
            'orders' => $this->get_orders_analytics($date_range),
            'tables' => $this->get_tables_analytics($date_range),
            'payments' => $this->get_payment_analytics($date_range),
        );
        
        return rest_ensure_response($analytics);
    }
    
    /**
     * Get table updates
     */
    public function get_table_updates($request) {
        $last_check = $request->get_param('last_check') ?: 0;
        $tables = $this->get_tables_data();
        
        return rest_ensure_response(array(
            'tables' => $tables,
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get order updates
     */
    public function get_order_updates($request) {
        $last_check = $request->get_param('last_check') ?: 0;
        $orders = $this->get_orders_data();
        
        // Find new orders since last check
        $new_orders = array();
        foreach ($orders as $order) {
            $order_time = strtotime($order['date']);
            if ($order_time > $last_check) {
                $new_orders[] = $order;
            }
        }
        
        return rest_ensure_response(array(
            'orders' => $orders,
            'new_orders' => $new_orders,
            'new_count' => count($new_orders),
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get notifications
     */
    public function get_notifications($request) {
        $notifications = array();
        
        // TODO: Implement real-time notifications
        // For now, return empty array
        
        return rest_ensure_response(array(
            'notifications' => $notifications,
            'count' => count($notifications),
        ));
    }
    
    /**
     * Get performance metrics
     */
    private function get_metrics() {
        $today_start = strtotime('today');
        
        $args = array(
            'limit' => -1,
            'date_created' => '>=' . $today_start,
        );
        
        $orders = wc_get_orders($args);
        
        $total_orders = count($orders);
        $total_revenue = 0;
        $completed_orders = 0;
        
        foreach ($orders as $order) {
            $total_revenue += $order->get_total();
            if ($order->get_status() === 'completed') {
                $completed_orders++;
            }
        }
        
        // Get table metrics
        $tables = get_posts(array(
            'post_type' => 'oj_table',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        
        $occupied_tables = 0;
        foreach ($tables as $table) {
            $status = get_post_meta($table->ID, '_oj_table_status', true);
            if ($status === 'occupied') {
                $occupied_tables++;
            }
        }
        
        return array(
            'todayOrders' => $total_orders,
            'todayRevenue' => $total_revenue,
            'occupiedTables' => $occupied_tables,
            'staffActivity' => 85, // TODO: Calculate real staff activity
            'avgOrderTime' => 15, // TODO: Calculate real average order time
            'totalCustomers' => $total_orders, // Assuming 1 customer per order
            'completedOrders' => $completed_orders,
            'pendingOrders' => $total_orders - $completed_orders,
        );
    }
    
    /**
     * Get tables data
     */
    private function get_tables_data() {
        $args = array(
            'post_type' => 'oj_table',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );
        
        $tables = get_posts($args);
        $table_data = array();
        
        foreach ($tables as $table) {
            $table_number = get_post_meta($table->ID, '_oj_table_number', true);
            $table_status = get_post_meta($table->ID, '_oj_table_status', true);
            $assigned_waiter_id = get_post_meta($table->ID, '_oj_assigned_waiter', true);
            $session_start = get_post_meta($table->ID, '_oj_session_start', true);
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
                'assignedWaiter' => $waiter_name,
                'sessionStart' => $session_start,
                'sessionTotal' => $session_total ? floatval($session_total) : 0,
            );
        }
        
        return $table_data;
    }
    
    /**
     * Get orders data
     */
    private function get_orders_data() {
        $args = array(
            'status' => array('processing'), // Only processing orders
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $orders = wc_get_orders($args);
        $order_data = array();
        
        foreach ($orders as $order) {
            $order_status = $order->get_meta('_oj_order_status') ?: 'placed';
            $table_number = $order->get_meta('_oj_table_number');
            $order_type = oj_get_order_type($order);
            
            $order_data[] = array(
                'id' => $order->get_id(),
                'orderNumber' => $order->get_order_number(),
                'tableNumber' => $table_number,
                'orderType' => $order_type,
                'orderTypeLabel' => oj_get_order_type_label($order_type),
                'deliveryAddress' => ($order_type === 'delivery') ? $order->get_meta('_oj_delivery_address') ?: $order->get_meta('_exwf_delivery_address') : '',
                'status' => $order_status,
                'total' => $order->get_total(),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'items' => count($order->get_items()),
            );
        }
        
        return $order_data;
    }
    
    /**
     * Get staff data
     */
    private function get_staff_data() {
        $staff = oj_get_staff_users();
        $staff_data = array();
        
        foreach ($staff as $member) {
            $assigned_tables = get_user_meta($member->ID, '_oj_assigned_tables', true);
            
            $staff_data[] = array(
                'id' => $member->ID,
                'name' => $member->display_name,
                'role' => $member->oj_role_name,
                'assignedTables' => is_array($assigned_tables) ? count($assigned_tables) : 0,
                'active' => true, // TODO: Implement activity tracking
            );
        }
        
        return $staff_data;
    }
    
    /**
     * Get revenue analytics
     */
    private function get_revenue_analytics($date_range) {
        // TODO: Implement revenue analytics
        return array(
            'total' => 0,
            'growth' => 0,
            'chartData' => array(),
        );
    }
    
    /**
     * Get orders analytics
     */
    private function get_orders_analytics($date_range) {
        // TODO: Implement orders analytics
        return array(
            'total' => 0,
            'growth' => 0,
            'chartData' => array(),
        );
    }
    
    /**
     * Get tables analytics
     */
    private function get_tables_analytics($date_range) {
        // TODO: Implement tables analytics
        return array(
            'occupancy' => 0,
            'turnover' => 0,
            'chartData' => array(),
        );
    }
    
    /**
     * Get payment analytics
     */
    private function get_payment_analytics($date_range) {
        // TODO: Implement payment analytics
        return array(
            'methods' => array(),
            'successRate' => 0,
            'chartData' => array(),
        );
    }
}

// Initialize the REST API
new Orders_Jet_REST_API();
