<?php
/**
 * Orders Express - Clean Architecture Implementation
 * Lightning fast active orders management with AJAX filtering
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check - verify user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Enqueue existing beautiful CSS - no new styles needed
wp_enqueue_style('oj-manager-orders-cards', ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css', array(), ORDERS_JET_VERSION);

// Enqueue Express Dashboard specific styles (Phase 1: CSS Extraction)
wp_enqueue_style('oj-dashboard-express', ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css', array('oj-manager-orders-cards'), ORDERS_JET_VERSION);

// Enqueue and localize JavaScript (Phase 2: JavaScript Localization)
wp_enqueue_script('oj-dashboard-express', ORDERS_JET_PLUGIN_URL . 'assets/js/dashboard-express.js', array('jquery'), ORDERS_JET_VERSION, true);
wp_localize_script('oj-dashboard-express', 'ojExpressData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'adminUrl' => admin_url('post.php'),
    'nonces' => array(
        'dashboard' => wp_create_nonce('oj_dashboard_nonce'),
        'table_order' => wp_create_nonce('oj_table_order')
    ),
    'i18n' => array(
        'confirming' => __('Confirming...', 'orders-jet'),
        'paid' => __('Paid?', 'orders-jet'),
        'closing' => __('Closing...', 'orders-jet'),
        'forceClosing' => __('Force Closing...', 'orders-jet'),
        'closeTable' => __('Close Table', 'orders-jet'),
        'paymentMethod' => __('Payment Method', 'orders-jet'),
        'howPaid' => __('How was this order paid?', 'orders-jet'),
        'cash' => __('Cash', 'orders-jet'),
        'card' => __('Card', 'orders-jet'),
        'other' => __('Other', 'orders-jet'),
        'viewOrderDetails' => __('View Order Details', 'orders-jet'),
        'dinein' => __('Dine-in', 'orders-jet'),
        'combined' => __('Combined', 'orders-jet'),
        'ready' => __('Ready', 'orders-jet'),
        'clickToContinue' => __('Click OK to continue or Cancel to keep the table open.', 'orders-jet'),
        // Notification messages
        'printFailed' => __('Print failed:', 'orders-jet'),
        'failedToLoadInvoice' => __('Failed to load invoice', 'orders-jet'),
        'paymentConfirmed' => __('Payment confirmed! Order completed.', 'orders-jet'),
        'failedToConfirmPayment' => __('Failed to confirm payment', 'orders-jet'),
        'connectionError' => __('Connection error', 'orders-jet'),
        'tableClosed' => __('Table closed! Combined order created.', 'orders-jet'),
        'failedToForceClose' => __('Failed to force close table', 'orders-jet'),
        'connectionErrorForceClose' => __('Connection error during force close', 'orders-jet'),
        'tableForceClose' => __('Table force closed! Combined order created.', 'orders-jet'),
        'failedToCloseTable' => __('Failed to close table', 'orders-jet')
    )
));

/**
 * Helper function: Get order method - EXACT copy from main orders page
 */
function oj_express_get_order_method($order) {
    $order_method = $order->get_meta('exwf_odmethod');
    
    // If no exwf_odmethod, determine from other meta with better logic
    if (empty($order_method)) {
        $table_number_check = $order->get_meta('_oj_table_number');
        
        if (!empty($table_number_check)) {
            $order_method = 'dinein';
        } else {
            // Check if it's a delivery order by looking at shipping vs billing
            $billing_address = $order->get_billing_address_1();
            $shipping_address = $order->get_shipping_address_1();
            
            // If shipping address exists and differs from billing, likely delivery
            if (!empty($shipping_address) && $shipping_address !== $billing_address) {
                $order_method = 'delivery';
            } else {
                // Default to takeaway
                $order_method = 'takeaway';
            }
        }
    }
    
    return $order_method;
}

/**
 * Helper function: Get order kitchen type
 */
function oj_express_get_kitchen_type($order) {
    $kitchen_types = array();
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // Check variation first, then fall back to main product
        $kitchen = '';
        if ($variation_id > 0) {
            $kitchen = get_post_meta($variation_id, 'Kitchen', true);
        }
        // Fall back to main product if variation doesn't have Kitchen field
        if (empty($kitchen)) {
            $kitchen = get_post_meta($product_id, 'Kitchen', true);
        }
        
        if (!empty($kitchen)) {
            $kitchen_types[] = strtolower(trim($kitchen));
        }
    }
    
    // Remove duplicates and determine final kitchen type
    $unique_types = array_unique($kitchen_types);
    
    if (count($unique_types) === 1) {
        return $unique_types[0];
    } elseif (count($unique_types) > 1) {
        return 'mixed';
    }
    
    // Default fallback to food if no kitchen field is set
    return 'food';
}

