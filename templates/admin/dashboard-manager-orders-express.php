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

// Initialize services for template use (Phase 3: Service Integration)
$kitchen_service = new Orders_Jet_Kitchen_Service();
$order_method_service = new Orders_Jet_Order_Method_Service();

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
 * Helper function: Prepare clean order data using services (Phase 3: Service Integration)
 */
function oj_express_prepare_order_data($order, $kitchen_service, $order_method_service) {
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    
    return array(
        'id' => $order->get_id(),
        'number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'method' => $order_method_service->get_order_method($order),
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
 * Helper function: Update filter counts (unchanged logic)
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
    $order_data = oj_express_prepare_order_data($order, $kitchen_service, $order_method_service);
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
                
                // Get the WooCommerce order object for service calls (Fix: Badge scope issue)
                $order = wc_get_order($order_id);
                
                // Get status badge using kitchen service (Phase 3: Service Integration)
                $status_badge_html = $kitchen_service->get_kitchen_status_badge($order);
                
                // Extract status data for template compatibility
                preg_match('/class="[^"]*oj-status-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $status_badge_html, $status_matches);
                $status_class = $status_matches[1] ?? 'kitchen';
                $status_icon = trim($status_matches[2] ?? '👨‍🍳');
                $status_text = trim($status_matches[3] ?? __('Kitchen', 'orders-jet'));
                
                // Get order method badge using service (Phase 3: Service Integration)
                $type_badge_html = $order_method_service->get_order_method_badge($order);
                
                // Get kitchen type badge using service (Phase 3: Service Integration)
                $kitchen_badge_html = $kitchen_service->get_kitchen_type_badge($order);
                
                // Extract badge data for template compatibility
                preg_match('/class="[^"]*oj-type-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $type_badge_html, $type_matches);
                $type_badge = array(
                    'class' => $type_matches[1] ?? $method,
                    'icon' => trim($type_matches[2] ?? '📦'),
                    'text' => trim($type_matches[3] ?? ucfirst($method))
                );
                
                preg_match('/class="[^"]*oj-kitchen-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $kitchen_badge_html, $kitchen_matches);
                $kitchen_badge = array(
                    'class' => $kitchen_matches[1] ?? $kitchen_type,
                    'icon' => trim($kitchen_matches[2] ?? '🍕'),
                    'text' => trim($kitchen_matches[3] ?? ucfirst($kitchen_type))
                );
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



