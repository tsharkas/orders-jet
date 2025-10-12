<?php
/**
 * Orders Jet - Manager Dashboard Template (Simple Version)
 * Basic WordPress admin interface for managers
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get user information
$current_user = wp_get_current_user();
$today = date('Y-m-d');
$today_formatted = date('F j, Y');

// Get real data from WooCommerce and tables
global $wpdb;

// Today's revenue from completed orders
$today_revenue = $wpdb->get_var($wpdb->prepare("
    SELECT SUM(meta_value) 
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
    WHERE pm.meta_key = '_order_total'
    AND p.post_type = 'shop_order'
    AND p.post_status IN ('wc-completed', 'wc-processing')
    AND DATE(p.post_date) = %s
", $today));

// Today's orders count
$today_orders = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'shop_order'
    AND post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
    AND DATE(post_date) = %s
", $today));

// Active tables (from existing table management)
$active_tables = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'oj_table'
    AND post_status = 'publish'
");

// Total tables
$total_tables = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'oj_table'
    AND post_status = 'publish'
");

// Format revenue
$currency_symbol = get_woocommerce_currency_symbol();
$formatted_revenue = $currency_symbol . number_format($today_revenue ?: 0, 2);

// Get recent orders
$recent_orders = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID, p.post_date, p.post_status, pm_total.meta_value as order_total
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold')
    ORDER BY p.post_date DESC
    LIMIT 5
"), ARRAY_A);

// Get table status
$table_status = $wpdb->get_results("
    SELECT p.ID, p.post_title, pm_status.meta_value as table_status
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_table_status'
    WHERE p.post_type = 'oj_table'
    AND p.post_status = 'publish'
    ORDER BY p.post_title ASC
    LIMIT 10
", ARRAY_A);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-businessman" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('Manager Dashboard', 'orders-jet'); ?>
    </h1>
    <p class="description"><?php echo sprintf(__('Welcome back, %s!', 'orders-jet'), $current_user->display_name); ?></p>
    
    <hr class="wp-header-end">

    <!-- Real Stats -->
    <div class="oj-dashboard-stats">
        <h2><?php echo sprintf(__('Today\'s Overview - %s', 'orders-jet'), $today_formatted); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box">
                <div class="oj-stat-number"><?php echo esc_html($today_orders ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Orders Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number"><?php echo esc_html($formatted_revenue); ?></div>
                <div class="oj-stat-label"><?php _e('Revenue Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number"><?php echo esc_html($active_tables ?: 0); ?>/<?php echo esc_html($total_tables ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Tables Available', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="oj-dashboard-actions">
        <h2><?php _e('Quick Actions', 'orders-jet'); ?></h2>
        
        <div class="oj-action-buttons">
            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-primary">
                <?php _e('View Orders', 'orders-jet'); ?>
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=oj_table'); ?>" class="button button-secondary">
                <?php _e('Manage Tables', 'orders-jet'); ?>
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary">
                <?php _e('Manage Menu', 'orders-jet'); ?>
            </a>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="oj-dashboard-orders">
        <h2><?php _e('Recent Orders', 'orders-jet'); ?></h2>
        
        <?php if (!empty($recent_orders)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order #', 'orders-jet'); ?></th>
                        <th><?php _e('Date', 'orders-jet'); ?></th>
                        <th><?php _e('Status', 'orders-jet'); ?></th>
                        <th><?php _e('Total', 'orders-jet'); ?></th>
                        <th><?php _e('Actions', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order) : ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($order['ID']); ?></strong></td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($order['post_date']))); ?></td>
                            <td>
                                <span class="oj-status-badge status-<?php echo esc_attr(str_replace('wc-', '', $order['post_status'])); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('wc-', '', $order['post_status']))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($currency_symbol . number_format($order['order_total'] ?: 0, 2)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $order['ID'] . '&action=edit'); ?>" class="button button-small">
                                    <?php _e('View', 'orders-jet'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="oj-no-orders">
                <p><?php _e('No recent orders found.', 'orders-jet'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Table Status -->
    <div class="oj-dashboard-tables">
        <h2><?php _e('Table Status', 'orders-jet'); ?></h2>
        
        <?php if (!empty($table_status)) : ?>
            <div class="oj-tables-grid">
                <?php foreach ($table_status as $table) : ?>
                    <div class="oj-table-card status-<?php echo esc_attr($table['table_status'] ?: 'available'); ?>">
                        <div class="oj-table-number"><?php echo esc_html($table['post_title']); ?></div>
                        <div class="oj-table-status">
                            <span class="oj-status-indicator"></span>
                            <?php echo esc_html(ucfirst($table['table_status'] ?: 'available')); ?>
                        </div>
                        <div class="oj-table-actions">
                            <a href="<?php echo admin_url('post.php?post=' . $table['ID'] . '&action=edit'); ?>" class="button button-small">
                                <?php _e('Manage', 'orders-jet'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="oj-no-tables">
                <p><?php _e('No tables configured yet.', 'orders-jet'); ?></p>
                <a href="<?php echo admin_url('post-new.php?post_type=oj_table'); ?>" class="button button-primary">
                    <?php _e('Add First Table', 'orders-jet'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Status -->
    <div class="oj-dashboard-info">
        <h2><?php _e('System Status', 'orders-jet'); ?></h2>
        <div class="oj-info-box">
            <p><strong><?php _e('Plugin Status:', 'orders-jet'); ?></strong> <?php _e('Active', 'orders-jet'); ?></p>
            <p><strong><?php _e('WooCommerce:', 'orders-jet'); ?></strong> <?php echo class_exists('WooCommerce') ? __('Active', 'orders-jet') : __('Inactive', 'orders-jet'); ?></p>
            <p><strong><?php _e('Current Date:', 'orders-jet'); ?></strong> <?php echo date('F j, Y'); ?></p>
            <p><strong><?php _e('Current Time:', 'orders-jet'); ?></strong> <?php echo date('H:i:s'); ?></p>
        </div>
    </div>
</div>

<style>
.oj-dashboard-stats,
.oj-dashboard-actions,
.oj-dashboard-info,
.oj-dashboard-orders,
.oj-dashboard-tables {
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
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.oj-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.oj-stat-label {
    font-size: 14px;
    color: #666;
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

/* Status badges */
.oj-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.oj-status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.oj-status-badge.status-processing {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.oj-status-badge.status-on-hold {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.oj-status-badge.status-completed {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Tables grid */
.oj-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.oj-table-card {
    background: #f8f9fa;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.oj-table-card.status-occupied {
    border-color: #dc3545;
    background: #fff5f5;
}

.oj-table-card.status-reserved {
    border-color: #ffc107;
    background: #fffdf5;
}

.oj-table-card.status-available {
    border-color: #28a745;
    background: #f5fff5;
}

.oj-table-number {
    font-size: 24px;
    font-weight: bold;
    color: #23282d;
    margin-bottom: 8px;
}

.oj-table-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-bottom: 10px;
}

.oj-status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6c757d;
}

.oj-table-card.status-occupied .oj-status-indicator {
    background: #dc3545;
}

.oj-table-card.status-reserved .oj-status-indicator {
    background: #ffc107;
}

.oj-table-card.status-available .oj-status-indicator {
    background: #28a745;
}

.oj-no-orders,
.oj-no-tables {
    text-align: center;
    padding: 40px 20px;
    color: #666;
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
    
    .oj-tables-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}
</style>