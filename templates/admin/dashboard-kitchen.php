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

// Get active orders using a more precise approach to avoid duplicates
$active_orders = array();

// Use WooCommerce's native method directly (more reliable)
if (function_exists('wc_get_orders')) {
    error_log('Orders Jet Kitchen: Using WooCommerce native method...');
    
    $wc_orders = wc_get_orders(array(
        'status' => array('pending', 'processing'), // Exclude on-hold (ready orders)
        'meta_key' => '_oj_table_number',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'ASC'
    ));
    
    error_log('Orders Jet Kitchen: Found ' . count($wc_orders) . ' orders with WooCommerce method');
    
    // Convert WC_Order objects to the format we need
    foreach ($wc_orders as $wc_order) {
        $active_orders[] = array(
            'ID' => $wc_order->get_id(),
            'post_date' => $wc_order->get_date_created()->format('Y-m-d H:i:s'),
            'post_status' => 'wc-' . $wc_order->get_status(),
            'order_total' => $wc_order->get_total(),
            'table_number' => $wc_order->get_meta('_oj_table_number'),
            'customer_name' => $wc_order->get_billing_first_name(),
            'session_id' => $wc_order->get_meta('_oj_session_id')
        );
    }
} else {
    // Fallback to get_posts if WooCommerce functions not available
    error_log('Orders Jet Kitchen: Falling back to get_posts method...');
    
    $active_orders_posts = get_posts(array(
        'post_type' => 'shop_order',
        'post_status' => array('wc-pending', 'wc-processing'), // Exclude wc-on-hold (ready orders)
        'meta_query' => array(
            array(
                'key' => '_oj_table_number',
                'compare' => 'EXISTS'
            )
        ),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC'
    ));
    
    // Convert posts to the format we need
    foreach ($active_orders_posts as $order_post) {
        $order = wc_get_order($order_post->ID);
        if ($order && $order->get_meta('_oj_table_number')) {
            $active_orders[] = array(
                'ID' => $order->get_id(),
                'post_date' => $order_post->post_date,
                'post_status' => 'wc-' . $order->get_status(),
                'order_total' => $order->get_total(),
                'table_number' => $order->get_meta('_oj_table_number'),
                'customer_name' => $order->get_billing_first_name(),
                'session_id' => $order->get_meta('_oj_session_id')
            );
        }
    }
}

// Sort by priority: processing first, then pending, then on-hold
usort($active_orders, function($a, $b) {
    $priority = array('wc-processing' => 1, 'wc-pending' => 2, 'wc-on-hold' => 3);
    $a_priority = $priority[$a['post_status']] ?? 4;
    $b_priority = $priority[$b['post_status']] ?? 4;
    
    if ($a_priority === $b_priority) {
        return strtotime($a['post_date']) - strtotime($b['post_date']);
    }
    return $a_priority - $b_priority;
});

error_log('Orders Jet Kitchen: Final order count: ' . count($active_orders));
error_log('Orders Jet Kitchen: Order IDs: ' . implode(', ', array_column($active_orders, 'ID')));

