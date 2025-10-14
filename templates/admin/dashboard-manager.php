<?php
/**
 * Orders Jet - Manager Orders Management Template
 * Built on proven Kitchen Dashboard foundation with manager-specific customizations
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions - Manager access
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Include the manager navigation
include ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-navigation.php';

// Manager Dashboard - No form handling needed

// Get user information
$current_user = wp_get_current_user();
$today = OJ_Universal_Time_Manager::now_formatted('Y-m-d');
$today_formatted = OJ_Universal_Time_Manager::now_formatted('F j, Y');

// Get real data from WooCommerce orders
global $wpdb;

// Get active orders using a more precise approach to avoid duplicates
$active_orders = array();

// Get ALL in-progress/processing orders - ultra simple
if (function_exists('wc_get_orders')) {
    error_log('Orders Jet Kitchen: Using ultra-simple approach...');
    
    $wc_orders = wc_get_orders(array(
        'status' => array('pending', 'processing', 'in-progress', 'on-hold', 'completed', 'cancelled', 'refunded'),
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC' // Show newest first for managers
    ));
    
    error_log('Orders Jet Kitchen: Found ' . count($wc_orders) . ' orders');
    
    // Convert to simple format with enhanced pickup logic
    $active_orders = array();
    foreach ($wc_orders as $wc_order) {
        $table_number = $wc_order->get_meta('_oj_table_number');
        
        // DEBUG: Log all meta fields for orders without table numbers (pickup/delivery orders)
        if (empty($table_number)) {
            error_log('Orders Jet Kitchen DEBUG: Order #' . $wc_order->get_id() . ' - All meta fields:');
            $all_meta = $wc_order->get_meta_data();
            foreach ($all_meta as $meta) {
                $key = $meta->get_data()['key'];
                $value = $meta->get_data()['value'];
                if (strpos($key, 'delivery') !== false || strpos($key, 'exwf') !== false || strpos($key, 'date') !== false || strpos($key, 'time') !== false) {
                    error_log('Orders Jet Kitchen DEBUG: ' . $key . ' = ' . print_r($value, true));
                }
            }
        }
        
        // Import WooFood delivery time to our custom fields (for existing orders)
        OJ_Delivery_Time_Manager::import_woofood_time($wc_order);
        
        // Get delivery/pickup time using our new manager
        $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($wc_order);
        $has_delivery_time = $delivery_info !== false;
        
        error_log('Orders Jet Manager DEBUG: Order #' . $wc_order->get_id() . ' - Delivery info: ' . ($has_delivery_time ? json_encode($delivery_info) : 'NONE'));
        
        // Determine order type and badge
        if (!empty($table_number)) {
            // Dine-in order
            $order_type = 'dinein';
            $order_type_label = __('DINE IN', 'orders-jet');
            $order_type_icon = '🍽️';
            $order_type_class = 'oj-order-type-dinein';
        } elseif ($has_delivery_time) {
            // Pickup order with scheduled time
            $order_type = 'pickup_timed';
            $order_type_icon = '🕒';
            $order_type_class = 'oj-order-type-pickup-timed';
            
            // Check if it's today or upcoming
            $today = date('Y-m-d');
            $delivery_date = date('Y-m-d', $delivery_info['timestamp']);
            
            if ($delivery_date === $today) {
                // Today's pickup - show only time
                $order_type_label = __('PICK UP', 'orders-jet') . ' ' . $delivery_info['time_only'];
            } else {
                // Upcoming pickup - show date + time
                $order_type_label = __('PICK UP', 'orders-jet') . ' ' . $delivery_info['formatted'];
            }
            
            error_log('Orders Jet Manager: TIMED pickup order #' . $wc_order->get_id() . ' - ' . $order_type_label);
        } else {
            // Regular pickup order (no scheduled time)
            $order_type = 'pickup';
            $order_type_label = __('PICK UP', 'orders-jet');
            $order_type_icon = '🥡';
            $order_type_class = 'oj-order-type-pickup';
            
            error_log('Orders Jet Manager: REGULAR pickup order #' . $wc_order->get_id());
        }
        
        $active_orders[] = array(
            'ID' => $wc_order->get_id(),
            'post_date' => OJ_Universal_Time_Manager::format(OJ_Universal_Time_Manager::get_order_created_timestamp($wc_order), 'Y-m-d H:i:s'),
            'post_status' => 'wc-' . $wc_order->get_status(),
            'order_total' => $wc_order->get_total(),
            'table_number' => $table_number,
            'customer_name' => $wc_order->get_billing_first_name(),
            'session_id' => $wc_order->get_meta('_oj_session_id'),
            'delivery_date' => $delivery_date,
            'delivery_time' => $delivery_time,
            // Enhanced badge logic
            'order_type' => $order_type,
            'order_type_label' => $order_type_label,
            'order_type_icon' => $order_type_icon,
            'order_type_class' => $order_type_class
        );
    }
} else {
    // Simple fallback
    error_log('Orders Jet Kitchen: Using simple fallback...');
    
    $active_orders_posts = get_posts(array(
        'post_type' => 'shop_order',
        'post_status' => array('wc-processing', 'wc-in-progress'),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC'
    ));
    
    $active_orders = array();
    foreach ($active_orders_posts as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order) {
            $table_number = $order->get_meta('_oj_table_number');
            
            // Import WooFood delivery time to our custom fields (for existing orders)
            OJ_Delivery_Time_Manager::import_woofood_time($order);
            
            // Get delivery/pickup time using our new manager
            $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($order);
            $has_delivery_time = $delivery_info !== false;
            
            // Determine order type and badge
            if (!empty($table_number)) {
                // Dine-in order
                $order_type = 'dinein';
                $order_type_label = __('DINE IN', 'orders-jet');
                $order_type_icon = '🍽️';
                $order_type_class = 'oj-order-type-dinein';
            } elseif ($has_delivery_time) {
                // Pickup order with scheduled time
                $order_type = 'pickup_timed';
                $order_type_icon = '🕒';
                $order_type_class = 'oj-order-type-pickup-timed';
                
                // Check if it's today or upcoming
                $today = date('Y-m-d');
                $delivery_date = date('Y-m-d', $delivery_info['timestamp']);
                
                if ($delivery_date === $today) {
                    // Today's pickup - show only time
                    $order_type_label = __('PICK UP', 'orders-jet') . ' ' . $delivery_info['time_only'];
                } else {
                    // Upcoming pickup - show date + time
                    $order_type_label = __('PICK UP', 'orders-jet') . ' ' . $delivery_info['formatted'];
                }
            } else {
                // Regular pickup order (no scheduled time)
                $order_type = 'pickup';
                $order_type_label = __('PICK UP', 'orders-jet');
                $order_type_icon = '🥡';
                $order_type_class = 'oj-order-type-pickup';
            }
            
            $active_orders[] = array(
                    'ID' => $order->get_id(),
                    'post_date' => OJ_Universal_Time_Manager::format(OJ_Universal_Time_Manager::parse_to_local_timestamp($order_post->post_date, 'wordpress_utc'), 'Y-m-d H:i:s'),
                    'post_status' => 'wc-' . $order->get_status(),
                    'order_total' => $order->get_total(),
                'table_number' => $table_number,
                    'customer_name' => $order->get_billing_first_name(),
                'session_id' => $order->get_meta('_oj_session_id'),
                'delivery_date' => $delivery_date,
                'delivery_time' => $delivery_time,
                // Enhanced badge logic
                'order_type' => $order_type,
                'order_type_label' => $order_type_label,
                'order_type_icon' => $order_type_icon,
                'order_type_class' => $order_type_class
            );
        }
    }
}

// Simple logical sorting: Dine-in first, then pickup orders at bottom, sorted by time ascending
usort($active_orders, function($a, $b) {
    // 1. Dine-in orders (table orders) come first
    $a_is_dinein = !empty($a['table_number']);
    $b_is_dinein = !empty($b['table_number']);
    
    if ($a_is_dinein && !$b_is_dinein) {
        return -1; // Dine-in comes first
    }
    if ($b_is_dinein && !$a_is_dinein) {
        return 1; // Dine-in comes first
    }
    
    // 2. Within same type (both dine-in or both pickup), sort by date/time ascending
    $a_time = strtotime($a['post_date']);
    $b_time = strtotime($b['post_date']);
    
    // For pickup orders with delivery time, use delivery time for sorting
    if (!$a_is_dinein && !empty($a['delivery_time'])) {
        $today = date('Y-m-d');
        $a_delivery_datetime = $today . ' ' . $a['delivery_time'];
        $a_time = strtotime($a_delivery_datetime);
    }
    
    if (!$b_is_dinein && !empty($b['delivery_time'])) {
        $today = date('Y-m-d');
        $b_delivery_datetime = $today . ' ' . $b['delivery_time'];
        $b_time = strtotime($b_delivery_datetime);
    }
    
    return $a_time - $b_time; // Ascending order (earliest first)
});

error_log('Orders Jet Kitchen: Final order count: ' . count($active_orders));
error_log('Orders Jet Kitchen: Order IDs: ' . implode(', ', array_column($active_orders, 'ID')));

// Get order items for each order using WooCommerce methods (same as frontend)
$orders_with_items = array();
foreach ($active_orders as $order) {
    $wc_order = wc_get_order($order['ID']);
    if ($wc_order) {
        // Order type is already set in the simple logic above - no need for complex detection
        
        $order_items = array();
        foreach ($wc_order->get_items() as $item) {
            // Get comprehensive item info for managers
            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'subtotal' => $item->get_subtotal(),
                'unit_price' => $item->get_total() / max($item->get_quantity(), 1),
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
        
        // Create a new order array with items and financial info for managers
        $order_with_items = $order;
        $order_with_items['items'] = $order_items;
        $order_with_items['order_subtotal'] = $wc_order->get_subtotal();
        $order_with_items['order_tax'] = $wc_order->get_total_tax();
        $order_with_items['order_shipping'] = $wc_order->get_shipping_total();
        $order_with_items['order_discount'] = $wc_order->get_discount_total();
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

// Manager stats (comprehensive order status tracking)
$pending_orders = 0;
$processing_orders = 0;
$ready_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;
$refunded_orders = 0;

foreach ($active_orders as $order) {
    switch ($order['post_status']) {
        case 'wc-pending':
            $pending_orders++;
            break;
        case 'wc-processing':
        case 'wc-in-progress':
            $processing_orders++;
            break;
        case 'wc-on-hold':
            $ready_orders++;
            break;
        case 'wc-completed':
            $completed_orders++;
            break;
        case 'wc-cancelled':
            $cancelled_orders++;
            break;
        case 'wc-refunded':
            $refunded_orders++;
            break;
    }
}

// Get completed orders (on-hold) - ultra simple
if (function_exists('wc_get_orders')) {
    $completed_wc_orders = wc_get_orders(array(
        'status' => array('on-hold'), // Ready orders
        'limit' => -1
    ));
    $completed_orders = count($completed_wc_orders);
} else {
    $completed_orders_posts = get_posts(array(
        'post_type' => 'shop_order',
        'post_status' => array('wc-on-hold'),
        'posts_per_page' => -1
    ));
    $completed_orders = count($completed_orders_posts);
}

// Debug logging
error_log('Orders Jet Kitchen Stats: In Progress Orders = ' . $in_progress_orders);
error_log('Orders Jet Kitchen Stats: Completed Orders = ' . $completed_orders);
error_log('Orders Jet Kitchen Stats: Active Orders Count = ' . count($active_orders));

// Additional debug for completed orders
if (function_exists('wc_get_orders')) {
    error_log('Orders Jet Kitchen Stats: Using wc_get_orders for completed orders');
    if (isset($completed_wc_orders)) {
        error_log('Orders Jet Kitchen Stats: Found ' . count($completed_wc_orders) . ' on-hold orders');
        foreach ($completed_wc_orders as $completed_order) {
            error_log('Orders Jet Kitchen Stats: On-hold Order #' . $completed_order->get_id() . ' - Status: ' . $completed_order->get_status() . ' - Table: ' . $completed_order->get_meta('_oj_table_number'));
        }
    }
} else {
    error_log('Orders Jet Kitchen Stats: Using get_posts fallback for completed orders');
}

// Format currency
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="wrap">
    <div class="oj-header-container">
        <div class="oj-header-left">
            <h2 class="oj-section-title">
                <span class="dashicons dashicons-clipboard" style="font-size: 24px; vertical-align: middle; margin-right: 8px;"></span>
                <?php _e('Orders Management', 'orders-jet'); ?>
            </h2>
            <p class="oj-last-updated">
                <span class="dashicons dashicons-clock" style="font-size: 14px; vertical-align: middle; margin-right: 4px;"></span>
                       <strong><?php _e('Last Updated:', 'orders-jet'); ?></strong> <?php echo esc_html(OJ_Universal_Time_Manager::now_formatted('g:i:s A')); ?>
            </p>
        </div>
        <div class="oj-header-right">
            <button type="button" class="oj-check-orders-btn" onclick="location.reload();">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Orders', 'orders-jet'); ?>
    </button>
    </div>
    </div>
    
    <hr class="wp-header-end">

    <!-- Manager Stats -->
    <div class="oj-kitchen-stats">
        <h2><?php echo sprintf(__('Business Overview - %s', 'orders-jet'), $today_formatted); ?></h2>
        
        <div class="oj-stats-row">
            <div class="oj-stat-box revenue">
                <div class="oj-stat-number"><?php echo $currency_symbol . number_format($today_revenue ?: 0, 2); ?></div>
                <div class="oj-stat-label"><?php _e('Revenue Today', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box processing">
                <div class="oj-stat-number"><?php echo esc_html($processing_orders); ?></div>
                <div class="oj-stat-label"><?php _e('Cooking', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box ready">
                <div class="oj-stat-number"><?php echo esc_html($ready_orders); ?></div>
                <div class="oj-stat-label"><?php _e('Ready', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box completed">
                <div class="oj-stat-number"><?php echo esc_html($completed_orders); ?></div>
                <div class="oj-stat-label"><?php _e('Completed', 'orders-jet'); ?></div>
            </div>
        </div>
        
        <!-- Secondary Stats Row -->
        <div class="oj-stats-row oj-secondary-stats">
            <div class="oj-stat-box pending">
                <div class="oj-stat-number"><?php echo esc_html($pending_orders); ?></div>
                <div class="oj-stat-label"><?php _e('Pending Payment', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box tables">
                <div class="oj-stat-number"><?php echo esc_html($active_tables ?: 0); ?>/<?php echo esc_html($total_tables ?: 0); ?></div>
                <div class="oj-stat-label"><?php _e('Tables Active', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box cancelled">
                <div class="oj-stat-number"><?php echo esc_html($cancelled_orders + $refunded_orders); ?></div>
                <div class="oj-stat-label"><?php _e('Cancelled/Refunded', 'orders-jet'); ?></div>
            </div>
            <div class="oj-stat-box avg-value">
                <div class="oj-stat-number"><?php echo $currency_symbol . number_format($today_orders > 0 ? ($today_revenue / $today_orders) : 0, 2); ?></div>
                <div class="oj-stat-label"><?php _e('Avg Order Value', 'orders-jet'); ?></div>
            </div>
        </div>
    </div>

    <!-- Manager Filters (Kitchen-style + Status additions) -->
    <div class="oj-order-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <span class="oj-filter-icon">📋</span>
            <span class="oj-filter-label"><?php _e('All Orders', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="dinein">
            <span class="oj-filter-icon">🍽️</span>
            <span class="oj-filter-label"><?php _e('Dining', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pickup-all">
            <span class="oj-filter-icon">🥡</span>
            <span class="oj-filter-label"><?php _e('All Pickup', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pickup-immediate">
            <span class="oj-filter-icon">⚡</span>
            <span class="oj-filter-label"><?php _e('Immediate Pickup', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pickup-today">
            <span class="oj-filter-icon">🕒</span>
            <span class="oj-filter-label"><?php _e('Today Pickup', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pickup-upcoming">
            <span class="oj-filter-icon">📅</span>
            <span class="oj-filter-label"><?php _e('Upcoming Pickup', 'orders-jet'); ?></span>
        </button>
        <!-- Additional Manager Status Filters -->
        <button class="oj-filter-btn" data-filter="ready">
            <span class="oj-filter-icon">✅</span>
            <span class="oj-filter-label"><?php _e('Ready', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pending">
            <span class="oj-filter-icon">⏳</span>
            <span class="oj-filter-label"><?php _e('Pending Payment', 'orders-jet'); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="cancelled">
            <span class="oj-filter-icon">❌</span>
            <span class="oj-filter-label"><?php _e('Cancelled', 'orders-jet'); ?></span>
        </button>
    </div>
    
    <!-- Order Queue -->
    <div class="oj-dashboard-orders">
        <h2><?php _e('Order Management', 'orders-jet'); ?></h2>
        
        <?php if (!empty($active_orders)) : ?>
            <div class="oj-kitchen-cards-container">
                <?php foreach ($active_orders as $order) : ?>
                    <div class="oj-kitchen-card" 
                         data-order-id="<?php echo esc_attr($order['ID']); ?>"
                         data-order-type="<?php echo esc_attr($order['order_type']); ?>"
                         data-delivery-date="<?php echo esc_attr($order['delivery_date'] ?? ''); ?>"
                         data-delivery-date-formatted="<?php echo esc_attr(!empty($order['delivery_date']) ? date('Y-m-d', strtotime($order['delivery_date'])) : ''); ?>"
                         data-delivery-time="<?php echo esc_attr($order['delivery_time'] ?? ''); ?>"
                         data-table-number="<?php echo esc_attr($order['table_number'] ?? ''); ?>">
                        <!-- Card Header -->
                        <div class="oj-card-header">
                            <div class="oj-card-info">
                                <div class="oj-table-info">
                                    <?php if ($order['table_number']) : ?>
                                        <span class="oj-table-number">Table <?php echo esc_html($order['table_number']); ?></span>
                                    <?php elseif ($order['order_type'] === 'delivery') : ?>
        <?php
                                        // Get delivery address for delivery orders
                                        $wc_order = wc_get_order($order['ID']);
                                        $delivery_address = '';
                                        if ($wc_order) {
                                            $delivery_address = $wc_order->get_meta('_oj_delivery_address') ?: 
                                                             $wc_order->get_meta('_exwf_delivery_address') ?: 
                                                             $wc_order->get_formatted_shipping_address();
                                        }
                                        ?>
                                        <span class="oj-delivery-address">
                                            <span class="dashicons dashicons-location-alt" style="font-size: 14px; margin-right: 4px;"></span>
                                            <?php echo esc_html($delivery_address ?: __('Delivery Address', 'orders-jet')); ?>
                                        </span>
                                    <?php endif; ?>
                                    <!-- Order Type Badge - SIMPLE VERSION -->
                                    <div class="oj-order-type-badge <?php echo esc_attr($order['order_type_class']); ?>">
                                        <span class="oj-type-icon"><?php echo esc_html($order['order_type_icon']); ?></span>
                                        <span class="oj-type-label"><?php echo esc_html($order['order_type_label']); ?></span>
            </div>
            </div>
                                <div class="oj-order-time">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php echo esc_html(OJ_Universal_Time_Manager::format(strtotime($order['post_date']), 'g:i A')); ?>
            </div>
            </div>
                            <div class="oj-card-status">
                                <?php if ($order['post_status'] === 'wc-pending') : ?>
                                    <span class="oj-status-badge pending">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php _e('Pending Payment', 'orders-jet'); ?>
                                    </span>
                                <?php elseif ($order['post_status'] === 'wc-processing') : ?>
                                    <?php
                                    // Enhanced COOKING badge with countdown for timed pickup orders
                                    $wc_order = wc_get_order($order['ID']);
                                    $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($wc_order);
                                    
                                    if ($delivery_info) {
                                        // Get countdown information
                                        $countdown_info = OJ_Delivery_Time_Manager::get_time_remaining($wc_order);
                                        
                                        if ($countdown_info) {
                                            $countdown_text = $countdown_info['short_text'];
                                            $countdown_class = $countdown_info['class'];
                                            
                                            // Debug the countdown calculation
                                            error_log('Orders Jet Manager: Order #' . $order['ID'] . ' Countdown Debug:');
                                            error_log('- Current time: ' . date('Y-m-d H:i:s', $countdown_info['current_time']));
                                            error_log('- Delivery time: ' . date('Y-m-d H:i:s', $countdown_info['delivery_time']));
                                            error_log('- Diff seconds: ' . $countdown_info['diff_seconds']);
                                            error_log('- Status: ' . $countdown_info['status']);
                                            error_log('- Text: ' . $countdown_text);
                                            ?>
                                            <span class="oj-status-badge processing <?php echo esc_attr($countdown_class); ?>">
                                                <span class="dashicons dashicons-admin-tools"></span>
                                                <?php echo esc_html($countdown_text); ?>
                                            </span>
                                            <?php
                                        } else {
                                            ?>
                                            <span class="oj-status-badge processing">
                                                <span class="dashicons dashicons-admin-tools"></span>
                                                <?php _e('COOKING', 'orders-jet'); ?>
                                            </span>
                                            <?php
                                        }
                                    } else {
                                        // Regular COOKING badge for orders without delivery time
                                        ?>
                                        <span class="oj-status-badge processing">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                            <?php _e('COOKING', 'orders-jet'); ?>
                                        </span>
                                        <?php
                                    }
                                    ?>
                                <?php elseif ($order['post_status'] === 'wc-on-hold') : ?>
                                    <span class="oj-status-badge ready">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Ready to Serve', 'orders-jet'); ?>
                                    </span>
                                <?php elseif ($order['post_status'] === 'wc-completed') : ?>
                                    <span class="oj-status-badge completed">
                                        <span class="dashicons dashicons-thumbs-up"></span>
                                        <?php _e('Completed', 'orders-jet'); ?>
                                    </span>
                                <?php elseif ($order['post_status'] === 'wc-cancelled') : ?>
                                    <span class="oj-status-badge cancelled">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php _e('Cancelled', 'orders-jet'); ?>
                                    </span>
                                <?php elseif ($order['post_status'] === 'wc-refunded') : ?>
                                    <span class="oj-status-badge refunded">
                                        <span class="dashicons dashicons-undo"></span>
                                        <?php _e('Refunded', 'orders-jet'); ?>
                                    </span>
                                <?php endif; ?>
    </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="oj-customer-info">
                                <?php if ($order['customer_name']) : ?>
                                <div class="oj-customer-name"><?php echo esc_html($order['customer_name']); ?></div>
                                <?php else : ?>
                                <div class="oj-customer-name">Guest</div>
                                <?php endif; ?>
                            <div class="oj-order-number">#<?php echo esc_html($order['ID']); ?></div>
                        </div>

                        <!-- Card Body - Items -->
                        <div class="oj-card-body">
                                <?php if (!empty($order['items'])) : ?>
                                <div class="oj-card-items">
                                    <?php foreach ($order['items'] as $item) : ?>
                                        <div class="oj-card-item">
                                            <div class="oj-item-header">
                                                <div class="oj-item-info">
                                                    <span class="oj-item-qty"><?php echo esc_html($item['quantity']); ?>×</span>
                                                    <span class="oj-item-name"><?php echo esc_html($item['name']); ?></span>
                                                </div>
                                                <div class="oj-item-pricing">
                                                    <span class="oj-item-price"><?php echo wc_price($item['total']); ?></span>
                                                    <?php if ($item['quantity'] > 1) : ?>
                                                        <span class="oj-unit-price"><?php echo wc_price($item['unit_price']); ?> each</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($item['variations'])) : ?>
                                                <div class="oj-item-variations">
                                                    <?php foreach ($item['variations'] as $variation_name => $variation_value) : ?>
                                                        <span class="oj-variation-tag"><?php echo esc_html($variation_name); ?>: <?php echo esc_html($variation_value); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['addons'])) : ?>
                                                <div class="oj-item-addons">
                                                    <span class="oj-addons-label">+</span>
                                                    <?php foreach ($item['addons'] as $addon) : ?>
                                                        <span class="oj-addon-tag"><?php echo esc_html($addon); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['notes'])) : ?>
                                                <div class="oj-item-notes">
                                                    <span class="dashicons dashicons-format-quote"></span>
                                                    <span class="oj-notes-text"><?php echo esc_html($item['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Order Total Section (Manager Only) -->
                                <div class="oj-order-total-section">
                                    <?php if (isset($order['order_subtotal']) && $order['order_subtotal'] != $order['order_total']) : ?>
                                    <div class="oj-order-subtotal">
                                        <span class="oj-total-label"><?php _e('Subtotal:', 'orders-jet'); ?></span>
                                        <span class="oj-total-value"><?php echo wc_price($order['order_subtotal']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($order['order_tax']) && $order['order_tax'] > 0) : ?>
                                    <div class="oj-order-tax">
                                        <span class="oj-total-label"><?php _e('Tax:', 'orders-jet'); ?></span>
                                        <span class="oj-total-value"><?php echo wc_price($order['order_tax']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($order['order_shipping']) && $order['order_shipping'] > 0) : ?>
                                    <div class="oj-order-shipping">
                                        <span class="oj-total-label"><?php _e('Shipping:', 'orders-jet'); ?></span>
                                        <span class="oj-total-value"><?php echo wc_price($order['order_shipping']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($order['order_discount']) && $order['order_discount'] > 0) : ?>
                                    <div class="oj-order-discount">
                                        <span class="oj-total-label"><?php _e('Discount:', 'orders-jet'); ?></span>
                                        <span class="oj-total-value">-<?php echo wc_price($order['order_discount']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="oj-order-total">
                                        <span class="oj-total-label"><?php _e('Total:', 'orders-jet'); ?></span>
                                        <span class="oj-total-value oj-total-final"><?php echo wc_price($order['order_total']); ?></span>
                                    </div>
                                </div>
                                <?php else : ?>
                                <div class="oj-no-items"><?php _e('No items found', 'orders-jet'); ?></div>
                                <?php endif; ?>
                        </div>

                        <!-- Card Footer - Manager Actions -->
                        <div class="oj-card-footer">
                            <div class="oj-manager-actions">
                                <button class="oj-action-btn oj-view-details" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('View Details', 'orders-jet'); ?>
                                </button>
                                <button class="oj-action-btn oj-customer-info" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e('Customer', 'orders-jet'); ?>
                                </button>
                                <?php if ($order['post_status'] === 'wc-processing') : ?>
                                    <button class="oj-action-btn oj-priority" data-order-id="<?php echo esc_attr($order['ID']); ?>">
                                        <span class="dashicons dashicons-flag"></span>
                                        <?php _e('Priority', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="oj-no-orders">
                <p><?php _e('No active orders found.', 'orders-jet'); ?></p>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
/* Header Layout */
.oj-header-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    gap: 20px;
}

