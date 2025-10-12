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
        <h2><?php _e('My Overview', 'orders-jet'); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box assigned">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('My Tables', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box occupied">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Occupied', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box orders">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Table Management -->
    <div class="oj-table-management">
        <h2><?php _e('Table Management', 'orders-jet'); ?></h2>
        
        <div class="oj-tables-info">
            <p><?php _e('No tables assigned yet.', 'orders-jet'); ?></p>
            <p><?php _e('Tables will appear here once they are assigned to you.', 'orders-jet'); ?></p>
        </div>
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

.oj-tables-info {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 20px;
    margin-top: 15px;
    text-align: center;
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