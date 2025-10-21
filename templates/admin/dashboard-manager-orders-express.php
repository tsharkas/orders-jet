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
 * Helper function: Process badge data from services (Phase 4B: Code Structure)
 */
function oj_express_process_badge_data($order, $kitchen_service, $order_method_service) {
    // Get badge HTML from services
    $status_badge_html = $kitchen_service->get_kitchen_status_badge($order);
    $type_badge_html = $order_method_service->get_order_method_badge($order);
    $kitchen_badge_html = $kitchen_service->get_kitchen_type_badge($order);
    
    // Extract status data
    preg_match('/class="[^"]*oj-status-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $status_badge_html, $status_matches);
    $status_data = array(
        'class' => $status_matches[1] ?? 'kitchen',
        'icon' => trim($status_matches[2] ?? '👨‍🍳'),
        'text' => trim($status_matches[3] ?? __('Kitchen', 'orders-jet'))
    );
    
    // Extract type badge data
    preg_match('/class="[^"]*oj-type-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $type_badge_html, $type_matches);
    $type_data = array(
        'class' => $type_matches[1] ?? $order->get_meta('exwf_odmethod') ?: 'takeaway',
        'icon' => trim($type_matches[2] ?? '📦'),
        'text' => trim($type_matches[3] ?? 'Takeaway')
    );
    
    // Extract kitchen badge data
    preg_match('/class="[^"]*oj-kitchen-badge\s+([^"]*)"[^>]*>([^<]*)\s*([^<]*)</', $kitchen_badge_html, $kitchen_matches);
    $kitchen_data = array(
        'class' => $kitchen_matches[1] ?? 'food',
        'icon' => trim($kitchen_matches[2] ?? '🍕'),
        'text' => trim($kitchen_matches[3] ?? 'Food')
    );
    
    return array(
        'status' => $status_data,
        'type' => $type_data,
        'kitchen' => $kitchen_data
    );
}

/**
 * Helper function: Generate action buttons HTML (Phase 4B: Code Structure)
 */
function oj_express_get_action_buttons($order_data, $kitchen_status) {
    $order_id = $order_data['id'];
    $status = $order_data['status'];
    $kitchen_type = $order_data['kitchen_type'];
    $table_number = $order_data['table'];
    
    $buttons = '';
    
    if ($status === 'processing') {
        if ($kitchen_type === 'mixed') {
            // Mixed kitchen - show individual buttons for unready kitchens
            if (!$kitchen_status['food_ready']) {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-mark-ready-food" data-order-id="%s" data-kitchen="food">🍕 %s</button>',
                    esc_attr($order_id),
                    __('Food Ready', 'orders-jet')
                );
            }
            if (!$kitchen_status['beverage_ready']) {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-mark-ready-beverage" data-order-id="%s" data-kitchen="beverages">🥤 %s</button>',
                    esc_attr($order_id),
                    __('Bev. Ready', 'orders-jet')
                );
            }
        } else {
            // Single kitchen - show appropriate button
            $icon = $kitchen_type === 'food' ? '🍕' : ($kitchen_type === 'beverages' ? '🥤' : '🔥');
            $text = $kitchen_type === 'food' ? __('Food Ready', 'orders-jet') : 
                   ($kitchen_type === 'beverages' ? __('Bev. Ready', 'orders-jet') : __('Mark Ready', 'orders-jet'));
            
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-mark-ready" data-order-id="%s" data-kitchen="%s">%s %s</button>',
                esc_attr($order_id),
                esc_attr($kitchen_type),
                $icon,
                $text
            );
        }
    } elseif ($status === 'pending') {
        if (!empty($table_number)) {
            // Table order - show close table button
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-close-table" data-order-id="%s" data-table-number="%s">🍽️ %s</button>',
                esc_attr($order_id),
                esc_attr($table_number),
                __('Close Table', 'orders-jet')
            );
        } else {
            // Individual order - show complete button
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-complete-order" data-order-id="%s">✅ %s</button>',
                esc_attr($order_id),
                __('Complete', 'orders-jet')
            );
        }
    }
    
    return $buttons;
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
    'beverage_kitchen' => 0
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
            <?php include __DIR__ . '/partials/empty-state.php'; ?>
        <?php else : ?>
            <?php foreach ($orders_data as $order_data) : ?>
                <?php include __DIR__ . '/partials/order-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>



