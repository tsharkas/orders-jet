<?php
/**
 * Order Card Partial - Individual Order Display
 * Reusable component for displaying order cards
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * 
 * Expected variables:
 * @var array $order_data - Order data array
 * @var Orders_Jet_Kitchen_Service $kitchen_service - Kitchen service instance
 * @var Orders_Jet_Order_Method_Service $order_method_service - Order method service instance
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract order data
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

// Use pre-processed data for performance (Phase 4A: Performance Critical)
$item_count = $order_data['item_count'];        // Pre-calculated
$items_display = $order_data['items_display'];  // Pre-processed
$order = $order_data['order_object'];           // No database query needed!

// Process badge data using optimized helper function (Phase 4A: Performance Critical)
$badge_data = oj_express_get_optimized_badge_data($order, $kitchen_service, $order_method_service);
$status_class = $badge_data['status']['class'];
$status_icon = $badge_data['status']['icon'];
$status_text = $badge_data['status']['text'];
$type_badge = $badge_data['type'];
$kitchen_badge = $badge_data['kitchen'];

// Generate action buttons using helper function (Phase 4B: Code Structure)
$action_buttons_html = oj_express_get_action_buttons($order_data, $kitchen_status);
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
            <?php echo $items_display; // Pre-processed for performance (Phase 4A) ?>
        </div>
    </div>
    
    <!-- Card Actions -->
    <div class="oj-card-actions">
        <?php echo $action_buttons_html; ?>
    </div>
</div>
