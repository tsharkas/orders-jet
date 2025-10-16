<?php
/**
 * Orders Jet - Table Invoice Template
 * Displays invoice for table orders after payment
 * Supports both Parent-Child and Legacy systems
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get table number and payment method from URL
$table_number = sanitize_text_field($_GET['table'] ?? '');
$payment_method = sanitize_text_field($_GET['payment_method'] ?? 'cash');
$parent_order_id = intval($_GET['parent_order_id'] ?? 0);

if (empty($table_number)) {
    wp_die(__('Table number is required', 'orders-jet'));
}

// Check if parent-child system is enabled and parent order ID is provided
$parent_child_manager = new Orders_Jet_Parent_Child_Manager();
$use_parent_child = $parent_child_manager->is_parent_child_enabled() && !empty($parent_order_id);

if ($use_parent_child) {
    // PARENT-CHILD SYSTEM: Use parent order for invoice
    $parent_order = wc_get_order($parent_order_id);
    
    if (!$parent_order || $parent_order->get_meta('_oj_table_number') != $table_number) {
        wp_die(__('Invalid parent order for this table', 'orders-jet'));
    }
    
    // Get child orders data from parent order meta
    $child_orders_data = $parent_order->get_meta('_oj_child_orders') ?: array();
    
    // Calculate totals from parent order (WooCommerce native)
    $subtotal = $parent_order->get_subtotal();
    $total_tax = $parent_order->get_total_tax();
    $grand_total = $parent_order->get_total();
    
    // Get tax breakdown
    $tax_totals = $parent_order->get_tax_totals();
    $service_tax = 0;
    $vat_tax = 0;
    
    foreach ($tax_totals as $tax_code => $tax) {
        $tax_rate = floatval($tax->rate);
        $tax_amount = floatval($tax->amount);
        
        if ($tax_rate == 12.0) {
            $service_tax = $tax_amount;
        } elseif ($tax_rate == 14.0) {
            $vat_tax = $tax_amount;
        }
    }
    
    $invoice_system = 'parent_child';
    $order_data = $child_orders_data; // Use child orders for display
    
} else {
    // LEGACY SYSTEM: Get all completed orders for this table
    $args = array(
        'post_type' => 'shop_order',
        'post_status' => array('wc-completed'),
        'meta_query' => array(
            array(
                'key' => '_oj_table_number',
                'value' => $table_number,
                'compare' => '='
            )
        ),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $orders = get_posts($args);
    $total_amount = 0;
    $order_data = array();

    foreach ($orders as $order_post) {
        $order = wc_get_order($order_post->ID);
        if (!$order) continue;
        
        $order_items = array();
        foreach ($order->get_items() as $item) {
            $order_items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }
        
        $order_data[] = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total' => $order->get_total(),
            'items' => $order_items,
            'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'payment_method' => $order->get_meta('_oj_payment_method') ?: $payment_method
        );
        
        $total_amount += $order->get_total();
    }
    
    // Legacy system: Try to get stored tax data or calculate basic totals
    $session_id = 'table_' . $table_number . '_' . date('Y-m-d');
    $table_tax_data = get_option('oj_table_tax_' . $session_id);
    
    if ($table_tax_data && is_array($table_tax_data)) {
        // Use stored tax data from legacy system
        $subtotal = $table_tax_data['subtotal'] ?? $total_amount;
        $service_tax = $table_tax_data['service_tax'] ?? 0;
        $vat_tax = $table_tax_data['vat_tax'] ?? 0;
        $total_tax = $table_tax_data['total_tax'] ?? 0;
        $grand_total = $table_tax_data['grand_total'] ?? $total_amount;
    } else {
        // Fallback: Basic calculation without tax breakdown
        $subtotal = $total_amount;
        $service_tax = 0;
        $vat_tax = 0;
        $total_tax = 0;
        $grand_total = $total_amount;
    }
    
    $invoice_system = 'legacy';
}

// Get table information
$table_id = oj_get_table_id_by_number($table_number);
$table_capacity = $table_id ? get_post_meta($table_id, '_oj_table_capacity', true) : '';
$table_location = $table_id ? get_post_meta($table_id, '_oj_table_location', true) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php printf(__('Invoice - Table %s', 'orders-jet'), $table_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }
        
        .invoice-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .invoice-header {
            background: #c41e3a;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .invoice-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .invoice-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .invoice-info {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .orders-section {
            padding: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #c41e3a;
            padding-bottom: 10px;
        }
        
        .order-item {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .order-number {
            font-weight: 600;
            color: #c41e3a;
            font-size: 16px;
        }
        
        .order-date {
            color: #666;
            font-size: 14px;
        }
        
        .order-items {
            margin-bottom: 15px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            display: block;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .item-variations,
        .item-addons,
        .item-notes {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 1px;
        }
        
        .item-variations {
            font-style: italic;
        }
        
        .item-addons {
            color: #007cba;
        }
        
        .item-notes {
            color: #d63638;
        }
        
        .item-quantity {
            width: 60px;
            text-align: center;
            color: #666;
        }
        
        .item-total {
            width: 80px;
            text-align: right;
            font-weight: 600;
        }
        
        .order-total {
            text-align: right;
            font-size: 16px;
            font-weight: 600;
            color: #c41e3a;
        }
        
        .invoice-total {
            background: #c41e3a;
            color: white;
            padding: 25px 30px;
        }
        
        .invoice-total h2 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .total-breakdown {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-row.subtotal {
            font-size: 16px;
        }
        
        .total-row.tax {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .total-row.total-tax {
            font-size: 16px;
            font-weight: 600;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 5px;
            padding-top: 12px;
        }
        
        .total-row.grand-total {
            font-size: 20px;
            font-weight: bold;
            border-top: 2px solid rgba(255, 255, 255, 0.5);
            margin-top: 10px;
            padding-top: 15px;
        }
        
        .system-indicator {
            text-align: center;
            margin-top: 15px;
            opacity: 0.7;
        }
        
        .payment-info {
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            text-align: center;
        }
        
        .payment-method {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
        }
        
        .thank-you {
            padding: 30px;
            text-align: center;
            background: #d4edda;
            color: #155724;
        }
        
        .thank-you h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007cba;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-btn:hover {
            background: #005a87;
        }
        
        @media print {
            .print-btn {
                display: none;
            }
            
            .invoice-container {
                box-shadow: none;
                margin: 0;
                max-width: none;
            }
            
            body {
                background: white;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                margin: 10px;
                border-radius: 0;
            }
            
            .invoice-header,
            .invoice-info,
            .orders-section,
            .invoice-total,
            .payment-info,
            .thank-you {
                padding: 20px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .item-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .item-quantity,
            .item-total {
                width: auto;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ <?php _e('Print Invoice', 'orders-jet'); ?></button>
    
    <div class="invoice-container">
        <div class="invoice-header">
            <h1><?php _e('Restaurant Invoice', 'orders-jet'); ?></h1>
            <p><?php printf(__('Table %s', 'orders-jet'), $table_number); ?></p>
        </div>
        
        <div class="invoice-info">
            <div class="info-row">
                <span class="info-label"><?php _e('Table Number:', 'orders-jet'); ?></span>
                <span class="info-value"><?php echo esc_html($table_number); ?></span>
            </div>
            <?php if ($table_capacity): ?>
            <div class="info-row">
                <span class="info-label"><?php _e('Capacity:', 'orders-jet'); ?></span>
                <span class="info-value"><?php echo esc_html($table_capacity); ?> <?php _e('people', 'orders-jet'); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($table_location): ?>
            <div class="info-row">
                <span class="info-label"><?php _e('Location:', 'orders-jet'); ?></span>
                <span class="info-value"><?php echo esc_html($table_location); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label"><?php _e('Invoice Date:', 'orders-jet'); ?></span>
                <span class="info-value"><?php echo current_time('Y-m-d H:i:s'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><?php _e('Number of Orders:', 'orders-jet'); ?></span>
                <span class="info-value"><?php echo count($order_data); ?></span>
            </div>
        </div>
        
        <div class="orders-section">
            <h2 class="section-title"><?php _e('Order Details', 'orders-jet'); ?></h2>
            
            <?php if (empty($order_data)): ?>
                <p style="text-align: center; color: #666; padding: 40px;"><?php _e('No orders found', 'orders-jet'); ?></p>
            <?php else: ?>
                <?php foreach ($order_data as $order): ?>
                    <div class="order-item">
                        <div class="order-header">
                            <?php if ($invoice_system === 'parent_child'): ?>
                                <span class="order-number"><?php printf(__('Order #%s', 'orders-jet'), $order['order_number'] ?? $order['child_order_id']); ?></span>
                                <span class="order-date"><?php echo esc_html($order['order_time'] ?? $order['order_date']); ?></span>
                                <?php if (!empty($order['customer_name'])): ?>
                                    <span class="customer-name"><?php echo esc_html($order['customer_name']); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="order-number"><?php printf(__('Order #%s', 'orders-jet'), $order['order_number']); ?></span>
                                <span class="order-date"><?php echo esc_html($order['date']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-items">
                            <?php 
                            $items = $order['items'];
                            foreach ($items as $item): 
                                if ($invoice_system === 'parent_child') {
                                    // Parent-child system: items have different structure
                                    $item_name = $item['name'];
                                    $item_quantity = $item['quantity'];
                                    $item_total = $item['total'];
                                    $item_variations = $item['variations'] ?? '';
                                    $item_addons = $item['addons'] ?? '';
                                    $item_notes = $item['notes'] ?? '';
                                } else {
                                    // Legacy system: standard structure
                                    $item_name = $item['name'];
                                    $item_quantity = $item['quantity'];
                                    $item_total = $item['total'];
                                    $item_variations = '';
                                    $item_addons = '';
                                    $item_notes = '';
                                }
                            ?>
                                <div class="item-row">
                                    <div class="item-details">
                                        <span class="item-name"><?php echo esc_html($item_name); ?></span>
                                        <?php if (!empty($item_variations)): ?>
                                            <small class="item-variations"><?php echo esc_html($item_variations); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($item_addons)): ?>
                                            <small class="item-addons"><?php echo esc_html($item_addons); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($item_notes)): ?>
                                            <small class="item-notes"><?php _e('Note:', 'orders-jet'); ?> <?php echo esc_html($item_notes); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="item-quantity">×<?php echo esc_html($item_quantity); ?></span>
                                    <span class="item-total"><?php echo wc_price($item_total); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($invoice_system === 'legacy'): ?>
                        <div class="order-total">
                            <?php printf(__('Order Total: %s', 'orders-jet'), wc_price($order['total'])); ?>
                        </div>
                        <?php else: ?>
                        <div class="order-subtotal">
                            <?php printf(__('Order Subtotal: %s', 'orders-jet'), wc_price($order['subtotal'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="invoice-total">
            <h2><?php _e('Invoice Summary', 'orders-jet'); ?></h2>
            
            <div class="total-breakdown">
                <div class="total-row subtotal">
                    <span><?php _e('Subtotal:', 'orders-jet'); ?></span>
                    <span><?php echo wc_price($subtotal); ?></span>
                </div>
                
                <?php if ($service_tax > 0): ?>
                <div class="total-row tax">
                    <span><?php _e('Service Tax (12%):', 'orders-jet'); ?></span>
                    <span><?php echo wc_price($service_tax); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($vat_tax > 0): ?>
                <div class="total-row tax">
                    <span><?php _e('VAT (14%):', 'orders-jet'); ?></span>
                    <span><?php echo wc_price($vat_tax); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_tax > 0): ?>
                <div class="total-row total-tax">
                    <span><?php _e('Total Tax:', 'orders-jet'); ?></span>
                    <span><?php echo wc_price($total_tax); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="total-row grand-total">
                    <span><?php _e('Grand Total:', 'orders-jet'); ?></span>
                    <span><?php echo wc_price($grand_total); ?></span>
                </div>
            </div>
            
            <!-- System indicator for debugging -->
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div class="system-indicator">
                <small><?php printf(__('System: %s', 'orders-jet'), $invoice_system); ?></small>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="payment-info">
            <span class="payment-method">
                <?php printf(__('Paid by %s', 'orders-jet'), ucfirst($payment_method)); ?>
            </span>
        </div>
        
        <div class="thank-you">
            <h3><?php _e('Thank you for your visit!', 'orders-jet'); ?></h3>
            <p><?php _e('We hope you enjoyed your meal. Please come again!', 'orders-jet'); ?></p>
        </div>
    </div>
</body>
</html>

