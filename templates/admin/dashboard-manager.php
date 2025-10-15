<?php
/**
 * Orders Jet - Manager Orders Management Template
 * Row-based view with bulk actions for efficient order management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions - Manager access
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Include the manager navigation
include ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-navigation.php';

// Get user information
$current_user = wp_get_current_user();
$today = OJ_Universal_Time_Manager::now_formatted('Y-m-d');
$today_formatted = OJ_Universal_Time_Manager::now_formatted('F j, Y');

// Get real data from WooCommerce orders - MANAGER FOCUSED
global $wpdb;

// Get actionable orders only (not completed/cancelled)
$actionable_orders = array();
if (function_exists('wc_get_orders')) {
    $wc_orders = wc_get_orders(array(
        'status' => array('processing', 'pending_payment'), // Only actionable statuses - clean cycle
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC' // Newest first for managers
    ));
    
    foreach ($wc_orders as $wc_order) {
        $table_number = $wc_order->get_meta('_oj_table_number');
        
        // Import WooFood delivery time to our custom fields (for existing orders)
        OJ_Delivery_Time_Manager::import_woofood_time($wc_order);
        
        // Get delivery/pickup time using our new manager
        $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($wc_order);
        $has_delivery_time = $delivery_info !== false;
        
        // Determine order type and category
        if (!empty($table_number)) {
            // Table/Dine-in order
            $order_category = 'table';
            $order_type = 'dinein';
            $order_type_label = __('Table', 'orders-jet') . ' ' . $table_number;
            $order_type_icon = '🍽️';
            $order_type_class = 'oj-order-type-dinein';
        } elseif ($has_delivery_time) {
            // Scheduled pickup order
            $order_category = 'individual';
            $order_type = 'pickup_timed';
            $order_type_icon = '🕒';
            $order_type_class = 'oj-order-type-pickup-timed';
            
            // Check if it's today or upcoming
            $today_date = date('Y-m-d');
            $delivery_date = date('Y-m-d', $delivery_info['timestamp']);
            
            if ($delivery_date === $today_date) {
                $order_type_label = __('Pickup', 'orders-jet') . ' ' . $delivery_info['time_only'];
            } else {
                $order_type_label = __('Pickup', 'orders-jet') . ' ' . $delivery_info['formatted'];
            }
        } else {
            // Regular pickup/individual order
            $order_category = 'individual';
            $order_type = 'pickup';
            $order_type_label = __('Pickup', 'orders-jet');
            $order_type_icon = '🥡';
            $order_type_class = 'oj-order-type-pickup';
        }
        
        // Get order items for summary
        $order_items = array();
        foreach ($wc_order->get_items() as $item) {
            $order_items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }
        
        $actionable_orders[] = array(
            'ID' => $wc_order->get_id(),
            'post_date' => OJ_Universal_Time_Manager::format(OJ_Universal_Time_Manager::get_order_created_timestamp($wc_order), 'Y-m-d H:i:s'),
            'post_status' => 'wc-' . $wc_order->get_status(),
            'order_total' => $wc_order->get_total(),
            'table_number' => $table_number,
            'customer_name' => $wc_order->get_billing_first_name() ?: 'Guest',
            'customer_phone' => $wc_order->get_billing_phone(),
            'session_id' => $wc_order->get_meta('_oj_session_id'),
            'delivery_date' => $has_delivery_time ? $delivery_info['datetime'] : '',
            'delivery_time' => $has_delivery_time ? $delivery_info['time_only'] : '',
            'items' => $order_items,
            'items_count' => count($order_items),
            // Enhanced categorization
            'order_category' => $order_category, // 'table' or 'individual'
            'order_type' => $order_type,
            'order_type_label' => $order_type_label,
            'order_type_icon' => $order_type_icon,
            'order_type_class' => $order_type_class
        );
    }
}

// Separate orders by category for statistics
$table_orders = array_filter($actionable_orders, function($order) {
    return $order['order_category'] === 'table';
});

$individual_orders = array_filter($actionable_orders, function($order) {
    return $order['order_category'] === 'individual';
});

// Group table orders by table number for bulk closing
$tables_with_orders = array();
foreach ($table_orders as $order) {
    $table_num = $order['table_number'];
    if (!isset($tables_with_orders[$table_num])) {
        $tables_with_orders[$table_num] = array(
            'table_number' => $table_num,
            'orders' => array(),
            'total_amount' => 0,
            'order_count' => 0
        );
    }
    $tables_with_orders[$table_num]['orders'][] = $order;
    $tables_with_orders[$table_num]['total_amount'] += $order['order_total'];
    $tables_with_orders[$table_num]['order_count']++;
}

// Calculate statistics
$ready_orders = array_filter($actionable_orders, function($order) {
    return $order['post_status'] === 'wc-pending_payment';
});

$processing_orders = array_filter($actionable_orders, function($order) {
    return $order['post_status'] === 'wc-processing';
});

// Format currency
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="wrap oj-manager-orders">
    
    <!-- Orders Management Header -->
    <div class="oj-orders-header">
        <div class="oj-header-left">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-clipboard" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
                <?php _e('Orders Management', 'orders-jet'); ?>
            </h1>
            <p class="description"><?php echo sprintf(__('Manage all restaurant orders - %s', 'orders-jet'), $today_formatted); ?></p>
        </div>
        <div class="oj-header-right">
            <button type="button" class="oj-refresh-btn" onclick="location.reload();">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Orders', 'orders-jet'); ?>
    </button>
    </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="oj-manager-stats-row">
        <div class="oj-stat-card oj-stat-total">
            <div class="stat-number"><?php echo count($actionable_orders); ?></div>
            <div class="stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card oj-stat-tables">
            <div class="stat-number"><?php echo count($tables_with_orders); ?></div>
            <div class="stat-label"><?php _e('Occupied Tables', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card oj-stat-pickup">
            <div class="stat-number"><?php echo count($individual_orders); ?></div>
            <div class="stat-label"><?php _e('Pickup Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card oj-stat-ready">
            <div class="stat-number"><?php echo count($ready_orders); ?></div>
            <div class="stat-label"><?php _e('Ready Orders', 'orders-jet'); ?></div>
            </div>
        </div>
        
    <!-- Filter Tabs (Keep existing scrolling filters) -->
    <div class="oj-order-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <span class="oj-filter-icon">📋</span>
            <span class="oj-filter-label"><?php _e('All Orders', 'orders-jet'); ?></span>
            <span class="oj-filter-count">(<?php echo count($actionable_orders); ?>)</span>
        </button>
        <button class="oj-filter-btn" data-filter="tables">
            <span class="oj-filter-icon">🍽️</span>
            <span class="oj-filter-label"><?php _e('Table Orders', 'orders-jet'); ?></span>
            <span class="oj-filter-count">(<?php echo count($table_orders); ?>)</span>
        </button>
        <button class="oj-filter-btn" data-filter="individual">
            <span class="oj-filter-icon">🥡</span>
            <span class="oj-filter-label"><?php _e('Pickup Orders', 'orders-jet'); ?></span>
            <span class="oj-filter-count">(<?php echo count($individual_orders); ?>)</span>
        </button>
        <button class="oj-filter-btn" data-filter="ready">
            <span class="oj-filter-icon">✅</span>
            <span class="oj-filter-label"><?php _e('Ready Orders', 'orders-jet'); ?></span>
            <span class="oj-filter-count">(<?php echo count($ready_orders); ?>)</span>
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            <span class="oj-filter-icon">👨‍🍳</span>
            <span class="oj-filter-label"><?php _e('In Kitchen', 'orders-jet'); ?></span>
            <span class="oj-filter-count">(<?php echo count($processing_orders); ?>)</span>
        </button>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="oj-bulk-actions-bar" style="display: none;">
        <div class="oj-bulk-actions-left">
            <span class="oj-selected-count">0 <?php _e('orders selected', 'orders-jet'); ?></span>
        </div>
        <div class="oj-bulk-actions-right">
            <select class="oj-bulk-action-select">
                <option value=""><?php _e('Bulk Actions', 'orders-jet'); ?></option>
                <option value="complete-individual"><?php _e('Complete Individual Orders', 'orders-jet'); ?></option>
                <option value="close-tables"><?php _e('Close Selected Tables', 'orders-jet'); ?></option>
            </select>
            <button class="oj-btn oj-btn-primary oj-apply-bulk-action">
                <?php _e('Apply', 'orders-jet'); ?>
        </button>
            <button class="oj-btn oj-btn-secondary oj-clear-selection">
                <?php _e('Clear Selection', 'orders-jet'); ?>
        </button>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="oj-manager-orders-table">
        <div class="oj-table-header">
            <div class="oj-col-select">
                <input type="checkbox" class="oj-select-all" title="<?php _e('Select All', 'orders-jet'); ?>">
            </div>
            <div class="oj-col-order"><?php _e('Order', 'orders-jet'); ?></div>
            <div class="oj-col-type"><?php _e('Type', 'orders-jet'); ?></div>
            <div class="oj-col-customer"><?php _e('Customer', 'orders-jet'); ?></div>
            <div class="oj-col-items"><?php _e('Items', 'orders-jet'); ?></div>
            <div class="oj-col-total"><?php _e('Total', 'orders-jet'); ?></div>
            <div class="oj-col-status"><?php _e('Status', 'orders-jet'); ?></div>
            <div class="oj-col-time"><?php _e('Time', 'orders-jet'); ?></div>
            <div class="oj-col-actions"><?php _e('Actions', 'orders-jet'); ?></div>
        </div>

        <div class="oj-table-body">
            <?php if (!empty($actionable_orders)) : ?>
                <?php foreach ($actionable_orders as $order) : ?>
                    <div class="oj-table-row" 
                         data-order-id="<?php echo esc_attr($order['ID']); ?>"
                         data-order-category="<?php echo esc_attr($order['order_category']); ?>"
                         data-order-type="<?php echo esc_attr($order['order_type']); ?>"
                         data-order-status="<?php echo esc_attr(str_replace('wc-', '', $order['post_status'])); ?>"
                         data-table-number="<?php echo esc_attr($order['table_number'] ?? ''); ?>">
                        
                        <!-- Selection Checkbox -->
                        <div class="oj-col-select">
                            <input type="checkbox" class="oj-order-select" 
                                   value="<?php echo esc_attr($order['ID']); ?>"
                                   data-category="<?php echo esc_attr($order['order_category']); ?>"
                                   data-table="<?php echo esc_attr($order['table_number'] ?? ''); ?>">
            </div>

                        <!-- Order Number & ID -->
                        <div class="oj-col-order">
                            <div class="oj-order-number">#<?php echo esc_html($order['ID']); ?></div>
                                    <?php if (!empty($order['table_number'])) : ?>
                                <div class="oj-table-badge">Table <?php echo esc_html($order['table_number']); ?></div>
                                    <?php endif; ?>
                        </div>

                        <!-- Order Type -->
                        <div class="oj-col-type">
                            <span class="oj-type-badge <?php echo esc_attr($order['order_type_class']); ?>">
                                <span class="oj-type-icon"><?php echo esc_html($order['order_type_icon']); ?></span>
                                <span class="oj-type-label"><?php echo esc_html($order['order_type_label']); ?></span>
                                    </span>
                        </div>

                        <!-- Customer -->
                        <div class="oj-col-customer">
                                <div class="oj-customer-name"><?php echo esc_html($order['customer_name']); ?></div>
                            <?php if (!empty($order['customer_phone']) && $order['customer_phone'] !== 'N/A') : ?>
                                <div class="oj-customer-phone"><?php echo esc_html($order['customer_phone']); ?></div>
                                <?php endif; ?>
                        </div>

                        <!-- Items Summary -->
                        <div class="oj-col-items">
                            <div class="oj-items-summary">
                                <?php 
                                $item_count = $order['items_count'];
                                echo $item_count . ' ' . _n('item', 'items', $item_count, 'orders-jet');
                                ?>
                                                </div>
                            <div class="oj-items-preview">
                                <?php 
                                $preview_items = array_slice($order['items'], 0, 2);
                                foreach ($preview_items as $item) :
                                ?>
                                    <span class="oj-item-preview"><?php echo esc_html($item['quantity']); ?>× <?php echo esc_html($item['name']); ?></span>
                                                    <?php endforeach; ?>
                                <?php if ($item_count > 2) : ?>
                                    <span class="oj-more-items">+<?php echo ($item_count - 2); ?> more</span>
                                            <?php endif; ?>
                                                </div>
                                </div>
                                
                        <!-- Total -->
                        <div class="oj-col-total">
                            <div class="oj-order-total"><?php echo wc_price($order['order_total']); ?></div>
                                    </div>

                        <!-- Status -->
                        <div class="oj-col-status">
                            <?php 
                            $status_class = str_replace('wc-', '', $order['post_status']);
                            $status_labels = array(
                                'processing' => __('Cooking', 'orders-jet'),
                                'pending_payment' => __('Ready', 'orders-jet')
                            );
                            $status_label = $status_labels[$status_class] ?? ucfirst(str_replace('-', ' ', $status_class));
                            ?>
                            <span class="oj-status-badge oj-status-<?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                                    </div>

                        <!-- Time -->
                        <div class="oj-col-time">
                            <div class="oj-order-time">
                                <?php echo esc_html(OJ_Universal_Time_Manager::format(strtotime($order['post_date']), 'g:i A')); ?>
                                    </div>
                            <div class="oj-order-date">
                                <?php echo esc_html(OJ_Universal_Time_Manager::format(strtotime($order['post_date']), 'M j')); ?>
                                    </div>
                        </div>

                        <!-- Actions -->
                        <div class="oj-col-actions">
                            <div class="oj-action-buttons">
                                <?php if ($order['order_category'] === 'table') : ?>
                                    <!-- Table Order Actions -->
                                    <?php if (in_array($order['post_status'], ['wc-pending_payment'])) : ?>
                                        <button class="oj-btn oj-btn-primary oj-close-table" 
                                                data-table="<?php echo esc_attr($order['table_number']); ?>"
                                                title="<?php _e('Close Table & Generate Invoice', 'orders-jet'); ?>">
                                        <span class="dashicons dashicons-money-alt"></span>
                                            <?php _e('Close Table', 'orders-jet'); ?>
                                    </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <!-- Individual Order Actions -->
                                    <?php if (in_array($order['post_status'], ['wc-pending_payment'])) : ?>
                                        <button class="oj-btn oj-btn-success oj-complete-order" 
                                                data-order-id="<?php echo esc_attr($order['ID']); ?>"
                                                title="<?php _e('Mark as Completed', 'orders-jet'); ?>">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php _e('Complete', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    
                                <!-- View Details (Always available) -->
                                <button class="oj-btn oj-btn-secondary oj-view-details" 
                                        data-order-id="<?php echo esc_attr($order['ID']); ?>"
                                        title="<?php _e('View Order Details', 'orders-jet'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Details', 'orders-jet'); ?>
                                        </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
        <?php else : ?>
            <div class="oj-no-orders">
                    <div class="oj-no-orders-icon">
                        <span class="dashicons dashicons-clipboard" style="font-size: 64px; color: #ddd;"></span>
                    </div>
                    <h3><?php _e('No Active Orders', 'orders-jet'); ?></h3>
                    <p><?php _e('All orders have been completed or there are no new orders yet.', 'orders-jet'); ?></p>
            </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<!-- Payment Method Modal -->
<div id="oj-payment-modal" class="oj-modal" style="display: none;">
    <div class="oj-modal-content">
        <div class="oj-modal-header">
            <h3><?php _e('Select Payment Method', 'orders-jet'); ?></h3>
            <button class="oj-modal-close">&times;</button>
        </div>
        <div class="oj-modal-body">
            <p id="oj-payment-modal-text"><?php _e('Please select the payment method for this table:', 'orders-jet'); ?></p>
            <div class="oj-payment-methods">
                <label class="oj-payment-option">
                    <input type="radio" name="payment_method" value="cash" checked>
                    <span class="oj-payment-label">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php _e('Cash', 'orders-jet'); ?>
                    </span>
                </label>
                <label class="oj-payment-option">
                    <input type="radio" name="payment_method" value="card">
                    <span class="oj-payment-label">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php _e('Card', 'orders-jet'); ?>
                    </span>
                </label>
                <label class="oj-payment-option">
                    <input type="radio" name="payment_method" value="fawaterak">
                    <span class="oj-payment-label">
                        <span class="dashicons dashicons-smartphone"></span>
                        <?php _e('Fawaterak', 'orders-jet'); ?>
                    </span>
                </label>
            </div>
        </div>
        <div class="oj-modal-footer">
            <button class="oj-btn oj-btn-primary" id="oj-confirm-payment">
                <?php _e('Close Table & Generate Invoice', 'orders-jet'); ?>
            </button>
            <button class="oj-btn oj-btn-secondary oj-modal-close">
                <?php _e('Cancel', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Manager Orders - Modern Row-Based Design */
