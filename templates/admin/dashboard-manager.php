<?php
/**
 * Orders Jet - Manager Orders Management Template (Clean Rebuild)
 * Simple, efficient order management interface
 * 
 * Requirements:
 * - Single query for all orders (lazy load 20 by 20)
 * - 6 horizontal scrollable filters with counts
 * - FIFO sorting by date_modified
 * - Table grouping with consolidation
 * - Payment method selection
 * - Thermal invoice integration
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_woocommerce')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'orders-jet'));
}

// ==========================================================================
// DATA LAYER - Single Query System
// ==========================================================================

/**
 * Get all orders with single query and lazy loading
 */
function oj_get_all_orders($offset = 0, $limit = 20) {
    if (!function_exists('wc_get_orders')) {
        return array();
    }
    
    // Single query for all orders - FIFO by date_modified
    $orders = wc_get_orders(array(
        'status' => array('processing', 'pending', 'completed'),
        'limit' => $limit,
        'offset' => $offset,
        'orderby' => 'date_modified',
        'order' => 'ASC' // FIFO - oldest modified first for operational priority
    ));
    
    $formatted_orders = array();
    
    foreach ($orders as $order) {
        $formatted_orders[] = oj_format_order($order);
    }
    
    return $formatted_orders;
}

/**
 * Format order data for consistent display
 */
function oj_format_order($order) {
    $table_number = $order->get_meta('_oj_table_number');
    $payment_method = $order->get_meta('_oj_payment_method');
    
    return array(
        'id' => $order->get_id(),
        'status' => $order->get_status(),
        'customer' => $order->get_billing_first_name() ?: 'Guest',
        'total' => $order->get_total(),
        'formatted_total' => wc_price($order->get_total()),
        'date_created' => $order->get_date_created()->format('H:i'),
        'date_modified' => $order->get_date_modified()->format('H:i'),
        'table_number' => $table_number,
        'type' => !empty($table_number) ? 'table' : 'pickup',
        'payment_method' => $payment_method,
        'created_timestamp' => $order->get_date_created()->getTimestamp(),
        'modified_timestamp' => $order->get_date_modified()->getTimestamp()
    );
}

/**
 * Get order counts for filters
 */
function oj_get_order_counts() {
    if (!function_exists('wc_get_orders')) {
        return array(
            'all' => 0,
            'active' => 0,
            'kitchen' => 0,
            'tables' => 0,
            'pickup' => 0,
            'completed' => 0
        );
    }
    
    // Get counts for each status
    $processing_count = wc_get_orders(array(
        'status' => 'processing',
        'return' => 'ids',
        'limit' => -1
    ));
    
    $pending_count = wc_get_orders(array(
        'status' => 'pending', 
        'return' => 'ids',
        'limit' => -1
    ));
    
    $completed_count = wc_get_orders(array(
        'status' => 'completed',
        'return' => 'ids', 
        'limit' => -1
    ));
    
    // Get table orders count
    $table_orders = wc_get_orders(array(
        'status' => array('processing', 'pending'),
        'meta_query' => array(
            array(
                'key' => '_oj_table_number',
                'value' => '',
                'compare' => '!='
            )
        ),
        'return' => 'ids',
        'limit' => -1
    ));
    
    // Get pickup orders count  
    $pickup_orders = wc_get_orders(array(
        'status' => array('processing', 'pending'),
        'meta_query' => array(
            array(
                'key' => '_oj_table_number',
                'value' => '',
                'compare' => '='
            )
        ),
        'return' => 'ids',
        'limit' => -1
    ));
    
    $processing_count = is_array($processing_count) ? count($processing_count) : 0;
    $pending_count = is_array($pending_count) ? count($pending_count) : 0;
    $completed_count = is_array($completed_count) ? count($completed_count) : 0;
    $table_count = is_array($table_orders) ? count($table_orders) : 0;
    $pickup_count = is_array($pickup_orders) ? count($pickup_orders) : 0;
    
    return array(
        'all' => $processing_count + $pending_count + $completed_count,
        'active' => $processing_count + $pending_count,
        'kitchen' => $processing_count,
        'tables' => $table_count,
        'pickup' => $pickup_count,
        'completed' => $completed_count
    );
}

/**
 * Group orders by table for display
 */
