<?php
declare(strict_types=1);
/**
 * Orders Jet - Kitchen Management Handler Class
 * Handles kitchen operations: marking orders ready and confirming payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Kitchen_Management_Handler {
    
    private $kitchen_service;
    private $notification_service;
    
    public function __construct($kitchen_service, $notification_service) {
        $this->kitchen_service = $kitchen_service;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Mark an order as ready from kitchen
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function mark_order_ready($post_data) {
        // Check user permissions
        if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
            throw new Exception(__('You do not have permission to perform this action.', 'orders-jet'));
        }
        
        $order_id = intval($post_data['order_id']);
        $kitchen_type = sanitize_text_field($post_data['kitchen_type'] ?? 'food'); // Which kitchen is marking ready
        
        if (!$order_id) {
            throw new Exception(__('Order ID is required.', 'orders-jet'));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            throw new Exception(__('Order not found.', 'orders-jet'));
        }
        
        // Validate order status
        $this->validate_order_status($order);
        
        // Process kitchen readiness
        return $this->process_kitchen_readiness($order, $kitchen_type);
    }
    
    /**
     * Confirm payment has been received for an order
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function confirm_payment_received($post_data) {
        // Check permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_woocommerce')) {
            throw new Exception(__('Permission denied', 'orders-jet'));
        }
        
        $order_id = intval($post_data['order_id'] ?? 0);
        
        if (!$order_id) {
            throw new Exception(__('Invalid order ID', 'orders-jet'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception(__('Order not found', 'orders-jet'));
        }
        
        // Process payment confirmation
        return $this->process_payment_confirmation($order);
    }
    
    /**
     * Validate that order can be marked as ready
     */
    private function validate_order_status($order) {
        $current_status = $order->get_status();
        if (!in_array($current_status, array('pending', 'processing'))) {
            throw new Exception(sprintf(__('Order cannot be marked ready from status: %s', 'orders-jet'), $current_status));
        }
    }
    
    /**
     * Process kitchen readiness logic
     */
    private function process_kitchen_readiness($order, $kitchen_type) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $order_type = !empty($table_number) ? 'table' : 'pickup';
        
        // Get or determine kitchen type for this order
        $order_kitchen_type = $order->get_meta('_oj_kitchen_type');
        if (empty($order_kitchen_type)) {
            $order_kitchen_type = $this->kitchen_service->get_order_kitchen_type($order);
            $order->update_meta_data('_oj_kitchen_type', $order_kitchen_type);
        }
        
        // Handle dual kitchen logic
        if ($order_kitchen_type === 'mixed') {
            return $this->handle_mixed_kitchen_readiness($order, $kitchen_type, $table_number, $order_type);
        } else {
            return $this->handle_single_kitchen_readiness($order, $order_kitchen_type, $table_number, $order_type);
        }
    }
    
    /**
     * Handle readiness for mixed kitchen orders (both food and beverage)
     */
    private function handle_mixed_kitchen_readiness($order, $kitchen_type, $table_number, $order_type) {
        $order_id = $order->get_id();
        
        // Mark specific kitchen as ready
        if ($kitchen_type === 'food') {
            $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
            $order->add_order_note(sprintf(
                __('Food items marked as ready by kitchen staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
        } else {
            $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
            $order->add_order_note(sprintf(
                __('Beverage items marked as ready by kitchen staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
        }
        
        // Check if both kitchens are ready
        $food_ready = $order->get_meta('_oj_food_kitchen_ready') === 'yes';
        $beverage_ready = $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
        
        if ($food_ready && $beverage_ready) {
            // All kitchens ready - mark as pending (ready for completion)
            $order->set_status('pending');
            $order->add_order_note(__('All kitchen items ready - order ready for completion', 'orders-jet'));
            $button_text = !empty($table_number) ? 'Close Table' : 'Complete';
            $button_class = !empty($table_number) ? 'oj-close-table' : 'oj-complete-order';
            $success_message = sprintf(__('Order #%d fully ready! All kitchens complete.', 'orders-jet'), $order_id);
            $partial_ready = false;
        } else {
            // Partial ready - stay in processing
            $order->set_status('processing');
            $waiting_for = ($food_ready !== 'yes') ? __('Food Kitchen', 'orders-jet') : __('Beverage Kitchen', 'orders-jet');
            $button_text = sprintf(__('Waiting for %s', 'orders-jet'), $waiting_for);
            $button_class = 'oj-waiting-kitchen';
            $success_message = sprintf(__('%s ready! Waiting for %s.', 'orders-jet'), 
                ucfirst($kitchen_type), $waiting_for);
            $partial_ready = true;
        }
        
        return $this->finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, $partial_ready);
    }
    
    /**
     * Handle readiness for single kitchen orders
     */
    private function handle_single_kitchen_readiness($order, $order_kitchen_type, $table_number, $order_type) {
        $order_id = $order->get_id();
        
        // Mark kitchen as ready
        if ($order_kitchen_type === 'food') {
            $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
        } else {
            $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
        }
        
        $order->set_status('pending');
        $order->add_order_note(sprintf(
            __('Order marked as ready by kitchen staff (%s) - %s order', 'orders-jet'), 
            wp_get_current_user()->display_name,
            ucfirst($order_type)
        ));
        
        $button_text = !empty($table_number) ? 'Close Table' : 'Complete';
        $button_class = !empty($table_number) ? 'oj-close-table' : 'oj-complete-order';
        $success_message = !empty($table_number) 
            ? sprintf(__('Table order #%d marked as ready!', 'orders-jet'), $order_id)
            : sprintf(__('Pickup order #%d marked as ready!', 'orders-jet'), $order_id);
        
        return $this->finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, false);
    }
    
    /**
     * Finalize kitchen readiness response
     */
    private function finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, $partial_ready) {
        $order_id = $order->get_id();
        $order_kitchen_type = $order->get_meta('_oj_kitchen_type');
        
        // Save the order
        $order->save();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet Kitchen: Order #' . $order_id . ' (' . $order_type . ') kitchen ready update by user #' . get_current_user_id());
        }
        
        // Send notifications if fully ready
        if ($order->get_status() === 'pending') {
            $this->notification_service->send_ready_notifications($order, $table_number);
        }
        
        // Get updated kitchen status for response
        $kitchen_status = $this->kitchen_service->get_kitchen_readiness_status($order);
        
        return array(
            'message' => $success_message,
            'order_id' => $order_id,
            'table_number' => $table_number,
            'order_type' => $order_type,
            'kitchen_type' => $order_kitchen_type,
            'kitchen_status' => $kitchen_status,
            'new_status' => $order->get_status(),
            'card_updates' => array(
                'order_id' => $order_id,
                'new_status' => $order->get_status(),
                'status_badge_html' => $this->kitchen_service->get_kitchen_status_badge($order),
                'kitchen_type_badge_html' => $this->kitchen_service->get_kitchen_type_badge($order),
                'button_text' => $button_text,
                'button_class' => $button_class,
                'table_number' => $table_number,
                'partial_ready' => $partial_ready
            )
        );
    }
    
    /**
     * Process payment confirmation
     */
    private function process_payment_confirmation($order) {
        $order_id = $order->get_id();
        
        // Add order note for payment confirmation
        $order->add_order_note(__('Payment confirmed by manager', 'orders-jet'));
        
        // Update order status to completed (if not already)
        if ($order->get_status() !== 'completed') {
            $order->set_status('completed');
            $order->save();
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: Payment confirmed for order #' . $order_id);
        }
        
        return array(
            'message' => sprintf(__('Payment confirmed for order #%d', 'orders-jet'), $order_id),
            'order_id' => $order_id,
            'status' => 'payment_confirmed'
        );
    }
}
