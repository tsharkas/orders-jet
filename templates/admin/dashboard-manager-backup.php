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

// Handle location filtering
$selected_location_id = isset($_GET['location']) ? intval($_GET['location']) : '';
$woofood_locations = oj_get_available_woofood_locations();

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

// Enhanced Analytics Data Collection
// 1. Revenue trend data (last 7 days)
$revenue_trend = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_revenue = $wpdb->get_var($wpdb->prepare("
        SELECT COALESCE(SUM(meta_value), 0)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_order_total'
        AND p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing')
        AND DATE(p.post_date) = %s
    ", $date));
    
    $revenue_trend[] = array(
        'date' => $date,
        'formatted_date' => date('M j', strtotime($date)),
        'revenue' => floatval($daily_revenue ?: 0)
    );
}

// 2. Order type breakdown (today)
$order_types = array(
    'dinein' => 0,
    'pickup' => 0,
    'delivery' => 0
);

$todays_orders = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID
    FROM {$wpdb->posts} p
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
    AND DATE(p.post_date) = %s
", $today));

foreach ($todays_orders as $order_post) {
    $order = wc_get_order($order_post->ID);
    if ($order) {
        $table_number = $order->get_meta('_oj_table_number');
        $delivery_date = $order->get_meta('exwfood_date_deli');
        $order_method = $order->get_meta('exwfood_order_method');
        
        if (!empty($table_number)) {
            $order_types['dinein']++;
        } elseif (!empty($delivery_date) || $order_method === 'delivery') {
            $order_types['pickup']++; // WooFood orders are pickup with delivery time
        } else {
            $order_types['pickup']++; // Regular pickup orders
        }
    }
}

// 3. Peak hours analysis (today's orders by hour)
$peak_hours = array();
for ($hour = 0; $hour < 24; $hour++) {
    $peak_hours[$hour] = 0;
}

foreach ($todays_orders as $order_post) {
    $order_hour = intval(date('H', strtotime($order_post->post_date ?? date('Y-m-d H:i:s'))));
    if (isset($peak_hours[$order_hour])) {
        $peak_hours[$order_hour]++;
    }
}

// 4. Top selling products (last 7 days)
$top_products = $wpdb->get_results("
    SELECT 
        oi.order_item_name as product_name,
        SUM(oim_qty.meta_value) as total_quantity,
        SUM(oim_total.meta_value) as total_revenue
    FROM {$wpdb->prefix}woocommerce_order_items oi
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
    INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
    WHERE oi.order_item_type = 'line_item'
    AND p.post_type = 'shop_order'
    AND p.post_status IN ('wc-completed', 'wc-processing')
    AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY oi.order_item_name
    ORDER BY total_quantity DESC
    LIMIT 5
");

// 5. Performance metrics
$avg_order_value = $today_orders > 0 ? ($today_revenue / $today_orders) : 0;
$yesterday_revenue = $wpdb->get_var($wpdb->prepare("
    SELECT COALESCE(SUM(meta_value), 0)
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
    WHERE pm.meta_key = '_order_total'
    AND p.post_type = 'shop_order'
    AND p.post_status IN ('wc-completed', 'wc-processing')
    AND DATE(p.post_date) = %s
", date('Y-m-d', strtotime('-1 day'))));

$revenue_change = $yesterday_revenue > 0 ? (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100 : 0;

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
        // Convert posts to the format we need and get order items
        $recent_orders = array();
        foreach ($recent_orders_posts as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order) {
                $order_data = array(
                    'ID' => $order->get_id(),
                    'post_date' => $order_post->post_date,
                    'post_status' => 'wc-' . $order->get_status(),
                    'order_total' => $order->get_total(),
                    'table_number' => $order->get_meta('_oj_table_number'),
                    'customer_name' => $order->get_billing_first_name(),
                    'items' => array()
                );
                
                // Get order items with variations using WooCommerce native methods
                foreach ($order->get_items() as $item) {
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
                                // Remove price information from add-ons for manager display
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
                        
                        // Also check for variations in meta (fallback)
                        if (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0) {
                            $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                            $attribute_label = wc_attribute_label($attribute_name);
                            if (!isset($item_data['variations'][$attribute_label])) {
                                $item_data['variations'][$attribute_label] = $meta_value;
                            }
                        }
                    }
                    
                    $order_data['items'][] = $item_data;
                }
                
                $recent_orders[] = $order_data;
            }
        }
}

error_log('Orders Jet Manager: Found ' . count($recent_orders) . ' recent orders using frontend logic');

// Get table status with active order information
// Build table status query with location filtering
$table_status_query = "
    SELECT p.ID, p.post_title, pm_status.meta_value as table_status,
           pm_capacity.meta_value as table_capacity, pm_location.meta_value as table_location,
           pm_woofood_loc.meta_value as woofood_location_id,
           (SELECT COUNT(*) FROM {$wpdb->posts} p2 
            INNER JOIN {$wpdb->postmeta} pm_table ON p2.ID = pm_table.post_id AND pm_table.meta_key = '_oj_table_number'
            WHERE p2.post_type = 'shop_order' 
            AND p2.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold')
            AND pm_table.meta_value = p.post_title) as active_orders_count
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_oj_table_status'
    LEFT JOIN {$wpdb->postmeta} pm_capacity ON p.ID = pm_capacity.post_id AND pm_capacity.meta_key = '_oj_table_capacity'
    LEFT JOIN {$wpdb->postmeta} pm_location ON p.ID = pm_location.post_id AND pm_location.meta_key = '_oj_table_location'
    LEFT JOIN {$wpdb->postmeta} pm_woofood_loc ON p.ID = pm_woofood_loc.post_id AND pm_woofood_loc.meta_key = '_oj_woofood_location_id'
    WHERE p.post_type = 'oj_table'
    AND p.post_status = 'publish'";

// Add location filter if selected
if ($selected_location_id) {
    $table_status_query .= $wpdb->prepare(" AND pm_woofood_loc.meta_value = %d", $selected_location_id);
}

$table_status_query .= " ORDER BY p.post_title ASC LIMIT 20";

$table_status = $wpdb->get_results($table_status_query, ARRAY_A);
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
    
    <?php if ($woofood_locations && !is_wp_error($woofood_locations) && count($woofood_locations) > 0): ?>
    <div class="oj-location-filter" style="float: right; margin-top: 5px;">
        <form method="get" style="display: inline-block;">
            <input type="hidden" name="page" value="orders-jet-manager">
            <label for="location-filter" style="margin-right: 5px;"><?php _e('Filter by Location:', 'orders-jet'); ?></label>
            <select name="location" id="location-filter" onchange="this.form.submit()" style="margin-right: 10px;">
                <option value=""><?php _e('All Locations', 'orders-jet'); ?></option>
                <?php foreach ($woofood_locations as $location): ?>
                    <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($selected_location_id, $location->term_id); ?>>
                        <?php echo esc_html($location->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div style="clear: both;"></div>
    <?php endif; ?>
    
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
                <div class="oj-stat-label">
                    <?php _e('Revenue Today', 'orders-jet'); ?>
                    <?php if ($revenue_change != 0): ?>
                        <span class="oj-trend <?php echo $revenue_change > 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $revenue_change > 0 ? '↗' : '↘'; ?> <?php echo abs(round($revenue_change, 1)); ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number"><?php echo esc_html($active_tables ?: 0); ?>/<?php echo esc_html($total_tables ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Tables Available', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box">
                <div class="oj-stat-number"><?php echo $currency_symbol . number_format($avg_order_value, 2); ?></div>
                <div class="oj-stat-label"><?php _e('Avg Order Value', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Enhanced Analytics Dashboard -->
    <div class="oj-analytics-dashboard">
        <h2><?php _e('Business Analytics', 'orders-jet'); ?></h2>
        
        <div class="oj-analytics-row">
            <!-- Revenue Trend Chart -->
            <div class="oj-analytics-card oj-chart-card">
                <h3><?php _e('Revenue Trend (7 Days)', 'orders-jet'); ?></h3>
                <canvas id="revenueChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Order Types Breakdown -->
            <div class="oj-analytics-card oj-chart-card">
                <h3><?php _e('Today\'s Order Types', 'orders-jet'); ?></h3>
                <canvas id="orderTypesChart" width="300" height="200"></canvas>
            </div>
        </div>
        
        <div class="oj-analytics-row">
            <!-- Peak Hours Heatmap -->
            <div class="oj-analytics-card">
                <h3><?php _e('Peak Hours Today', 'orders-jet'); ?></h3>
                <div class="oj-peak-hours-grid">
                    <?php for ($hour = 0; $hour < 24; $hour++): ?>
                        <?php 
                        $order_count = $peak_hours[$hour];
                        $max_orders = max($peak_hours);
                        $intensity = $max_orders > 0 ? ($order_count / $max_orders) : 0;
                        $opacity = 0.1 + ($intensity * 0.9); // 0.1 to 1.0
                        ?>
                        <div class="oj-hour-cell" 
                             style="background-color: rgba(0, 123, 186, <?php echo $opacity; ?>);"
                             title="<?php echo sprintf(__('%d orders at %02d:00', 'orders-jet'), $order_count, $hour); ?>">
                            <div class="oj-hour-label"><?php echo sprintf('%02d:00', $hour); ?></div>
                            <div class="oj-hour-count"><?php echo $order_count; ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="oj-analytics-card">
                <h3><?php _e('Top Products (7 Days)', 'orders-jet'); ?></h3>
                <div class="oj-top-products">
                    <?php if (!empty($top_products)): ?>
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="oj-product-item">
                                <div class="oj-product-rank">#<?php echo $index + 1; ?></div>
                                <div class="oj-product-info">
                                    <div class="oj-product-name"><?php echo esc_html($product->product_name); ?></div>
                                    <div class="oj-product-stats">
                                        <span class="oj-product-qty"><?php echo esc_html($product->total_quantity); ?> sold</span>
                                        <span class="oj-product-revenue"><?php echo $currency_symbol . number_format($product->total_revenue, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="oj-no-data"><?php _e('No sales data available for the last 7 days.', 'orders-jet'); ?></p>
                    <?php endif; ?>
                </div>
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
            <?php if (class_exists('Orders_Jet_Menu_Integration')): ?>
            <a href="<?php echo admin_url('admin.php?page=orders-jet-menu-analyzer'); ?>" class="button button-secondary">
                <?php _e('Menu Analytics', 'orders-jet'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (class_exists('Orders_Jet_Menu_Integration')): ?>
    <!-- Menu Management Section -->
    <div class="oj-dashboard-section">
        <h2><?php _e('Menu Management', 'orders-jet'); ?></h2>
        
        <?php
        // Get menu analytics
        global $wpdb;
        
        // Get total products
        $total_products = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");
        
        // Get available products
        $available_products = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_oj_menu_availability'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'unavailable')
        ");
        
        // Get featured products
        $featured_products = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND ((pm.meta_key = '_oj_menu_featured' AND pm.meta_value = '1')
                 OR (pm.meta_key = '_oj_menu_priority' AND pm.meta_value = 'featured'))
        ");
        
        // Get unavailable products
        $unavailable_products = $total_products - $available_products;
        ?>
        
        <div class="oj-menu-stats-grid">
            <div class="oj-menu-stat-card">
                <div class="oj-stat-number"><?php echo $total_products; ?></div>
                <div class="oj-stat-label"><?php _e('Total Menu Items', 'orders-jet'); ?></div>
            </div>
            <div class="oj-menu-stat-card oj-stat-success">
                <div class="oj-stat-number"><?php echo $available_products; ?></div>
                <div class="oj-stat-label"><?php _e('Available Items', 'orders-jet'); ?></div>
            </div>
            <div class="oj-menu-stat-card oj-stat-warning">
                <div class="oj-stat-number"><?php echo $unavailable_products; ?></div>
                <div class="oj-stat-label"><?php _e('Unavailable Items', 'orders-jet'); ?></div>
            </div>
            <div class="oj-menu-stat-card oj-stat-featured">
                <div class="oj-stat-number"><?php echo $featured_products; ?></div>
                <div class="oj-stat-label"><?php _e('Featured Items', 'orders-jet'); ?></div>
            </div>
        </div>
        
        <?php if (taxonomy_exists('exwoofood_loc') && $woofood_locations && !is_wp_error($woofood_locations)): ?>
        <div class="oj-location-menu-overview">
            <h3><?php _e('Menu by Location', 'orders-jet'); ?></h3>
            <div class="oj-location-menu-grid">
                <?php foreach ($woofood_locations as $location): 
                    // Get products count for this location
                    $location_products = get_posts(array(
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'numberposts' => -1,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'exwoofood_loc',
                                'field' => 'term_id',
                                'terms' => $location->term_id
                            )
                        ),
                        'fields' => 'ids'
                    ));
                    
                    $location_product_count = count($location_products);
                ?>
                <div class="oj-location-menu-card">
                    <h4>📍 <?php echo esc_html($location->name); ?></h4>
                    <div class="oj-location-stats">
                        <span class="oj-location-count"><?php echo $location_product_count; ?> <?php _e('items', 'orders-jet'); ?></span>
                    </div>
                    <div class="oj-location-actions">
                        <a href="<?php echo admin_url('edit.php?post_type=product&exwoofood_loc=' . $location->slug); ?>" 
                           class="button button-small">
                            <?php _e('View Menu', 'orders-jet'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                        <th><?php _e('Customer/Table', 'orders-jet'); ?></th>
                        <th><?php _e('Items', 'orders-jet'); ?></th>
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
                            <td class="oj-manager-items">
                                <?php if (!empty($order['items'])) : ?>
                                    <?php foreach ($order['items'] as $item) : ?>
                                        <div class="oj-manager-item">
                                            <span class="oj-item-qty"><?php echo esc_html($item['quantity']); ?>x</span>
                                            <strong class="oj-item-name"><?php echo esc_html($item['name']); ?></strong>
                                            
                                            <?php if (!empty($item['variations'])) : ?>
                                                <div class="oj-item-variations-compact">
                                                    <?php foreach ($item['variations'] as $variation_name => $variation_value) : ?>
                                                        <span class="oj-variation-compact"><?php echo esc_html($variation_name); ?>: <?php echo esc_html($variation_value); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['addons'])) : ?>
                                                <div class="oj-item-addons-compact">
                                                    <?php foreach ($item['addons'] as $addon) : ?>
                                                        <span class="oj-addon-compact">+ <?php echo esc_html($addon); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <span class="oj-no-items"><?php _e('No items found', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($currency_symbol . number_format($order['order_total'] ?: 0, 2)); ?></strong></td>
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

    <!-- Ready Orders (Orders ready for pickup) -->
    <div class="oj-dashboard-ready-orders">
        <h2><?php _e('Ready for Pickup', 'orders-jet'); ?> 
            <span class="dashicons dashicons-bell" style="font-size: 20px; color: #d63384; vertical-align: middle; margin-left: 8px;"></span>
        </h2>
        
        <?php
        // Get orders that are ready for pickup (on-hold status)
        $ready_orders_posts = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-on-hold'), // Ready orders
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

        $ready_orders = array();
        foreach ($ready_orders_posts as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order) {
                $ready_orders[] = array(
                    'ID' => $order->get_id(),
                    'post_date' => $order_post->post_date,
                    'post_status' => $order_post->post_status,
                    'order_total' => $order->get_total(),
                    'table_number' => $order->get_meta('_oj_table_number'),
                    'customer_name' => $order->get_billing_first_name(),
                    'session_id' => $order->get_meta('_oj_session_id')
                );
            }
        }
        ?>
        
        <?php if (!empty($ready_orders)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;"><?php _e('Order #', 'orders-jet'); ?></th>
                        <th style="width: 20%;"><?php _e('Customer/Table', 'orders-jet'); ?></th>
                        <th style="width: 15%;"><?php _e('Date', 'orders-jet'); ?></th>
                        <th style="width: 15%;"><?php _e('Total', 'orders-jet'); ?></th>
                        <th style="width: 20%;"><?php _e('Status', 'orders-jet'); ?></th>
                        <th style="width: 15%;"><?php _e('Action', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ready_orders as $order) : ?>
                        <tr style="background: #fff3cd; border-left: 4px solid #ffc107;">
                            <td><strong>#<?php echo esc_html($order['ID']); ?></strong></td>
                            <td>
                                <?php if ($order['customer_name']) : ?>
                                    <?php echo esc_html($order['customer_name']); ?><br>
                                <?php endif; ?>
                                <strong><?php echo esc_html($order['table_number'] ?: __('N/A', 'orders-jet')); ?></strong>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($order['post_date']))); ?></td>
                            <td><strong><?php echo wc_price($order['order_total']); ?></strong></td>
                            <td>
                                <span class="status-badge" style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                    <span class="dashicons dashicons-clock" style="font-size: 12px; vertical-align: middle; margin-right: 4px;"></span>
                                    <?php _e('Ready for Pickup', 'orders-jet'); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-primary oj-deliver-order" data-order-id="<?php echo esc_attr($order['ID']); ?>" style="background: #00a32a; border-color: #00a32a; font-size: 12px; padding: 4px 8px;">
                                    <span class="dashicons dashicons-yes" style="font-size: 14px; vertical-align: middle; margin-right: 4px;"></span>
                                    <?php _e('Mark Delivered', 'orders-jet'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="oj-no-orders" style="text-align: center; padding: 20px; color: #666; background: #f8f9fa; border-radius: 4px;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 32px; margin-bottom: 10px; color: #00a32a;"></span>
                <p><?php _e('No orders ready for pickup.', 'orders-jet'); ?></p>
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
                        
                        <?php if ($table['woofood_location_id'] && class_exists('EX_WooFood')) : 
                            $woofood_loc = get_term($table['woofood_location_id'], 'exwoofood_loc');
                            if ($woofood_loc && !is_wp_error($woofood_loc)) :
                        ?>
                            <div class="oj-table-info" style="color: #0073aa; font-weight: 500;">
                                📍 <?php echo esc_html($woofood_loc->name); ?>
                            </div>
                        <?php endif; endif; ?>
                        
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
.oj-dashboard-tables,
.oj-dashboard-section {
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

/* Menu Management Styles */
.oj-menu-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.oj-menu-stat-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.2s ease;
}

.oj-menu-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.oj-menu-stat-card.oj-stat-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    border-color: #c3e6cb;
}

.oj-menu-stat-card.oj-stat-warning {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-color: #ffeaa7;
}

.oj-menu-stat-card.oj-stat-featured {
    background: linear-gradient(135deg, #ffd700, #ffb347);
    border-color: #ffb347;
}

.oj-menu-stat-card .oj-stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.oj-menu-stat-card .oj-stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.oj-location-menu-overview {
    margin-top: 30px;
}

.oj-location-menu-overview h3 {
    margin-bottom: 15px;
    color: #333;
}

.oj-location-menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.oj-location-menu-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    transition: all 0.2s ease;
}

.oj-location-menu-card:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
}

.oj-location-menu-card h4 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 16px;
}

.oj-location-stats {
    margin-bottom: 15px;
}

.oj-location-count {
    background: #f0f0f1;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: #666;
}

.oj-location-actions .button {
    width: 100%;
}

@media (max-width: 768px) {
    .oj-menu-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .oj-location-menu-grid {
        grid-template-columns: 1fr;
    }
    
    .oj-menu-stat-card .oj-stat-number {
        font-size: 2em;
    }
}

/* Enhanced Analytics Dashboard Styles */
.oj-analytics-dashboard {
    margin: 30px 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.oj-analytics-dashboard h2 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}

.oj-analytics-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.oj-analytics-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.oj-analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.oj-analytics-card h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.oj-chart-card canvas {
    max-width: 100%;
    height: auto;
}

/* Trend indicators */
.oj-trend {
    font-size: 12px;
    font-weight: bold;
    margin-left: 8px;
}

.oj-trend.positive {
    color: #4CAF50;
}

.oj-trend.negative {
    color: #f44336;
}

/* Peak Hours Heatmap */
.oj-peak-hours-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
    margin-top: 10px;
}