.oj-header-left {
    flex: 1;
}

.oj-header-right {
    flex-shrink: 0;
}

.oj-last-updated {
    color: #6c757d;
    font-size: 13px;
    margin: 8px 0 0 0;
}

.oj-check-orders-btn {
    background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0, 124, 186, 0.3);
}

.oj-check-orders-btn:hover {
    background: linear-gradient(135deg, #005a87 0%, #004666 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 124, 186, 0.4);
}

.oj-check-orders-btn .dashicons {
    font-size: 16px;
    animation: spin 2s linear infinite;
}

.oj-check-orders-btn:hover .dashicons {
    animation-duration: 0.5s;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.oj-kitchen-stats,
.oj-order-queue {
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
    
    /* Mobile filter styles */
    .oj-order-filters {
        padding: 10px;
        gap: 8px;
    }
    
    .oj-filter-btn {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    .oj-filter-icon {
        font-size: 14px;
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

/* Kitchen Cards Layout */
.oj-kitchen-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.oj-kitchen-card {
    background: #ffffff;
    border: 2px solid #e1e5e9;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    overflow: hidden;
}

.oj-kitchen-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

/* Card Header */
.oj-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.oj-card-info {
    flex: 1;
}

.oj-table-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.oj-table-number {
    background: #007cba;
    color: #ffffff;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

/* Order Type Badge - styled like order number */
.oj-order-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.oj-order-type-badge .oj-type-icon {
    font-size: 12px;
}

.oj-order-type-badge .oj-type-label {
    font-size: 11px;
}

/* Order Type Colors */
.oj-order-type-badge.oj-order-type-dinein {
    background: #4CAF50 !important;
    color: #ffffff !important;
}

.oj-order-type-badge.oj-order-type-delivery {
    background: #2196F3 !important;
    color: #ffffff !important;
}

.oj-order-type-badge.oj-order-type-pickup {
    background: #9C27B0 !important;
    color: #ffffff !important;
    font-weight: bold;
}

.oj-order-type-badge.oj-order-type-pickup-timed {
    background: #FF5722 !important;
    color: #ffffff !important;
    font-weight: bold;
}

.oj-order-type-badge.oj-order-type-unknown {
    background: #757575 !important;
    color: #ffffff !important;
}

/* Order Filters - Kitchen Dashboard */
.oj-order-filters {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    position: sticky;
    top: 32px;
    z-index: 100;
}

.oj-order-filters::-webkit-scrollbar {
    display: none;
}

.oj-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    background: white;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    color: #333;
    text-decoration: none;
    min-width: auto;
    box-shadow: none;
}

.oj-filter-btn:hover {
    border-color: #007cba;
    background: #f8f9fa;
    color: #007cba;
    transform: translateY(-1px);
}

.oj-filter-btn.active {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.oj-filter-btn.active:hover {
    background: #005a87;
    color: white;
}

.oj-filter-icon {
    font-size: 16px;
}

.oj-filter-label {
    font-weight: 600;
}

/* Visual indicators for upcoming orders */
.oj-kitchen-card[data-order-type="pickup_timed"] {
    position: relative;
}

.oj-kitchen-card[data-order-type="pickup_timed"]:not([data-delivery-date-formatted=""]) {
    /* Add a subtle left border for timed orders */
    border-left: 4px solid transparent;
}

.oj-kitchen-card[data-order-type="pickup_timed"][data-delivery-date-formatted] {
    /* Today's orders - green left border */
    border-left-color: #4CAF50;
}

/* Upcoming orders styling - will be applied via JavaScript */
.oj-kitchen-card.oj-upcoming-order {
    border-left-color: #673AB7 !important;
    opacity: 1;
}

.oj-kitchen-card.oj-upcoming-order .oj-card-header {
    background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%);
}

.oj-kitchen-card.oj-upcoming-order .oj-order-type-badge {
    background: #673AB7 !important;
    color: #ffffff !important;
    font-weight: bold;
}

/* Customer Info */
.oj-customer-info {
    padding: 12px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.oj-customer-name {
    font-weight: 500;
    color: #495057;
    margin-bottom: 4px;
}

.oj-order-number {
    background: #6c757d;
    color: #ffffff;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.oj-order-time {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #6c757d;
    font-size: 14px;
}

/* Card Body */
.oj-card-body {
    padding: 20px;
    background: #f8f9fa;
}

.oj-card-items {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.oj-card-item {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #007cba;
}

.oj-item-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.oj-item-qty {
    background: #007cba;
    color: #ffffff;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    min-width: 32px;
    text-align: center;
}

.oj-item-name {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    flex: 1;
}

.oj-item-variations {
    margin-left: 44px;
    margin-bottom: 8px;
}

.oj-variation-tag {
    background: #87CEEB;
    color: #ffffff;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    margin-right: 6px;
}

.oj-item-addons {
    margin-left: 44px;
    margin-bottom: 8px;
}

.oj-addons-label {
    background: #4CAF50;
    color: #ffffff;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    margin-right: 8px;
}

.oj-addon-tag {
    background: #FFF3CD;
    color: #856404;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    margin-right: 6px;
}

.oj-item-notes {
    background: #F8D7DA;
    color: #721C24;
    padding: 12px;
    border-radius: 8px;
    margin-left: 44px;
    border-left: 3px solid #DC3545;
}

.oj-notes-text {
    font-style: italic;
    font-size: 14px;
    line-height: 1.4;
}

.oj-table-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.oj-table-number {
    background: #007cba;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
}

.oj-delivery-address {
    background: #2196F3;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.oj-order-number {
    background: #6c757d;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.oj-customer-name {
    font-weight: 500;
    color: #495057;
    margin-bottom: 4px;
}

.oj-order-time {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #6c757d;
    font-size: 13px;
}

.oj-order-time .dashicons {
    font-size: 14px;
}

.oj-card-status {
    margin-left: 16px;
}

.oj-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    line-height: 1;
}

.oj-status-badge.pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.oj-status-badge.processing {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* Countdown Status Classes */
.oj-status-badge.oj-countdown-upcoming {
    background: #e3f2fd;
    color: #1565c0;
    border: 1px solid #bbdefb;
}

.oj-status-badge.oj-countdown-soon {
    background: #fff3e0;
    color: #ef6c00;
    border: 1px solid #ffcc02;
}

.oj-status-badge.oj-countdown-urgent {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
    animation: pulse-urgent 2s infinite;
}

.oj-status-badge.oj-countdown-overdue {
    background: #ffcdd2;
    color: #b71c1c;
    border: 1px solid #f44336;
    animation: pulse-overdue 1s infinite;
}

@keyframes pulse-urgent {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes pulse-overdue {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Additional Status Badges for Manager */
.oj-status-badge.ready {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.oj-status-badge.completed {
    background: #e2e3e5;
    color: #383d41;
    border: 1px solid #d6d8db;
}

.oj-status-badge.cancelled {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.oj-status-badge.refunded {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Manager Pricing Styles */
.oj-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
}

.oj-item-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.oj-item-pricing {
    text-align: right;
    font-weight: 600;
    color: #28a745;
    min-width: 80px;
}

.oj-unit-price {
    font-size: 11px;
    color: #6c757d;
    display: block;
    font-weight: 400;
}

/* Order Total Section */
.oj-order-total-section {
    margin-top: 16px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-top: 2px solid #007cba;
}

.oj-order-subtotal,
.oj-order-tax,
.oj-order-shipping,
.oj-order-discount,
.oj-order-total {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 14px;
}

.oj-order-total {
    font-weight: 700;
    font-size: 16px;
    border-top: 1px solid #dee2e6;
    padding-top: 8px;
    margin-top: 8px;
    margin-bottom: 0;
}

.oj-total-label {
    color: #495057;
}

.oj-total-value {
    font-weight: 600;
}

.oj-total-final {
    color: #28a745;
    font-size: 18px;
}

/* Secondary Stats Styling */
.oj-secondary-stats {
    margin-top: 10px;
    opacity: 0.9;
}

.oj-secondary-stats .oj-stat-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

/* Status-specific stat box colors */
.oj-stat-box.revenue {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.oj-stat-box.processing {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.oj-stat-box.ready {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: white;
}

.oj-stat-box.completed {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
}

.oj-stat-box.pending {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.oj-stat-box.cancelled {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

/* Enhanced countdown badge styles */
.oj-countdown-badge {
    position: relative;
    transition: all 0.3s ease;
}

.oj-countdown-badge .oj-countdown-text {
    font-size: 11px;
    font-weight: 600;
    margin-left: 4px;
}

/* Time remaining urgency styles */
.oj-countdown-badge.oj-time-normal .oj-countdown-text {
    color: #0c5460;
}

.oj-countdown-badge.oj-time-soon {
    background: #fff3cd !important;
    color: #856404 !important;
    border-color: #ffeaa7 !important;
    animation: pulse-soon 2s infinite;
}

.oj-countdown-badge.oj-time-soon .oj-countdown-text {
    color: #856404;
}

.oj-countdown-badge.oj-time-urgent {
    background: #f8d7da !important;
    color: #721c24 !important;
    border-color: #f5c6cb !important;
    animation: pulse-urgent 1s infinite;
}

.oj-countdown-badge.oj-time-urgent .oj-countdown-text {
    color: #721c24;
    font-weight: 700;
}

.oj-countdown-badge.oj-time-now {
    background: #ff6b6b !important;
    color: white !important;
    border-color: #ff5252 !important;
    animation: flash-now 0.8s infinite alternate;
}

.oj-countdown-badge.oj-time-now .oj-countdown-text {
    color: white;
    font-weight: 700;
}

.oj-countdown-badge.oj-time-overdue {
    background: #dc3545 !important;
    color: white !important;
    border-color: #c82333 !important;
    animation: flash-overdue 0.5s infinite alternate;
}

.oj-countdown-badge.oj-time-overdue .oj-countdown-text {
    color: white;
    font-weight: 700;
}

/* Countdown animations */
@keyframes pulse-soon {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.02); }
}

@keyframes pulse-urgent {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.9; transform: scale(1.05); }
}

@keyframes flash-now {
    0% { background: #ff6b6b !important; }
    100% { background: #ff5252 !important; }
}

@keyframes flash-overdue {
    0% { background: #dc3545 !important; }
    100% { background: #c82333 !important; }
}

.oj-status-badge .dashicons {
    font-size: 14px;
    line-height: 1;
    vertical-align: middle;
}

/* Card Body */
.oj-card-body {
    padding: 20px;
    background: #f8f9fa;
}

.oj-card-items {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.oj-card-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px;
}

.oj-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.oj-item-qty {
    background: #007cba;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    min-width: 28px;
    text-align: center;
}

.oj-item-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 15px;
}

.oj-item-variations {
    margin: 6px 0;
}

.oj-variation-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-right: 6px;
    margin-bottom: 4px;
    display: inline-block;
}

.oj-item-addons {
    margin: 6px 0;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.oj-addons-label {
    background: #28a745;
    color: white;
    padding: 2px 6px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: bold;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.oj-addon-tag {
    background: #fff3cd;
    color: #856404;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-bottom: 4px;
    display: inline-block;
}

.oj-item-notes {
    margin: 8px 0;
    background: #f8d7da;
    color: #721c24;
    padding: 8px;
    border-radius: 6px;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
}

.oj-item-notes .dashicons {
    font-size: 14px;
    margin-top: 1px;
}

.oj-notes-text {
    font-style: italic;
    line-height: 1.4;
}

/* Card Footer */
.oj-card-footer {
    padding: 16px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

/* Manager Orders Management - Section Title */
.oj-section-title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

/* Manager Dashboard - Order ID Section Styling */
.oj-kitchen-card .oj-customer-info:not(.oj-action-btn) {
    background: #f8f9fa !important;
    background-color: #f8f9fa !important;
    color: #212529 !important;
    border-radius: 8px 8px 0 0;
    border-bottom: 1px solid #dee2e6;
}

.oj-kitchen-card .oj-customer-info:not(.oj-action-btn) .oj-customer-name {
    color: #212529 !important;
    font-weight: 600;
}

.oj-kitchen-card .oj-customer-info:not(.oj-action-btn) .oj-order-number {
    color: #FFFFFF !important;
    font-weight: 500;
}

/* Ensure all text elements in order ID section are visible */
.oj-kitchen-card .oj-customer-info:not(.oj-action-btn) * {
    color: #212529 !important;
}


/* Manager Actions */
.oj-manager-actions {
    display: flex;
    gap: 8px;
    justify-content: space-between;
}

.oj-manager-actions .oj-action-btn {
    flex: 1;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 600;
    min-height: auto;
    width: auto;
    border: none;
    border-radius: 6px;
    color: white !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.oj-manager-actions .oj-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.oj-view-details {
    background: #764ba2;
}

.oj-view-details:hover {
    background: #6a4190;
}

.oj-customer-info.oj-action-btn {
    background: #11998e;
}

.oj-customer-info.oj-action-btn:hover {
    background: #0e8078;
}

.oj-priority {
    background: #ee5a24;
}

.oj-priority:hover {
    background: #d63031;
}

.oj-priority.priority-active {
    background: #fd79a8;
    animation: pulse-priority 2s infinite;
}

@keyframes pulse-priority {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.oj-action-btn {
    width: 100%;
    padding: 12px 16px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.oj-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.oj-action-btn.oj-start-cooking {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.oj-action-btn.oj-mark-ready {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.oj-action-btn.oj-resume-order {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.oj-action-btn .dashicons {
    font-size: 16px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .oj-header-container {
        flex-direction: column;
        gap: 12px;
    }
    
    .oj-header-right {
        align-self: stretch;
    }
    
    .oj-check-orders-btn {
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
        font-size: 15px;
    }
    
    .oj-kitchen-cards-container {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .oj-kitchen-card {
        margin: 0 -10px;
        border-radius: 8px;
    }
    
    .oj-card-header {
        padding: 12px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .oj-card-status {
        margin-left: 0;
        align-self: flex-end;
    }
    
    .oj-table-info {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .oj-card-body {
        padding: 16px;
    }
    
    .oj-card-item {
        padding: 10px;
    }
    
    .oj-item-header {
        flex-wrap: wrap;
    }
    
    .oj-action-btn {
        padding: 14px 16px;
        font-size: 16px;
    }
}

@media (max-width: 480px) {
    .oj-kitchen-cards-container {
        gap: 12px;
    }
    
    .oj-kitchen-card {
        margin: 0 -5px;
    }
    
    .oj-card-header,
    .oj-card-body,
    .oj-card-footer {
        padding: 12px;
    }
    
    .oj-card-header {
        padding: 12px 16px;
    }
    
    .oj-customer-info {
        padding: 8px 16px;
    }
    
    .oj-card-body {
        padding: 16px;
    }
    
    .oj-order-type-badge {
        padding: 3px 6px;
        font-size: 10px;
    }
    
    .oj-order-type-badge .oj-type-icon {
        font-size: 10px;
    }
    
    .oj-order-type-badge .oj-type-label {
        font-size: 9px;
    }
    
    .oj-order-number {
        padding: 3px 6px;
        font-size: 10px;
    }
    
    .oj-item-qty {
        padding: 3px 6px;
        font-size: 11px;
        min-width: 28px;
    }
    
    .oj-item-name {
        font-size: 14px;
    }
    
    .oj-item-variations, .oj-item-addons, .oj-item-notes {
        margin-left: 36px;
    }
    
    .oj-table-number,
    .oj-order-number {
        font-size: 12px;
        padding: 3px 8px;
    }
    
    .oj-item-name {
        font-size: 14px;
    }
    
    .oj-action-btn {
        padding: 16px;
        font-size: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // DISABLED: Loading state JavaScript interferes with form submission
    // The form works perfectly without JavaScript - keeping it simple and reliable
    
    // Auto-dismiss success/error notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);
    
    // Real-time countdown updates
    function initCountdownTimers() {
        const countdownElements = document.querySelectorAll('.oj-countdown-badge[data-countdown-target]');
        
        countdownElements.forEach(element => {
            const targetTimestamp = parseInt(element.dataset.countdownTarget);
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000); // Current timestamp in seconds
                const distance = targetTimestamp - now;
                
                const countdownText = element.querySelector('.oj-countdown-text');
                if (!countdownText) return;
                
                // Remove existing urgency classes
                element.classList.remove('oj-time-normal', 'oj-time-soon', 'oj-time-urgent', 'oj-time-now', 'oj-time-overdue');
                
                if (distance < 0) {
                    countdownText.textContent = '(OVERDUE)';
                    element.classList.add('oj-time-overdue');
                    return;
                }
                
                if (distance <= 0) {
                    countdownText.textContent = '(NOW)';
                    element.classList.add('oj-time-now');
                    return;
                }
                
                const hours = Math.floor(distance / 3600);
                const minutes = Math.floor((distance % 3600) / 60);
                
                let timeText = '';
                if (hours > 0) {
                    if (minutes > 0) {
                        timeText = hours + 'h ' + minutes + 'm';
                    } else {
                        timeText = hours + 'h';
                    }
                } else {
                    timeText = minutes + 'm';
                }
                
                countdownText.textContent = '(' + timeText + ')';
                
                // Update urgency classes
                if (distance <= 1800) { // 30 minutes
                    element.classList.add('oj-time-urgent');
                } else if (distance <= 3600) { // 1 hour
                    element.classList.add('oj-time-soon');
                } else {
                    element.classList.add('oj-time-normal');
                }
            }
            
            // Update immediately and then every minute
            updateCountdown();
            setInterval(updateCountdown, 60000); // Update every minute
        });
    }
    
    // Initialize countdown timers
    initCountdownTimers();
    
    // Order Filtering Functionality
    const filterBtns = $('.oj-filter-btn');
    const orderCards = $('.oj-kitchen-card');
    
    // Initialize filter counts
    updateFilterCounts();
    
    // Apply visual styling for upcoming orders
    applyUpcomingOrderStyling();
    
    filterBtns.on('click', function(e) {
        e.preventDefault();
        
        const filter = $(this).data('filter');
        
        // Update active button
        filterBtns.removeClass('active');
        $(this).addClass('active');
        
        // Filter orders
        filterOrders(filter);
    });
    
    function filterOrders(filter) {
        const today = new Date().toISOString().split('T')[0];
        
        orderCards.each(function() {
            const card = $(this);
            const orderType = card.data('order-type');
            const deliveryDate = card.data('delivery-date');
            const deliveryTime = card.data('delivery-time');
            const tableNumber = card.data('table-number');
            const orderTotal = parseFloat(card.find('.oj-order-total').text().replace(/[^0-9.]/g, '')) || 0;
            const orderStatus = card.find('.oj-status-badge').length > 0 ? 
                              card.find('.oj-status-badge').attr('class').includes('completed') ? 'completed' : 'active' : 'active';
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                // Kitchen-style operational filters
                case 'dinein':
                    show = orderType === 'dinein';
                    break;
                case 'pickup-all':
                    show = orderType === 'pickup' || orderType === 'pickup_timed';
                    break;
                case 'pickup-immediate':
                    show = orderType === 'pickup';
                    break;
                case 'pickup-today':
                    if (orderType === 'pickup_timed') {
                        const deliveryDateFormatted = card.data('delivery-date-formatted');
                        show = deliveryDateFormatted === today;
                    }
                    break;
                case 'pickup-upcoming':
                    if (orderType === 'pickup_timed') {
                        const deliveryDateFormatted = card.data('delivery-date-formatted');
                        show = deliveryDateFormatted > today;
                    }
                    break;
                // Additional Manager Status filters
                case 'ready':
                    show = card.find('.oj-status-badge.ready').length > 0;
                    break;
                case 'pending':
                    show = card.find('.oj-status-badge.pending').length > 0;
                    break;
                case 'cancelled':
                    show = card.find('.oj-status-badge.cancelled').length > 0 || 
                           card.find('.oj-status-badge.refunded').length > 0;
                    break;
            }
            
            if (show) {
                card.show();
            } else {
                card.hide();
            }
        });
        
        // Update counts
        updateFilterCounts();
        
        // Show/hide empty state
        const visibleCards = orderCards.filter(':visible');
        if (visibleCards.length === 0) {
            showEmptyState(filter);
        } else {
            hideEmptyState();
        }
    }
    
    function updateFilterCounts() {
        const today = new Date().toISOString().split('T')[0];
        
        filterBtns.each(function() {
            const btn = $(this);
            const filter = btn.data('filter');
            let count = 0;
            
            orderCards.each(function() {
                const card = $(this);
                const orderType = card.data('order-type');
                const deliveryDate = card.data('delivery-date');
                
                let matches = false;
                
                switch(filter) {
                    case 'all':
                        matches = true;
                        break;
                    case 'dinein':
                        matches = orderType === 'dinein';
                        break;
                    case 'pickup-all':
                        matches = orderType === 'pickup' || orderType === 'pickup_timed';
                        break;
                    case 'pickup-immediate':
                        matches = orderType === 'pickup';
                        break;
                    case 'pickup-today':
                        if (orderType === 'pickup_timed') {
                            const deliveryDateFormatted = card.data('delivery-date-formatted');
                            matches = deliveryDateFormatted === today;
                        }
                        break;
                    case 'pickup-upcoming':
                        if (orderType === 'pickup_timed') {
                            const deliveryDateFormatted = card.data('delivery-date-formatted');
                            matches = deliveryDateFormatted > today;
                        }
                        break;
                    // Additional Manager Status filters
                    case 'ready':
                        matches = card.find('.oj-status-badge.ready').length > 0;
                        break;
                    case 'pending':
                        matches = card.find('.oj-status-badge.pending').length > 0;
                        break;
                    case 'cancelled':
                        matches = card.find('.oj-status-badge.cancelled').length > 0 || 
                                 card.find('.oj-status-badge.refunded').length > 0;
                        break;
                }
                
                if (matches) count++;
            });
            
            // Update button text with count (optional)
            const label = btn.find('.oj-filter-label');
            const originalText = label.text().replace(/ \(\d+\)$/, '');
            if (count > 0) {
                label.text(originalText + ' (' + count + ')');
            } else {
                label.text(originalText);
            }
        });
    }
    
    function showEmptyState(filter) {
        const container = $('.oj-kitchen-cards-container');
        
        // Remove existing empty state
        $('.oj-empty-state').remove();
        
        const filterNames = {
            'all': '<?php _e('orders', 'orders-jet'); ?>',
            'dinein': '<?php _e('dining orders', 'orders-jet'); ?>',
            'pickup-all': '<?php _e('pickup orders', 'orders-jet'); ?>',
            'pickup-immediate': '<?php _e('immediate pickup orders', 'orders-jet'); ?>',
            'pickup-today': '<?php _e('today pickup orders', 'orders-jet'); ?>',
            'pickup-upcoming': '<?php _e('upcoming pickup orders', 'orders-jet'); ?>',
            'ready': '<?php _e('ready orders', 'orders-jet'); ?>',
            'pending': '<?php _e('pending payment orders', 'orders-jet'); ?>',
            'cancelled': '<?php _e('cancelled orders', 'orders-jet'); ?>'
        };
        
        const emptyState = $('<div class="oj-empty-state" style="text-align: center; padding: 40px; color: #666;">' +
            '<div style="font-size: 48px; margin-bottom: 16px;">📋</div>' +
            '<h3><?php _e('No Orders Found', 'orders-jet'); ?></h3>' +
            '<p><?php _e('There are currently no', 'orders-jet'); ?> ' + (filterNames[filter] || '<?php _e('orders', 'orders-jet'); ?>') + '.</p>' +
            '</div>');
            
        container.append(emptyState);
    }
    
    function hideEmptyState() {
        $('.oj-empty-state').remove();
    }
    
    function applyUpcomingOrderStyling() {
        const today = new Date().toISOString().split('T')[0];
        
        orderCards.each(function() {
            const card = $(this);
            const orderType = card.data('order-type');
            const deliveryDateFormatted = card.data('delivery-date-formatted');
            
            // Add upcoming order styling for future pickup orders
            if (orderType === 'pickup_timed' && deliveryDateFormatted && deliveryDateFormatted > today) {
                card.addClass('oj-upcoming-order');
            }
        });
    }
    
    // Manager Action Handlers
    $('.oj-view-details').on('click', function() {
        const orderId = $(this).data('order-id');
        // Redirect to WooCommerce order edit page
        window.open('<?php echo admin_url('post.php?action=edit&post='); ?>' + orderId, '_blank');
    });
    
    $('.oj-customer-info').on('click', function() {
        const orderId = $(this).data('order-id');
        const card = $(this).closest('.oj-kitchen-card');
        const customerName = card.find('.oj-customer-name').text();
        const tableNumber = card.find('.oj-table-number').text() || 'Pickup Order';
        
        alert('<?php _e('Customer:', 'orders-jet'); ?> ' + customerName + '\n<?php _e('Order:', 'orders-jet'); ?> #' + orderId + '\n<?php _e('Type:', 'orders-jet'); ?> ' + tableNumber);
    });
    
    $('.oj-priority').on('click', function() {
        const orderId = $(this).data('order-id');
        const button = $(this);
        
        if (button.hasClass('priority-active')) {
            button.removeClass('priority-active');
            button.find('.oj-filter-label').text('<?php _e('Priority', 'orders-jet'); ?>');
            button.closest('.oj-kitchen-card').removeClass('oj-priority-order');
        } else {
            button.addClass('priority-active');
            button.find('.oj-filter-label').text('<?php _e('High Priority', 'orders-jet'); ?>');
            button.closest('.oj-kitchen-card').addClass('oj-priority-order');
        }
    });
});
</script>