function oj_group_orders_by_table($orders) {
    $table_groups = array();
    $pickup_orders = array();
    
    foreach ($orders as $order) {
        if ($order['type'] === 'table' && !empty($order['table_number'])) {
            $table_number = $order['table_number'];
            
            if (!isset($table_groups[$table_number])) {
                $table_groups[$table_number] = array(
                    'table_number' => $table_number,
                    'orders' => array(),
                    'total_amount' => 0,
                    'order_count' => 0,
                    'all_ready' => true,
                    'has_cooking' => false,
                    'has_ready' => false,
                    'earliest_time' => null
                );
            }
            
            $table_groups[$table_number]['orders'][] = $order;
            $table_groups[$table_number]['total_amount'] += $order['total'];
            $table_groups[$table_number]['order_count']++;
            
            // Update status flags
            if ($order['status'] === 'processing') {
                $table_groups[$table_number]['all_ready'] = false;
                $table_groups[$table_number]['has_cooking'] = true;
            } elseif ($order['status'] === 'pending') {
                $table_groups[$table_number]['has_ready'] = true;
            }
            
            // Track earliest time
            if (is_null($table_groups[$table_number]['earliest_time']) || 
                $order['modified_timestamp'] < strtotime($table_groups[$table_number]['earliest_time'])) {
                $table_groups[$table_number]['earliest_time'] = $order['date_modified'];
            }
        } else {
            $pickup_orders[] = $order;
        }
    }
    
    return array(
        'table_groups' => $table_groups,
        'pickup_orders' => $pickup_orders
    );
}

// ==========================================================================
// INITIALIZE DATA
// ==========================================================================

// Get initial orders (first 20)
$all_orders = oj_get_all_orders(0, 20);
$order_counts = oj_get_order_counts();
$grouped_data = oj_group_orders_by_table($all_orders);

// Extract grouped data
$table_groups = $grouped_data['table_groups'];
$pickup_orders = $grouped_data['pickup_orders'];

// Separate orders by status for easy filtering
$processing_orders = array_filter($all_orders, function($order) {
    return $order['status'] === 'processing';
});

$pending_orders = array_filter($all_orders, function($order) {
    return $order['status'] === 'pending';
});

$completed_orders = array_filter($all_orders, function($order) {
    return $order['status'] === 'completed';
});

?>

