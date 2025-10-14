<?php
/**
 * Debug WooFood Orders - WordPress Admin Page
 * Add this as a temporary admin page to check order data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu item
add_action('admin_menu', 'oj_debug_woofood_menu');

function oj_debug_woofood_menu() {
    add_submenu_page(
        'tools.php',
        'Debug WooFood Orders',
        'Debug WooFood Orders',
        'manage_options',
        'debug-woofood-orders',
        'oj_debug_woofood_page'
    );
}

function oj_debug_woofood_page() {
    ?>
    <div class="wrap">
        <h1>WooFood Orders Debug Report</h1>
        
        <?php
        // Get recent orders
        $orders = wc_get_orders(array(
            'limit' => 20,
            'status' => array('processing', 'pending', 'on-hold'),
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        echo "<p>Found " . count($orders) . " recent orders to analyze:</p>";
        
        $dine_in_count = 0;
        $timed_pickup_count = 0;
        $regular_pickup_count = 0;
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Table #</th>
                    <th>WooFood Date</th>
                    <th>WooFood Time</th>
                    <th>Unix Timestamp</th>
                    <th>Order Type</th>
                    <th>Timezone Analysis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    // Get meta data
                    $table_number = $order->get_meta('_oj_table_number');
                    $delivery_date = $order->get_meta('exwfood_date_deli');
                    $delivery_time = $order->get_meta('exwfood_time_deli');
                    $delivery_unix = $order->get_meta('exwfood_datetime_deli_unix');
                    $order_method = $order->get_meta('exwfood_order_method');
                    
                    // Determine order type
                    if (!empty($table_number)) {
                        $order_type = 'DINE IN';
                        $order_type_class = 'dinein';
                        $dine_in_count++;
                    } elseif (!empty($delivery_date) && !empty($delivery_time)) {
                        $order_type = 'TIMED PICKUP';
                        $order_type_class = 'timed-pickup';
                        $timed_pickup_count++;
                    } else {
                        $order_type = 'REGULAR PICKUP';
                        $order_type_class = 'regular-pickup';
                        $regular_pickup_count++;
                    }
                ?>
                <tr>
                    <td><strong>#<?php echo $order->get_id(); ?></strong></td>
                    <td><?php echo $order->get_status(); ?></td>
                    <td><?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></td>
                    <td><?php echo $table_number ? $table_number : '-'; ?></td>
                    <td><?php echo $delivery_date ? $delivery_date : '-'; ?></td>
                    <td><?php echo $delivery_time ? $delivery_time : '-'; ?></td>
                    <td>
                        <?php if ($delivery_unix): ?>
                            <?php echo $delivery_unix; ?><br>
                            <small><?php echo date('Y-m-d H:i:s', $delivery_unix); ?></small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="order-type-<?php echo $order_type_class; ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                            <?php echo $order_type; ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($delivery_date) && !empty($delivery_time) && class_exists('OJ_Time_Helper')): 
                            $analysis = OJ_Time_Helper::analyze_woofood_delivery($delivery_date, $delivery_time, $delivery_unix);
                        ?>
                            <strong>Local:</strong> <?php echo $analysis['local_datetime']; ?><br>
                            <strong>Display:</strong> <?php echo $analysis['display_datetime']; ?><br>
                            <strong>Today:</strong> <?php echo $analysis['is_today'] ? '✅ YES' : '❌ NO'; ?><br>
                            <strong>Upcoming:</strong> <?php echo $analysis['is_upcoming'] ? '🔮 YES' : '⏰ NO'; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <h3>Summary</h3>
            <ul>
                <li><strong>Dine In Orders:</strong> <?php echo $dine_in_count; ?></li>
                <li><strong>Timed Pickup Orders:</strong> <?php echo $timed_pickup_count; ?></li>
                <li><strong>Regular Pickup Orders:</strong> <?php echo $regular_pickup_count; ?></li>
            </ul>
            
            <?php if ($timed_pickup_count > 0): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px;">
                    <h4>✅ Great! You have <?php echo $timed_pickup_count; ?> timed pickup orders to test countdown with.</h4>
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>✅ You have timed orders - ready to implement countdown!</li>
                        <li>🎯 Implement countdown in COOKING badge</li>
                        <li>🧪 Test countdown functionality</li>
                    </ol>
                </div>
            <?php else: ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px;">
                    <h4>⚠️ No timed pickup orders found.</h4>
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>📝 Create test orders with WooFood delivery date/time</li>
                        <li>🔄 Refresh this page to verify</li>
                        <li>🎯 Then implement countdown in COOKING badge</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .order-type-dinein {
            background: #d1ecf1;
            color: #0c5460;
        }
        .order-type-timed-pickup {
            background: #fff3cd;
            color: #856404;
        }
        .order-type-regular-pickup {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
    </div>
    <?php
}
?>