// Get order items for each order using WooCommerce methods (same as frontend)
$orders_with_items = array();
foreach ($active_orders as $order) {
    $wc_order = wc_get_order($order['ID']);
    if ($wc_order) {
        $order_items = array();
        foreach ($wc_order->get_items() as $item) {
            // Get basic item info
            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'variations' => array(),
                'addons' => array(),
                'notes' => ''
            );
            
            // Get variations using WooCommerce native methods
            $product = $item->get_product();
            if ($product && $product->is_type('variation')) {
                // For variation products, get variation attributes directly
                $variation_attributes = $product->get_variation_attributes();
                foreach ($variation_attributes as $attribute_name => $attribute_value) {
                    if (!empty($attribute_value)) {
                        // Clean attribute name and get proper label
                        $clean_attribute_name = str_replace('attribute_', '', $attribute_name);
                        $attribute_label = wc_attribute_label($clean_attribute_name);
                        $item_data['variations'][$attribute_label] = $attribute_value;
                    }
                }
                
            } else {
                // For non-variation products, still check meta for any variation info
                $item_meta = $item->get_meta_data();
                foreach ($item_meta as $meta) {
                    $meta_key = $meta->key;
                    $meta_value = $meta->value;
                    
                    // Get variations from stored meta
                    if (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0) {
                        $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                        $attribute_label = wc_attribute_label($attribute_name);
                        $item_data['variations'][$attribute_label] = $meta_value;
                    }
                }
            }
            
            // Get add-ons and notes from item meta
            $item_meta = $item->get_meta_data();
            foreach ($item_meta as $meta) {
                $meta_key = $meta->key;
                $meta_value = $meta->value;
                
                // Get add-ons
                if ($meta_key === '_oj_item_addons') {
                    $addons = explode(', ', $meta_value);
                    $item_data['addons'] = array_map(function($addon) {
                        // Remove price information from add-ons for kitchen display
                        // Convert "Combo Plus (+90,00 EGP)" to "Combo Plus"
                        // Convert "Combo + 60 EGP" to "Combo"
                        $addon_clean = strip_tags($addon);
                        // Remove price in parentheses (e.g., "(+90,00 EGP)" or "(+0,00 EGP)")
                        $addon_clean = preg_replace('/\s*\(\+[^)]+\)/', '', $addon_clean);
                        // Remove price in format "+ XX EGP" (e.g., "+ 60 EGP")
                        $addon_clean = preg_replace('/\s*\+\s*\d+[.,]?\d*\s*EGP/', '', $addon_clean);
                        return trim($addon_clean);
                    }, $addons);
                }
                
                // Get notes
                if ($meta_key === '_oj_item_notes') {
                    $item_data['notes'] = $meta_value;
                }
            }
            
            $order_items[] = $item_data;
        }
        
        // Create a new order array with items (avoid reference issues)
        $order_with_items = $order;
        $order_with_items['items'] = $order_items;
        $orders_with_items[] = $order_with_items;
    } else {
        // Create order with empty items if WC order not found
        $order_with_items = $order;
        $order_with_items['items'] = array();
        $orders_with_items[] = $order_with_items;
    }
}

// Replace the original array with the new one
$active_orders = $orders_with_items;

// Kitchen stats (matching actual workflow)
$pending_orders = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm_table ON p.ID = pm_table.post_id AND pm_table.meta_key = '_oj_table_number'
    WHERE p.post_type = 'shop_order'
    AND p.post_status = 'wc-pending'
    AND pm_table.meta_value IS NOT NULL
"));

$processing_orders = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm_table ON p.ID = pm_table.post_id AND pm_table.meta_key = '_oj_table_number'
    WHERE p.post_type = 'shop_order'
    AND p.post_status = 'wc-processing'
    AND pm_table.meta_value IS NOT NULL
"));

$completed_today = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm_table ON p.ID = pm_table.post_id AND pm_table.meta_key = '_oj_table_number'
    WHERE p.post_type = 'shop_order'
    AND p.post_status = 'wc-completed'
    AND pm_table.meta_value IS NOT NULL
    AND DATE(p.post_date) = %s
", $today));

