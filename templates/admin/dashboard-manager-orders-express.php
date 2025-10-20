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
        
        // Check variation first, then main product
        $check_id = $variation_id > 0 ? $variation_id : $product_id;
        $kitchen = get_post_meta($check_id, 'Kitchen', true);
        
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
    
    // Count by kitchen type
    if ($kitchen_type === 'food') {
        $counts['food_kitchen']++;
    } elseif ($kitchen_type === 'beverages') {
        $counts['beverage_kitchen']++;
    } elseif ($kitchen_type === 'mixed') {
        $counts['mixed_kitchen']++;
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
            🔥 <?php _e('Active Orders', 'orders-jet'); ?>
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
            🍕 <?php _e('Food Kitchen', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['food_kitchen']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="beverage-kitchen">
            🥤 <?php _e('Beverage Kitchen', 'orders-jet'); ?>
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
                            $status_text = __('Waiting for Beverages', 'orders-jet');
                            $status_class = 'partial';
                            $status_icon = '🍕✅ 🥤⏳';
                        } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                            $status_text = __('Waiting for Food', 'orders-jet');
                            $status_class = 'partial';
                            $status_icon = '🍕⏳ 🥤✅';
                        } else {
                            $status_text = __('Both Kitchens Working', 'orders-jet');
                            $status_class = 'kitchen';
                            $status_icon = '🍕⏳ 🥤⏳';
                        }
                    } elseif ($kitchen_type === 'food') {
                        $status_text = __('Food Kitchen', 'orders-jet');
                        $status_class = 'kitchen';
                        $status_icon = '🍕⏳';
                    } elseif ($kitchen_type === 'beverages') {
                        $status_text = __('Beverage Kitchen', 'orders-jet');
                        $status_class = 'kitchen';
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
                     
                    <!-- Card Header -->
                    <div class="oj-card-header">
                        <div class="oj-order-info">
                            <h3 class="oj-order-number">#<?php echo esc_html($order_number); ?></h3>
                            <?php if (!empty($table_number)) : ?>
                                <span class="oj-table-number">Table <?php echo esc_html($table_number); ?></span>
                            <?php endif; ?>
                            <span class="oj-order-time"><?php echo esc_html($time_ago); ?> <?php _e('ago', 'orders-jet'); ?></span>
                        </div>
                        <div class="oj-order-badges">
                            <span class="oj-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo $status_icon; ?> <?php echo esc_html($status_text); ?>
                            </span>
                            <span class="oj-type-badge <?php echo esc_attr($type_badge['class']); ?>">
                                <?php echo $type_badge['icon']; ?> <?php echo esc_html($type_badge['text']); ?>
                            </span>
                            <span class="oj-kitchen-badge <?php echo esc_attr($kitchen_badge['class']); ?>">
                                <?php echo $kitchen_badge['icon']; ?> <?php echo esc_html($kitchen_badge['text']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="oj-card-body">
                        <div class="oj-customer-info">
                            <span class="oj-customer-name"><?php echo esc_html($customer_name); ?></span>
                            <span class="oj-order-total"><?php echo wc_price($total); ?></span>
                        </div>
                        
                        <div class="oj-order-summary">
                            <span class="oj-item-count"><?php echo esc_html($item_count); ?> <?php echo _n('item', 'items', $item_count, 'orders-jet'); ?></span>
                        </div>
                        
                        <!-- Quick Items Preview -->
                        <div class="oj-items-preview">
                            <?php 
                            $preview_items = array_slice($items, 0, 3);
                            foreach ($preview_items as $item) :
                                $product_name = $item->get_name();
                                $quantity = $item->get_quantity();
                            ?>
                                <span class="oj-item-preview"><?php echo esc_html($quantity); ?>x <?php echo esc_html($product_name); ?></span>
                            <?php endforeach; ?>
                            <?php if ($item_count > 3) : ?>
                                <span class="oj-more-items">+<?php echo ($item_count - 3); ?> <?php _e('more', 'orders-jet'); ?></span>
                            <?php endif; ?>
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
                                        🥤 <?php _e('Beverages Ready', 'orders-jet'); ?>
                                    </button>
                                <?php endif; ?>
                            <?php else : ?>
                                <button class="oj-action-btn primary oj-mark-ready" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="<?php echo esc_attr($kitchen_type); ?>">
                                    <?php if ($kitchen_type === 'food') : ?>
                                        🍕 <?php _e('Food Ready', 'orders-jet'); ?>
                                    <?php elseif ($kitchen_type === 'beverages') : ?>
                                        🥤 <?php _e('Beverages Ready', 'orders-jet'); ?>
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
                        
                        <button class="oj-action-btn secondary oj-view-order" data-order-id="<?php echo esc_attr($order_id); ?>">
                            👁️ <?php _e('Details', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
                
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal CSS for Payment Selection -->
<style>
.oj-success-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.oj-success-modal {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
    position: relative;
    text-align: center;
}

.oj-success-modal h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 20px;
}

.oj-success-modal p {
    margin: 0 0 20px 0;
    color: #666;
}

.oj-payment-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.oj-payment-btn {
    background: #0073aa;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
    min-width: 100px;
}

.oj-payment-btn:hover {
    background: #005a87;
}

.oj-payment-btn.cash {
    background: #28a745;
}

.oj-payment-btn.cash:hover {
    background: #218838;
}

.oj-payment-btn.card {
    background: #007cba;
}

.oj-payment-btn.card:hover {
    background: #005a87;
}

.oj-payment-btn.other {
    background: #6c757d;
}

.oj-payment-btn.other:hover {
    background: #5a6268;
}

.oj-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #999;
    padding: 5px;
    line-height: 1;
}

.oj-modal-close:hover {
    color: #333;
}

.oj-success-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 15px 20px;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    z-index: 10000;
    max-width: 300px;
}

