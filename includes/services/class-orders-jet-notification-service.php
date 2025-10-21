<?php
declare(strict_types=1);
/**
 * Orders Jet - Notification Service Class
 * Handles order notifications and staff alerts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Notification_Service {
    
    /**
     * Send order notification to staff
     * 
     * @param WC_Order $order The order to send notification for
     * @return bool Success status
     */
    public function send_order_notification($order) {
        if (!$order) {
            error_log('Orders Jet Notifications: No order provided for notification');
            return false;
        }
        
        $table_number = $order->get_meta('_oj_table_number');
        $order_id = $order->get_id();
        $order_total = $order->get_total();
        
        // Determine notification type
        $notification_type = !empty($table_number) ? 'table_order' : 'pickup_order';
        
        // Prepare notification data
        $notification_data = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'table_number' => $table_number,
            'total' => $order_total,
            'formatted_total' => wc_price($order_total),
            'items_count' => count($order->get_items()),
            'timestamp' => current_time('mysql'),
            'type' => $notification_type
        );
        
        // Get order items for notification
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'notes' => $item->get_meta('_oj_item_notes')
            );
        }
        $notification_data['items'] = $items;
        
        // Send notification via multiple channels
        $success = true;
        
        // 1. WordPress admin notification
        $success &= $this->send_admin_notification($notification_data);
        
        // 2. Email notification (if enabled)
        if (get_option('oj_email_notifications', 'yes') === 'yes') {
            $success &= $this->send_email_notification($notification_data);
        }
        
        // 3. Browser notification (stored for dashboard polling)
        $success &= $this->store_dashboard_notification($notification_data);
        
        // Log notification attempt
        if ($success) {
            error_log('Orders Jet Notifications: Successfully sent notifications for order #' . $order_id);
        } else {
            error_log('Orders Jet Notifications: Failed to send some notifications for order #' . $order_id);
        }
        
        return $success;
    }
    
    /**
     * Send ready notifications when order is marked ready
     * 
     * @param WC_Order $order The order that's ready
     * @param string $table_number Table number for the order
     * @return bool Success status
     */
    public function send_ready_notifications($order, $table_number) {
        if (!$order) {
            error_log('Orders Jet Notifications: No order provided for ready notification');
            return false;
        }
        
        $notification_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'table_number' => $table_number,
            'total' => $order->get_total(),
            'formatted_total' => wc_price($order->get_total()),
            'timestamp' => current_time('mysql'),
            'type' => 'order_ready'
        );
        
        // Send ready notification
        $success = true;
        
        // Admin notification
        $success &= $this->send_admin_notification($notification_data);
        
        // Dashboard notification for waiters/managers
        $success &= $this->store_dashboard_notification($notification_data);
        
        // Optional: SMS or push notification to customer (if implemented)
        if (get_option('oj_customer_ready_notifications', 'no') === 'yes') {
            $success &= $this->send_customer_ready_notification($notification_data);
        }
        
        error_log('Orders Jet Notifications: Order #' . $order->get_id() . ' ready notifications sent');
        return $success;
    }
    
    /**
     * Send WordPress admin notification
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_admin_notification($data) {
        try {
            // Create admin notice that will be displayed on next page load
            $message = $this->format_admin_message($data);
            
            // Store as transient for admin notices
            $notices = get_transient('oj_admin_notifications') ?: array();
            $notices[] = array(
                'message' => $message,
                'type' => 'info',
                'timestamp' => time()
            );
            
            // Keep only last 10 notifications
            if (count($notices) > 10) {
                $notices = array_slice($notices, -10);
            }
            
            set_transient('oj_admin_notifications', $notices, HOUR_IN_SECONDS);
            
            return true;
        } catch (Exception $e) {
            error_log('Orders Jet Notifications: Admin notification failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification to staff
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_email_notification($data) {
        try {
            $to = get_option('oj_notification_email', get_option('admin_email'));
            $subject = $this->get_email_subject($data);
            $message = $this->format_email_message($data);
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            return wp_mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log('Orders Jet Notifications: Email notification failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store notification for dashboard polling
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function store_dashboard_notification($data) {
        try {
            // Store in database for real-time dashboard updates
            $notifications = get_option('oj_dashboard_notifications', array());
            
            // Add new notification
            $notifications[] = array_merge($data, array(
                'id' => uniqid(),
                'read' => false,
                'created_at' => current_time('mysql')
            ));
            
            // Keep only last 50 notifications
            if (count($notifications) > 50) {
                $notifications = array_slice($notifications, -50);
            }
            
            return update_option('oj_dashboard_notifications', $notifications);
        } catch (Exception $e) {
            error_log('Orders Jet Notifications: Dashboard notification storage failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send customer ready notification (placeholder for future implementation)
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_customer_ready_notification($data) {
        // Placeholder for SMS/push notification implementation
        error_log('Orders Jet Notifications: Customer ready notification (not implemented): Order #' . $data['order_id']);
        return true;
    }
    
    /**
     * Format admin message
     * 
     * @param array $data Notification data
     * @return string Formatted message
     */
    private function format_admin_message($data) {
        switch ($data['type']) {
            case 'table_order':
                return sprintf(
                    __('New table order #%s for Table %s - %s (%d items)', 'orders-jet'),
                    $data['order_number'],
                    $data['table_number'],
                    $data['formatted_total'],
                    $data['items_count']
                );
                
            case 'pickup_order':
                return sprintf(
                    __('New pickup order #%s - %s (%d items)', 'orders-jet'),
                    $data['order_number'],
                    $data['formatted_total'],
                    $data['items_count']
                );
                
            case 'order_ready':
                return sprintf(
                    __('Order #%s is ready for Table %s - %s', 'orders-jet'),
                    $data['order_number'],
                    $data['table_number'],
                    $data['formatted_total']
                );
                
            default:
                return sprintf(
                    __('Order notification #%s', 'orders-jet'),
                    $data['order_number']
                );
        }
    }
    
    /**
     * Get email subject
     * 
     * @param array $data Notification data
     * @return string Email subject
     */
    private function get_email_subject($data) {
        $site_name = get_bloginfo('name');
        
        switch ($data['type']) {
            case 'table_order':
                return sprintf('[%s] New Table Order #%s', $site_name, $data['order_number']);
            case 'pickup_order':
                return sprintf('[%s] New Pickup Order #%s', $site_name, $data['order_number']);
            case 'order_ready':
                return sprintf('[%s] Order Ready #%s', $site_name, $data['order_number']);
            default:
                return sprintf('[%s] Order Notification #%s', $site_name, $data['order_number']);
        }
    }
    
    /**
     * Format email message
     * 
     * @param array $data Notification data
     * @return string Formatted HTML email message
     */
    private function format_email_message($data) {
        $html = '<html><body>';
        $html .= '<h2>' . $this->format_admin_message($data) . '</h2>';
        
        if (!empty($data['items'])) {
            $html .= '<h3>' . __('Order Items:', 'orders-jet') . '</h3>';
            $html .= '<ul>';
            foreach ($data['items'] as $item) {
                $html .= '<li>';
                $html .= $item['quantity'] . 'x ' . esc_html($item['name']);
                if (!empty($item['notes'])) {
                    $html .= ' <em>(' . esc_html($item['notes']) . ')</em>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '<p><strong>' . __('Total:', 'orders-jet') . '</strong> ' . $data['formatted_total'] . '</p>';
        $html .= '<p><strong>' . __('Time:', 'orders-jet') . '</strong> ' . $data['timestamp'] . '</p>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Get unread dashboard notifications
     * 
     * @return array Unread notifications
     */
    public function get_unread_notifications() {
        $notifications = get_option('oj_dashboard_notifications', array());
        return array_filter($notifications, function($notification) {
            return !$notification['read'];
        });
    }
    
    /**
     * Mark notification as read
     * 
     * @param string $notification_id Notification ID
     * @return bool Success status
     */
    public function mark_notification_read($notification_id) {
        $notifications = get_option('oj_dashboard_notifications', array());
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notification_id) {
                $notification['read'] = true;
                break;
            }
        }
        
        return update_option('oj_dashboard_notifications', $notifications);
    }
    
    /**
     * Clear old notifications
     * 
     * @param int $days_old Days old to clear (default 7)
     * @return bool Success status
     */
    public function clear_old_notifications($days_old = 7) {
        $notifications = get_option('oj_dashboard_notifications', array());
        $cutoff_time = strtotime('-' . $days_old . ' days');
        
        $notifications = array_filter($notifications, function($notification) use ($cutoff_time) {
            $notification_time = strtotime($notification['created_at']);
            return $notification_time > $cutoff_time;
        });
        
        return update_option('oj_dashboard_notifications', array_values($notifications));
    }
}
