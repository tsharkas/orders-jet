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

// Total tables
$total_tables = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts}
    WHERE post_type = 'oj_table'
    AND post_status = 'publish'
");

// Active tables (tables with active orders) - using same logic as frontend
$active_tables_posts = get_posts(array(
    'post_type' => 'shop_order',
    'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold'),
    'meta_query' => array(
        array(
            'key' => '_oj_table_number',
            'compare' => 'EXISTS'
        )
    ),
    'posts_per_page' => -1
));

// Get unique table numbers from active orders
$active_table_numbers = array();
foreach ($active_tables_posts as $order_post) {
    $order = wc_get_order($order_post->ID);
    if ($order) {
        $table_number = $order->get_meta('_oj_table_number');
        if ($table_number && !in_array($table_number, $active_table_numbers)) {
            $active_table_numbers[] = $table_number;
        }
    }
}

$active_tables = count($active_table_numbers);

// Format revenue
$currency_symbol = get_woocommerce_currency_symbol();
$formatted_revenue = $currency_symbol . number_format($today_revenue ?: 0, 2);

// Get recent orders using the SAME logic as frontend order history
$recent_orders_posts = get_posts(array(
    'post_type' => 'shop_order',
    'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold'),
    'meta_query' => array(
        array(
            'key' => '_oj_table_number',
            'compare' => 'EXISTS'
        )
    ),
    'posts_per_page' => 5,
    'orderby' => 'date',
    'order' => 'DESC'
));

// If no orders found with get_posts, try WooCommerce's native method
if (count($recent_orders_posts) == 0 && function_exists('wc_get_orders')) {
    error_log('Orders Jet Manager: Trying WooCommerce native method...');
    
    $wc_orders = wc_get_orders(array(
        'status' => array('pending', 'processing', 'on-hold'),
        'meta_key' => '_oj_table_number',
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // Convert WC_Order objects to the format we need
    $recent_orders = array();
    foreach ($wc_orders as $wc_order) {
        $recent_orders[] = array(
            'ID' => $wc_order->get_id(),
            'post_date' => $wc_order->get_date_created()->format('Y-m-d H:i:s'),
            'post_status' => 'wc-' . $wc_order->get_status(),
            'order_total' => $wc_order->get_total(),
            'table_number' => $wc_order->get_meta('_oj_table_number'),
            'customer_name' => $wc_order->get_billing_first_name()
        );
    }
} else {
    // Convert posts to the format we need
    $recent_orders = array();
    foreach ($recent_orders_posts as $order_post) {
        $order = wc_get_order($order_post->ID);
        if ($order) {
            $recent_orders[] = array(
                'ID' => $order->get_id(),
                'post_date' => $order_post->post_date,
                'post_status' => 'wc-' . $order->get_status(),
                'order_total' => $order->get_total(),
                'table_number' => $order->get_meta('_oj_table_number'),
                'customer_name' => $order->get_billing_first_name()
            );
        }
    }
}

error_log('Orders Jet Manager: Found ' . count($recent_orders) . ' recent orders using frontend logic');

// Get table status with active order information
$table_status = $wpdb->get_results("
    SELECT p.ID, p.post_title, pm_status.meta_value as table_status,
           pm_capacity.meta_value as table_capacity, pm_location.meta_value as table_location,
           (SELECT COUNT(*) FROM {$wpdb->posts} p2 
            INNER JOIN {$wpdb->postmeta} pm_table ON p2.ID = pm_table.post_id AND pm_table.meta_key = '_oj_table_number'
            WHERE p2.post_type = 'shop_order' 
            AND p2.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold')
            AND pm_table.meta_value = p.post_title) as active_orders_count
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_oj_table_status'
    LEFT JOIN {$wpdb->postmeta} pm_capacity ON p.ID = pm_capacity.post_id AND pm_capacity.meta_key = '_oj_table_capacity'
    LEFT JOIN {$wpdb->postmeta} pm_location ON p.ID = pm_location.post_id AND pm_location.meta_key = '_oj_table_location'
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
    <button type="button" class="button oj-refresh-dashboard" style="margin-left: 10px;">
        <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
        <?php _e('Refresh', 'orders-jet'); ?>
    </button>
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
                        <th><?php _e('Customer/Table', 'orders-jet'); ?></th>
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
                                <?php if ($order['table_number']) : ?>
                                    <strong>Table <?php echo esc_html($order['table_number']); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($order['customer_name']) : ?>
                                    <?php echo esc_html($order['customer_name']); ?>
                                <?php else : ?>
                                    Guest
                                <?php endif; ?>
                            </td>
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
                    <?php 
                    $meta_status = $table['table_status'] ?: 'available';
                    $has_active_orders = intval($table['active_orders_count']) > 0;
                    $display_status = $has_active_orders ? 'occupied' : $meta_status;
                    ?>
                    <div class="oj-table-card status-<?php echo esc_attr($display_status); ?>">
                        <div class="oj-table-number"><?php echo esc_html($table['post_title']); ?></div>
                        
                        <?php if ($table['table_capacity']) : ?>
                            <div class="oj-table-info"><?php echo sprintf(__('Capacity: %s', 'orders-jet'), esc_html($table['table_capacity'])); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($table['table_location']) : ?>
                            <div class="oj-table-info"><?php echo esc_html($table['table_location']); ?></div>
                        <?php endif; ?>
                        
                        <div class="oj-table-status">
                            <span class="oj-status-indicator"></span>
                            <?php 
                            if ($has_active_orders) {
                                echo sprintf(__('%s Active Orders', 'orders-jet'), $table['active_orders_count']);
                            } else {
                                echo esc_html(ucfirst($meta_status));
                            }
                            ?>
                        </div>
                        <div class="oj-table-actions">
                            <a href="<?php echo admin_url('post.php?post=' . $table['ID'] . '&action=edit'); ?>" class="button button-small">
                                <?php _e('Manage', 'orders-jet'); ?>
                            </a>
                            <?php if ($has_active_orders) : ?>
                                <a href="<?php echo admin_url('edit.php?post_type=shop_order&table_number=' . urlencode($table['post_title'])); ?>" class="button button-primary button-small">
                                    <?php _e('View Orders', 'orders-jet'); ?>
                                </a>
                            <?php endif; ?>
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

.oj-table-info {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
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