<?php
/**
 * Orders Jet - Table Invoice Template
 * Displays invoice for table orders after payment
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get table number and payment method from URL
$table_number = sanitize_text_field($_GET['table'] ?? '');
$payment_method = sanitize_text_field($_GET['payment_method'] ?? 'cash');

if (empty($table_number)) {
    wp_die(__('Table number is required', 'orders-jet'));
}

// Get all completed orders for this table
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
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-name {
            flex: 1;
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
            text-align: center;
        }
        
        .invoice-total h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .invoice-total p {
            opacity: 0.9;
            font-size: 16px;
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
                            <span class="order-number"><?php printf(__('Order #%s', 'orders-jet'), $order['order_number']); ?></span>
                            <span class="order-date"><?php echo esc_html($order['date']); ?></span>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="item-row">
                                    <span class="item-name"><?php echo esc_html($item['name']); ?></span>
                                    <span class="item-quantity"><?php echo esc_html($item['quantity']); ?></span>
                                    <span class="item-total"><?php echo wc_price($item['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            <?php printf(__('Order Total: %s', 'orders-jet'), wc_price($order['total'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="invoice-total">
            <h2><?php _e('Total Amount', 'orders-jet'); ?></h2>
            <p><?php echo wc_price($total_amount); ?></p>
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

