<?php
/**
 * Orders Jet - Waiter Dashboard Template (Simple Version)
 * Basic WordPress admin interface for waiters
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get user information
$current_user = wp_get_current_user();
$today = date('Y-m-d');
$today_formatted = date('F j, Y');

// Get real data from tables and orders
global $wpdb;

// Get all tables with their status and current orders
$tables = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_status,
           pm_status.meta_value as table_status,
           pm_capacity.meta_value as table_capacity,
           pm_location.meta_value as table_location
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_table_status'
    LEFT JOIN {$wpdb->postmeta} pm_capacity ON p.ID = pm_capacity.post_id AND pm_capacity.meta_key = '_table_capacity'
    LEFT JOIN {$wpdb->postmeta} pm_location ON p.ID = pm_location.post_id AND pm_location.meta_key = '_table_location'
    WHERE p.post_type = 'oj_table'
    AND p.post_status = 'publish'
    ORDER BY p.post_title ASC
", ARRAY_A);

// Get active orders for each table
foreach ($tables as &$table) {
    $table_orders = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_date, p.post_status, pm_total.meta_value as order_total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
        LEFT JOIN {$wpdb->postmeta} pm_table ON p.ID = pm_table.post_id AND pm_table.meta_key = '_table_number'
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold')
        AND pm_table.meta_value = %s
        ORDER BY p.post_date DESC
    ", $table['post_title']), ARRAY_A);
    
    $table['orders'] = $table_orders;
    $table['has_orders'] = !empty($table_orders);
    $table['total_amount'] = array_sum(array_column($table_orders, 'order_total'));
}

// Waiter stats
$total_tables = count($tables);
$occupied_tables = count(array_filter($tables, function($table) {
    return $table['table_status'] === 'occupied' || $table['has_orders'];
}));
$available_tables = $total_tables - $occupied_tables;

// Orders served today by this waiter
$orders_served_today = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_waiter ON p.ID = pm_waiter.post_id AND pm_waiter.meta_key = '_assigned_waiter'
    WHERE p.post_type = 'shop_order'
    AND p.post_status = 'wc-completed'
    AND DATE(p.post_date) = %s
    AND pm_waiter.meta_value = %s
", $today, $current_user->user_login));

// Format currency
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-tickets-alt" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('My Tables', 'orders-jet'); ?>
    </h1>
    <p class="description"><?php echo sprintf(__('Welcome, %s!', 'orders-jet'), $current_user->display_name); ?></p>
    
    <hr class="wp-header-end">

    <!-- Waiter Stats -->
    <div class="oj-waiter-stats">
        <h2><?php echo sprintf(__('My Performance - %s', 'orders-jet'), $today_formatted); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box assigned">
                <div class="oj-stat-number"><?php echo esc_html($orders_served_today ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Tables Served Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box occupied">
                <div class="oj-stat-number"><?php echo esc_html($occupied_tables); ?></div>
                <div class="oj-stat-label"><?php _e('Active Tables', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box orders">
                <div class="oj-stat-number"><?php echo esc_html($available_tables); ?></div>
                <div class="oj-stat-label"><?php _e('Available Tables', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Table Management -->
    <div class="oj-table-management">
        <h2><?php _e('Table Management', 'orders-jet'); ?></h2>
        
        <?php if (!empty($tables)) : ?>
            <div class="oj-table-grid">
                <?php foreach ($tables as $table) : ?>
                    <?php 
                    $table_status = $table['table_status'] ?: 'available';
                    $has_orders = $table['has_orders'];
                    $display_status = $has_orders ? 'occupied' : $table_status;
                    ?>
                    <div class="oj-table-card status-<?php echo esc_attr($display_status); ?>">
                        <div class="oj-table-number"><?php echo esc_html($table['post_title']); ?></div>
                        
                        <?php if ($table['table_capacity']) : ?>
                            <div class="oj-table-capacity">
                                <span class="dashicons dashicons-groups"></span>
                                <?php echo sprintf(__('Capacity: %s', 'orders-jet'), esc_html($table['table_capacity'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($table['table_location']) : ?>
                            <div class="oj-table-location">
                                <span class="dashicons dashicons-location"></span>
                                <?php echo esc_html($table['table_location']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="oj-table-status">
                            <span class="oj-status-indicator"></span>
                            <?php 
                            if ($has_orders) {
                                echo sprintf(__('%s Active Orders', 'orders-jet'), count($table['orders']));
                            } else {
                                echo esc_html(ucfirst($table_status));
                            }
                            ?>
                        </div>
                        
                        <?php if ($has_orders) : ?>
                            <div class="oj-table-orders-summary">
                                <div class="oj-orders-count">
                                    <strong><?php echo count($table['orders']); ?></strong> <?php _e('orders', 'orders-jet'); ?>
                                </div>
                                <div class="oj-orders-total">
                                    <?php echo esc_html($currency_symbol . number_format($table['total_amount'] ?: 0, 2)); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="oj-table-actions">
                            <?php if ($has_orders) : ?>
                                <a href="<?php echo admin_url('edit.php?post_type=shop_order&table_number=' . urlencode($table['post_title'])); ?>" class="button button-primary">
                                    <?php _e('View Orders', 'orders-jet'); ?>
                                </a>
                                <button class="button button-secondary oj-close-table" data-table-id="<?php echo esc_attr($table['ID']); ?>">
                                    <?php _e('Close Table', 'orders-jet'); ?>
                                </button>
                            <?php else : ?>
                                <?php if ($display_status === 'available') : ?>
                                    <button class="button button-primary oj-open-table" data-table-id="<?php echo esc_attr($table['ID']); ?>">
                                        <?php _e('Open Table', 'orders-jet'); ?>
                                    </button>
                                <?php else : ?>
                                    <button class="button oj-manage-table" data-table-id="<?php echo esc_attr($table['ID']); ?>">
                                        <?php _e('Manage', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="<?php echo admin_url('post.php?post=' . $table['ID'] . '&action=edit'); ?>" class="button button-small">
                                <?php _e('Details', 'orders-jet'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="oj-no-tables">
                <div class="oj-no-tables-icon">
                    <span class="dashicons dashicons-tablet" style="font-size: 48px; color: #ccc;"></span>
                </div>
                <h3><?php _e('No Tables Configured', 'orders-jet'); ?></h3>
                <p><?php _e('Contact your manager to set up tables for your restaurant.', 'orders-jet'); ?></p>
                <a href="<?php echo admin_url('post-new.php?post_type=oj_table'); ?>" class="button button-primary">
                    <?php _e('Add Table', 'orders-jet'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="oj-waiter-actions">
        <h2><?php _e('Quick Actions', 'orders-jet'); ?></h2>
        
        <div class="oj-action-buttons">
            <a href="<?php echo admin_url('edit.php?post_type=oj_table'); ?>" class="button button-primary">
                <?php _e('View All Tables', 'orders-jet'); ?>
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-secondary">
                <?php _e('View Orders', 'orders-jet'); ?>
            </a>
            <button type="button" class="button button-secondary" onclick="location.reload()">
                <?php _e('Refresh Tables', 'orders-jet'); ?>
            </button>
        </div>
    </div>

    <!-- Waiter Info -->
    <div class="oj-waiter-info">
        <h2><?php _e('My Status', 'orders-jet'); ?></h2>
        <div class="oj-info-box">
            <p><strong><?php _e('Status:', 'orders-jet'); ?></strong> <?php _e('Available', 'orders-jet'); ?></p>
            <p><strong><?php _e('Last Activity:', 'orders-jet'); ?></strong> <?php echo date('H:i:s'); ?></p>
            <p><strong><?php _e('Assigned To:', 'orders-jet'); ?></strong> <?php echo esc_html($current_user->display_name); ?></p>
        </div>
    </div>
</div>

<style>
.oj-waiter-stats,
.oj-table-management,
.oj-waiter-actions,
.oj-waiter-info {
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

.oj-stat-box.assigned {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.oj-stat-box.occupied {
    background: #fff5f5;
    border-color: #fed7d7;
}

.oj-stat-box.orders {
    background: #f0fff4;
    border-color: #9ae6b4;
}

.oj-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.oj-stat-box.assigned .oj-stat-number {
    color: #2563eb;
}

.oj-stat-box.occupied .oj-stat-number {
    color: #e53e3e;
}

.oj-stat-box.orders .oj-stat-number {
    color: #38a169;
}

.oj-stat-label {
    font-size: 14px;
    color: #666;
}

/* Table Cards */
.oj-table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.oj-table-card {
    background: white;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
}

.oj-table-card.status-occupied {
    border-color: #e53e3e;
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
    margin-bottom: 10px;
    text-align: center;
}

.oj-table-capacity,
.oj-table-location {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 14px;
    color: #4a5568;
}

.oj-table-capacity .dashicons,
.oj-table-location .dashicons {
    font-size: 16px;
    color: #718096;
}

.oj-table-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 15px;
    font-weight: 500;
}

.oj-status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6c757d;
}

.oj-table-card.status-occupied .oj-status-indicator {
    background: #e53e3e;
}

.oj-table-card.status-reserved .oj-status-indicator {
    background: #ffc107;
}

.oj-table-card.status-available .oj-status-indicator {
    background: #28a745;
}

.oj-table-orders-summary {
    background: rgba(0,0,0,0.05);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 15px;
    text-align: center;
}

.oj-orders-count {
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 5px;
}

.oj-orders-total {
    font-size: 16px;
    font-weight: bold;
    color: #2d3748;
}

.oj-table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.oj-table-actions .button {
    flex: 1;
    min-width: 100px;
    font-size: 12px;
    padding: 6px 12px;
}

.oj-no-tables {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.oj-no-tables-icon {
    margin-bottom: 15px;
}

.oj-no-tables h3 {
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