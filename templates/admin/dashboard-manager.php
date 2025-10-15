<?php
/**
 * Orders Jet - Manager Orders Management Template (Fresh Clean Version)
 * Simple, clean order management interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Include the manager navigation
include ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-navigation.php';

// Get current date
$today_formatted = date('F j, Y');

// DEBUG: Retrieve ALL orders to see what exists
$all_orders = array();
$processing_orders = array();
$ready_orders = array();

// Get orders using WooCommerce native function
if (function_exists('wc_get_orders')) {
    // DEBUG: Get ALL orders to see what statuses exist
    $all_wc_orders = wc_get_orders(array(
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // DEBUG: Log what we found
    error_log('Orders Jet Manager DEBUG: Found ' . count($all_wc_orders) . ' total orders');
    
    // DEBUG: Check each order status
    $status_counts = array();
    foreach ($all_wc_orders as $order) {
        $status = $order->get_status();
        if (!isset($status_counts[$status])) {
            $status_counts[$status] = 0;
        }
        $status_counts[$status]++;
        
        // Log first few orders for debugging
        if (count($all_orders) < 10) {
            error_log('Orders Jet Manager DEBUG: Order #' . $order->get_id() . ' has status: ' . $status);
        }
    }
    
    // DEBUG: Log status summary
    foreach ($status_counts as $status => $count) {
        error_log('Orders Jet Manager DEBUG: Status "' . $status . '": ' . $count . ' orders');
    }
    
    // Now separate by status for display
    $processing_wc_orders = array();
    $ready_wc_orders = array();
    
    // Separate orders by status
    foreach ($all_wc_orders as $order) {
        $status = $order->get_status();
        
        if ($status === 'processing') {
            $processing_wc_orders[] = $order;
        } elseif ($status === 'pending-payment') {
            $ready_wc_orders[] = $order;
        }
    }
    
    error_log('Orders Jet Manager DEBUG: Processing orders: ' . count($processing_wc_orders));
    error_log('Orders Jet Manager DEBUG: Ready orders: ' . count($ready_wc_orders));
    
    // Process processing orders
    foreach ($processing_wc_orders as $order) {
        $processing_orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'customer' => $order->get_billing_first_name() ?: 'Guest',
            'table' => $order->get_meta('_oj_table_number') ?: '',
            'date' => $order->get_date_created()->format('H:i'),
            'type' => !empty($order->get_meta('_oj_table_number')) ? 'table' : 'pickup'
        );
    }
    
    // Process ready orders
    foreach ($ready_wc_orders as $order) {
        $ready_orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'customer' => $order->get_billing_first_name() ?: 'Guest',
            'table' => $order->get_meta('_oj_table_number') ?: '',
            'date' => $order->get_date_created()->format('H:i'),
            'type' => !empty($order->get_meta('_oj_table_number')) ? 'table' : 'pickup'
        );
    }
    
    // Combine all orders
    $all_orders = array_merge($processing_orders, $ready_orders);
}

// Calculate statistics
$total_orders = count($all_orders);
$processing_count = count($processing_orders);
$ready_count = count($ready_orders);
$table_orders = array_filter($all_orders, function($order) { return $order['type'] === 'table'; });
$pickup_orders = array_filter($all_orders, function($order) { return $order['type'] === 'pickup'; });

?>

<div class="wrap oj-manager-orders">
    
    <!-- Header -->
    <div class="oj-header">
        <h1>
            <span class="dashicons dashicons-clipboard"></span>
            <?php _e('Orders Management', 'orders-jet'); ?>
        </h1>
        <p><?php echo sprintf(__('Manage all restaurant orders - %s', 'orders-jet'), $today_formatted); ?></p>
        <button onclick="location.reload()" class="button">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh', 'orders-jet'); ?>
        </button>
    </div>
    
    <!-- DEBUG INFO -->
    <?php if (isset($status_counts) && !empty($status_counts)) : ?>
    <div class="oj-debug-info" style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <h3>🔍 DEBUG: Order Status Summary</h3>
        <p><strong>Total Orders Found:</strong> <?php echo count($all_wc_orders); ?></p>
        <ul>
            <?php foreach ($status_counts as $status => $count) : ?>
                <li><strong><?php echo esc_html($status); ?>:</strong> <?php echo $count; ?> orders</li>
            <?php endforeach; ?>
        </ul>
        <p><em>Check error logs for detailed order information.</em></p>
    </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="oj-stats">
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo count($table_orders); ?></div>
            <div class="stat-label"><?php _e('Table Orders', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo count($pickup_orders); ?></div>
            <div class="stat-label"><?php _e('Pickup Orders', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $processing_count; ?></div>
            <div class="stat-label"><?php _e('In Kitchen', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $ready_count; ?></div>
            <div class="stat-label"><?php _e('Ready Orders', 'orders-jet'); ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <?php _e('All Orders', 'orders-jet'); ?> (<?php echo $total_orders; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            <?php _e('In Kitchen', 'orders-jet'); ?> (<?php echo $processing_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="ready">
            <?php _e('Ready Orders', 'orders-jet'); ?> (<?php echo $ready_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="table">
            <?php _e('Table Orders', 'orders-jet'); ?> (<?php echo count($table_orders); ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pickup">
            <?php _e('Pickup Orders', 'orders-jet'); ?> (<?php echo count($pickup_orders); ?>)
        </button>
    </div>
    
    <!-- Orders Table -->
    <div class="oj-orders-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Order #', 'orders-jet'); ?></th>
                    <th><?php _e('Customer', 'orders-jet'); ?></th>
                    <th><?php _e('Type', 'orders-jet'); ?></th>
                    <th><?php _e('Status', 'orders-jet'); ?></th>
                    <th><?php _e('Total', 'orders-jet'); ?></th>
                    <th><?php _e('Time', 'orders-jet'); ?></th>
                    <th><?php _e('Actions', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_orders)) : ?>
                    <?php foreach ($all_orders as $order) : ?>
                        <tr class="oj-order-row" 
                            data-status="<?php echo esc_attr($order['status']); ?>"
                            data-type="<?php echo esc_attr($order['type']); ?>">
                            
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            
                            <td><?php echo esc_html($order['customer']); ?></td>
                            
                            <td>
                                <?php if ($order['type'] === 'table') : ?>
                                    🍽️ <?php echo sprintf(__('Table %s', 'orders-jet'), $order['table']); ?>
                                <?php else : ?>
                                    🥡 <?php _e('Pickup', 'orders-jet'); ?>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($order['status'] === 'processing') : ?>
                                    <span class="oj-status cooking">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                                <?php elseif ($order['status'] === 'pending-payment') : ?>
                                    <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td><?php echo wc_price($order['total']); ?></td>
                            
                            <td><?php echo $order['date']; ?></td>
                            
                            <td>
                                <?php if ($order['status'] === 'pending-payment') : ?>
                                    <?php if ($order['type'] === 'table') : ?>
                                        <button class="button button-primary oj-close-table" 
                                                data-order-id="<?php echo $order['id']; ?>"
                                                data-table="<?php echo esc_attr($order['table']); ?>">
                                            <?php _e('Close Table', 'orders-jet'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button class="button button-primary oj-complete-order" 
                                                data-order-id="<?php echo $order['id']; ?>">
                                            <?php _e('Complete Order', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="oj-status-note"><?php _e('In Kitchen', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" class="oj-no-orders">
                            <?php _e('No active orders found.', 'orders-jet'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
</div>

<style>
.oj-manager-orders {
    max-width: 1200px;
}

.oj-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.oj-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.oj-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.oj-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.oj-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.oj-filter-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.3s;
}

.oj-filter-btn:hover,
.oj-filter-btn.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.oj-orders-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.oj-order-row.hidden {
    display: none;
}

.oj-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.oj-status.cooking {
    background: #fff3cd;
    color: #856404;
}

.oj-status.ready {
    background: #d1edff;
    color: #0c5460;
}

.oj-status-note {
    color: #666;
    font-style: italic;
}

.oj-no-orders {
    text-align: center;
    padding: 40px;
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Filter functionality
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter orders
        $('.oj-order-row').each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const type = $row.data('type');
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'processing':
                    show = status === 'processing';
                    break;
                case 'ready':
                    show = status === 'pending-payment';
                    break;
                case 'table':
                    show = type === 'table';
                    break;
                case 'pickup':
                    show = type === 'pickup';
                    break;
            }
            
            if (show) {
                $row.removeClass('hidden');
            } else {
                $row.addClass('hidden');
            }
        });
    });
    
    // Close table action
    $('.oj-close-table').on('click', function() {
        const orderId = $(this).data('order-id');
        const table = $(this).data('table');
        
        if (confirm('<?php _e('Close this table and complete all orders?', 'orders-jet'); ?>')) {
            // AJAX call to close table
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_close_table',
                    table_number: table,
                    nonce: '<?php echo wp_create_nonce('oj_close_table'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Error closing table', 'orders-jet'); ?>');
                    }
                }
            });
        }
    });
    
    // Complete order action
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        
        if (confirm('<?php _e('Complete this order?', 'orders-jet'); ?>')) {
            // AJAX call to complete order
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_complete_individual_order',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('oj_complete_order'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Error completing order', 'orders-jet'); ?>');
                    }
                }
            });
        }
    });
    
});
</script>
