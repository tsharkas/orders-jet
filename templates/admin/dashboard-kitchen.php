<?php
/**
 * Orders Jet - Kitchen Dashboard Template (Simple Version)
 * Basic WordPress admin interface for kitchen staff
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get user information
$current_user = wp_get_current_user();
$today = date('Y-m-d');
$today_formatted = date('F j, Y');

// Get real data from WooCommerce orders
global $wpdb;

// Active orders for kitchen
$active_orders = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID, p.post_date, p.post_status, 
           pm_total.meta_value as order_total,
           pm_customer.meta_value as customer_name,
           pm_table.meta_value as table_number
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_billing_first_name'
    LEFT JOIN {$wpdb->postmeta} pm_table ON p.ID = pm_table.post_id AND pm_table.meta_key = '_table_number'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold')
    ORDER BY 
        CASE p.post_status 
            WHEN 'wc-processing' THEN 1
            WHEN 'wc-pending' THEN 2
            WHEN 'wc-on-hold' THEN 3
        END,
        p.post_date ASC
"), ARRAY_A);

// Get order items for each order
foreach ($active_orders as &$order) {
    $order_items = $wpdb->get_results($wpdb->prepare("
        SELECT oi.order_item_name, oim_qty.meta_value as quantity
        FROM {$wpdb->prefix}woocommerce_order_items oi
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
        WHERE oi.order_id = %d
        AND oi.order_item_type = 'line_item'
    ", $order['ID']), ARRAY_A);
    
    $order['items'] = $order_items;
}

// Kitchen stats
$pending_orders = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'shop_order'
    AND post_status = 'wc-pending'
");

$processing_orders = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'shop_order'
    AND post_status = 'wc-processing'
");

$completed_today = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'shop_order'
    AND post_status = 'wc-completed'
    AND DATE(post_date) = %s
", $today));

// Format currency
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-food" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('Kitchen Display', 'orders-jet'); ?>
    </h1>
    <p class="description"><?php echo sprintf(__('Welcome to the kitchen, %s!', 'orders-jet'), $current_user->display_name); ?></p>
    
    <hr class="wp-header-end">

    <!-- Kitchen Stats -->
    <div class="oj-kitchen-stats">
        <h2><?php echo sprintf(__('Kitchen Overview - %s', 'orders-jet'), $today_formatted); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box pending">
                <div class="oj-stat-number"><?php echo esc_html($pending_orders ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Pending Orders', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box processing">
                <div class="oj-stat-number"><?php echo esc_html($processing_orders ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('In Progress', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box completed">
                <div class="oj-stat-number"><?php echo esc_html($completed_today ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Completed Today', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Order Queue -->
    <div class="oj-order-queue">
        <h2><?php _e('Order Queue', 'orders-jet'); ?></h2>
        
        <?php if (!empty($active_orders)) : ?>
            <div class="oj-orders-grid">
                <?php foreach ($active_orders as $order) : ?>
                    <div class="oj-order-card status-<?php echo esc_attr(str_replace('wc-', '', $order['post_status'])); ?>">
                        <div class="oj-order-header">
                            <span class="oj-order-number">#<?php echo esc_html($order['ID']); ?></span>
                            <span class="oj-order-time"><?php echo esc_html(human_time_diff(strtotime($order['post_date']), current_time('timestamp')) . ' ' . __('ago', 'orders-jet')); ?></span>
                            <span class="oj-order-status status-<?php echo esc_attr(str_replace('wc-', '', $order['post_status'])); ?>">
                                <?php echo esc_html(ucfirst(str_replace('wc-', '', $order['post_status']))); ?>
                            </span>
                        </div>
                        
                        <?php if ($order['table_number']) : ?>
                            <div class="oj-order-table">
                                <span class="dashicons dashicons-tablet"></span>
                                <?php echo sprintf(__('Table %s', 'orders-jet'), esc_html($order['table_number'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order['customer_name']) : ?>
                            <div class="oj-order-customer">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php echo esc_html($order['customer_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="oj-order-items">
                            <?php if (!empty($order['items'])) : ?>
                                <?php foreach ($order['items'] as $item) : ?>
                                    <div class="oj-order-item">
                                        <span class="oj-item-qty"><?php echo esc_html($item['quantity'] ?: 1); ?>x</span>
                                        <span class="oj-item-name"><?php echo esc_html($item['order_item_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="oj-order-item">
                                    <?php _e('No items found', 'orders-jet'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="oj-order-total">
                            <strong><?php echo esc_html($currency_symbol . number_format($order['order_total'] ?: 0, 2)); ?></strong>
                        </div>
                        
                        <div class="oj-order-actions">
                            <?php if ($order['post_status'] === 'wc-pending') : ?>
                                <button class="button button-primary oj-start-cooking" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                    <?php _e('Start Cooking', 'orders-jet'); ?>
                                </button>
                            <?php elseif ($order['post_status'] === 'wc-processing') : ?>
                                <button class="button button-secondary oj-complete-order" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                    <?php _e('Mark Ready', 'orders-jet'); ?>
                                </button>
                            <?php else : ?>
                                <button class="button oj-resume-order" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                    <?php _e('Resume', 'orders-jet'); ?>
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo admin_url('post.php?post=' . $order['ID'] . '&action=edit'); ?>" class="button button-small">
                                <?php _e('View Details', 'orders-jet'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="oj-no-orders">
                <div class="oj-no-orders-icon">
                    <span class="dashicons dashicons-food" style="font-size: 48px; color: #ccc;"></span>
                </div>
                <h3><?php _e('No Active Orders', 'orders-jet'); ?></h3>
                <p><?php _e('All orders are completed! Great job!', 'orders-jet'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="oj-kitchen-actions">
        <h2><?php _e('Quick Actions', 'orders-jet'); ?></h2>
        
        <div class="oj-action-buttons">
            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-primary">
                <?php _e('View All Orders', 'orders-jet'); ?>
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary">
                <?php _e('Manage Menu Items', 'orders-jet'); ?>
            </a>
            <button type="button" class="button button-secondary" onclick="location.reload()">
                <?php _e('Refresh Orders', 'orders-jet'); ?>
            </button>
        </div>
    </div>

    <!-- Kitchen Info -->
    <div class="oj-kitchen-info">
        <h2><?php _e('Kitchen Status', 'orders-jet'); ?></h2>
        <div class="oj-info-box">
            <p><strong><?php _e('Kitchen Status:', 'orders-jet'); ?></strong> <?php _e('Ready', 'orders-jet'); ?></p>
            <p><strong><?php _e('Last Updated:', 'orders-jet'); ?></strong> <?php echo date('H:i:s'); ?></p>
            <p><strong><?php _e('Staff:', 'orders-jet'); ?></strong> <?php echo esc_html($current_user->display_name); ?></p>
        </div>
    </div>
</div>

<style>
.oj-kitchen-stats,
.oj-order-queue,
.oj-kitchen-actions,
.oj-kitchen-info {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.oj-stats-row {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.oj-stat-box {
    flex: 1;
    text-align: center;
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.oj-stat-box.pending {
    background: #fff5f5;
    border-color: #fed7d7;
}

.oj-stat-box.processing {
    background: #fffbf0;
    border-color: #fbd38d;
}

.oj-stat-box.completed {
    background: #f0fff4;
    border-color: #9ae6b4;
}

.oj-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.oj-stat-box.pending .oj-stat-number {
    color: #e53e3e;
}

.oj-stat-box.processing .oj-stat-number {
    color: #dd6b20;
}

.oj-stat-box.completed .oj-stat-number {
    color: #38a169;
}

.oj-stat-label {
    font-size: 14px;
    color: #666;
}

/* Order Cards */
.oj-orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.oj-order-card {
    background: white;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
}

