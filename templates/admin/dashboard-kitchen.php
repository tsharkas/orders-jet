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
        <h2><?php _e('Kitchen Overview', 'orders-jet'); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box pending">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Pending Orders', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box processing">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('In Progress', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box completed">
                <div class="oj-stat-number">0</div>
                <div class="oj-stat-label"><?php _e('Completed Today', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Order Queue -->
    <div class="oj-order-queue">
        <h2><?php _e('Order Queue', 'orders-jet'); ?></h2>
        
        <div class="oj-queue-info">
            <p><?php _e('No pending orders at the moment.', 'orders-jet'); ?></p>
            <p><?php _e('Orders will appear here as they come in.', 'orders-jet'); ?></p>
        </div>
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

.oj-queue-info {
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