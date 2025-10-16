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

// Clean order retrieval
$all_orders = array();
$processing_orders = array();
$ready_orders = array();

// Get orders using WooCommerce native function
if (function_exists('wc_get_orders')) {
    // Get processing orders (cooking) - FIFO approach like kitchen dashboard
    $processing_wc_orders = wc_get_orders(array(
        'status' => 'processing',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'ASC' // Oldest first - operational priority (FIFO)
    ));
    
    // Get ready orders (pending - awaiting payment) - FIFO approach like kitchen dashboard
    $ready_wc_orders = wc_get_orders(array(
        'status' => 'pending',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'ASC' // Oldest first - operational priority (FIFO)
    ));
    
    // Get recent completed orders (minimal load - only latest 15) - newest first for recent view
    $recent_completed_wc_orders = wc_get_orders(array(
        'status' => 'completed',
        'limit' => 15, // Only latest 15 for performance
        'orderby' => 'date_modified',
        'order' => 'DESC' // Newest first for completed orders (recent view)
    ));
    
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
    
    // Process recent completed orders
    $recent_completed_orders = array();
    foreach ($recent_completed_wc_orders as $order) {
        $recent_completed_orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'customer' => $order->get_billing_first_name() ?: 'Guest',
            'table' => $order->get_meta('_oj_table_number') ?: '',
            'date' => $order->get_date_modified()->format('H:i'),
            'payment_method' => $order->get_meta('_oj_payment_method') ?: '',
            'type' => !empty($order->get_meta('_oj_table_number')) ? 'table' : 'pickup'
        );
    }
    
    // Combine active orders only (exclude completed from main operations)
    $active_orders = array_merge($processing_orders, $ready_orders);
    
    // NEW APPROACH: Group table orders by table number for collapsed view
    $table_groups = array();
    $pickup_orders = array();
    
    foreach ($active_orders as $order) {
        if ($order['type'] === 'table' && !empty($order['table'])) {
            $table_number = $order['table'];
            
            // Initialize table group if not exists
            if (!isset($table_groups[$table_number])) {
                $table_groups[$table_number] = array(
                    'table_number' => $table_number,
                    'orders' => array(),
                    'total_amount' => 0,
                    'order_count' => 0,
                    'earliest_time' => null,
                    'all_ready' => true,
                    'has_cooking' => false,
                    'has_ready' => false
                );
            }
            
            // Add order to table group
            $table_groups[$table_number]['orders'][] = $order;
            $table_groups[$table_number]['total_amount'] += floatval($order['total']);
            $table_groups[$table_number]['order_count']++;
            
            // Track earliest time (table opened time)
            if (!$table_groups[$table_number]['earliest_time'] || 
                $order['date'] < $table_groups[$table_number]['earliest_time']) {
                $table_groups[$table_number]['earliest_time'] = $order['date'];
            }
            
            // Track order statuses
            if ($order['status'] === 'processing') {
                $table_groups[$table_number]['has_cooking'] = true;
                $table_groups[$table_number]['all_ready'] = false;
            } elseif ($order['status'] === 'pending') {
                $table_groups[$table_number]['has_ready'] = true;
            }
        } else {
            // Pickup orders remain individual
            $pickup_orders[] = $order;
        }
    }
    
    // Sort table groups by earliest time (FIFO)
    uasort($table_groups, function($a, $b) {
        return strcmp($a['earliest_time'], $b['earliest_time']);
    });
    
    // Sort pickup orders by time (FIFO)
    usort($pickup_orders, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    
    // For display purposes, we'll create a mixed array of table groups and pickup orders
    $display_orders = array();
    
    // Add table groups first (they have priority)
    foreach ($table_groups as $table_group) {
        $display_orders[] = array(
            'type' => 'table_group',
            'table_number' => $table_group['table_number'],
            'order_count' => $table_group['order_count'],
            'total_amount' => $table_group['total_amount'],
            'earliest_time' => $table_group['earliest_time'],
            'all_ready' => $table_group['all_ready'],
            'has_cooking' => $table_group['has_cooking'],
            'has_ready' => $table_group['has_ready'],
            'orders' => $table_group['orders']
        );
    }
    
    // Add pickup orders
    foreach ($pickup_orders as $pickup_order) {
        $display_orders[] = $pickup_order;
    }
    
    // All orders including completed (for display purposes)
    $all_orders = array_merge($display_orders, $recent_completed_orders);
    
    // Debug logging for sorting verification
    error_log('Orders Jet Manager: Applied FIFO sorting - Active orders count: ' . count($active_orders));
    if (!empty($active_orders)) {
        $order_sequence = array();
        foreach ($active_orders as $order) {
            $order_sequence[] = '#' . $order['id'] . ' (' . $order['type'] . ', ' . $order['date'] . ')';
        }
        error_log('Orders Jet Manager: Order sequence after FIFO sorting: ' . implode(', ', $order_sequence));
    }
}

// Calculate statistics for new grouped structure
$active_orders_count = count($active_orders); // Total individual orders
$total_display_items = count($display_orders); // Table groups + pickup orders
$processing_count = count($processing_orders);
$ready_count = count($ready_orders);
$recent_completed_count = count($recent_completed_orders);

// Count table groups and pickup orders separately
$table_groups_count = count($table_groups);
$pickup_orders_count = count($pickup_orders);

// For backward compatibility with existing template code
$active_table_orders = array();
foreach ($table_groups as $group) {
    $active_table_orders = array_merge($active_table_orders, $group['orders']);
}
$active_pickup_orders = $pickup_orders;