<div class="wrap oj-manager-orders">
    <h1 class="wp-heading-inline"><?php _e('Orders Management', 'orders-jet'); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="oj-stats-grid">
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $order_counts['active']; ?></div>
            <div class="stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $order_counts['tables']; ?></div>
            <div class="stat-label"><?php _e('Active Tables', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $order_counts['pickup']; ?></div>
            <div class="stat-label"><?php _e('Pickup Orders', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $order_counts['kitchen']; ?></div>
            <div class="stat-label"><?php _e('In Kitchen', 'orders-jet'); ?></div>
        </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo count($pending_orders); ?></div>
            <div class="stat-label"><?php _e('Ready Orders', 'orders-jet'); ?></div>
        </div>
    </div>

    <!-- Horizontal Scrollable Filters -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <span class="oj-filter-icon">📊</span>
            <?php _e('All Orders', 'orders-jet'); ?> (<?php echo $order_counts['all']; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="active">
            <span class="oj-filter-icon">📋</span>
            <?php _e('Active Orders', 'orders-jet'); ?> (<?php echo $order_counts['active']; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="kitchen">
            <span class="oj-filter-icon">🍳</span>
            <?php _e('Kitchen', 'orders-jet'); ?> (<?php echo $order_counts['kitchen']; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="tables">
            <span class="oj-filter-icon">🏢</span>
            <?php _e('Grouped Tables', 'orders-jet'); ?> (<?php echo $order_counts['tables']; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pickup">
            <span class="oj-filter-icon">🥡</span>
            <?php _e('Pickup', 'orders-jet'); ?> (<?php echo $order_counts['pickup']; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="completed">
            <span class="oj-filter-icon">✅</span>
            <?php _e('Completed', 'orders-jet'); ?> (<?php echo $order_counts['completed']; ?>)
        </button>
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
                    <th><?php _e('View', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody id="oj-orders-tbody">
                
                <!-- TABLE GROUPS -->
                <?php foreach ($table_groups as $table_group) : ?>
                    <tr class="oj-order-row oj-table-group-row" 
                        data-filter-type="table-group"
                        data-table="<?php echo esc_attr($table_group['table_number']); ?>"
                        data-status="<?php echo $table_group['all_ready'] ? 'ready' : 'processing'; ?>">
                        
                        <td class="check-column">
                            <input type="checkbox" class="oj-table-checkbox" 
                                   value="table-<?php echo esc_attr($table_group['table_number']); ?>" />
                        </td>
                        
                        <td class="oj-order-number">
                            <strong><?php _e('Table', 'orders-jet'); ?> <?php echo esc_html($table_group['table_number']); ?></strong>
                        </td>
                        
                        <td><?php _e('Table Guest', 'orders-jet'); ?></td>
                        
                        <td class="oj-type-badge">
                            🍽️ <?php _e('Dine In', 'orders-jet'); ?>
                        </td>
                        
                        <td>
                            <span class="oj-status <?php echo $table_group['all_ready'] ? 'ready' : 'processing'; ?>">
                                <?php if ($table_group['all_ready']) : ?>
                                    ✅ <?php _e('All Ready', 'orders-jet'); ?>
                                <?php else : ?>
                                    🍳 <?php _e('Cooking', 'orders-jet'); ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($table_group['has_cooking']) : ?>
                                <span class="oj-status-indicator">🍳</span>
                            <?php endif; ?>
                            <?php if ($table_group['has_ready']) : ?>
                                <span class="oj-status-indicator">✅</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <span class="oj-order-count-highlight"><?php echo $table_group['order_count']; ?></span>
                            <strong><?php echo wc_price($table_group['total_amount']); ?></strong>
                        </td>
                        
                        <td><?php echo esc_html($table_group['earliest_time']); ?></td>
                        
                        <td class="oj-actions">
                            <?php if ($table_group['all_ready']) : ?>
                                <button class="button oj-close-table-group" 
                                        data-table="<?php echo esc_attr($table_group['table_number']); ?>">
                                    <?php _e('Close Table', 'orders-jet'); ?>
                                </button>
                            <?php else : ?>
                                <button class="button oj-close-table-group" 
                                        data-table="<?php echo esc_attr($table_group['table_number']); ?>"
                                        disabled>
                                    <?php _e('Close Table', 'orders-jet'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <button class="button oj-expand-table" 
                                    data-table="<?php echo esc_attr($table_group['table_number']); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </td>
                    </tr>
                    
                    <!-- CHILD ORDERS (Hidden by default) -->
                    <?php foreach ($table_group['orders'] as $child_order) : ?>
                        <tr class="oj-order-row oj-child-order-row" 
                            data-filter-type="table"
                            data-table="<?php echo esc_attr($table_group['table_number']); ?>"
                            data-status="<?php echo esc_attr($child_order['status']); ?>"
                            data-order-id="<?php echo esc_attr($child_order['id']); ?>"
                            style="display: none;">
                            
                            <td class="check-column">
                                <input type="checkbox" name="order_ids[]" 
                                       value="<?php echo esc_attr($child_order['id']); ?>" />
                            </td>
                            
                            <td class="oj-order-number">
                                ↳ <strong>#<?php echo $child_order['id']; ?></strong>
                            </td>
                            
                            <td><?php echo esc_html($child_order['customer']); ?></td>
                            
                            <td class="oj-type-badge">
                                🍽️ <?php _e('Table', 'orders-jet'); ?> <?php echo esc_html($child_order['table_number']); ?>
                            </td>
                            
                            <td>
                                <?php if ($child_order['status'] === 'processing') : ?>
                                    <span class="oj-status processing">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                                <?php elseif ($child_order['status'] === 'pending') : ?>
                                    <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td><?php echo $child_order['formatted_total']; ?></td>
                            
                            <td><?php echo esc_html($child_order['date_modified']); ?></td>
                            
                            <td class="oj-actions">
                                <?php if ($child_order['status'] === 'processing') : ?>
                                    <button class="button oj-mark-ready" 
                                            data-order-id="<?php echo esc_attr($child_order['id']); ?>">
                                        <?php _e('Mark Ready', 'orders-jet'); ?>
                                    </button>
                                <?php elseif ($child_order['status'] === 'pending') : ?>
                                    <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <button class="button oj-view-order" 
                                        data-order-id="<?php echo esc_attr($child_order['id']); ?>">
                                    👁️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                
                <!-- PICKUP ORDERS -->
                <?php foreach ($pickup_orders as $order) : ?>
                    <tr class="oj-order-row oj-pickup-order" 
                        data-filter-type="pickup"
                        data-status="<?php echo esc_attr($order['status']); ?>"
                        data-order-id="<?php echo esc_attr($order['id']); ?>">
                        
                        <td class="check-column">
                            <input type="checkbox" name="order_ids[]" 
                                   value="<?php echo esc_attr($order['id']); ?>" />
                        </td>
                        
                        <td class="oj-order-number">
                            <strong>#<?php echo $order['id']; ?></strong>
                        </td>
                        
                        <td><?php echo esc_html($order['customer']); ?></td>
                        
                        <td class="oj-type-badge">
                            🥡 <?php _e('Pickup', 'orders-jet'); ?>
                        </td>
                        
                        <td>
                            <?php if ($order['status'] === 'processing') : ?>
                                <span class="oj-status processing">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                            <?php elseif ($order['status'] === 'pending') : ?>
                                <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                            <?php elseif ($order['status'] === 'completed') : ?>
                                <span class="oj-status completed">✅ <?php _e('Completed', 'orders-jet'); ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td><?php echo $order['formatted_total']; ?></td>
                        
                        <td><?php echo esc_html($order['date_modified']); ?></td>
                        
                        <td class="oj-actions">
                            <?php if ($order['status'] === 'processing') : ?>
                                <button class="button oj-mark-ready" 
                                        data-order-id="<?php echo esc_attr($order['id']); ?>">
                                    <?php _e('Mark Ready', 'orders-jet'); ?>
                                </button>
                            <?php elseif ($order['status'] === 'pending') : ?>
                                <button class="button oj-complete-order" 
                                        data-order-id="<?php echo esc_attr($order['id']); ?>">
                                    <?php _e('Complete', 'orders-jet'); ?>
                                </button>
                            <?php elseif ($order['status'] === 'completed') : ?>
                                <button class="button oj-invoice-print" 
                                        data-order-id="<?php echo esc_attr($order['id']); ?>">
                                    🖨️
                                </button>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <button class="button oj-view-order" 
                                    data-order-id="<?php echo esc_attr($order['id']); ?>">
                                👁️
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- COMPLETED ORDERS -->
                <?php foreach ($completed_orders as $order) : ?>
                    <tr class="oj-order-row oj-completed-order" 
                        data-filter-type="completed"
                        data-status="completed"
                        data-order-id="<?php echo esc_attr($order['id']); ?>">
                        
                        <td class="check-column">
                            <input type="checkbox" name="order_ids[]" 
                                   value="<?php echo esc_attr($order['id']); ?>" />
                        </td>
                        
                        <td class="oj-order-number">
                            <strong>#<?php echo $order['id']; ?></strong>
                        </td>
                        
                        <td><?php echo esc_html($order['customer']); ?></td>
                        
                        <td class="oj-type-badge">
                            <?php if ($order['type'] === 'table') : ?>
                                🍽️ <?php _e('Table', 'orders-jet'); ?> <?php echo esc_html($order['table_number']); ?>
                            <?php else : ?>
                                🥡 <?php _e('Pickup', 'orders-jet'); ?>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <span class="oj-status completed">✅ <?php _e('Completed', 'orders-jet'); ?></span>
                        </td>
                        
                        <td><?php echo $order['formatted_total']; ?></td>
                        
                        <td><?php echo esc_html($order['date_modified']); ?></td>
                        
                        <td class="oj-actions">
                            <button class="button oj-invoice-print" 
                                    data-order-id="<?php echo esc_attr($order['id']); ?>">
                                🖨️
                            </button>
                        </td>
                        
                        <td>
                            <button class="button oj-view-order" 
                                    data-order-id="<?php echo esc_attr($order['id']); ?>">
                                👁️
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- No Orders Message -->
                <?php if (empty($all_orders)) : ?>
                    <tr>
                        <td colspan="9" class="oj-text-center">
                            <?php _e('No orders found.', 'orders-jet'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
            </tbody>
        </table>
        
        <!-- Load More Button -->
        <?php if (count($all_orders) >= 20) : ?>
            <div class="oj-load-more-container">
                <button class="button oj-load-more" data-offset="20">
                    <?php _e('Load 20 More Orders', 'orders-jet'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include CSS -->
<link rel="stylesheet" href="<?php echo ORDERS_JET_PLUGIN_URL . 'assets/css/manager-dashboard.css?v=' . time(); ?>" />

<script>
jQuery(document).ready(function($) {
    
    // ==========================================================================
    // FILTER FUNCTIONALITY
    // ==========================================================================
    
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active filter button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Apply filter
        applyFilter(filter);
    });
    
    function applyFilter(filter) {
        $('.oj-order-row').each(function() {
            const $row = $(this);
            const filterType = $row.data('filter-type');
            const status = $row.data('status');
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'active':
                    show = ['processing', 'pending'].includes(status) || 
                           (filterType === 'table-group' && status !== 'completed');
                    break;
                case 'kitchen':
                    show = status === 'processing' || 
                           (filterType === 'table-group' && status === 'processing');
                    break;
                case 'tables':
                    show = filterType === 'table-group' || filterType === 'table';
                    break;
                case 'pickup':
                    show = filterType === 'pickup';
                    break;
                case 'completed':
                    show = status === 'completed' || filterType === 'completed';
                    break;
            }
            
            $row.toggle(show);
        });
    }
    
    // ==========================================================================
    // TABLE EXPAND/COLLAPSE
    // ==========================================================================
    
    $('.oj-expand-table').on('click', function() {
        const tableNumber = $(this).data('table');
        const $childRows = $(`.oj-child-order-row[data-table="${tableNumber}"]`);
        const $icon = $(this).find('.dashicons');
        
        if ($childRows.is(':visible')) {
            $childRows.hide();
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
            $childRows.show();
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }
    });
    
    // ==========================================================================
    // ORDER ACTIONS
    // ==========================================================================
    
    // Mark Ready
    $('.oj-mark-ready').on('click', function() {
        const orderId = $(this).data('order-id');
        markOrderReady(orderId);
    });
    
    // Complete Order
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        showPaymentMethodModal(orderId, 'complete');
    });
    
    // Close Table
    $('.oj-close-table-group').on('click', function() {
        const tableNumber = $(this).data('table');
        showPaymentMethodModal(tableNumber, 'close-table');
    });
    
    // View Order
    $('.oj-view-order').on('click', function() {
        const orderId = $(this).data('order-id');
        showOrderDetailsModal(orderId);
    });
    
    // Print Invoice
    $('.oj-invoice-print').on('click', function() {
        const orderId = $(this).data('order-id');
        printThermalInvoice(orderId);
    });
    
    // ==========================================================================
    // LAZY LOADING
    // ==========================================================================
    
    $('.oj-load-more').on('click', function() {
        const $button = $(this);
        const offset = $button.data('offset');
        
        $button.prop('disabled', true).text('<?php _e('Loading...', 'orders-jet'); ?>');
        
        loadMoreOrders(offset).then(function(success) {
            if (success) {
                $button.data('offset', offset + 20);
                $button.prop('disabled', false).text('<?php _e('Load 20 More Orders', 'orders-jet'); ?>');
            } else {
                $button.hide();
            }
        });
    });
    
    // ==========================================================================
    // HELPER FUNCTIONS (Placeholders - to be implemented)
    // ==========================================================================
    
    function markOrderReady(orderId) {
        // TODO: Implement mark ready functionality
        console.log('Mark ready:', orderId);
    }
    
    function showPaymentMethodModal(id, action) {
        // TODO: Implement payment method modal
        console.log('Payment modal:', id, action);
    }
    
    function showOrderDetailsModal(orderId) {
        // TODO: Implement order details modal
        console.log('Order details:', orderId);
    }
    
    function printThermalInvoice(orderId) {
        // TODO: Implement thermal invoice printing
        console.log('Print invoice:', orderId);
    }
    
    function loadMoreOrders(offset) {
        // TODO: Implement lazy loading
        console.log('Load more orders:', offset);
        return Promise.resolve(false);
    }
    
});
</script>