.oj-hour-cell {
    aspect-ratio: 1;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #333;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 50px;
}

.oj-hour-cell:hover {
    transform: scale(1.05);
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.oj-hour-label {
    font-weight: bold;
    margin-bottom: 2px;
}

.oj-hour-count {
    font-size: 12px;
    font-weight: bold;
}

/* Top Products */
.oj-top-products {
    max-height: 300px;
    overflow-y: auto;
}

.oj-product-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.oj-product-item:hover {
    background-color: #f8f9fa;
}

.oj-product-item:last-child {
    border-bottom: none;
}

.oj-product-rank {
    width: 30px;
    height: 30px;
    background: #007cba;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
    margin-right: 15px;
    flex-shrink: 0;
}

.oj-product-info {
    flex: 1;
}

.oj-product-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.oj-product-stats {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #666;
}

.oj-product-qty {
    color: #4CAF50;
    font-weight: 500;
}

.oj-product-revenue {
    color: #007cba;
    font-weight: 500;
}

.oj-no-data {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

/* Enhanced stat boxes */
.oj-stat-box {
    position: relative;
}

.oj-stat-box .oj-stat-label {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 5px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .oj-analytics-row {
        grid-template-columns: 1fr;
    }
    
    .oj-peak-hours-grid {
        grid-template-columns: repeat(6, 1fr);
    }
    
    .oj-hour-cell {
        min-height: 40px;
        font-size: 9px;
    }
    
    .oj-product-stats {
        flex-direction: column;
        gap: 5px;
    }
}

@media (max-width: 480px) {
    .oj-analytics-dashboard {
        padding: 15px;
        margin: 15px 0;
    }
    
    .oj-analytics-card {
        padding: 15px;
    }
    
    .oj-peak-hours-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Refresh button functionality
    $('.oj-refresh-dashboard').on('click', function() {
        location.reload();
    });
    
    // Initialize Charts
    initializeCharts();
    
    function initializeCharts() {
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const revenueData = <?php echo json_encode($revenue_trend); ?>;
            
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueData.map(item => item.formatted_date),
                    datasets: [{
                        label: '<?php _e('Revenue', 'orders-jet'); ?>',
                        data: revenueData.map(item => item.revenue),
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 123, 186, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#007cba',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '<?php echo $currency_symbol; ?>' + value.toFixed(2);
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    elements: {
                        point: {
                            hoverBackgroundColor: '#007cba'
                        }
                    }
                }
            });
        }
        
        // Order Types Pie Chart
        const orderTypesCtx = document.getElementById('orderTypesChart');
        if (orderTypesCtx) {
            const orderTypesData = <?php echo json_encode($order_types); ?>;
            
            new Chart(orderTypesCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        '<?php _e('Dine In', 'orders-jet'); ?>',
                        '<?php _e('Pickup', 'orders-jet'); ?>'
                    ],
                    datasets: [{
                        data: [orderTypesData.dinein, orderTypesData.pickup],
                        backgroundColor: [
                            '#4CAF50',
                            '#9C27B0'
                        ],
                        borderColor: [
                            '#ffffff',
                            '#ffffff'
                        ],
                        borderWidth: 3,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
    }
    
    // Enhanced hover effects for analytics cards
    $('.oj-analytics-card').hover(
        function() {
            $(this).find('h3').css('color', '#007cba');
        },
        function() {
            $(this).find('h3').css('color', '#333');
        }
    );
    
    // Peak hours cell click functionality
    $('.oj-hour-cell').on('click', function() {
        const hour = $(this).find('.oj-hour-label').text();
        const count = $(this).find('.oj-hour-count').text();
        
        if (count > 0) {
            alert('<?php _e('Orders at', 'orders-jet'); ?> ' + hour + ': ' + count + ' <?php _e('orders', 'orders-jet'); ?>');
        }
    });
});
</script>