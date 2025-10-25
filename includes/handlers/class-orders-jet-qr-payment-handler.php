<?php
declare(strict_types=1);
/**
 * Orders Jet - QR Payment Handler Class
 * Handles QR menu specific payment processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_QR_Payment_Handler {
    
    private $table_closure_handler;
    
    public function __construct($table_closure_handler = null) {
        $this->table_closure_handler = $table_closure_handler;
    }
    
    /**
     * Process table payment from QR menu
     * 
     * @param array $payment_data Payment request data
     * @return array Payment response data
     * @throws Exception On payment processing errors
     */
    public function process_table_payment($payment_data) {
        // Validate payment request
        $this->validate_payment_request($payment_data);
        
        $table_number = sanitize_text_field($payment_data['table_number']);
        $payment_method = sanitize_text_field($payment_data['payment_method']);
        
        // Check if table has orders to pay for
        if (!$this->has_orders_to_pay($table_number)) {
            throw new Exception(__('No orders found for this table', 'orders-jet'));
        }
        
        // Use existing table closure handler if available
        if ($this->table_closure_handler) {
            return $this->table_closure_handler->process_closure($payment_data);
        }
        
        // Fallback to direct processing
        return $this->process_payment_direct($table_number, $payment_method);
    }
    
    /**
     * Validate payment request data
     * 
     * @param array $payment_data Payment request data
     * @throws Exception On validation errors
     */
    public function validate_payment_request($payment_data) {
        if (empty($payment_data['table_number'])) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        if (empty($payment_data['payment_method'])) {
            throw new Exception(__('Payment method is required', 'orders-jet'));
        }
        
        // Validate payment method
        $valid_methods = array('cash', 'card', 'online');
        if (!in_array($payment_data['payment_method'], $valid_methods)) {
            throw new Exception(__('Invalid payment method', 'orders-jet'));
        }
    }
    
    /**
     * Generate payment response for QR menu
     * 
     * @param array $result Processing result from table closure
     * @return array Formatted response for QR menu
     */
    public function generate_payment_response($result) {
        if (!$result || !isset($result['consolidated_order_id'])) {
            throw new Exception(__('Invalid payment result', 'orders-jet'));
        }
        
        $consolidated_order = wc_get_order($result['consolidated_order_id']);
        if (!$consolidated_order) {
            throw new Exception(__('Consolidated order not found', 'orders-jet'));
        }
        
        return array(
            'success' => true,
            'message' => __('Payment processed successfully', 'orders-jet'),
            'order_id' => $consolidated_order->get_id(),
            'order_number' => $consolidated_order->get_order_number(),
            'total' => $consolidated_order->get_total(),
            'formatted_total' => wc_price($consolidated_order->get_total()),
            'payment_method' => $consolidated_order->get_payment_method_title(),
            'date' => $consolidated_order->get_date_created()->format('M j, Y g:i A'),
            'invoice_url' => $this->get_invoice_url($consolidated_order->get_id()),
            'table_status' => 'closed'
        );
    }
    
    /**
     * Get available payment methods for QR menu
     * 
     * @return array Available payment methods
     */
    public function get_available_payment_methods() {
        return array(
            'cash' => array(
                'id' => 'cash',
                'title' => __('Cash Payment', 'orders-jet'),
                'description' => __('Pay with cash at the counter', 'orders-jet'),
                'icon' => '💵'
            ),
            'card' => array(
                'id' => 'card',
                'title' => __('Card Payment', 'orders-jet'),
                'description' => __('Pay with credit/debit card', 'orders-jet'),
                'icon' => '💳'
            ),
            'online' => array(
                'id' => 'online',
                'title' => __('Online Payment', 'orders-jet'),
                'description' => __('Pay online with Stripe', 'orders-jet'),
                'icon' => '🌐'
            )
        );
    }
    
    /**
     * Check if table has orders that need payment
     * 
     * @param string $table_number The table number
     * @return bool True if table has unpaid orders
     */
    private function has_orders_to_pay($table_number) {
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-pending', 'wc-processing'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );
        
        $orders = get_posts($args);
        return !empty($orders);
    }
    
    /**
     * Direct payment processing (fallback method)
     * 
     * @param string $table_number The table number
     * @param string $payment_method The payment method
     * @return array Processing result
     */
    private function process_payment_direct($table_number, $payment_method) {
        // Get all table orders
        $orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-pending', 'wc-processing'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        if (empty($orders)) {
            throw new Exception(__('No orders found for payment', 'orders-jet'));
        }
        
        $total_amount = 0;
        
        // Complete all orders and calculate total
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order) {
                $order->update_status('completed', __('Paid via QR menu', 'orders-jet'));
                $order->set_payment_method($payment_method);
                $order->save();
                
                $total_amount += $order->get_total();
            }
        }
        
        return array(
            'success' => true,
            'total' => $total_amount,
            'formatted_total' => wc_price($total_amount),
            'payment_method' => $payment_method,
            'orders_completed' => count($orders)
        );
    }
    
    /**
     * Get invoice URL for order
     * 
     * @param int $order_id The order ID
     * @return string Invoice URL
     */
    private function get_invoice_url($order_id) {
        return admin_url('admin-ajax.php') . '?' . http_build_query(array(
            'action' => 'oj_get_order_invoice',
            'order_id' => $order_id,
            'nonce' => wp_create_nonce('oj_table_order')
        ));
    }
}
