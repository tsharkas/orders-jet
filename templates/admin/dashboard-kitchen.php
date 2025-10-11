<?php
/**
 * Orders Jet - Kitchen Dashboard Template
 * This template renders the React dashboard for kitchen staff
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_kitchen_dashboard')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get user information
$current_user = wp_get_current_user();
$user_role = oj_get_user_role();
?>

<div class="wrap">
    <div id="orders-jet-kitchen-dashboard">
        <!-- Loading fallback -->
        <div class="loading" id="dashboard-loading">
            <div class="loading-spinner"></div>
            <p><?php _e('Loading kitchen dashboard...', 'orders-jet'); ?></p>
        </div>
    </div>
</div>

<script>
// WordPress configuration for React app
window.OrdersJetConfig = {
    apiUrl: '<?php echo esc_url(rest_url('orders-jet/v1/')); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    userRole: '<?php echo esc_js($user_role); ?>',
    userId: <?php echo intval($current_user->ID); ?>,
    userName: '<?php echo esc_js($current_user->display_name); ?>',
    siteUrl: '<?php echo esc_url(home_url()); ?>',
    pluginUrl: '<?php echo esc_url(ORDERS_JET_PLUGIN_URL); ?>',
    websocketUrl: 'ws://localhost:8080' // TODO: Configure WebSocket URL
};
</script>

<style>
/* Additional WordPress admin styles */
#orders-jet-kitchen-dashboard {
    margin: -20px -20px 0 -20px;
}

#orders-jet-kitchen-dashboard .dashboard-container {
    min-height: calc(100vh - 32px);
}

/* WordPress admin bar adjustment */
body.admin-bar #orders-jet-kitchen-dashboard .header {
    top: 32px;
}

body.admin-bar #orders-jet-kitchen-dashboard .main-content {
    margin-top: 92px;
}
</style>