.oj-order-card.status-pending {
    border-color: #e53e3e;
    background: #fff5f5;
}

.oj-order-card.status-processing {
    border-color: #dd6b20;
    background: #fffbf0;
}

.oj-order-card.status-on-hold {
    border-color: #a0aec0;
    background: #f7fafc;
}

.oj-order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e1e5e9;
}

.oj-order-number {
    font-size: 18px;
    font-weight: bold;
    color: #2d3748;
}

.oj-order-time {
    font-size: 12px;
    color: #718096;
}

.oj-order-status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.oj-order-status.status-pending {
    background: #fed7d7;
    color: #c53030;
}

.oj-order-status.status-processing {
    background: #fbd38d;
    color: #c05621;
}

.oj-order-status.status-on-hold {
    background: #e2e8f0;
    color: #4a5568;
}

.oj-order-table,
.oj-order-customer {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 14px;
    color: #4a5568;
}

.oj-order-table .dashicons,
.oj-order-customer .dashicons {
    font-size: 16px;
    color: #718096;
}

.oj-order-items {
    margin-bottom: 15px;
}

.oj-order-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}

.oj-order-item:last-child {
    border-bottom: none;
}

.oj-item-qty {
    font-weight: 600;
    color: #2d3748;
    margin-right: 10px;
}

.oj-item-name {
    flex: 1;
    color: #4a5568;
}

.oj-order-total {
    text-align: right;
    margin-bottom: 15px;
    font-size: 16px;
    color: #2d3748;
}

.oj-order-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.oj-order-actions .button {
    flex: 1;
    min-width: 120px;
}

.oj-no-orders {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.oj-no-orders-icon {
    margin-bottom: 15px;
}

.oj-no-orders h3 {
    margin-bottom: 10px;
    color: #4a5568;
}

.oj-action-buttons {
    margin-top: 15px;
}

.oj-action-buttons .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.oj-info-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.oj-info-box p {
    margin: 8px 0;
    font-size: 14px;
}

@media (max-width: 768px) {
    .oj-stats-row {
        flex-direction: column;
    }
    
    .oj-action-buttons .button {
        display: block;
        width: 100%;
        margin-right: 0;
    }
}
</style>