// Format currency
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-food" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('Kitchen Display', 'orders-jet'); ?>
    </h1>
    <button type="button" class="button oj-refresh-dashboard" style="margin-left: 10px;">
        <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
        <?php _e('Refresh', 'orders-jet'); ?>
    </button>
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
    <div class="oj-dashboard-orders">
        <h2><?php _e('Order Queue', 'orders-jet'); ?></h2>
        
        <?php if (!empty($active_orders)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Customer/Table', 'orders-jet'); ?></th>
                        <th><?php _e('Date', 'orders-jet'); ?></th>
                        <th style="width: 50%;"><?php _e('Items & Add-ons', 'orders-jet'); ?></th>
                        <th><?php _e('Mark Ready', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_orders as $order) : ?>
                        <tr>
                            <td>
                                <?php if ($order['table_number']) : ?>
                                    <strong>Table <?php echo esc_html($order['table_number']); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($order['customer_name']) : ?>
                                    <?php echo esc_html($order['customer_name']); ?>
                                <?php else : ?>
                                    Guest
                                <?php endif; ?>
                                <br><small><strong>#<?php echo esc_html($order['ID']); ?></strong></small>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($order['post_date']))); ?></td>
                            <td class="oj-kitchen-items">
                                <?php if (!empty($order['items'])) : ?>
                                    <?php foreach ($order['items'] as $item) : ?>
                                        <div class="oj-kitchen-item">
                                            <div class="oj-item-main">
                                                <span class="oj-item-qty"><?php echo esc_html($item['quantity']); ?>x</span>
                                                <strong class="oj-item-name"><?php echo esc_html($item['name']); ?></strong>
                                            </div>
                                            
                                            <?php if (!empty($item['variations'])) : ?>
                                                <div class="oj-item-variations">
                                                    <?php foreach ($item['variations'] as $variation_name => $variation_value) : ?>
                                                        <span class="oj-variation"><?php echo esc_html($variation_name); ?>: <?php echo esc_html($variation_value); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['addons'])) : ?>
                                                <div class="oj-item-addons">
                                                    <span class="oj-addons-label"><?php _e('Add-ons:', 'orders-jet'); ?></span>
                                                    <?php foreach ($item['addons'] as $addon) : ?>
                                                        <span class="oj-addon"><?php echo esc_html($addon); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['notes'])) : ?>
                                                <div class="oj-item-notes">
                                                    <span class="oj-notes-label"><?php _e('Notes:', 'orders-jet'); ?></span>
                                                    <span class="oj-notes-text"><?php echo esc_html($item['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <span class="oj-no-items"><?php _e('No items found', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['post_status'] === 'wc-pending') : ?>
                                    <button class="button button-primary oj-start-cooking" data-order-id="<?php echo esc_attr($order['ID']); ?>" style="background: #00a32a; border-color: #00a32a; color: white; font-weight: 600; padding: 6px 12px; font-size: 13px;">
                                        <span class="dashicons dashicons-controls-play" style="font-size: 14px; vertical-align: middle; margin-right: 4px;"></span>
                                        <?php _e('Start Cooking', 'orders-jet'); ?>
                                    </button>
                                <?php elseif ($order['post_status'] === 'wc-processing') : ?>
                                    <button class="button button-secondary oj-complete-order" data-order-id="<?php echo esc_attr($order['ID']); ?>" style="background: #00a32a; border-color: #00a32a; color: white; font-weight: 600; padding: 6px 12px; font-size: 13px;">
                                        <span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
                                        <?php _e('Mark Ready', 'orders-jet'); ?>
                                    </button>
                                <?php else : ?>
                                    <button class="button oj-resume-order" data-order-id="<?php echo esc_attr($order['ID']); ?>" style="background: #dba617; border-color: #dba617; color: white; font-weight: 600; padding: 6px 12px; font-size: 13px;">
                                        <span class="dashicons dashicons-controls-repeat" style="font-size: 14px; vertical-align: middle; margin-right: 4px;"></span>
                                        <?php _e('Resume', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="oj-no-orders">
                <p><?php _e('No active orders found.', 'orders-jet'); ?></p>
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

/* Kitchen-specific styles for order items */
.oj-kitchen-items {
    padding: 10px 0;
}

.oj-kitchen-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 8px;
}

.oj-kitchen-item:last-child {
    margin-bottom: 0;
}

.oj-item-main {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.oj-item-qty {
    background: #007cba;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    min-width: 24px;
    text-align: center;
}

.oj-item-name {
    font-weight: 600;
    color: #2c3e50;
}

.oj-item-variations {
    margin: 5px 0;
    font-size: 12px;
}

.oj-variation {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 5px;
    display: inline-block;
    margin-bottom: 2px;
}

.oj-item-addons {
    margin: 5px 0;
    font-size: 12px;
}

.oj-addons-label {
    font-weight: 600;
    color: #e67e22;
    margin-right: 5px;
}

.oj-addon {
    background: #fff3cd;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 5px;
    display: inline-block;
    margin-bottom: 2px;
}

.oj-item-notes {
    margin: 5px 0;
    font-size: 12px;
    background: #f8d7da;
    color: #721c24;
    padding: 5px;
    border-radius: 3px;
}

.oj-notes-label {
    font-weight: 600;
    margin-right: 5px;
}

.oj-notes-text {
    font-style: italic;
}

.oj-no-items {
    color: #6c757d;
    font-style: italic;
}
</style>