/**
 * Helper function: Get kitchen readiness status
 */
function oj_express_get_kitchen_status($order) {
    $kitchen_type = $order->get_meta('_oj_kitchen_type');
    if (empty($kitchen_type)) {
        $kitchen_type = oj_express_get_kitchen_type($order);
    }
    
    $food_ready = $order->get_meta('_oj_food_kitchen_ready') === 'yes';
    $beverage_ready = $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
    
    return array(
        'kitchen_type' => $kitchen_type,
        'food_ready' => $food_ready,
        'beverage_ready' => $beverage_ready,
        'all_ready' => ($kitchen_type === 'food' && $food_ready) || 
                      ($kitchen_type === 'beverages' && $beverage_ready) || 
                      ($kitchen_type === 'mixed' && $food_ready && $beverage_ready)
    );
}

/**
 * Helper function: Prepare clean order data
 */
function oj_express_prepare_order_data($order) {
    $kitchen_status = oj_express_get_kitchen_status($order);
    
    return array(
        'id' => $order->get_id(),
        'number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'method' => oj_express_get_order_method($order),
        'table' => $order->get_meta('_oj_table_number'),
        'total' => $order->get_total(),
        'date' => $order->get_date_created(),
        'customer' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: $order->get_billing_email() ?: __('Guest', 'orders-jet'),
        'items' => $order->get_items(),
        'kitchen_type' => $kitchen_status['kitchen_type'],
        'kitchen_status' => $kitchen_status
    );
}

/**
 * Helper function: Update filter counts
 */
function oj_express_update_filter_counts(&$counts, $order_data) {
    $status = $order_data['status'];
    $method = $order_data['method'];
    $kitchen_type = $order_data['kitchen_type'];
    
    // Count all active orders
    $counts['active']++;
    
    // Count by status
    if ($status === 'processing') {
        $counts['processing']++;
    } elseif ($status === 'pending') {
        $counts['pending']++;
    }
    
    // Count by method (all methods including fallback)
    if ($method === 'dinein') {
        $counts['dinein']++;
    } elseif ($method === 'takeaway') {
        $counts['takeaway']++;
    } elseif ($method === 'delivery') {
        $counts['delivery']++;
    }
    
    // Count by kitchen type - mixed orders count in both kitchens
    if ($kitchen_type === 'food' || $kitchen_type === 'mixed') {
        $counts['food_kitchen']++;
    }
    if ($kitchen_type === 'beverages' || $kitchen_type === 'mixed') {
        $counts['beverage_kitchen']++;
    }
}

// ============================================================================
// MAIN QUERY - Single optimized query for active orders only
// ============================================================================

$active_orders = wc_get_orders(array(
    'status' => array('wc-pending', 'wc-processing'),
    'limit' => 50,
    'orderby' => 'date',
    'order' => 'ASC', // Oldest first for operational priority
    'return' => 'objects'
));

// ============================================================================
// DATA PREPARATION - Clean data structure
// ============================================================================

$orders_data = array();
$filter_counts = array(
    'active' => 0,
    'processing' => 0,
    'pending' => 0,
    'dinein' => 0,
    'takeaway' => 0,
    'delivery' => 0,
    'food_kitchen' => 0,
    'beverage_kitchen' => 0,
    'mixed_kitchen' => 0
);

foreach ($active_orders as $order) {
    $order_data = oj_express_prepare_order_data($order);
    $orders_data[] = $order_data;
    oj_express_update_filter_counts($filter_counts, $order_data);
}

?>

