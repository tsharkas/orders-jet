<?php
declare(strict_types=1);
/**
 * Orders Jet - Individual Order Completion Handler Class
 * Handles completion of individual (non-table) orders with payment processing and tax calculation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Individual_Order_Completion_Handler {
    
    private $tax_service;
    
    public function __construct($tax_service) {
        $this->tax_service = $tax_service;
    }
    
    /**
     * Complete an individual order with payment processing
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function complete_order($post_data) {
        $order_id = intval($post_data['order_id']);
        $payment_method = sanitize_text_field($post_data['payment_method'] ?? 'cash');
        
        if (empty($order_id)) {
            throw new Exception(__('Order ID is required', 'orders-jet'));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            throw new Exception(__('Order not found', 'orders-jet'));
        }
        
        // Validate this is an individual order (not a table order)
        $this->validate_individual_order($order);
        
        // Store original totals for logging
        $original_subtotal = $order->get_subtotal();
        $original_total = $order->get_total();
        
        // Process the order completion
        $this->process_order_completion($order, $payment_method);
        
        // Generate response data
        return $this->generate_completion_response($order, $payment_method, $original_total);
    }
    
    /**
     * Validate that this is an individual order (not a table order)
     */
    private function validate_individual_order($order) {
        $table_number = $order->get_meta('_oj_table_number');
        if (!empty($table_number)) {
            throw new Exception(__('This is a table order. Use Close Table instead.', 'orders-jet'));
        }
    }
    
    /**
     * Process the order completion with payment and tax calculation
     */
    private function process_order_completion($order, $payment_method) {
        // Store payment method and tax calculation method
        $order->update_meta_data('_oj_payment_method', $payment_method);
        $order->update_meta_data('_oj_tax_method', 'individual_order');
        
        // Calculate tax efficiently (only if needed)
        if (wc_tax_enabled()) {
            $this->tax_service->calculate_individual_order_taxes($order);
        }
        
        // Mark order as completed using proper WooCommerce method (triggers invoice generation)
        $order->set_status('completed');
        
        // Add completion note
        $order->add_order_note(sprintf(
            __('Individual order completed by manager (%s) - Payment: %s - Tax calculated per order (Subtotal: %s, Tax: %s, Total: %s)', 'orders-jet'),
            wp_get_current_user()->display_name,
            $payment_method,
            wc_price($order->get_subtotal()),
            wc_price($order->get_total_tax()),
            wc_price($order->get_total())
        ));
        
        // Save the order (triggers proper WooCommerce completion process)
        $order->save();
    }
    
    /**
     * Generate completion response data
     */
    private function generate_completion_response($order, $payment_method, $original_total) {
        $order_id = $order->get_id();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: Individual order #' . $order_id . ' completed - Original Total: ' . $original_total . ', New Total with Tax: ' . $order->get_total());
        }
        
        // Generate thermal invoice URL
        $thermal_invoice_url = add_query_arg(array(
            'action' => 'oj_get_order_invoice',
            'order_id' => $order_id,
            'print' => '1',
            'nonce' => wp_create_nonce('oj_get_invoice')
        ), admin_url('admin-ajax.php'));

        return array(
            'message' => sprintf(__('Order #%d completed successfully!', 'orders-jet'), $order_id),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'total' => $order->get_total(),
            'payment_method' => $payment_method,
            'tax_method' => 'individual_order',
            'thermal_invoice_url' => $thermal_invoice_url,
            'card_updates' => array(
                'order_id' => $order_id,
                'new_status' => 'completed',
                'status_badge_text' => 'READY FOR PAYMENT',
                'status_badge_class' => 'completed',
                'button_text' => '🖨️ Print Invoice',
                'button_class' => 'oj-print-invoice',
                'button_action' => 'print_invoice',
                'invoice_url' => $thermal_invoice_url
            )
        );
    }
}
