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