<div class="wrap oj-manager-orders">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="oj-page-title">
            ⚡ <?php _e('Orders Express', 'orders-jet'); ?>
            <span class="oj-subtitle"><?php _e('Active Orders Only - Lightning Fast', 'orders-jet'); ?></span>
        </h1>
        <div class="oj-page-stats">
            <div class="oj-stat-item">
                <span class="oj-stat-number"><?php echo $filter_counts['processing']; ?></span>
                <span class="oj-stat-label"><?php _e('Kitchen', 'orders-jet'); ?></span>
            </div>
            <div class="oj-stat-item">
                <span class="oj-stat-number"><?php echo $filter_counts['pending']; ?></span>
                <span class="oj-stat-label"><?php _e('Ready', 'orders-jet'); ?></span>
            </div>
            <div class="oj-stat-item">
                <span class="oj-stat-number"><?php echo $filter_counts['active']; ?></span>
                <span class="oj-stat-label"><?php _e('Total Active', 'orders-jet'); ?></span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="active">
            🔥 <?php _e('Active', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['active']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            👨‍🍳 <?php _e('Kitchen', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['processing']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pending">
            ✅ <?php _e('Ready', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['pending']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="dinein">
            🍽️ <?php _e('Dine-in', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['dinein']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="takeaway">
            📦 <?php _e('Takeaway', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['takeaway']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="delivery">
            🚚 <?php _e('Delivery', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['delivery']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="food-kitchen">
            🍕 <?php _e('Food', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['food_kitchen']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="beverage-kitchen">
            🥤 <?php _e('Beverage', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['beverage_kitchen']; ?></span>
        </button>
    </div>

    <!-- Orders Grid -->
    <div class="oj-orders-grid">
        <?php if (empty($orders_data)) : ?>
            <div class="oj-empty-state">
                <div class="oj-empty-icon">🎉</div>
                <h3><?php _e('No Active Orders', 'orders-jet'); ?></h3>
                <p><?php _e('All caught up! No orders need attention right now.', 'orders-jet'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($orders_data as $order_data) : 
                $order_id = $order_data['id'];
                $order_number = $order_data['number'];
                $status = $order_data['status'];
                $method = $order_data['method'];
                $table_number = $order_data['table'];
                $total = $order_data['total'];
                $date_created = $order_data['date'];
                $customer_name = $order_data['customer'];
                $items = $order_data['items'];
                $kitchen_type = $order_data['kitchen_type'];
                $kitchen_status = $order_data['kitchen_status'];
                
                $time_ago = human_time_diff($date_created->getTimestamp(), current_time('timestamp'));
                $item_count = count($items);
                
                // Kitchen-aware status badge
                if ($status === 'pending') {
                    $status_text = __('Ready', 'orders-jet');
                    $status_class = 'ready';
                    $status_icon = '✅';
                } elseif ($status === 'processing') {
                    if ($kitchen_type === 'mixed') {
                        if ($kitchen_status['food_ready'] && !$kitchen_status['beverage_ready']) {
                            $status_text = __('Waiting for Bev.', 'orders-jet');
                            $status_class = 'partial';
                            $status_icon = '🍕✅ 🥤⏳';
                        } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                            $status_text = __('Waiting for Food', 'orders-jet');
                            $status_class = 'partial';
                            $status_icon = '🍕⏳ 🥤✅';
                        } else {
                            $status_text = __('Both Kitchens', 'orders-jet');
                            $status_class = 'partial';
                            $status_icon = '🍕⏳ 🥤⏳';
                        }
                    } elseif ($kitchen_type === 'food') {
                        $status_text = __('Waiting for Food', 'orders-jet');
                        $status_class = 'partial';
                        $status_icon = '🍕⏳';
                    } elseif ($kitchen_type === 'beverages') {
                        $status_text = __('Waiting for Bev.', 'orders-jet');
                        $status_class = 'partial';
                        $status_icon = '🥤⏳';
                    } else {
                        $status_text = __('Kitchen', 'orders-jet');
                        $status_class = 'kitchen';
                        $status_icon = '👨‍🍳';
                    }
                }
                
                // Order type badge
                $type_badges = array(
                    'dinein' => array('icon' => '🍽️', 'text' => __('Dine-in', 'orders-jet'), 'class' => 'dinein'),
                    'takeaway' => array('icon' => '📦', 'text' => __('Takeaway', 'orders-jet'), 'class' => 'takeaway'),
                    'delivery' => array('icon' => '🚚', 'text' => __('Delivery', 'orders-jet'), 'class' => 'delivery')
                );
                $type_badge = $type_badges[$method] ?? $type_badges['takeaway']; // Default to takeaway if method not found
                
                // Kitchen type badge
                $kitchen_badges = array(
                    'food' => array('icon' => '🍕', 'text' => __('Food', 'orders-jet'), 'class' => 'food'),
                    'beverages' => array('icon' => '🥤', 'text' => __('Beverages', 'orders-jet'), 'class' => 'beverages'),
                    'mixed' => array('icon' => '🍽️', 'text' => __('Mixed', 'orders-jet'), 'class' => 'mixed')
                );
                $kitchen_badge = $kitchen_badges[$kitchen_type] ?? $kitchen_badges['food'];
                ?>
                
                <div class="oj-order-card" 
                     data-order-id="<?php echo esc_attr($order_id); ?>" 
                     data-status="<?php echo esc_attr($status); ?>"
                     data-method="<?php echo esc_attr($method); ?>"
                     data-table-number="<?php echo esc_attr($table_number); ?>"
                     data-kitchen-type="<?php echo esc_attr($kitchen_type); ?>"
                     data-food-ready="<?php echo $kitchen_status['food_ready'] ? 'yes' : 'no'; ?>"
                     data-beverage-ready="<?php echo $kitchen_status['beverage_ready'] ? 'yes' : 'no'; ?>">
                     
                    <!-- Row 1: Order number + Type badges -->
                    <div class="oj-card-row-1">
                        <div class="oj-order-header">
                            <span class="oj-view-icon oj-view-order" data-order-id="<?php echo esc_attr($order_id); ?>" title="<?php _e('View Order Details', 'orders-jet'); ?>">👁️</span>
                            <?php if (!empty($table_number)) : ?>
                                <span class="oj-table-ref"><?php echo esc_html($table_number); ?></span>
                            <?php endif; ?>
                            <span class="oj-order-number">#<?php echo esc_html($order_number); ?></span>
                        </div>
                        <div class="oj-type-badges">
                            <span class="oj-type-badge <?php echo esc_attr($type_badge['class']); ?>">
                                <?php echo $type_badge['icon']; ?> <?php echo esc_html($type_badge['text']); ?>
                            </span>
                            <span class="oj-kitchen-badge <?php echo esc_attr($kitchen_badge['class']); ?>">
                                <?php echo $kitchen_badge['icon']; ?> <?php echo esc_html($kitchen_badge['text']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Row 2: Time + Status -->
                    <div class="oj-card-row-2">
                        <span class="oj-order-time"><?php echo esc_html($time_ago); ?> <?php _e('ago', 'orders-jet'); ?></span>
                        <span class="oj-status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo $status_icon; ?> <?php echo esc_html($status_text); ?>
                        </span>
                    </div>

                    <!-- Row 3: Customer + Price -->
                    <div class="oj-card-row-3">
                        <span class="oj-customer-name"><?php echo esc_html($customer_name); ?></span>
                        <span class="oj-order-total"><?php echo wc_price($total); ?></span>
                    </div>

                    <!-- Row 4: Item count -->
                    <div class="oj-card-row-4">
                        <span class="oj-item-count"><?php echo esc_html($item_count); ?> <?php echo _n('item', 'items', $item_count, 'orders-jet'); ?></span>
                    </div>

                    <!-- Row 5: Item details -->
                    <div class="oj-card-row-5">
                        <div class="oj-items-list">
                            <?php 
                            $items_text = array();
                            foreach ($items as $item) :
                                $product_name = $item->get_name();
                                $quantity = $item->get_quantity();
                                $items_text[] = esc_html($quantity) . 'x ' . esc_html($product_name);
                            endforeach;
                            echo implode(' ', $items_text);
                            ?>
                        </div>
                    </div>
                    
                    <!-- Card Actions -->
                    <div class="oj-card-actions">
                        <?php if ($status === 'processing') : ?>
                            <?php if ($kitchen_type === 'mixed') : ?>
                                <?php if (!$kitchen_status['food_ready']) : ?>
                                    <button class="oj-action-btn primary oj-mark-ready-food" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="food">
                                        🍕 <?php _e('Food Ready', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if (!$kitchen_status['beverage_ready']) : ?>
                                    <button class="oj-action-btn primary oj-mark-ready-beverage" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="beverages">
                                        🥤 <?php _e('Bev. Ready', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                            <?php else : ?>
                                <button class="oj-action-btn primary oj-mark-ready" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="<?php echo esc_attr($kitchen_type); ?>">
                                    <?php if ($kitchen_type === 'food') : ?>
                                        🍕 <?php _e('Food Ready', 'orders-jet'); ?>
                                    <?php elseif ($kitchen_type === 'beverages') : ?>
                                        🥤 <?php _e('Bev. Ready', 'orders-jet'); ?>
                                    <?php else : ?>
                                        🔥 <?php _e('Mark Ready', 'orders-jet'); ?>
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        <?php elseif ($status === 'pending') : ?>
                            <?php if (!empty($table_number)) : ?>
                                <button class="oj-action-btn primary oj-close-table" data-order-id="<?php echo esc_attr($order_id); ?>" data-table-number="<?php echo esc_attr($table_number); ?>">
                                    🍽️ <?php _e('Close Table', 'orders-jet'); ?>
                                </button>
                            <?php else : ?>
                                <button class="oj-action-btn primary oj-complete-order" data-order-id="<?php echo esc_attr($order_id); ?>">
                                    ✅ <?php _e('Complete', 'orders-jet'); ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>