// All orders including completed (for backward compatibility)
$table_orders = $active_table_orders;
$pickup_orders_all = array_merge($pickup_orders, 
    array_filter($recent_completed_orders, function($order) { 
        return isset($order['type']) && $order['type'] === 'pickup'; 
    })
);

?>

<div class="wrap oj-manager-orders">
    
    <!-- Header -->
    <div class="oj-header">
        <h1>
            <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Orders Management', 'orders-jet'); ?>
        </h1>
        <p><?php echo sprintf(__('Manage all restaurant orders - %s', 'orders-jet'), $today_formatted); ?></p>
        <div class="oj-header-actions">
            <div class="oj-quick-search">
                <input type="text" id="oj-order-search" placeholder="<?php _e('Order # for invoice...', 'orders-jet'); ?>" />
                <button type="button" class="button" id="oj-search-invoice">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </div>
            <button onclick="location.reload()" class="button">
                    <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'orders-jet'); ?>
        </button>
        </div>
    </div>
    
    
    <!-- Statistics -->
    <div class="oj-stats">
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $active_orders_count; ?></div>
            <div class="stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $table_groups_count; ?></div>
            <div class="stat-label"><?php _e('Active Tables', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $pickup_orders_count; ?></div>
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
            <?php _e('Active Orders', 'orders-jet'); ?> (<?php echo $active_orders_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            <?php _e('In Kitchen', 'orders-jet'); ?> (<?php echo $processing_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="ready">
            <?php _e('Ready Orders', 'orders-jet'); ?> (<?php echo $ready_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="table">
            <?php _e('Table Groups', 'orders-jet'); ?> (<?php echo $table_groups_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pickup">
            <?php _e('Pickup Orders', 'orders-jet'); ?> (<?php echo $pickup_orders_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="completed">
            ✅ <?php _e('Recent', 'orders-jet'); ?> (<?php echo $recent_completed_count; ?>)
        </button>
    </div>
    
    <!-- Bulk Actions Bar -->
    <div class="oj-bulk-actions-bar" style="display: none;">
        <div class="oj-bulk-actions-content">
            <span class="oj-selected-count">0 <?php _e('orders selected', 'orders-jet'); ?></span>
            <div class="oj-bulk-actions-buttons">
                <select id="oj-bulk-action-select">
                    <option value=""><?php _e('Bulk Actions', 'orders-jet'); ?></option>
                    <option value="mark_ready"><?php _e('Mark as Ready', 'orders-jet'); ?></option>
                    <option value="complete_pickup_orders" class="pickup-only"><?php _e('Complete Pickup Orders', 'orders-jet'); ?></option>
                    <option value="close_tables" class="table-only"><?php _e('Close Tables', 'orders-jet'); ?></option>
                    <option value="cancel_orders"><?php _e('Cancel Orders', 'orders-jet'); ?></option>
                </select>
                <button type="button" class="button oj-apply-bulk-action"><?php _e('Apply', 'orders-jet'); ?></button>
                <button type="button" class="button oj-clear-selection"><?php _e('Clear Selection', 'orders-jet'); ?></button>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="oj-orders-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="cb-select-all" />
                    </th>
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
                    <?php foreach ($all_orders as $item) : ?>
                        
                        <?php if ($item['type'] === 'table_group') : ?>
                            <!-- TABLE GROUP ROW (Expanded by default) - Formatted to match table headers -->
                            <tr class="oj-table-group-row expanded" 
                                data-table="<?php echo esc_attr($item['table_number']); ?>"
                                data-type="table_group">
                                
                                <!-- Checkbox Column -->
                                <td class="check-column">
                                    <input type="checkbox" class="oj-table-checkbox" 
                                           value="<?php echo esc_attr($item['table_number']); ?>" />
                                </td>
                                
                                <!-- Order # Column: Table Number -->
                                <td><strong><?php echo esc_html($item['table_number']); ?></strong></td>
                                
                                <!-- Customer Column: Table Guest -->
                                <td><?php _e('Table Guest', 'orders-jet'); ?></td>
                                
                                <!-- Type Column: Dine In -->
                                <td>🍽️ <?php _e('Dine In', 'orders-jet'); ?></td>
                                
                                <!-- Status Column: Opened with indicators -->
                                <td>
                                    <span class="oj-table-status"><?php _e('Opened', 'orders-jet'); ?></span>
                                    <?php if ($item['has_cooking']) : ?>
                                        <span class="oj-status-indicator cooking">🍳</span>
                                    <?php endif; ?>
                                    <?php if ($item['has_ready']) : ?>
                                        <span class="oj-status-indicator ready">✅</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Total Column: Order count highlighted + Total amount -->
                                <td class="oj-table-total">
                                    <span class="oj-order-count-highlight"><?php echo $item['order_count']; ?></span>
                                    <span class="oj-separator">|</span>
                                    <span class="oj-total-amount"><?php echo number_format($item['total_amount'], 2); ?></span>
                                </td>
                                
                                <!-- Time Column: Opened time -->
                                <td><?php echo esc_html($item['earliest_time']); ?></td>
                                
                                <!-- Actions Column: Close Table + Collapse icon -->
                                <td class="oj-table-actions">
                                    <?php if ($item['all_ready']) : ?>
                                        <button class="button button-primary oj-close-table-group" 
                                                data-table="<?php echo esc_attr($item['table_number']); ?>">
                                            <?php _e('Close Table', 'orders-jet'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button class="button button-secondary oj-close-table-group" 
                                                data-table="<?php echo esc_attr($item['table_number']); ?>"
                                                disabled title="<?php _e('All orders must be ready first', 'orders-jet'); ?>">
                                            <?php _e('Close Table', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="oj-expand-table" data-table="<?php echo esc_attr($item['table_number']); ?>">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- CHILD ORDERS (Visible by default) -->
                            <?php foreach ($item['orders'] as $child_order) : ?>
                                <tr class="oj-child-order-row" 
                                    data-table="<?php echo esc_attr($item['table_number']); ?>"
                                    data-order-id="<?php echo esc_attr($child_order['id']); ?>"
                                    data-status="<?php echo esc_attr($child_order['status']); ?>"
                                    style="display: table-row;">
                                    
                                    <td></td>
                                    
                                    <td class="oj-child-order">
                                        <span class="oj-child-indicator">└─</span>
                                        <strong>#<?php echo $child_order['id']; ?></strong>
                                    </td>
                                    
                                    <td><?php echo esc_html($child_order['customer']); ?></td>
                                    
                                    <td>🍽️ <?php echo sprintf(__('Table %s', 'orders-jet'), $item['table_number']); ?></td>
                                    
                                    <td>
                                        <?php if ($child_order['status'] === 'processing') : ?>
                                            <span class="oj-status cooking">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                                        <?php elseif ($child_order['status'] === 'pending') : ?>
                                            <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td><?php echo wc_price($child_order['total']); ?></td>
                                    
                                    <td><?php echo $child_order['date']; ?></td>
                                    
                                    <td>
                                        <?php if ($child_order['status'] === 'processing') : ?>
                                            <button class="button oj-mark-ready" 
                                                    data-order-id="<?php echo $child_order['id']; ?>">
                                                <?php _e('Mark Ready', 'orders-jet'); ?>
                                            </button>
                                        <?php elseif ($child_order['status'] === 'pending') : ?>
                                            <span class="oj-status-note ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                        <?php else : ?>
                            <!-- PICKUP ORDER ROW (Individual) -->
                            <tr class="oj-order-row pickup-order" 
                                data-status="<?php echo esc_attr($item['status']); ?>"
                                data-type="pickup"
                                data-order-id="<?php echo esc_attr($item['id']); ?>">
                                
                                <td class="check-column">
                                    <input type="checkbox" class="oj-order-checkbox" value="<?php echo esc_attr($item['id']); ?>" />
                                </td>
                                
                                <td><strong>#<?php echo $item['id']; ?></strong></td>
                                
                                <td><?php echo esc_html($item['customer']); ?></td>
                                
                                <td>🥡 <?php _e('Pickup', 'orders-jet'); ?></td>
                                
                                <td>
                                    <?php if ($item['status'] === 'processing') : ?>
                                        <span class="oj-status cooking">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                                    <?php elseif ($item['status'] === 'pending') : ?>
                                        <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                    <?php elseif ($item['status'] === 'completed') : ?>
                                        <span class="oj-status completed">✅ <?php _e('Completed', 'orders-jet'); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td><?php echo wc_price($item['total']); ?></td>
                                
                                <td><?php echo $item['date']; ?></td>
                                
                                <td>
                                    <?php if ($item['status'] === 'processing') : ?>
                                        <button class="button oj-mark-ready" 
                                                data-order-id="<?php echo $item['id']; ?>">
                                            <?php _e('Mark Ready', 'orders-jet'); ?>
                                        </button>
                                    <?php elseif ($item['status'] === 'pending') : ?>
                                        <button class="button button-primary oj-complete-order" 
                                                data-order-id="<?php echo $item['id']; ?>">
                                            <?php _e('Complete Order', 'orders-jet'); ?>
                                        </button>
                                    <?php elseif ($item['status'] === 'completed') : ?>
                                        <button class="button-link oj-quick-invoice" 
                                                data-order-id="<?php echo $item['id']; ?>" 
                                                data-type="pickup">
                                            📄 <?php _e('Invoice', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8" class="oj-no-orders">
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

.oj-header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.oj-quick-search {
    display: flex;
    align-items: center;
    gap: 5px;
}

#oj-order-search {
    width: 180px;
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 13px;
}

#oj-search-invoice {
    padding: 4px 8px;
    height: 28px;
    min-height: 28px;
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

.oj-status.completed {
    background: #d1f2eb;
    color: #0f5132;
}

.oj-completion-time {
    display: block;
    color: #666;
    font-size: 11px;
    margin-top: 2px;
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

/* NEW: Table Group Styles */
.oj-table-group-row {
    background: #f8f9fa;
    border-left: 4px solid #007cba;
    font-weight: 500;
}

.oj-table-group-row.collapsed {
    border-left-color: #007cba;
}

.oj-table-group-row.expanded {
    border-left-color: #00a32a;
    background: #f0f6ff;
}

/* Table Status Styling */
.oj-table-status {
    color: #646970;
    font-weight: 500;
}

/* Table Total Column Styling */
.oj-table-total {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.oj-order-count-highlight {
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 13px;
    min-width: 20px;
    text-align: center;
}


.oj-total-amount {
    font-weight: 600;
    color: #135e96;
    font-size: 14px;
}

.oj-separator {
    color: #c3c4c7;
}

.oj-status-indicator {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.oj-status-indicator.cooking {
    background: #fff3cd;
    color: #856404;
}

.oj-status-indicator.ready {
    background: #d1f2eb;
    color: #0f5132;
}

.oj-table-actions {
    text-align: right;
    white-space: nowrap;
}

.oj-expand-table {
    background: #f6f7f7;
    border: 1px solid #ddd;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 4px;
    transition: all 0.2s;
    margin-left: 8px;
    vertical-align: middle;
}

.oj-expand-table:hover {
    background: #e8e9ea;
    border-color: #999;
}

.oj-expand-table .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #646970;
    display: block;
    line-height: 1;
}

.oj-table-group-row.expanded .oj-expand-table .dashicons {
    transform: rotate(180deg);
}

.oj-close-table-group {
    font-size: 12px;
    padding: 6px 12px;
    height: auto;
    line-height: 1.4;
    vertical-align: middle;
}

/* Child Order Styles */
.oj-child-order-row {
    background: #fafafa;
    border-left: 2px solid #c3c4c7;
}

.oj-child-order {
    padding-left: 20px;
}

.oj-child-indicator {
    color: #8c8f94;
    margin-right: 8px;
    font-family: monospace;
}

.oj-child-order-row td {
    padding-top: 6px;
    padding-bottom: 6px;
}

.oj-child-order-row .oj-status-note.ready {
    color: #0f5132;
    font-weight: 500;
}

/* Pickup Order Styles */
.pickup-order {
    border-left: 2px solid #dba617;
}

/* Enhanced Button Styles */
.oj-close-table-group {
    font-size: 12px;
    padding: 4px 12px;
    height: auto;
    line-height: 1.4;
}

.oj-close-table-group:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.oj-mark-ready {
    font-size: 12px;
    padding: 4px 10px;
    background: #2271b1;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.oj-mark-ready:hover {
    background: #135e96;
}

/* Bulk Actions Styles */
.oj-bulk-actions-bar {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.oj-bulk-actions-content {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.oj-selected-count {
    font-weight: bold;
    color: #2271b1;
}

.oj-bulk-actions-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}

#oj-bulk-action-select {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.oj-apply-bulk-action {
    background: #2271b1 !important;
    color: white !important;
    border-color: #2271b1 !important;
}

.oj-clear-selection {
    background: #666 !important;
    color: white !important;
    border-color: #666 !important;
}

.check-column {
    width: 40px;
    text-align: center;
}

.oj-order-row.selected {
    background-color: #e7f3ff !important;
}

/* Modal Styles */
.oj-payment-modal-overlay,
.oj-invoice-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
}

.oj-payment-modal,
.oj-invoice-modal {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    text-align: center;
}

.oj-payment-modal h3,
.oj-invoice-modal h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.oj-payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.oj-payment-btn {
    padding: 15px 20px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.oj-payment-btn:hover {
    border-color: #2271b1;
    background: #f0f6fc;
    transform: translateY(-2px);
}

.oj-modal-actions,
.oj-invoice-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.oj-invoice-actions .button {
    margin: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Apply default filter on page load (Active Orders)
    applyFilter('all');
    
    // Filter functionality
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Apply filter
        applyFilter(filter);
    });
    
    // Reusable filter function (Updated for table groups)
    function applyFilter(filter) {
        // Handle table groups
        $('.oj-table-group-row').each(function() {
            const $groupRow = $(this);
            const tableNumber = $groupRow.data('table');
            const $childRows = $(`.oj-child-order-row[data-table="${tableNumber}"]`);
            
            let showGroup = false;
            
            switch(filter) {
                case 'all':
                    showGroup = true; // Show all table groups
                    break;
                case 'table':
                    showGroup = true; // Show table groups when filtering by table
                    break;
                case 'processing':
                    // Show if any child order is processing
                    $childRows.each(function() {
                        if ($(this).data('status') === 'processing') {
                            showGroup = true;
                            return false;
                        }
                    });
                    break;
                case 'ready':
                    // Show if any child order is ready (pending)
                    $childRows.each(function() {
                        if ($(this).data('status') === 'pending') {
                            showGroup = true;
                            return false;
                        }
                    });
                    break;
                case 'pickup':
                case 'completed':
                    showGroup = false; // Don't show table groups for pickup/completed filters
                    break;
            }
            
            if (showGroup) {
                $groupRow.removeClass('hidden');
                // Also show child rows if expanded
                if ($groupRow.hasClass('expanded')) {
                    $childRows.show();
                }
            } else {
                $groupRow.addClass('hidden');
                $childRows.hide();
            }
        });
        
        // Handle pickup orders
        $('.pickup-order').each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const type = $row.data('type');
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    // Show only active pickup orders (exclude completed)
                    show = status !== 'completed';
                    break;
                case 'processing':
                    show = status === 'processing';
                    break;
                case 'ready':
                    show = status === 'pending';
                    break;
                case 'pickup':
                    // Show only active pickup orders (exclude completed)
                    show = status !== 'completed';
                    break;
                case 'completed':
                    show = status === 'completed';
                    break;
                case 'table':
                    show = false; // Don't show pickup orders when filtering by table
                    break;
            }
            
            if (show) {
                $row.removeClass('hidden');
            } else {
                $row.addClass('hidden');
            }
        });
        
        // Handle completed orders (if any)
        $('.oj-order-row:not(.pickup-order)').each(function() {
            const $row = $(this);
            const status = $row.data('status');
            
            if (status === 'completed') {
                if (filter === 'completed' || filter === 'all') {
                    $row.removeClass('hidden');
                } else {
                    $row.addClass('hidden');
                }
            }
        });
    }
    
    // NEW: Table Group Expand/Collapse Functionality
    $('.oj-expand-table').on('click', function() {
        const tableNumber = $(this).data('table');
        const $groupRow = $(this).closest('.oj-table-group-row');
        const $childRows = $(`.oj-child-order-row[data-table="${tableNumber}"]`);
        
        if ($groupRow.hasClass('collapsed')) {
            // Expand
            $groupRow.removeClass('collapsed').addClass('expanded');
            $childRows.show();
        } else {
            // Collapse
            $groupRow.removeClass('expanded').addClass('collapsed');
            $childRows.hide();
        }
    });
    
    // NEW: Mark Ready functionality for individual orders
    $('.oj-mark-ready').on('click', function() {
        const orderId = $(this).data('order-id');
        const $button = $(this);
        
        if (confirm('<?php _e('Mark this order as ready?', 'orders-jet'); ?>')) {
            $button.prop('disabled', true).text('<?php _e('Processing...', 'orders-jet'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_mark_order_ready',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Update the row to show ready status
                        const $row = $button.closest('tr');
                        $row.find('.oj-status').removeClass('cooking').addClass('ready')
                            .html('✅ <?php _e('Ready', 'orders-jet'); ?>');
                        $button.replaceWith('<span class="oj-status-note ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>');
                        
                        // Check if all orders in this table are now ready
                        const tableNumber = $row.data('table');
                        const $tableGroupRow = $(`.oj-table-group-row[data-table="${tableNumber}"]`);
                        const $allChildRows = $(`.oj-child-order-row[data-table="${tableNumber}"]`);
                        
                        let allReady = true;
                        $allChildRows.each(function() {
                            if ($(this).data('status') === 'processing') {
                                allReady = false;
                                return false;
                            }
                        });
                        
                        if (allReady) {
                            // Enable the Close Table button
                            $tableGroupRow.find('.oj-close-table-group')
                                .removeClass('button-secondary')
                                .addClass('button-primary')
                                .prop('disabled', false)
                                .removeAttr('title');
                        }
                        
                        alert('<?php _e('Order marked as ready!', 'orders-jet'); ?>');
                    } else {
                        alert('<?php _e('Error: ', 'orders-jet'); ?>' + response.data.message);
                        $button.prop('disabled', false).text('<?php _e('Mark Ready', 'orders-jet'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('Error marking order as ready', 'orders-jet'); ?>');
                    $button.prop('disabled', false).text('<?php _e('Mark Ready', 'orders-jet'); ?>');
                }
            });
        }
    });
    
    // NEW: Close Table Group functionality
    $('.oj-close-table-group').on('click', function() {
        const tableNumber = $(this).data('table');
        const $button = $(this);
        
        if ($button.prop('disabled')) {
            alert('<?php _e('All orders must be ready before closing the table', 'orders-jet'); ?>');
            return;
        }
        
        if (confirm('<?php _e('Close this table and create consolidated invoice?', 'orders-jet'); ?>')) {
            // Show payment method modal for table group
            showPaymentMethodModal(null, 'table_group', tableNumber);
        }
    });
    
    // Close table action with payment method selection
    $('.oj-close-table').on('click', function() {
        const orderId = $(this).data('order-id');
        const table = $(this).data('table');
        
        if (confirm('<?php _e('Close this table and complete all orders?', 'orders-jet'); ?>')) {
            // Show payment method modal for table
            showPaymentMethodModal(orderId, 'table', table);
        }
    });
    
    // Complete order action with payment method selection
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        
        // Show payment method modal
        showPaymentMethodModal(orderId, 'individual');
    });
    
    // Payment method modal functionality
    function showPaymentMethodModal(orderId, orderType, tableNumber = null) {
        const modal = $(`
            <div class="oj-payment-modal-overlay">
                <div class="oj-payment-modal">
                    <h3><?php _e('Complete Order', 'orders-jet'); ?> #${orderId}</h3>
                    <p><?php _e('Select payment method:', 'orders-jet'); ?></p>
                    <div class="oj-payment-methods">
                        <button class="oj-payment-btn" data-method="cash">
                            💰 <?php _e('Cash', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn" data-method="card">
                            💳 <?php _e('Card', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn" data-method="digital">
                            📱 <?php _e('Digital Payment', 'orders-jet'); ?>
                        </button>
                    </div>
                    <div class="oj-modal-actions">
                        <button class="button oj-cancel-payment"><?php _e('Cancel', 'orders-jet'); ?></button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle payment method selection
        modal.find('.oj-payment-btn').on('click', function() {
            const paymentMethod = $(this).data('method');
            completeOrderWithPayment(orderId, paymentMethod, orderType, tableNumber);
            modal.remove();
        });
        
        // Handle cancel
        modal.find('.oj-cancel-payment').on('click', function() {
            modal.remove();
        });
        
        // Close on overlay click
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    function completeOrderWithPayment(orderId, paymentMethod, orderType, tableNumber = null) {
        let actionName;
        
        // Determine the correct action based on order type
        if (orderType === 'table_group') {
            actionName = 'oj_close_table_group';
        } else if (orderType === 'table') {
            actionName = 'oj_close_table';
        } else {
            actionName = 'oj_complete_individual_order';
        }
        
        let requestData = {
            action: actionName,
            payment_method: paymentMethod
        };
        
        // Add appropriate ID parameter and nonce
        if (orderType === 'table_group' || orderType === 'table') {
            requestData.table_number = tableNumber;
            requestData.nonce = '<?php echo wp_create_nonce('oj_table_order'); ?>';
        } else {
            requestData.order_id = orderId;
            requestData.nonce = '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>';
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    // Show success message and invoice option
                    if (orderType === 'table_group' || orderType === 'table') {
                        showTableInvoiceModal(tableNumber, response.data);
                    } else {
                        showInvoiceModal(orderId, response.data);
                    }
                    
                    // Don't auto-refresh - let user interact with invoice modal first
                } else {
                    alert(response.data.message || '<?php _e('Error completing order', 'orders-jet'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Network error. Please try again.', 'orders-jet'); ?>');
            }
        });
    }
    
    function showInvoiceModal(orderId, responseData) {
        const modal = $(`
            <div class="oj-invoice-modal-overlay">
                <div class="oj-invoice-modal">
                    <h3>✅ <?php _e('Order Completed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Order', 'orders-jet'); ?> #${orderId} <?php _e('has been completed.', 'orders-jet'); ?></p>
                    <div class="oj-invoice-actions">
                        <button class="button button-primary oj-view-invoice" data-order-id="${orderId}">
                            📄 <?php _e('View PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-print-invoice" data-order-id="${orderId}">
                            🖨️ <?php _e('Print PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-download-invoice" data-order-id="${orderId}">
                            💾 <?php _e('Download PDF', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle view PDF invoice
        modal.find('.oj-view-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Use our secure admin PDF endpoint
            const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&document_type=invoice&output=html&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
            window.open(invoiceUrl, '_blank');
        });
        
        // Handle print PDF invoice
        modal.find('.oj-print-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Use our secure admin PDF endpoint for printing
            const pdfUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&document_type=invoice&output=pdf&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
            
            // Create hidden iframe for printing
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = pdfUrl;
            document.body.appendChild(iframe);
            
            iframe.onload = function() {
                try {
                    // Try to print the PDF directly
                    iframe.contentWindow.print();
                } catch (e) {
                    // Fallback: open in new window for manual printing
                    window.open(pdfUrl, '_blank');
                }
                // Remove iframe after printing
                setTimeout(() => {
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                }, 2000);
            };
            
            // Fallback if iframe fails to load
            iframe.onerror = function() {
                window.open(pdfUrl, '_blank');
                if (document.body.contains(iframe)) {
                    document.body.removeChild(iframe);
                }
            };
        });
        
        // Handle download PDF invoice
        modal.find('.oj-download-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Use our secure admin PDF endpoint for download
            const downloadUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&document_type=invoice&output=pdf&force_download=1&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
            
            // Create temporary link for download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'invoice-' + orderId + '.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // Handle close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
            location.reload();
        });
        
        // Close on overlay click
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
                location.reload();
            }
        });
    }
    
    function showTableInvoiceModal(tableNumber, responseData) {
        // Debug: Log the response data to see what we're receiving
        console.log('Table Invoice Modal - Response Data:', responseData);
        
        // For consolidated orders, use the consolidated_order_id
        let consolidatedOrderId = null;
        let childOrderIds = [];
        
        if (responseData && responseData.consolidated_order_id) {
            consolidatedOrderId = responseData.consolidated_order_id;
            childOrderIds = responseData.child_order_ids || [];
            console.log('Table Invoice Modal - Consolidated Order ID:', consolidatedOrderId);
            console.log('Table Invoice Modal - Child Order IDs:', childOrderIds);
        } else if (responseData && responseData.order_ids) {
            // Fallback for legacy system
            childOrderIds = responseData.order_ids;
            console.log('Table Invoice Modal - Legacy Order IDs:', childOrderIds);
        }
        
        const modal = $(`
            <div class="oj-invoice-modal-overlay">
                <div class="oj-invoice-modal">
                    <h3>✅ <?php _e('Table Closed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Table', 'orders-jet'); ?> #${tableNumber} <?php _e('has been closed and consolidated invoice generated.', 'orders-jet'); ?></p>
                    ${consolidatedOrderId ? `<p><small><?php _e('Consolidated Order ID:', 'orders-jet'); ?> #${consolidatedOrderId}</small></p>` : ''}
                    ${childOrderIds.length > 0 ? `<p><small><?php _e('Child Orders:', 'orders-jet'); ?> ${childOrderIds.map(id => '#' + id).join(', ')}</small></p>` : ''}
                    <div class="oj-invoice-actions">
                        <button class="button button-primary oj-view-consolidated-invoice" data-order-id="${consolidatedOrderId}" data-table="${tableNumber}">
                            📄 <?php _e('View Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-print-consolidated-invoice" data-order-id="${consolidatedOrderId}" data-table="${tableNumber}">
                            🖨️ <?php _e('Print Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-download-consolidated-invoice" data-order-id="${consolidatedOrderId}" data-table="${tableNumber}">
                            💾 <?php _e('Download PDF', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-refresh-page">
                            🔄 <?php _e('Refresh Dashboard', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle view consolidated invoice
        modal.find('.oj-view-consolidated-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            const tableNumber = $(this).data('table');
            if (orderId) {
                // Use standard WooCommerce order view or custom invoice template
                const invoiceUrl = '<?php echo admin_url('post.php'); ?>?post=' + orderId + '&action=edit';
                window.open(invoiceUrl, '_blank');
            } else {
                alert('<?php _e('No consolidated order found.', 'orders-jet'); ?>');
            }
        });
        
        // Handle print consolidated invoice
        modal.find('.oj-print-consolidated-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            if (orderId) {
                // Generate PDF for consolidated order
                const pdfUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
                
                // Create hidden iframe for printing
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = pdfUrl;
                document.body.appendChild(iframe);
                
                iframe.onload = function() {
                    try {
                        // Try to print the PDF directly
                        iframe.contentWindow.print();
                    } catch (e) {
                        // Fallback: open in new window for manual printing
                        window.open(pdfUrl, '_blank');
                    }
                    // Remove iframe after printing
                    setTimeout(() => {
                        if (document.body.contains(iframe)) {
                            document.body.removeChild(iframe);
                        }
                    }, 2000);
                };
                
                // Fallback if iframe fails to load
                iframe.onerror = function() {
                    window.open(pdfUrl, '_blank');
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                };
            } else {
                alert('<?php _e('No orders found for this table.', 'orders-jet'); ?>');
            }
        });
        
        // Handle download consolidated invoice
        modal.find('.oj-download-consolidated-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            const tableNumber = $(this).data('table');
            if (orderId) {
                // Generate PDF for consolidated order download
                const downloadUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&force_download=1&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
                
                // Create temporary link for download
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'consolidated-order-' + orderId + '-table-' + tableNumber + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert('<?php _e('No orders found for this table.', 'orders-jet'); ?>');
            }
        });
        
        // Handle refresh dashboard
        modal.find('.oj-refresh-page').on('click', function() {
            modal.remove();
            location.reload();
        });
        
        // Handle close (without refresh)
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
        });
        
        // Close on overlay click (without refresh)
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    // ===== BULK ACTIONS FUNCTIONALITY =====
    
    // Select All functionality
    $('#cb-select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.oj-order-checkbox:visible').prop('checked', isChecked);
        updateBulkActionsBar();
        updateRowHighlighting();
    });
    
    // Individual checkbox functionality
    $(document).on('change', '.oj-order-checkbox', function() {
        updateBulkActionsBar();
        updateRowHighlighting();
        
        // Update "select all" checkbox state
        const totalCheckboxes = $('.oj-order-checkbox:visible').length;
        const checkedCheckboxes = $('.oj-order-checkbox:visible:checked').length;
        $('#cb-select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#cb-select-all').prop('checked', checkedCheckboxes === totalCheckboxes);
    });
    
    // Update bulk actions bar visibility and count
    function updateBulkActionsBar() {
        const selectedCount = $('.oj-order-checkbox:checked').length;
        
        if (selectedCount > 0) {
            $('.oj-bulk-actions-bar').show();
            $('.oj-selected-count').text(selectedCount + ' <?php _e("orders selected", "orders-jet"); ?>');
            
            // Update available actions based on selected order types
            updateAvailableActions();
        } else {
            $('.oj-bulk-actions-bar').hide();
            // Reset all options to visible when no selection
            $('#oj-bulk-action-select option').show();
        }
    }
    
    // Update available actions based on selected order types
    function updateAvailableActions() {
        const selectedOrders = $('.oj-order-checkbox:checked');
        let hasPickupOrders = false;
        let hasTableOrders = false;
        let tableNumbers = new Set();
        
        selectedOrders.each(function() {
            const row = $(this).closest('tr');
            const orderType = row.data('type');
            
            if (orderType === 'pickup') {
                hasPickupOrders = true;
            } else if (orderType === 'table') {
                hasTableOrders = true;
                // Extract table number from the row
                const tableText = row.find('td:nth-child(5)').text(); // Type column
                const tableMatch = tableText.match(/Table (\w+)/);
                if (tableMatch) {
                    tableNumbers.add(tableMatch[1]);
                }
            }
        });
        
        // Show/hide options based on selection
        if (hasPickupOrders && !hasTableOrders) {
            // Only pickup orders selected
            $('#oj-bulk-action-select .pickup-only').show();
            $('#oj-bulk-action-select .table-only').hide();
            $('.oj-selected-count').text(selectedOrders.length + ' <?php _e("pickup orders selected", "orders-jet"); ?>');
        } else if (hasTableOrders && !hasPickupOrders) {
            // Only table orders selected
            $('#oj-bulk-action-select .pickup-only').hide();
            $('#oj-bulk-action-select .table-only').show();
            
            if (tableNumbers.size === 1) {
                $('.oj-selected-count').text('<?php _e("Table", "orders-jet"); ?> ' + Array.from(tableNumbers)[0] + ' (' + selectedOrders.length + ' <?php _e("orders selected", "orders-jet"); ?>)');
            } else {
                $('.oj-selected-count').text(tableNumbers.size + ' <?php _e("tables selected", "orders-jet"); ?> (' + selectedOrders.length + ' <?php _e("orders", "orders-jet"); ?>)');
            }
        } else if (hasPickupOrders && hasTableOrders) {
            // Mixed selection - show warning and limited actions
            $('#oj-bulk-action-select .pickup-only').hide();
            $('#oj-bulk-action-select .table-only').hide();
            $('.oj-selected-count').html('<span style="color: #d63638;"><?php _e("Mixed selection: pickup + table orders", "orders-jet"); ?></span>');
        } else {
            // Show all options
            $('#oj-bulk-action-select option').show();
        }
    }
    
    // Update row highlighting
    function updateRowHighlighting() {
        $('.oj-order-checkbox').each(function() {
            const row = $(this).closest('tr');
            if ($(this).is(':checked')) {
                row.addClass('selected');
            } else {
                row.removeClass('selected');
            }
        });
    }
    
    // Clear selection
    $('.oj-clear-selection').on('click', function() {
        $('.oj-order-checkbox, #cb-select-all').prop('checked', false);
        updateBulkActionsBar();
        updateRowHighlighting();
    });
    
    // Apply bulk action
    $('.oj-apply-bulk-action').on('click', function() {
        const selectedAction = $('#oj-bulk-action-select').val();
        const selectedOrders = $('.oj-order-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!selectedAction) {
            alert('<?php _e("Please select an action", "orders-jet"); ?>');
            return;
        }
        
        if (selectedOrders.length === 0) {
            alert('<?php _e("Please select at least one order", "orders-jet"); ?>');
            return;
        }
        
        // Validate action based on selection
        const validationResult = validateBulkAction(selectedAction, selectedOrders);
        if (!validationResult.valid) {
            alert(validationResult.message);
            return;
        }
        
        // Confirm action
        const actionText = $('#oj-bulk-action-select option:selected').text();
        if (!confirm('<?php _e("Are you sure you want to", "orders-jet"); ?> ' + actionText.toLowerCase() + ' <?php _e("for", "orders-jet"); ?> ' + selectedOrders.length + ' <?php _e("orders?", "orders-jet"); ?>')) {
            return;
        }
        
        // Execute bulk action
        executeBulkAction(selectedAction, selectedOrders);
    });
    
    // Validate bulk action based on order types
    function validateBulkAction(action, orderIds) {
        const selectedOrders = $('.oj-order-checkbox:checked');
        let hasPickupOrders = false;
        let hasTableOrders = false;
        let tableNumbers = new Set();
        
        selectedOrders.each(function() {
            const row = $(this).closest('tr');
            const orderType = row.data('type');
            
            if (orderType === 'pickup') {
                hasPickupOrders = true;
            } else if (orderType === 'table') {
                hasTableOrders = true;
                const tableText = row.find('td:nth-child(5)').text();
                const tableMatch = tableText.match(/Table (\w+)/);
                if (tableMatch) {
                    tableNumbers.add(tableMatch[1]);
                }
            }
        });
        
        switch (action) {
            case 'complete_pickup_orders':
                if (hasTableOrders) {
                    return {
                        valid: false,
                        message: '<?php _e("Cannot complete table orders individually. Use Close Tables action instead.", "orders-jet"); ?>'
                    };
                }
                if (!hasPickupOrders) {
                    return {
                        valid: false,
                        message: '<?php _e("No pickup orders selected.", "orders-jet"); ?>'
                    };
                }
                break;
                
            case 'close_tables':
                if (hasPickupOrders) {
                    return {
                        valid: false,
                        message: '<?php _e("Cannot close tables for pickup orders. Use Complete Pickup Orders instead.", "orders-jet"); ?>'
                    };
                }
                if (!hasTableOrders) {
                    return {
                        valid: false,
                        message: '<?php _e("No table orders selected.", "orders-jet"); ?>'
                    };
                }
                break;
                
            case 'mark_ready':
            case 'cancel_orders':
                // These actions are safe for both types
                break;
                
            default:
                return {
                    valid: false,
                    message: '<?php _e("Unknown action selected.", "orders-jet"); ?>'
                };
        }
        
        return { valid: true };
    }
    
    // Execute bulk action via AJAX
    function executeBulkAction(action, orderIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_bulk_action',
                bulk_action: action,
                order_ids: orderIds,
                nonce: '<?php echo wp_create_nonce('oj_bulk_action'); ?>'
            },
            beforeSend: function() {
                $('.oj-apply-bulk-action').prop('disabled', true).text('<?php _e("Processing...", "orders-jet"); ?>');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Refresh the page to show updated statuses
                } else {
                    alert('<?php _e("Error:", "orders-jet"); ?> ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php _e("Network error. Please try again.", "orders-jet"); ?>');
            },
            complete: function() {
                $('.oj-apply-bulk-action').prop('disabled', false).text('<?php _e("Apply", "orders-jet"); ?>');
            }
        });
    }
    
    // Update bulk actions when filters change
    $('.oj-filter-btn').on('click', function() {
        // Clear selections when filter changes
        $('.oj-order-checkbox, #cb-select-all').prop('checked', false);
        updateBulkActionsBar();
        updateRowHighlighting();
    });
    
    // ===== SEARCH AND INVOICE FUNCTIONALITY =====
    
    // Quick order search for invoice
    $('#oj-search-invoice').on('click', function() {
        const orderNumber = $('#oj-order-search').val().trim();
        if (!orderNumber) {
            alert('<?php _e("Please enter an order number", "orders-jet"); ?>');
            return;
        }
        
        // Search and show invoice directly
        searchOrderInvoice(orderNumber);
    });
    
    // Enter key support for search
    $('#oj-order-search').on('keypress', function(e) {
        if (e.which === 13) {
            $('#oj-search-invoice').click();
        }
    });
    
    // Quick invoice for recent completed orders
    $(document).on('click', '.oj-quick-invoice', function() {
        const orderId = $(this).data('order-id');
        const orderType = $(this).data('type');
        const table = $(this).data('table');
        
        openOrderInvoice(orderId, orderType, table);
    });
    
    // Search order and open invoice
    function searchOrderInvoice(orderNumber) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_search_order_invoice',
                order_number: orderNumber,
                nonce: '<?php echo wp_create_nonce('oj_search_invoice'); ?>'
            },
            beforeSend: function() {
                $('#oj-search-invoice').prop('disabled', true).find('.dashicons').removeClass('dashicons-search').addClass('dashicons-update');
            },
            success: function(response) {
                if (response.success) {
                    const order = response.data;
                    openOrderInvoice(order.id, order.type, order.table);
                    $('#oj-order-search').val(''); // Clear search box
                } else {
                    alert('<?php _e("Order not found or not completed", "orders-jet"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Search failed. Please try again.", "orders-jet"); ?>');
            },
            complete: function() {
                $('#oj-search-invoice').prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-search');
            }
        });
    }
    
    // Open invoice (unified function)
    function openOrderInvoice(orderId, orderType, table) {
        let invoiceUrl;
        
        if (orderType === 'table' && table) {
            invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_table_pdf&table_number=' + encodeURIComponent(table) + '&order_ids=' + orderId + '&output=html&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
        } else {
            invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_generate_admin_pdf&order_id=' + orderId + '&document_type=invoice&output=html&nonce=<?php echo wp_create_nonce('oj_admin_pdf'); ?>';
        }
        
        window.open(invoiceUrl, '_blank');
    }
    
});
</script>
