<?php
/**
 * Orders Jet - Manager Settings Template
 * Configure restaurant settings and system options
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Handle form submission
if (isset($_POST['oj_settings_submit']) && wp_verify_nonce($_POST['oj_settings_nonce'], 'oj_settings')) {
    
    // Handle parent-child order system toggle
    if (isset($_POST['oj_enable_parent_child_orders'])) {
        $parent_child_manager = new Orders_Jet_Parent_Child_Manager();
        if ($_POST['oj_enable_parent_child_orders'] === '1') {
            $parent_child_manager->enable_parent_child_orders();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Parent-Child order system enabled successfully!', 'orders-jet') . '</p></div>';
        } else {
            $parent_child_manager->disable_parent_child_orders();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Parent-Child order system disabled. Using legacy system.', 'orders-jet') . '</p></div>';
        }
    }
}

// Get current settings
$parent_child_manager = new Orders_Jet_Parent_Child_Manager();
$parent_child_enabled = $parent_child_manager->is_parent_child_enabled();

?>

<div class="wrap">
    <h1><?php _e('Restaurant Settings', 'orders-jet'); ?></h1>
    <p class="description"><?php _e('Configure restaurant settings, preferences, and system options.', 'orders-jet'); ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('oj_settings', 'oj_settings_nonce'); ?>
        
        <div class="oj-settings-container">
            
            <!-- Order System Settings -->
            <div class="oj-settings-section">
                <h2><?php _e('Order Management System', 'orders-jet'); ?></h2>
                <p class="description"><?php _e('Configure how table orders are processed and managed.', 'orders-jet'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oj_enable_parent_child_orders"><?php _e('Table Order System', 'orders-jet'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Table Order System', 'orders-jet'); ?></legend>
                                
                                <label>
                                    <input type="radio" name="oj_enable_parent_child_orders" value="0" <?php checked($parent_child_enabled, false); ?>>
                                    <strong><?php _e('Legacy System', 'orders-jet'); ?></strong>
                                    <p class="description"><?php _e('Each table order is processed individually. Tax calculated per order.', 'orders-jet'); ?></p>
                                </label>
                                
                                <br><br>
                                
                                <label>
                                    <input type="radio" name="oj_enable_parent_child_orders" value="1" <?php checked($parent_child_enabled, true); ?>>
                                    <strong><?php _e('Parent-Child System (Recommended)', 'orders-jet'); ?></strong>
                                    <p class="description"><?php _e('Table orders are grouped under a parent order with combined invoice. Tax calculated on combined total.', 'orders-jet'); ?></p>
                                </label>
                                
                                <?php if ($parent_child_enabled): ?>
                                    <div class="oj-system-status oj-status-enabled">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <strong><?php _e('Parent-Child System Active', 'orders-jet'); ?></strong>
                                        <p><?php _e('Table orders will use the new parent-child system with combined invoices and proper tax calculation.', 'orders-jet'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="oj-system-status oj-status-disabled">
                                        <span class="dashicons dashicons-warning"></span>
                                        <strong><?php _e('Legacy System Active', 'orders-jet'); ?></strong>
                                        <p><?php _e('Table orders will use the legacy individual order system. Tax display on table invoices may be limited.', 'orders-jet'); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="oj-system-comparison">
                                    <h4><?php _e('System Comparison', 'orders-jet'); ?></h4>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Feature', 'orders-jet'); ?></th>
                                                <th><?php _e('Legacy System', 'orders-jet'); ?></th>
                                                <th><?php _e('Parent-Child System', 'orders-jet'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php _e('Tax Calculation', 'orders-jet'); ?></td>
                                                <td><?php _e('Manual calculation + storage', 'orders-jet'); ?></td>
                                                <td><strong><?php _e('WooCommerce native', 'orders-jet'); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><?php _e('Invoice Generation', 'orders-jet'); ?></td>
                                                <td><?php _e('Custom template + session data', 'orders-jet'); ?></td>
                                                <td><strong><?php _e('Standard WooCommerce invoice', 'orders-jet'); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><?php _e('Order Management', 'orders-jet'); ?></td>
                                                <td><?php _e('Multiple separate orders', 'orders-jet'); ?></td>
                                                <td><strong><?php _e('Single parent order', 'orders-jet'); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><?php _e('Kitchen Workflow', 'orders-jet'); ?></td>
                                                <td><?php _e('Individual orders', 'orders-jet'); ?></td>
                                                <td><?php _e('Individual child orders (unchanged)', 'orders-jet'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php _e('Tax Display', 'orders-jet'); ?></td>
                                                <td><?php _e('Limited on table invoices', 'orders-jet'); ?></td>
                                                <td><strong><?php _e('Full tax breakdown', 'orders-jet'); ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
        </div>
        
        <p class="submit">
            <input type="submit" name="oj_settings_submit" class="button-primary" value="<?php _e('Save Settings', 'orders-jet'); ?>">
        </p>
    </form>
</div>

<style>
.oj-settings-container {
    max-width: 1000px;
}

.oj-settings-section {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.oj-settings-section h2 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #23282d;
}

.oj-system-status {
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.oj-status-enabled {
    background: #d1f2eb;
    border-left: 4px solid #00a32a;
}

.oj-status-disabled {
    background: #fff3cd;
    border-left: 4px solid #dba617;
}

.oj-system-status .dashicons {
    margin-top: 2px;
    flex-shrink: 0;
}

.oj-status-enabled .dashicons {
    color: #00a32a;
}

.oj-status-disabled .dashicons {
    color: #dba617;
}

.oj-system-status div {
    flex: 1;
}

.oj-system-status strong {
    display: block;
    margin-bottom: 5px;
}

.oj-system-comparison {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.oj-system-comparison h4 {
    margin-bottom: 10px;
}

.oj-system-comparison table {
    margin-top: 10px;
}

.oj-system-comparison td:nth-child(3) {
    font-weight: 500;
}

.form-table th {
    width: 200px;
}

.form-table fieldset label {
    display: block;
    margin-bottom: 10px;
}

.form-table fieldset .description {
    margin-left: 25px;
    margin-top: 5px;
}
</style>