.oj-manager-orders {
    background: #f8f9fa;
    padding: 0;
}

/* Header */
.oj-orders-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    gap: 20px;
}

.oj-header-left {
    flex: 1;
}

.oj-header-right {
    flex-shrink: 0;
}

.oj-refresh-btn {
    background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.oj-refresh-btn:hover {
    background: linear-gradient(135deg, #005a87 0%, #004666 100%);
    transform: translateY(-2px);
}

/* Statistics Cards */
.oj-manager-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.oj-stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.2s ease;
}

.oj-stat-card:hover {
    transform: translateY(-2px);
}

.oj-stat-card.oj-stat-total {
    border-left: 4px solid #667eea;
}

.oj-stat-card.oj-stat-tables {
    border-left: 4px solid #48bb78;
}

.oj-stat-card.oj-stat-pickup {
    border-left: 4px solid #ed8936;
}

.oj-stat-card.oj-stat-ready {
    border-left: 4px solid #38b2ac;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Tabs (Keep existing styles but enhance) */
.oj-order-filters {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    position: sticky;
    top: 32px;
    z-index: 100;
}

.oj-order-filters::-webkit-scrollbar {
    display: none;
}

.oj-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    background: white;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    color: #333;
    text-decoration: none;
    min-width: auto;
    box-shadow: none;
}

.oj-filter-btn:hover {
    border-color: #007cba;
    background: #f8f9fa;
    color: #007cba;
    transform: translateY(-1px);
}

.oj-filter-btn.active {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.oj-filter-btn.active:hover {
    background: #005a87;
    color: white;
}

.oj-filter-icon {
    font-size: 16px;
}

.oj-filter-label {
    font-weight: 600;
}

.oj-filter-count {
    background: rgba(255,255,255,0.2);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 4px;
}

.oj-filter-btn.active .oj-filter-count {
    background: rgba(255,255,255,0.3);
}

/* Bulk Actions Bar */
.oj-bulk-actions-bar {
    background: #667eea;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.oj-bulk-actions-left {
    font-weight: 500;
}

.oj-bulk-actions-right {
    display: flex;
    gap: 12px;
    align-items: center;
}

.oj-bulk-action-select {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    background: white;
    color: #333;
}

/* Modern Table Design */
.oj-manager-orders-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.oj-table-header {
    display: grid;
    grid-template-columns: 50px 120px 140px 140px 200px 100px 120px 100px 180px;
    gap: 16px;
    padding: 20px 24px;
    background: #f7fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 600;
    font-size: 13px;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    align-items: center;
}

.oj-table-row {
    display: grid;
    grid-template-columns: 50px 120px 140px 140px 200px 100px 120px 100px 180px;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    align-items: center;
}

.oj-table-row:hover {
    background: #f8fafc;
}

.oj-table-row:last-child {
    border-bottom: none;
}

.oj-table-row.oj-row-selected {
    background: #ebf8ff;
    border-left: 4px solid #667eea;
}

/* Column Styles */
.oj-col-select {
    display: flex;
    justify-content: center;
}

.oj-col-order {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.oj-order-number {
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.oj-table-badge {
    background: #667eea;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    width: fit-content;
}

.oj-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.oj-type-badge.oj-order-type-dinein {
    background: #d4edda;
    color: #155724;
}

.oj-type-badge.oj-order-type-pickup {
    background: #f3e5f5;
    color: #6a1b9a;
}

.oj-type-badge.oj-order-type-pickup-timed {
    background: #fff3cd;
    color: #856404;
}

.oj-customer-name {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 2px;
}

.oj-customer-phone {
    font-size: 12px;
    color: #718096;
}

.oj-items-summary {
    font-weight: 500;
    color: #2d3748;
    margin-bottom: 4px;
}

.oj-items-preview {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.oj-item-preview {
    font-size: 12px;
    color: #718096;
}

.oj-more-items {
    font-size: 11px;
    color: #a0aec0;
    font-style: italic;
}

.oj-order-total {
    font-weight: 600;
    font-size: 16px;
    color: #2d3748;
}

.oj-status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.oj-status-badge.oj-status-processing {
    background: #bee3f8;
    color: #2c5282;
}

.oj-status-badge.oj-status-pending_payment {
    background: #c6f6d5;
    color: #22543d;
}

.oj-status-badge.oj-status-on-hold {
    background: #c6f6d5;
    color: #22543d;
}

.oj-status-badge.oj-status-on-hold {
    background: #fed7d7;
    color: #c53030;
}

.oj-col-time {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.oj-order-time {
    font-weight: 500;
    color: #2d3748;
}

.oj-order-date {
    font-size: 12px;
    color: #718096;
}

/* Action Buttons */
.oj-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.oj-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.oj-btn-primary {
    background: #667eea;
    color: white;
}

.oj-btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-1px);
}

.oj-btn-success {
    background: #48bb78;
    color: white;
}

.oj-btn-success:hover {
    background: #38a169;
    transform: translateY(-1px);
}

.oj-btn-secondary {
    background: #edf2f7;
    color: #4a5568;
}

.oj-btn-secondary:hover {
    background: #e2e8f0;
}

.oj-btn .dashicons {
        font-size: 14px;
    }
    
/* No Orders State */
.oj-no-orders {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.oj-no-orders h3 {
    margin: 20px 0 10px 0;
    color: #4a5568;
}

/* Payment Modal */
.oj-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.oj-modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.oj-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.oj-modal-header h3 {
    margin: 0;
    color: #2d3748;
}

.oj-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #a0aec0;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.oj-modal-close:hover {
    color: #4a5568;
}

.oj-modal-body {
    padding: 24px;
}

.oj-payment-methods {
    display: grid;
    gap: 12px;
    margin-top: 20px;
}

.oj-payment-option {
    display: flex;
    align-items: center;
    padding: 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.oj-payment-option:hover {
    border-color: #667eea;
    background: #f7fafc;
}

.oj-payment-option input[type="radio"] {
    margin-right: 12px;
}

.oj-payment-option input[type="radio"]:checked + .oj-payment-label {
    color: #667eea;
    font-weight: 600;
}

.oj-payment-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.oj-modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Responsive Design */
@media (max-width: 1400px) {
    .oj-table-header,
    .oj-table-row {
        grid-template-columns: 50px 100px 120px 120px 180px 90px 100px 90px 160px;
        gap: 12px;
        padding: 16px 20px;
    }
}

@media (max-width: 1200px) {
    .oj-table-header,
    .oj-table-row {
        grid-template-columns: 50px 90px 100px 100px 150px 80px 90px 80px 140px;
        gap: 10px;
        padding: 14px 16px;
    }
}

@media (max-width: 768px) {
    .oj-manager-orders-table {
        overflow-x: auto;
    }
    
    .oj-table-header,
    .oj-table-row {
        min-width: 900px;
    }
    
    .oj-orders-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .oj-header-right {
        align-self: stretch;
    }
    
    .oj-refresh-btn {
        width: 100%;
        justify-content: center;
    }
    
    .oj-manager-stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .oj-order-filters {
        padding: 10px;
        gap: 8px;
    }
    
    .oj-filter-btn {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    .oj-bulk-actions-bar {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
    
    .oj-bulk-actions-right {
        flex-wrap: wrap;
        justify-content: center;
}
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    let selectedOrders = [];
    let currentPaymentCallback = null;
    
    // Initialize
    updateFilterCounts();
    
    // Select All functionality
    $('.oj-select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.oj-order-select:visible').prop('checked', isChecked).trigger('change');
    });
    
    // Individual order selection
    $('.oj-order-select').on('change', function() {
        updateSelection();
    });
    
    // Filter functionality
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        filterOrders(filter);
        updateFilterCounts();
    });
    
    // Close Table Action
    $('.oj-close-table').on('click', function() {
        const tableNumber = $(this).data('table');
        const row = $(this).closest('.oj-table-row');
        
        showPaymentMethodModal(tableNumber, function(paymentMethod) {
            closeTable(tableNumber, paymentMethod, row);
        });
    });
    
    // Complete Individual Order
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        const row = $(this).closest('.oj-table-row');
        
        completeIndividualOrder(orderId, row);
    });
    
    // View Order Details
    $('.oj-view-details').on('click', function() {
        const orderId = $(this).data('order-id');
        // TODO: Implement order details modal
        alert('Order details for #' + orderId + ' - Coming soon!');
    });
    
    // Bulk Actions
    $('.oj-apply-bulk-action').on('click', function() {
        const action = $('.oj-bulk-action-select').val();
        if (!action || selectedOrders.length === 0) {
            alert('Please select an action and at least one order.');
            return;
        }
        
        if (action === 'complete-individual') {
            bulkCompleteIndividualOrders();
        } else if (action === 'close-tables') {
            bulkCloseTables();
        }
    });
    
    // Clear Selection
    $('.oj-clear-selection').on('click', function() {
        $('.oj-order-select').prop('checked', false);
        $('.oj-select-all').prop('checked', false);
        updateSelection();
    });
    
    // Modal functionality
    $('.oj-modal-close').on('click', function() {
        $('#oj-payment-modal').hide();
    });
    
    $('#oj-confirm-payment').on('click', function() {
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        $('#oj-payment-modal').hide();
        if (currentPaymentCallback) {
            currentPaymentCallback(paymentMethod);
        }
    });
    
    function updateSelection() {
        selectedOrders = [];
        $('.oj-order-select:checked').each(function() {
            selectedOrders.push({
                id: $(this).val(),
                category: $(this).data('category'),
                table: $(this).data('table')
            });
        });
        
        $('.oj-selected-count').text(selectedOrders.length + ' orders selected');
        
        if (selectedOrders.length > 0) {
            $('.oj-bulk-actions-bar').show();
            $('.oj-table-row').removeClass('oj-row-selected');
            $('.oj-order-select:checked').closest('.oj-table-row').addClass('oj-row-selected');
        } else {
            $('.oj-bulk-actions-bar').hide();
            $('.oj-table-row').removeClass('oj-row-selected');
        }
        
        // Update select all checkbox
        const totalVisible = $('.oj-order-select:visible').length;
        const totalSelected = $('.oj-order-select:visible:checked').length;
        $('.oj-select-all').prop('checked', totalVisible > 0 && totalSelected === totalVisible);
    }
    
    function filterOrders(filter) {
        $('.oj-table-row').each(function() {
            const row = $(this);
            const category = row.data('order-category');
            const status = row.data('order-status');
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'tables':
                    show = category === 'table';
                    break;
                case 'individual':
                    show = category === 'individual';
                    break;
                case 'ready':
                    show = status === 'pending_payment';
                    break;
                case 'processing':
                    show = status === 'processing';
                    break;
            }
            
            if (show) {
                row.show();
            } else {
                row.hide();
                row.find('.oj-order-select').prop('checked', false);
            }
        });
        
        updateSelection();
    }
    
    function updateFilterCounts() {
        $('.oj-filter-btn').each(function() {
            const btn = $(this);
            const filter = btn.data('filter');
            let count = 0;
            
            $('.oj-table-row').each(function() {
                const row = $(this);
                const category = row.data('order-category');
                const status = row.data('order-status');
                let matches = false;
                
                switch(filter) {
                    case 'all':
                        matches = true;
                        break;
                    case 'tables':
                        matches = category === 'table';
                        break;
                    case 'individual':
                        matches = category === 'individual';
                        break;
                    case 'ready':
                        matches = status === 'pending_payment';
                        break;
                    case 'processing':
                        matches = status === 'processing';
                        break;
                }
                
                if (matches) count++;
            });
            
            btn.find('.oj-filter-count').text('(' + count + ')');
        });
    }
    
    function showPaymentMethodModal(tableNumber, callback) {
        $('#oj-payment-modal-text').text('Please select the payment method for Table ' + tableNumber + ':');
        currentPaymentCallback = callback;
        $('#oj-payment-modal').show();
    }
    
    function closeTable(tableNumber, paymentMethod, row) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                action: 'oj_close_table',
                table_number: tableNumber,
                payment_method: paymentMethod,
                nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
            },
            beforeSend: function() {
                row.find('.oj-close-table').prop('disabled', true).text('Closing...');
                },
                success: function(response) {
                    if (response.success) {
                    // Remove all rows for this table
                    $('.oj-table-row[data-table-number="' + tableNumber + '"]').fadeOut(300, function() {
                        $(this).remove();
                        updateSelection();
                        updateFilterCounts();
                    });
                    
                    showNotification('success', response.data.message);
                    
                    // Open invoice in new tab
                    if (response.data.invoice_url) {
                        window.open(response.data.invoice_url, '_blank');
                    }
                    } else {
                    showNotification('error', response.data.message);
                    row.find('.oj-close-table').prop('disabled', false).text('Close Table');
                    }
                },
                error: function() {
                showNotification('error', 'Failed to close table. Please try again.');
                row.find('.oj-close-table').prop('disabled', false).text('Close Table');
                }
            });
        }
    
    function completeIndividualOrder(orderId, row) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_complete_individual_order',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
            },
            beforeSend: function() {
                row.find('.oj-complete-order').prop('disabled', true).text('Completing...');
            },
            success: function(response) {
                if (response.success) {
                    // Update row status and hide action buttons
                    row.find('.oj-status-badge')
                       .removeClass()
                       .addClass('oj-status-badge oj-status-completed')
                       .text('Completed');
                    
                    row.find('.oj-action-buttons').fadeOut();
                    
                    showNotification('success', response.data.message);
                    updateFilterCounts();
                } else {
                    showNotification('error', response.data.message);
                    row.find('.oj-complete-order').prop('disabled', false).text('Complete');
                }
            },
            error: function() {
                showNotification('error', 'Failed to complete order. Please try again.');
                row.find('.oj-complete-order').prop('disabled', false).text('Complete');
            }
        });
    }
    
    function bulkCompleteIndividualOrders() {
        const individualOrders = selectedOrders.filter(order => order.category === 'individual');
        if (individualOrders.length === 0) {
            alert('No individual orders selected.');
            return;
        }
        
        if (!confirm('Complete ' + individualOrders.length + ' individual orders?')) {
            return;
        }
        
        individualOrders.forEach(order => {
            const row = $('.oj-table-row[data-order-id="' + order.id + '"]');
            completeIndividualOrder(order.id, row);
        });
    }
    
    function bulkCloseTables() {
        const tableOrders = selectedOrders.filter(order => order.category === 'table');
        const uniqueTables = [...new Set(tableOrders.map(order => order.table))];
        
        if (uniqueTables.length === 0) {
            alert('No table orders selected.');
            return;
        }
        
        if (!confirm('Close ' + uniqueTables.length + ' tables?')) {
            return;
        }
        
        // For bulk table closing, use cash as default payment method
        // In a real implementation, you might want to show a payment method selection for each table
        uniqueTables.forEach(tableNumber => {
            const row = $('.oj-table-row[data-table-number="' + tableNumber + '"]').first();
            closeTable(tableNumber, 'cash', row);
        });
    }
    
    function showNotification(type, message) {
        const notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.oj-manager-orders').prepend(notification);
        
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }
});
</script>