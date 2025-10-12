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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-businessman" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('Manager Dashboard', 'orders-jet'); ?>
    </h1>
    <p class="description"><?php echo sprintf(__('Welcome back, %s!', 'orders-jet'), $current_user->display_name); ?></p>
    
    <hr class="wp-header-end">

    <!-- Simple Stats -->
    <div class="oj-dashboard-stats">
        <h2><?php _e('Today\'s Overview', 'orders-jet'); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Orders Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Revenue Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Active Tables', 'orders-jet'); ?></div>
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

    <!-- Simple Info -->
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
.oj-dashboard-info {
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