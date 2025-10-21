<?php
declare(strict_types=1);
/**
 * Orders Jet - Invoice Generation Handler Class
 * Handles invoice generation for orders with thermal printer optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Invoice_Generation_Handler {
    
    /**
     * Generate invoice for an order
     * 
     * @param array $get_data The $_GET data from request
     * @return void Outputs HTML directly and exits
     * @throws Exception On processing errors
     */
    public function generate_invoice($get_data) {
        $order_id = intval($get_data['order_id'] ?? 0);
        $order_type = sanitize_text_field($get_data['type'] ?? '');
        $print_mode = isset($get_data['print']);
        $nonce = sanitize_text_field($get_data['nonce'] ?? '');
        
        if (!$order_id) {
            wp_die(__('Invalid order ID', 'orders-jet'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'oj_get_invoice')) {
            wp_die(__('Security check failed', 'orders-jet'));
        }
        
        // Check permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'orders-jet'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'orders-jet'));
        }
        
        // Generate invoice HTML
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: Generating invoice for order #' . $order_id);
        }
        
        $invoice_html = $this->generate_single_order_invoice_html($order, $print_mode);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: Invoice HTML generated successfully for order #' . $order_id);
        }
        
        // Set proper headers
        header('Content-Type: text/html; charset=utf-8');
        
        echo $invoice_html;
        exit;
    }
    
    /**
     * Generate HTML for single order invoice (thermal printer optimized)
     */
    private function generate_single_order_invoice_html($order, $print_mode = false) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $order_type = !empty($table_number) ? 'Table' : 'Pickup';
        
        // Get order items for thermal format
        $items_html = '';
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $notes = $item->get_meta('_oj_item_notes');
            $addons_text = '';
            
            // Get addons if available
            if (function_exists('wc_pb_get_bundled_order_items')) {
                $addon_names = array();
                $addons = $item->get_meta('_wc_pao_addon_value');
                if (!empty($addons)) {
                    foreach ($addons as $addon) {
                        if (!empty($addon['name'])) {
                            $addon_names[] = $addon['name'] . ': ' . $addon['value'];
                        }
                    }
                    $addons_text = implode(', ', $addon_names);
                }
            }
            
            $name = $item->get_name();
            // Truncate long names for thermal width (max 25 chars)
            if (strlen($name) > 25) {
                $name = substr($name, 0, 22) . '...';
            }
            
            $items_html .= '<tr>';
            $items_html .= '<td>' . $name;
            if ($notes) {
                $items_html .= '<br><span class="thermal-note">Note: ' . esc_html($notes) . '</span>';
            }
            if ($addons_text) {
                $items_html .= '<br><span class="thermal-note">+ ' . esc_html($addons_text) . '</span>';
            }
            $items_html .= '</td>';
            $items_html .= '<td class="thermal-center">' . $item->get_quantity() . '</td>';
            $items_html .= '<td class="thermal-right">' . wc_price($item->get_total()) . '</td>';
            $items_html .= '</tr>';
        }
        
        // Get payment method
        $payment_method = $order->get_meta('_oj_payment_method');
        if (empty($payment_method)) {
            $payment_method = $order->get_payment_method_title();
        }
        if (empty($payment_method)) {
            $payment_method = __('Cash', 'orders-jet');
        }
        
        // Get order date/time
        $order_date = $order->get_date_created();
        $formatted_date = $order_date ? $order_date->format('Y-m-d H:i:s') : current_time('Y-m-d H:i:s');
        
        // Generate thermal-optimized HTML
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . sprintf(__('Invoice - Order #%d', 'orders-jet'), $order_id) . '</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
        
        body {
            font-family: "Courier New", monospace;
            font-size: 12px;
            line-height: 1.3;
            margin: 0;
            padding: 10px;
            max-width: 300px;
            background: white;
        }
        
        .thermal-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .thermal-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .thermal-subtitle {
            font-size: 12px;
            margin: 0;
        }
        
        .thermal-info {
            margin-bottom: 15px;
        }
        
        .thermal-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .thermal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .thermal-table th,
        .thermal-table td {
            padding: 3px 2px;
            text-align: left;
            font-size: 11px;
        }
        
        .thermal-table th {
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        
        .thermal-center { text-align: center; }
        .thermal-right { text-align: right; }
        
        .thermal-note {
            font-size: 10px;
            color: #666;
            font-style: italic;
        }
        
        .thermal-total {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .thermal-total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .thermal-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 10px;
        }
        
        .print-button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #005a87;
        }
        
        @media (max-width: 350px) {
            body { font-size: 11px; }
            .thermal-title { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="print-button">🖨️ ' . __('Print Invoice', 'orders-jet') . '</button>
    </div>
    
    <div class="thermal-header">
        <div class="thermal-title">' . get_bloginfo('name') . '</div>
        <div class="thermal-subtitle">' . sprintf(__('Invoice #%d', 'orders-jet'), $order_id) . '</div>
    </div>
    
    <div class="thermal-info">
        <div class="thermal-info-row">
            <span>' . __('Order Type:', 'orders-jet') . '</span>
            <span>' . esc_html($order_type) . '</span>
        </div>';
        
        if (!empty($table_number)) {
            $html .= '<div class="thermal-info-row">
                <span>' . __('Table:', 'orders-jet') . '</span>
                <span>' . esc_html($table_number) . '</span>
            </div>';
        }
        
        $html .= '<div class="thermal-info-row">
            <span>' . __('Date:', 'orders-jet') . '</span>
            <span>' . esc_html($formatted_date) . '</span>
        </div>
        <div class="thermal-info-row">
            <span>' . __('Status:', 'orders-jet') . '</span>
            <span>' . esc_html(wc_get_order_status_name($order->get_status())) . '</span>
        </div>
    </div>
    
    <table class="thermal-table">
        <thead>
            <tr>
                <th>' . __('Item', 'orders-jet') . '</th>
                <th class="thermal-center">' . __('Qty', 'orders-jet') . '</th>
                <th class="thermal-right">' . __('Total', 'orders-jet') . '</th>
            </tr>
        </thead>
        <tbody>
            ' . $items_html . '
        </tbody>
    </table>
    
    <div class="thermal-total">
        <div class="thermal-info-row">
            <span>' . __('Subtotal:', 'orders-jet') . '</span>
            <span>' . wc_price($order->get_subtotal()) . '</span>
        </div>';
        
        if ($order->get_total_tax() > 0) {
            $html .= '<div class="thermal-info-row">
                <span>' . __('Tax:', 'orders-jet') . '</span>
                <span>' . wc_price($order->get_total_tax()) . '</span>
            </div>';
        }
        
        $html .= '<div class="thermal-total-row">
            <span>' . __('TOTAL:', 'orders-jet') . '</span>
            <span>' . wc_price($order->get_total()) . '</span>
        </div>
        
        <div class="thermal-info-row">
            <span>' . __('Payment:', 'orders-jet') . '</span>
            <span>' . esc_html($payment_method) . '</span>
        </div>
    </div>
    
    <div class="thermal-footer">
        ' . __('Thank you for your order!', 'orders-jet') . '<br>
        ' . sprintf(__('Generated: %s', 'orders-jet'), current_time('Y-m-d H:i:s')) . '
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" class="print-button">🖨️ ' . __('Print Invoice', 'orders-jet') . '</button>
    </div>
</body>
</html>';
        
        return $html;
    }
}