.oj-success-notification.error {
    background: #dc3545;
}

.oj-notification-close {
    background: none;
    border: none;
    color: white;
    float: right;
    font-size: 16px;
    cursor: pointer;
    margin-left: 10px;
    padding: 0;
    line-height: 1;
}

/* Kitchen-specific styles */
.oj-kitchen-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 4px;
}

.oj-kitchen-badge.food {
    background: #ff6b35;
    color: white;
}

.oj-kitchen-badge.beverages {
    background: #4ecdc4;
    color: white;
}

.oj-kitchen-badge.mixed {
    background: #45b7d1;
    color: white;
}

/* Kitchen status badges */
.oj-status-badge.partial {
    background: #ffeaa7;
    color: #d63031;
    font-size: 10px;
}

.oj-status-badge.kitchen {
    background: #fdcb6e;
    color: #e17055;
}

/* Kitchen-specific action buttons */
.oj-mark-ready-food,
.oj-mark-ready-beverage {
    background: var(--primary-color, #007cba);
    color: white;
    font-weight: 600;
}

.oj-mark-ready-food:hover,
.oj-mark-ready-beverage:hover {
    background: var(--primary-hover, #005a87);
}

.oj-mark-ready-food:disabled,
.oj-mark-ready-beverage:disabled {
    background: #28a745;
    cursor: not-allowed;
}

/* Kitchen filter buttons */
.oj-filter-btn[data-filter="food-kitchen"] {
    border-color: #ff6b35;
}

.oj-filter-btn[data-filter="food-kitchen"].active {
    background: #ff6b35;
    color: white;
}

.oj-filter-btn[data-filter="beverage-kitchen"] {
    border-color: #4ecdc4;
}

.oj-filter-btn[data-filter="beverage-kitchen"].active {
    background: #4ecdc4;
    color: white;
}
</style>

<!-- Clean JavaScript Implementation -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // ========================================================================
    // AJAX FILTERING - Client-side for instant response
    // ========================================================================
    
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        const $cards = $('.oj-order-card');
        
        // Update active filter button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter cards with smooth animation
        $cards.each(function() {
            const $card = $(this);
            const status = $card.data('status');
            const method = $card.data('method');
            const kitchenType = $card.data('kitchen-type');
            let showCard = false;
            
            switch (filter) {
                case 'active':
                    showCard = true; // Show all active orders
                    break;
                case 'processing':
                    showCard = (status === 'processing'); // Kitchen - orders being prepared
                    break;
                case 'pending':
                    showCard = (status === 'pending'); // Ready - orders ready to serve
                    break;
                case 'dinein':
                    showCard = (method === 'dinein');
                    break;
                case 'takeaway':
                    showCard = (method === 'takeaway');
                    break;
                case 'delivery':
                    showCard = (method === 'delivery');
                    break;
                case 'food-kitchen':
                    showCard = (kitchenType === 'food' || kitchenType === 'mixed');
                    break;
                case 'beverage-kitchen':
                    showCard = (kitchenType === 'beverages' || kitchenType === 'mixed');
                    break;
            }
            
            if (showCard) {
                $card.fadeIn(300);
            } else {
                $card.fadeOut(300);
            }
        });
        
        console.log('Express filter applied:', filter);
    });
    
    // ========================================================================
    // ORDER MANAGEMENT - Reuse existing AJAX handlers
    // ========================================================================
    
    // Mark Ready - Change processing → pending
    // Mark Order Ready - Enhanced with dual kitchen support
    $(document).on('click', '.oj-mark-ready, .oj-mark-ready-food, .oj-mark-ready-beverage', function() {
        const orderId = $(this).data('order-id');
        const kitchenType = $(this).data('kitchen') || 'food';
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        $btn.prop('disabled', true).html('⏳ <?php _e('Marking...', 'orders-jet'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                kitchen_type: kitchenType,
                nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.card_updates) {
                    const updates = response.data.card_updates;
                    
                    // Update status badge with kitchen-aware content
                    if (updates.status_badge_html) {
                        $card.find('.oj-status-badge').replaceWith(updates.status_badge_html);
                    }
                    
                    // Update kitchen type badge if provided
                    if (updates.kitchen_type_badge_html) {
                        $card.find('.oj-kitchen-badge').replaceWith(updates.kitchen_type_badge_html);
                    }
                    
                    // Update card data attributes
                    $card.attr('data-status', updates.new_status);
                    if (response.data.kitchen_status) {
                        $card.attr('data-food-ready', response.data.kitchen_status.food_ready ? 'yes' : 'no');
                        $card.attr('data-beverage-ready', response.data.kitchen_status.beverage_ready ? 'yes' : 'no');
                    }
                    
                    // Handle button updates based on kitchen readiness
                    if (updates.partial_ready) {
                        // Mixed order - partial ready, update this specific button
                        if (kitchenType === 'food') {
                            $btn.html('🍕✅ <?php _e('Food Ready', 'orders-jet'); ?>').prop('disabled', true);
                        } else {
                            $btn.html('🥤✅ <?php _e('Beverages Ready', 'orders-jet'); ?>').prop('disabled', true);
                        }
                        showExpressNotification(`✅ ${kitchenType.charAt(0).toUpperCase() + kitchenType.slice(1)} ready! ${updates.button_text}`, 'success');
                    } else {
                        // Fully ready - replace all buttons with completion button
                        const tableNumber = $card.attr('data-table-number');
                        let newButton;
                        if (tableNumber && tableNumber !== '') {
                            newButton = `<button class="oj-action-btn primary oj-close-table" data-order-id="${orderId}" data-table-number="${tableNumber}">🍽️ <?php _e('Close Table', 'orders-jet'); ?></button>`;
                        } else {
                            newButton = `<button class="oj-action-btn primary oj-complete-order" data-order-id="${orderId}">✅ <?php _e('Complete', 'orders-jet'); ?></button>`;
                        }
                        
                        // Replace all kitchen buttons with completion button
                        $card.find('.oj-card-actions').html(newButton + '<button class="oj-action-btn secondary oj-view-order" data-order-id="' + orderId + '">👁️ <?php _e('Details', 'orders-jet'); ?></button>');
                        
                        showExpressNotification('✅ Order fully ready!', 'success');
                    }
                    
                    // Add update animation
                    $card.addClass('oj-card-updated');
                    setTimeout(() => $card.removeClass('oj-card-updated'), 1000);
                    
                } else {
                    $btn.prop('disabled', false);
                    // Restore original button text based on kitchen type
                    if (kitchenType === 'food') {
                        $btn.html('🍕 <?php _e('Food Ready', 'orders-jet'); ?>');
                    } else if (kitchenType === 'beverages') {
                        $btn.html('🥤 <?php _e('Beverages Ready', 'orders-jet'); ?>');
                    } else {
                        $btn.html('🔥 <?php _e('Mark Ready', 'orders-jet'); ?>');
                    }
                    showExpressNotification('❌ Failed to mark order ready', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                // Restore original button text
                if (kitchenType === 'food') {
                    $btn.html('🍕 <?php _e('Food Ready', 'orders-jet'); ?>');
                } else if (kitchenType === 'beverages') {
                    $btn.html('🥤 <?php _e('Beverages Ready', 'orders-jet'); ?>');
                } else {
                    $btn.html('🔥 <?php _e('Mark Ready', 'orders-jet'); ?>');
                }
                showExpressNotification('❌ Connection error', 'error');
            }
        });
    });
    
    // Complete Order - Individual orders workflow
    $(document).on('click', '.oj-complete-order', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        // Show payment method modal
        showExpressPaymentModal(orderId, function(paymentMethod) {
            $btn.prop('disabled', true).html('⏳ <?php _e('Completing...', 'orders-jet'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_complete_individual_order',
                    order_id: orderId,
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.card_updates) {
                        // Update to Print Invoice state
                        $btn.removeClass('oj-complete-order')
                            .addClass('oj-print-invoice')
                            .html('🖨️ <?php _e('Print Invoice', 'orders-jet'); ?>')
                            .prop('disabled', false)
                            .attr('data-invoice-url', response.data.card_updates.invoice_url);
                        
                        showExpressNotification('✅ Order completed! Print invoice for payment.', 'success');
                    } else {
                        $btn.prop('disabled', false).html('✅ <?php _e('Complete', 'orders-jet'); ?>');
                        showExpressNotification('❌ Failed to complete order', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('✅ <?php _e('Complete', 'orders-jet'); ?>');
                    showExpressNotification('❌ Connection error', 'error');
                }
            });
        });
    });
    
    // Print Invoice - Hidden iframe thermal printing
    $(document).on('click', '.oj-print-invoice', function() {
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        const invoiceUrl = $btn.attr('data-invoice-url');
        
        if (!invoiceUrl) {
            showExpressNotification('❌ Invoice URL not found', 'error');
            return;
        }
        
        // Create hidden iframe for printing
        const iframe = $('<iframe>', {
            src: invoiceUrl,
            style: 'position: absolute; left: -9999px; width: 1px; height: 1px;'
        });
        
        $('body').append(iframe);
        
        iframe.on('load', function() {
            setTimeout(() => {
                try {
                    iframe[0].contentWindow.print();
                    showExpressNotification('🖨️ Print dialog opened', 'success');
                    
                    // Update button to "Paid?"
                    $btn.removeClass('oj-print-invoice')
                        .addClass('oj-confirm-payment')
                        .html('💰 <?php _e('Paid?', 'orders-jet'); ?>');
                    
                    setTimeout(() => iframe.remove(), 1000);
                } catch (e) {
                    showExpressNotification('❌ Print failed: ' + e.message, 'error');
                    iframe.remove();
                }
            }, 500);
        });
        
        iframe.on('error', function() {
            showExpressNotification('❌ Failed to load invoice', 'error');
            iframe.remove();
        });
    });
    
    // Confirm Payment - Final step
    $(document).on('click', '.oj-confirm-payment', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        $btn.prop('disabled', true).html('⏳ <?php _e('Confirming...', 'orders-jet'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_confirm_payment_received',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Slide out card
                    $card.addClass('oj-card-removing');
                    setTimeout(() => {
                        $card.remove();
                        showExpressNotification('✅ Payment confirmed! Order completed.', 'success');
                    }, 500);
                } else {
                    $btn.prop('disabled', false).html('💰 <?php _e('Paid?', 'orders-jet'); ?>');
                    showExpressNotification('❌ Failed to confirm payment', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('💰 <?php _e('Paid?', 'orders-jet'); ?>');
                showExpressNotification('❌ Connection error', 'error');
            }
        });
    });
    
    // Close Table - Table orders workflow
    $(document).on('click', '.oj-close-table', function() {
        const orderId = $(this).data('order-id');
        const tableNumber = $(this).data('table-number');
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        // Show payment method modal for table
        showExpressPaymentModal(orderId, function(paymentMethod) {
            $btn.prop('disabled', true).html('⏳ <?php _e('Closing...', 'orders-jet'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_close_table_group',
                    table_number: tableNumber,
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.combined_order) {
                        // Remove all child order cards for this table
                        $(`.oj-order-card[data-table-number="${tableNumber}"]`).addClass('oj-card-removing');
                        
                        setTimeout(() => {
                            $(`.oj-order-card[data-table-number="${tableNumber}"]`).remove();
                            
                            // Add combined order card
                            const combinedOrder = response.data.combined_order;
                            const combinedCard = createExpressCombinedOrderCard(combinedOrder);
                            $('.oj-orders-grid').prepend(combinedCard);
                            
                            showExpressNotification('✅ Table closed! Combined order created.', 'success');
                        }, 500);
                    } else if (response.data && response.data.show_confirmation) {
                        // Handle processing orders confirmation - same as main orders page
                        const confirmMessage = response.data.message + '\n\n<?php _e('Click OK to continue or Cancel to keep the table open.', 'orders-jet'); ?>';
                        
                        if (confirm(confirmMessage)) {
                            // User confirmed - retry with force_close flag
                            $btn.prop('disabled', true).html('⏳ <?php _e('Force Closing...', 'orders-jet'); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'oj_close_table_group',
                                    table_number: tableNumber,
                                    payment_method: paymentMethod,
                                    force_close: 'true',
                                    nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                                },
                                success: function(forceResponse) {
                                    if (forceResponse.success && forceResponse.data.combined_order) {
                                        // Same success logic as above
                                        $(`.oj-order-card[data-table-number="${tableNumber}"]`).addClass('oj-card-removing');
                                        
                                        setTimeout(() => {
                                            $(`.oj-order-card[data-table-number="${tableNumber}"]`).remove();
                                            
                                            const combinedOrder = forceResponse.data.combined_order;
                                            const combinedCard = createExpressCombinedOrderCard(combinedOrder);
                                            $('.oj-orders-grid').prepend(combinedCard);
                                            
                                            showExpressNotification('✅ Table force closed! Combined order created.', 'success');
                                        }, 500);
                                    } else {
                                        $btn.prop('disabled', false).html('🍽️ <?php _e('Close Table', 'orders-jet'); ?>');
                                        showExpressNotification('❌ Failed to force close table', 'error');
                                    }
                                },
                                error: function() {
                                    $btn.prop('disabled', false).html('🍽️ <?php _e('Close Table', 'orders-jet'); ?>');
                                    showExpressNotification('❌ Connection error during force close', 'error');
                                }
                            });
                        } else {
                            // User cancelled - restore button
                            $btn.prop('disabled', false).html('🍽️ <?php _e('Close Table', 'orders-jet'); ?>');
                        }
                    } else {
                        $btn.prop('disabled', false).html('🍽️ <?php _e('Close Table', 'orders-jet'); ?>');
                        const errorMessage = response.data && response.data.message ? response.data.message : 'Failed to close table';
                        showExpressNotification('❌ ' + errorMessage, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('🍽️ <?php _e('Close Table', 'orders-jet'); ?>');
                    showExpressNotification('❌ Connection error', 'error');
                }
            });
        });
    });
    
    // View Order Details
    $(document).on('click', '.oj-view-order', function() {
        const orderId = $(this).data('order-id');
        const url = '<?php echo admin_url('post.php'); ?>?post=' + orderId + '&action=edit';
        window.open(url, '_blank');
    });
    
    // ========================================================================
    // HELPER FUNCTIONS
    // ========================================================================
    
    function showExpressPaymentModal(orderId, callback) {
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3><?php _e('Payment Method', 'orders-jet'); ?></h3>
                    <p><?php _e('How was this order paid?', 'orders-jet'); ?></p>
                    <div class="oj-payment-buttons">
                        <button class="oj-payment-btn cash" data-method="cash">
                            💵 <?php _e('Cash', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn card" data-method="card">
                            💳 <?php _e('Card', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn other" data-method="other">
                            📱 <?php _e('Other', 'orders-jet'); ?>
                        </button>
                    </div>
                    <button class="oj-modal-close">✕</button>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        modal.find('.oj-payment-btn').on('click', function() {
            const method = $(this).data('method');
            modal.remove();
            callback(method);
        });
        
        modal.find('.oj-modal-close').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    function showExpressNotification(message, type = 'success') {
        const notification = $(`
            <div class="oj-success-notification ${type}">
                ${message}
                <button class="oj-notification-close">✕</button>
            </div>
        `);
        
        $('body').append(notification);
        
        notification.find('.oj-notification-close').on('click', function() {
            notification.remove();
        });
        
        setTimeout(() => notification.remove(), 5000);
    }
    
    function createExpressCombinedOrderCard(combinedOrder) {
        return $(`
            <div class="oj-order-card oj-combined-card" 
                 data-order-id="${combinedOrder.order_id}" 
                 data-status="pending" 
                 data-method="dinein" 
                 data-table-number="${combinedOrder.table_number}">
                <div class="oj-card-header">
                    <div class="oj-order-info">
                        <h3 class="oj-order-number">#${combinedOrder.order_number}</h3>
                        <span class="oj-table-number">Table ${combinedOrder.table_number}</span>
                        <span class="oj-order-time">${combinedOrder.date}</span>
                    </div>
                    <div class="oj-order-badges">
                        <span class="oj-status-badge ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                        <span class="oj-type-badge dinein">🍽️ <?php _e('Dine-in', 'orders-jet'); ?></span>
                        <span class="oj-combined-badge">🔗 <?php _e('Combined Order', 'orders-jet'); ?></span>
                    </div>
                </div>
                <div class="oj-card-body">
                    <div class="oj-customer-info">
                        <span class="oj-customer-name">Table ${combinedOrder.table_number}</span>
                        <span class="oj-order-total">${combinedOrder.total}</span>
                    </div>
                    <div class="oj-order-summary">
                        <span class="oj-item-count">${combinedOrder.item_count} <?php _e('items', 'orders-jet'); ?></span>
                    </div>
                    <div class="oj-items-preview">
                        ${combinedOrder.items.slice(0, 3).map(item => `<span class="oj-item-preview">${item.quantity}x ${item.name}</span>`).join('')}
                        ${combinedOrder.items.length > 3 ? `<span class="oj-more-items">+${combinedOrder.items.length - 3} <?php _e('more', 'orders-jet'); ?></span>` : ''}
                    </div>
                </div>
                <div class="oj-card-actions">
                    <button class="oj-action-btn primary oj-print-invoice" data-order-id="${combinedOrder.order_id}" data-invoice-url="${combinedOrder.invoice_url}">
                        🖨️ <?php _e('Print Invoice', 'orders-jet'); ?>
                    </button>
                    <button class="oj-action-btn secondary oj-view-order" data-order-id="${combinedOrder.order_id}">
                        👁️ <?php _e('Details', 'orders-jet'); ?>
                    </button>
                </div>
            </div>
        `);
    }
});
</script>
