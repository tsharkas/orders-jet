<?php
/**
 * Manager Orders Dashboard - Beautiful Card-Based Design
 * Clean, responsive, and intuitive order management
 * 
 * @package Orders_Jet
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue beautiful card CSS
wp_enqueue_style('oj-manager-orders-cards', ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css', array(), ORDERS_JET_VERSION);


// Server-side filtering with pagination
$current_filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Build query based on filter
$query_args = array(
    'limit' => $per_page,
    'offset' => $offset,
    'orderby' => 'date',
    'order' => 'ASC'
);

// Apply filter-specific status and meta queries
switch ($current_filter) {
    case 'active':
        $query_args['status'] = array('wc-processing', 'wc-pending');
        break;
    case 'processing':
        $query_args['status'] = 'wc-processing';
        break;
    case 'pending':
        $query_args['status'] = 'wc-pending';
        break;
    case 'completed':
        $query_args['status'] = 'wc-completed';
        break;
    case 'dinein':
        $query_args['status'] = array('wc-processing', 'wc-pending');
        $query_args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'exwf_odmethod',
                'value' => 'dinein',
                'compare' => '='
            ),
            array(
                'key' => '_oj_table_number',
                'compare' => 'EXISTS'
            )
        );
        break;
    case 'takeaway':
        $query_args['status'] = array('wc-processing', 'wc-pending');
        $query_args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'exwf_odmethod',
                'value' => 'takeaway',
                'compare' => '='
            ),
            array(
                'key' => '_oj_table_number',
                'value' => '',
                'compare' => 'NOT EXISTS'
            )
        );
        break;
    case 'delivery':
        $query_args['status'] = array('wc-processing', 'wc-pending');
        $query_args['meta_query'] = array(
            array(
                'key' => 'exwf_odmethod',
                'value' => 'delivery',
                'compare' => '='
            )
        );
        break;
    default: // 'all'
        $query_args['status'] = array('wc-processing', 'wc-pending', 'wc-completed');
        break;
}

// Get filtered orders
$all_orders = wc_get_orders($query_args);

// Get total count for pagination (same filter)
$count_args = $query_args;
$count_args['limit'] = -1;
$count_args['offset'] = 0;
$total_orders = count(wc_get_orders($count_args));
$total_pages = ceil($total_orders / $per_page);

// Count orders for filter tabs - all WooCommerce orders
$processing_count = count(wc_get_orders(array(
    'status' => 'wc-processing',
    'limit' => -1
)));

$pending_count = count(wc_get_orders(array(
    'status' => 'wc-pending', 
    'limit' => -1
)));

$completed_count = count(wc_get_orders(array(
    'status' => 'wc-completed',
    'limit' => -1
)));

$active_count = $processing_count + $pending_count;
$all_count = $active_count + $completed_count;

// Count by order type - check all orders and determine type
$dinein_count = 0;
$takeaway_count = 0;
$delivery_count = 0;

// Get active orders only for operational filter counts (dinein, takeaway, delivery)
$active_orders_for_count = wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending'),
    'limit' => -1
));

// Count operational filters (dinein, takeaway, delivery) using ACTIVE orders only
foreach ($active_orders_for_count as $order) {
    $order_method = $order->get_meta('exwf_odmethod');
    
    // If no exwf_odmethod, determine from other meta with better logic
    if (empty($order_method)) {
        $table_number = $order->get_meta('_oj_table_number');
        
        if (!empty($table_number)) {
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
    
    if ($order_method === 'dinein') {
        $dinein_count++;
    } elseif ($order_method === 'takeaway') {
        $takeaway_count++;
    } elseif ($order_method === 'delivery') {
        $delivery_count++;
    }
}
?>

<div class="wrap oj-manager-orders">

    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="oj-page-title"><?php _e('Orders Management', 'orders-jet'); ?></h1>
    </div>
    
    <!-- Filter Tabs -->
    <div class="oj-filters">
        <button class="oj-filter-btn <?php echo $current_filter === 'all' ? 'active' : ''; ?>" data-filter="all">
            <?php _e('All Orders', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $all_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'active' ? 'active' : ''; ?>" data-filter="active">
            <?php _e('Active Orders', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $active_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'processing' ? 'active' : ''; ?>" data-filter="processing">
            🍳 <?php _e('Kitchen', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $processing_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'pending' ? 'active' : ''; ?>" data-filter="pending">
            ✅ <?php _e('Ready', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $pending_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'dinein' ? 'active' : ''; ?>" data-filter="dinein">
            🏢 <?php _e('Table', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $dinein_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'takeaway' ? 'active' : ''; ?>" data-filter="takeaway">
            📦 <?php _e('Pickup', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $takeaway_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'delivery' ? 'active' : ''; ?>" data-filter="delivery">
            🚚 <?php _e('Delivery', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $delivery_count; ?></span>
        </button>
        <button class="oj-filter-btn <?php echo $current_filter === 'completed' ? 'active' : ''; ?>" data-filter="completed">
            📄 <?php _e('Completed', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $completed_count; ?></span>
        </button>
    </div>

    <!-- Orders Grid -->
    <div class="oj-orders-grid" id="oj-orders-grid">
        <?php if (empty($all_orders)) : ?>
            <div class="oj-empty-state">
                <div class="oj-empty-icon">📋</div>
                <div class="oj-empty-title"><?php _e('No Orders Found', 'orders-jet'); ?></div>
                <div class="oj-empty-message"><?php _e('Orders will appear here when customers place them.', 'orders-jet'); ?></div>
            </div>
        <?php else : ?>
            <?php foreach ($all_orders as $order) : 
                $order_id = $order->get_id();
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
                $table_number = $order->get_meta('_oj_table_number');
                $customer_name = $order->get_meta('_oj_customer_name') ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $status = $order->get_status();
                $total = $order->get_total();
                $date_created = $order->get_date_created();
                $items_count = $order->get_item_count();
                
                // Status mapping for display
                $status_class = $status;
                $status_text = '';
                $status_icon = '';
                
                if ($status === 'processing') {
                    $status_text = __('Cooking', 'orders-jet');
                    $status_class = 'cooking';
                    $status_icon = '🍳';
                } elseif ($status === 'pending') {
                    $status_text = __('Ready', 'orders-jet');
                    $status_class = 'ready';
                    $status_icon = '✅';
                } elseif ($status === 'completed') {
                    $status_text = __('Completed', 'orders-jet');
                    $status_class = 'completed';
                    $status_icon = '📄';
                } else {
                    $status_text = ucfirst($status);
                    $status_icon = '❓';
                }
                
                // Order type display and class
                $type_display = '';
                $type_class = '';
                $type_icon = '';
                
                if ($order_method === 'dinein') {
                    $type_display = sprintf(__('Dine In', 'orders-jet'));
                    $type_class = 'dinein';
                    $type_icon = '🏢';
                } elseif ($order_method === 'takeaway') {
                    $type_display = __('Pickup', 'orders-jet');
                    $type_class = 'takeaway';
                    $type_icon = '📦';
                } elseif ($order_method === 'delivery') {
                    $type_display = __('Delivery', 'orders-jet');
                    $type_class = 'delivery';
                    $type_icon = '🚚';
                } else {
                    $type_display = __('Unknown', 'orders-jet');
                    $type_class = 'unknown';
                    $type_icon = '❓';
                }
                
                // Customer display name
                if ($order_method === 'dinein' && !empty($table_number)) {
                    $display_customer = __('Table Guest', 'orders-jet');
                    $table_info = sprintf(__('T%s', 'orders-jet'), $table_number);
                } else {
                    $display_customer = !empty($customer_name) ? $customer_name : __('Guest', 'orders-jet');
                    $table_info = '';
                }
            ?>
                <div class="oj-order-card" 
                     data-order-id="<?php echo esc_attr($order_id); ?>"
                     data-status="<?php echo esc_attr($status); ?>"
                     data-order-type="<?php echo esc_attr($order_method); ?>">
                    
                    <!-- Card Header -->
                    <div class="oj-card-header">
                        <div>
                            <h3 class="oj-order-id">
                                #<?php echo $order_id; ?>
                                <?php if (!empty($table_info)) : ?>
                                    | <?php echo $table_info; ?>
                                <?php endif; ?>
                            </h3>
                        </div>
                        
                        <div class="oj-status-badges">
                            <span class="oj-type-badge <?php echo esc_attr($type_class); ?>">
                                <?php echo $type_icon; ?> <?php echo esc_html($type_display); ?>
                            </span>
                            <span class="oj-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo $status_icon; ?> <?php echo esc_html($status_text); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Card Content -->
                    <div class="oj-card-content">
                        <div class="oj-customer-name"><?php echo esc_html($display_customer); ?></div>
                        
                        <div class="oj-order-meta">
                            <div class="oj-order-time">
                                🕐 <?php echo $date_created ? $date_created->format('g:i A') : ''; ?>
                            </div>
                            <div class="oj-order-total">
                                <?php echo wc_price($total); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Actions -->
                    <div class="oj-card-actions">
                        <?php if ($status === 'completed') : ?>
                            <!-- Completed Order Actions -->
                            <button class="oj-action-btn primary oj-invoice-print" 
                                    data-order-id="<?php echo esc_attr($order_id); ?>">
                                📄 <?php _e('Invoice', 'orders-jet'); ?>
                            </button>
                            <button class="oj-action-btn secondary oj-view-order" 
                                    data-order-id="<?php echo esc_attr($order_id); ?>">
                                👁️ <?php _e('Details', 'orders-jet'); ?>
                            </button>
                            
                        <?php elseif ($status === 'pending') : ?>
                            <!-- Ready Order Actions -->
                            <?php if ($order_method === 'dinein') : ?>
                                <!-- Table Order - Close Table -->
                                <button class="oj-action-btn success oj-close-table" 
                                        data-table="<?php echo esc_attr($table_number); ?>"
                                        data-order-id="<?php echo esc_attr($order_id); ?>">
                                    🏢 <?php _e('Close Table', 'orders-jet'); ?>
                                </button>
                            <?php else : ?>
                                <!-- Pickup/Delivery Order - Complete -->
                                <button class="oj-action-btn success oj-complete-order" 
                                        data-order-id="<?php echo esc_attr($order_id); ?>"
                                        data-type="<?php echo esc_attr($order_method); ?>">
                                    ✅ <?php _e('Complete', 'orders-jet'); ?>
                                </button>
                            <?php endif; ?>
                            <button class="oj-action-btn secondary oj-view-order" 
                                    data-order-id="<?php echo esc_attr($order_id); ?>">
                                👁️ <?php _e('Details', 'orders-jet'); ?>
                            </button>
                            
                        <?php elseif ($status === 'processing') : ?>
                            <!-- Cooking Order Actions -->
                            <button class="oj-action-btn warning oj-mark-ready" 
                                    data-order-id="<?php echo esc_attr($order_id); ?>">
                                ✅ <?php _e('Mark Ready', 'orders-jet'); ?>
                            </button>
                            <button class="oj-action-btn secondary oj-view-order" 
                                    data-order-id="<?php echo esc_attr($order_id); ?>">
                                👁️ <?php _e('Details', 'orders-jet'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination Controls -->
    <?php if ($total_orders > 0) : ?>
        <div class="oj-pagination-container">
            <div class="oj-pagination-info">
                <?php printf(__('Page %d of %d (%d total orders)', 'orders-jet'), $current_page, $total_pages, $total_orders); ?>
            </div>
            
            <div class="oj-pagination-controls">
                <?php if ($current_page > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array('paged' => $current_page - 1, 'filter' => isset($_GET['filter']) ? $_GET['filter'] : 'all'))); ?>" class="oj-pagination-btn oj-prev-btn">
                        <strong>← <?php _e('Previous', 'orders-jet'); ?></strong>
                    </a>
                <?php endif; ?>
                
                <span class="oj-pagination-current">
                    <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                </span>
                
                <?php if ($current_page < $total_pages) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array('paged' => $current_page + 1, 'filter' => isset($_GET['filter']) ? $_GET['filter'] : 'all'))); ?>" class="oj-pagination-btn oj-next-btn">
                        <strong><?php _e('Next', 'orders-jet'); ?> →</strong>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    
    
    // Server-side filtering - no need for client-side filter restoration
    
    // Filter Logic - Server-side filtering with page reload
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Navigate to filtered page (server-side filtering)
        const url = new URL(window.location);
        url.searchParams.set('filter', filter);
        url.searchParams.delete('paged'); // Reset to page 1
        window.location.href = url.toString();
    });
    
    // Mark Ready - Use event delegation for dynamic buttons
    $(document).on('click', '.oj-mark-ready', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        
        if (confirm('<?php _e('Mark this order as ready?', 'orders-jet'); ?>')) {
            $btn.prop('disabled', true).text('<?php _e('Processing...', 'orders-jet'); ?>');
            
            $.post(ajaxurl, {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    // Update card without page reload
                    updateOrderCard(response.data.card_updates);
                    showSuccessNotification(response.data.message);
                    // Update filter counts
                    updateFilterCounts();
                } else {
                    alert(response.data.message || '<?php _e('Error occurred', 'orders-jet'); ?>');
                    $btn.prop('disabled', false).text('<?php _e('Mark Ready', 'orders-jet'); ?>');
                }
            }).fail(function() {
                alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                $btn.prop('disabled', false).text('<?php _e('Mark Ready', 'orders-jet'); ?>');
            });
        }
    });
    
    // Complete Order - DISABLED: Using direct binding instead of event delegation
    // The event delegation handler has been disabled to prevent conflicts with direct binding
    // All Complete Order functionality is now handled by the direct binding in updateOrderCard()
    
    // Close Table
    $('.oj-close-table').on('click', function() {
        const tableNumber = $(this).data('table');
        const orderId = $(this).data('order-id');
        
        if (confirm('<?php _e('Close this table and generate consolidated invoice?', 'orders-jet'); ?>')) {
            // Show payment method selection
            const paymentMethods = [
                {value: 'cash', text: '<?php _e('Cash', 'orders-jet'); ?>'},
                {value: 'card', text: '<?php _e('Card', 'orders-jet'); ?>'},
                {value: 'online', text: '<?php _e('Online Payment', 'orders-jet'); ?>'}
            ];
            
            let paymentOptions = '';
            paymentMethods.forEach(method => {
                paymentOptions += `<option value="${method.value}">${method.text}</option>`;
            });
            
            const modal = $(`
                <div class="oj-payment-modal-overlay">
                    <div class="oj-payment-modal">
                        <h3><?php _e('Close Table', 'orders-jet'); ?> #${tableNumber}</h3>
                        <p><?php _e('Select payment method:', 'orders-jet'); ?></p>
                        <select class="oj-payment-method">
                            ${paymentOptions}
                        </select>
                        <div class="oj-modal-actions">
                            <button class="button button-primary oj-confirm-close">
                                <?php _e('Close Table', 'orders-jet'); ?>
                            </button>
                            <button class="button oj-cancel-close">
                                <?php _e('Cancel', 'orders-jet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            // Confirm closure
            modal.find('.oj-confirm-close').on('click', function() {
                const paymentMethod = modal.find('.oj-payment-method').val();
                modal.remove();
                
                $.post(ajaxurl, {
                    action: 'oj_close_table_group',
                    table_number: tableNumber,
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                }, function(response) {
                if (response.success) {
                    // Remove table order cards without page reload
                    removeTableOrderCards(response.data.card_updates.order_ids);
                    // Update filter counts
                    updateFilterCounts();
                    // Show success modal with thermal print option
                    showTableSuccessModalWithThermalPrint(tableNumber, response.data);
                } else if (response.data && response.data.show_confirmation) {
                        // Handle processing orders confirmation
                        const confirmMessage = response.data.message + '\n\n<?php _e('Click OK to continue or Cancel to keep the table open.', 'orders-jet'); ?>';
                        
                        if (confirm(confirmMessage)) {
                            // User confirmed - retry with force_close flag
                            $.post(ajaxurl, {
                                action: 'oj_close_table_group',
                                table_number: tableNumber,
                                payment_method: paymentMethod,
                                force_close: 'true',
                                nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                        }, function(retryResponse) {
                            if (retryResponse.success) {
                                // Remove table order cards without page reload
                                removeTableOrderCards(retryResponse.data.card_updates.order_ids);
                                // Update filter counts
                                updateFilterCounts();
                                // Show success modal with thermal print option
                                showTableSuccessModalWithThermalPrint(tableNumber, retryResponse.data);
                            } else {
                                    alert(retryResponse.data.message || '<?php _e('Error occurred during table closure', 'orders-jet'); ?>');
                                }
                            }).fail(function() {
                                alert('<?php _e('Network error occurred during table closure', 'orders-jet'); ?>');
                            });
                        }
                        // If user cancels, do nothing (table stays open)
                    } else {
                        alert(response.data.message || '<?php _e('Error occurred', 'orders-jet'); ?>');
                    }
                }).fail(function() {
                    alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                });
            });
            
            // Cancel
            modal.find('.oj-cancel-close').on('click', function() {
                modal.remove();
            });
        }
    });
    
    // Thermal Print Invoice
    $('.oj-invoice-print').on('click', function() {
        const orderId = $(this).data('order-id');
        const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_get_order_invoice&order_id=' + orderId + '&print=1&nonce=<?php echo wp_create_nonce('oj_get_invoice'); ?>';
        window.open(invoiceUrl, '_blank');
    });
    
    // View Order Details
    $('.oj-view-order').on('click', function() {
        const orderId = $(this).data('order-id');
        // Open WooCommerce order edit page
        const orderUrl = '<?php echo admin_url('post.php'); ?>?post=' + orderId + '&action=edit';
        window.open(orderUrl, '_blank');
    });
    
    
    
    
    // ===== AJAX CARD UPDATE FUNCTIONS =====
    
    /**
     * Replace child order cards with combined order card
     */
    function replaceWithCombinedOrder(cardUpdates, combinedOrder) {
        console.log('🔄 Replacing child order cards with combined order card');
        console.log('Child order IDs to remove:', cardUpdates.child_order_ids);
        console.log('Combined order data:', combinedOrder);
        
        // Find the first child order card to use as insertion point
        const firstChildCard = $(`.oj-order-card[data-order-id="${cardUpdates.child_order_ids[0]}"]`);
        
        if (firstChildCard.length) {
            // Create the new combined order card HTML
            const combinedCardHtml = `
                <div class="oj-order-card" data-order-id="${combinedOrder.order_id}" data-status="${combinedOrder.status}" data-type="${combinedOrder.order_type}">
                    <div class="oj-card-header">
                        <div class="oj-order-info">
                            <h3 class="oj-order-number">#${combinedOrder.order_id} | ${combinedOrder.table_number}</h3>
                            <span class="oj-order-type-badge dinein">
                                🏢 <?php _e('DINE IN', 'orders-jet'); ?>
                            </span>
                        </div>
                        <span class="oj-status-badge completed"><?php _e('READY FOR PAYMENT', 'orders-jet'); ?></span>
                    </div>
                    
                    <div class="oj-card-body">
                        <div class="oj-order-meta">
                            <span class="oj-meta-item">
                                <strong><?php _e('Table Guest', 'orders-jet'); ?></strong>
                            </span>
                            <span class="oj-meta-item">
                                ⏰ ${combinedOrder.date}
                            </span>
                            <span class="oj-meta-item">
                                📦 ${combinedOrder.item_count} <?php _e('items', 'orders-jet'); ?>
                            </span>
                        </div>
                        
                        <div class="oj-order-total">
                            ${combinedOrder.total} <?php echo get_woocommerce_currency(); ?>
                        </div>
                    </div>
                    
                    <div class="oj-card-footer">
                        <button class="oj-action-btn success oj-print-invoice-combined" 
                                data-order-id="${combinedOrder.order_id}"
                                data-invoice-url="${combinedOrder.invoice_url}"
                                data-table-number="${combinedOrder.table_number}"
                                data-type="combined">
                            🖨️ <?php _e('Print Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="oj-action-btn secondary oj-view-details" 
                                data-order-id="${combinedOrder.order_id}">
                            👁️ <?php _e('Details', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            `;
            
            // Insert the new combined order card before the first child card
            firstChildCard.before(combinedCardHtml);
            
            // Remove all child order cards with animation
            cardUpdates.child_order_ids.forEach(orderId => {
                const $card = $(`.oj-order-card[data-order-id="${orderId}"]`);
                if ($card.length) {
                    $card.addClass('oj-card-removing');
                    setTimeout(() => {
                        $card.remove();
                        console.log('✅ Removed child order card:', orderId);
                    }, 300);
                }
            });
            
            // Bind Print Invoice functionality to the new combined order card
            setTimeout(() => {
                const $combinedCard = $(`.oj-order-card[data-order-id="${combinedOrder.order_id}"]`);
                const $printBtn = $combinedCard.find('.oj-print-invoice-combined');
                
                $printBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const invoiceUrl = $(this).data('invoice-url');
                    const orderId = $(this).data('order-id');
                    const tableNumber = $(this).data('table-number');
                    
                    if (invoiceUrl) {
                        console.log('🖨️ Opening combined order invoice with direct print for order:', orderId);
                        
                        // Create hidden iframe for direct printing
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.style.position = 'absolute';
                        iframe.style.left = '-9999px';
                        iframe.style.top = '-9999px';
                        iframe.style.width = '1px';
                        iframe.style.height = '1px';
                        iframe.src = invoiceUrl;
                        document.body.appendChild(iframe);
                        
                        let printAttempted = false;
                        const printTimeout = setTimeout(() => {
                            if (!printAttempted) {
                                console.error('❌ Print timeout - invoice failed to load');
                                showPrintError('Invoice failed to load. Please try again.');
                                cleanupIframe();
                            }
                        }, 10000);
                        
                        iframe.onload = function() {
                            console.log('✅ Invoice loaded, preparing to print...');
                            setTimeout(() => {
                                try {
                                    printAttempted = true;
                                    clearTimeout(printTimeout);
                                    iframe.contentWindow.print();
                                    console.log('🖨️ Print dialog opened successfully');
                                    setTimeout(() => {
                                        cleanupIframe();
                                    }, 1000);
                                } catch (error) {
                                    console.error('❌ Print failed:', error);
                                    showPrintError('Print dialog could not be opened. Please try again.');
                                    cleanupIframe();
                                }
                            }, 500);
                        };
                        
                        iframe.onerror = function() {
                            console.error('❌ Invoice failed to load');
                            clearTimeout(printTimeout);
                            showPrintError('Invoice could not be loaded. Please check the URL and try again.');
                            cleanupIframe();
                        };
                        
                        function cleanupIframe() {
                            if (iframe && iframe.parentNode) {
                                document.body.removeChild(iframe);
                                console.log('🧹 Iframe cleaned up');
                            }
                        }
                        
                        function showPrintError(message) {
                            const errorNotification = $(`
                                <div class="oj-error-notification">
                                    <span>❌ ${message}</span>
                                    <button class="oj-error-close">×</button>
                                </div>
                            `);
                            $('body').append(errorNotification);
                            setTimeout(() => {
                                errorNotification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }, 5000);
                            errorNotification.find('.oj-error-close').on('click', function() {
                                errorNotification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                        }
                        
                        // Update button to "Paid?" after print dialog opens
                        setTimeout(() => {
                            $printBtn.text('Paid?');
                            $printBtn.removeClass('oj-print-invoice-combined');
                            $printBtn.addClass('oj-confirm-payment-combined');
                            $printBtn.off('click');
                            
                            // Update status badge
                            $combinedCard.find('.oj-status-badge').text('<?php _e('WAITING PAYMENT', 'orders-jet'); ?>').removeClass('completed').addClass('waiting-payment');
                            
                            // Bind Paid? functionality
                            $printBtn.on('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                if (confirm('<?php _e('Confirm payment received for this order?', 'orders-jet'); ?>')) {
                                    $.post(ajaxurl, {
                                        action: 'oj_confirm_payment_received',
                                        order_id: orderId,
                                        order_type: 'combined',
                                        table_number: tableNumber,
                                        nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
                                    }, function(response) {
                                        if (response.success) {
                                            console.log('✅ Payment confirmed, removing combined order card');
                                            removeOrderCard(orderId);
                                            updateFilterCounts();
                                            showSuccessNotification(response.data.message);
                                        } else {
                                            alert(response.data.message || '<?php _e('Error confirming payment', 'orders-jet'); ?>');
                                        }
                                    }).fail(function() {
                                        alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                                    });
                                }
                            });
                        }, 1000);
                    }
                });
                
                console.log('✅ Combined order card created and Print Invoice button bound');
            }, 350); // Wait for child cards to be removed
        }
    }
    
    /**
     * Update multiple table order cards to Print Invoice state
     */
    function updateTableOrderCards(cardUpdates) {
        if (cardUpdates.action === 'update_to_print_invoice' && cardUpdates.order_ids) {
            console.log('🔄 Updating table order cards to Print Invoice state');
            cardUpdates.order_ids.forEach(orderId => {
                const $card = $(`.oj-order-card[data-order-id="${orderId}"]`);
                if ($card.length) {
                    // Update status badge
                    const $statusBadge = $card.find('.oj-status-badge');
                    $statusBadge.text(cardUpdates.status_badge_text);
                    $statusBadge.removeClass('processing pending completed ready waiting-payment');
                    $statusBadge.addClass(cardUpdates.status_badge_class);
                    
                    // Update the first button (action button)
                    const $actionBtn = $card.find('.oj-action-btn:first');
                    $actionBtn.text(cardUpdates.button_text);
                    $actionBtn.removeClass('oj-mark-ready oj-complete-order oj-thermal-print oj-close-table oj-print-invoice oj-print-invoice-table oj-confirm-payment');
                    $actionBtn.addClass(cardUpdates.button_class);
                    
                    // Set data attributes for Print Invoice functionality
                    $actionBtn.attr('data-invoice-url', cardUpdates.invoice_url || '');
                    $actionBtn.attr('data-order-id', orderId);
                    $actionBtn.attr('data-table-number', cardUpdates.table_number || '');
                    $actionBtn.attr('data-type', 'table');
                    
                    // Remove old handlers and attributes
                    $actionBtn.removeAttr('onclick');
                    $actionBtn.removeAttr('formaction');
                    $actionBtn.removeAttr('form');
                    $actionBtn.attr('type', 'button');
                    $actionBtn.prop('disabled', false);
                    $actionBtn.off('click');
                    
                    // Bind Print Invoice functionality for table orders
                    $actionBtn.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const invoiceUrl = $(this).data('invoice-url');
                        const orderId = $(this).data('order-id');
                        const tableNumber = $(this).data('table-number');
                        
                        if (invoiceUrl) {
                            console.log('🖨️ Opening table invoice with direct print for order:', orderId);
                            
                            // Create hidden iframe for direct printing
                            const iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.style.position = 'absolute';
                            iframe.style.left = '-9999px';
                            iframe.style.top = '-9999px';
                            iframe.style.width = '1px';
                            iframe.style.height = '1px';
                            iframe.src = invoiceUrl;
                            document.body.appendChild(iframe);
                            
                            let printAttempted = false;
                            const printTimeout = setTimeout(() => {
                                if (!printAttempted) {
                                    console.error('❌ Print timeout - invoice failed to load');
                                    showPrintError('Invoice failed to load. Please try again.');
                                    cleanupIframe();
                                }
                            }, 10000);
                            
                            iframe.onload = function() {
                                console.log('✅ Invoice loaded, preparing to print...');
                                setTimeout(() => {
                                    try {
                                        printAttempted = true;
                                        clearTimeout(printTimeout);
                                        iframe.contentWindow.print();
                                        console.log('🖨️ Print dialog opened successfully');
                                        setTimeout(() => {
                                            cleanupIframe();
                                        }, 1000);
                                    } catch (error) {
                                        console.error('❌ Print failed:', error);
                                        showPrintError('Print dialog could not be opened. Please try again.');
                                        cleanupIframe();
                                    }
                                }, 500);
                            };
                            
                            iframe.onerror = function() {
                                console.error('❌ Invoice failed to load');
                                clearTimeout(printTimeout);
                                showPrintError('Invoice could not be loaded. Please check the URL and try again.');
                                cleanupIframe();
                            };
                            
                            function cleanupIframe() {
                                if (iframe && iframe.parentNode) {
                                    document.body.removeChild(iframe);
                                    console.log('🧹 Iframe cleaned up');
                                }
                            }
                            
                            function showPrintError(message) {
                                const errorNotification = $(`
                                    <div class="oj-error-notification">
                                        <span>❌ ${message}</span>
                                        <button class="oj-error-close">×</button>
                                    </div>
                                `);
                                $('body').append(errorNotification);
                                setTimeout(() => {
                                    errorNotification.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                }, 5000);
                                errorNotification.find('.oj-error-close').on('click', function() {
                                    errorNotification.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                });
                            }
                            
                            // Update ALL table order cards to "Paid?" after print dialog opens
                            setTimeout(() => {
                                updateTableOrderCardsToPaid(cardUpdates.order_ids, tableNumber);
                            }, 1000);
                        }
                    });
                    
                    console.log('Table order card updated to Print Invoice:', {
                        orderId: orderId,
                        tableNumber: cardUpdates.table_number,
                        invoiceUrl: cardUpdates.invoice_url
                    });
                }
            });
        }
    }
    
    /**
     * Update all table order cards to Paid? state
     */
    function updateTableOrderCardsToPaid(orderIds, tableNumber) {
        console.log('💰 Updating table order cards to Paid? state');
        orderIds.forEach(orderId => {
            const $card = $(`.oj-order-card[data-order-id="${orderId}"]`);
            if ($card.length) {
                // Update status badge
                const $statusBadge = $card.find('.oj-status-badge');
                $statusBadge.text('WAITING PAYMENT');
                $statusBadge.removeClass('processing pending completed ready waiting-payment');
                $statusBadge.addClass('waiting-payment');
                
                // Update button
                const $actionBtn = $card.find('.oj-action-btn:first');
                $actionBtn.text('Paid?');
                $actionBtn.removeClass('oj-mark-ready oj-complete-order oj-thermal-print oj-close-table oj-print-invoice oj-print-invoice-table oj-confirm-payment oj-confirm-payment-table');
                $actionBtn.addClass('oj-confirm-payment-table');
                
                // Set data attributes
                $actionBtn.attr('data-order-id', orderId);
                $actionBtn.attr('data-type', 'table');
                $actionBtn.attr('data-table-number', tableNumber);
                
                // Remove old handlers
                $actionBtn.off('click');
                
                // Bind Paid? functionality for table orders
                $actionBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const orderId = $(this).data('order-id');
                    const tableNumber = $(this).data('table-number');
                    
                    console.log('💰 Payment confirmation clicked for table order:', orderId, 'table:', tableNumber);
                    
                    if (confirm('<?php _e('Confirm payment received for this table?', 'orders-jet'); ?>')) {
                        $.post(ajaxurl, {
                            action: 'oj_confirm_payment_received',
                            order_id: orderId,
                            order_type: 'table',
                            table_number: tableNumber,
                            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                console.log('✅ Payment confirmed, removing all table cards');
                                // Remove all table order cards
                                if (response.data.table_order_ids) {
                                    removeTableOrderCards(response.data.table_order_ids);
                                }
                                updateFilterCounts();
                                showSuccessNotification(response.data.message);
                            } else {
                                alert(response.data.message || '<?php _e('Error confirming payment', 'orders-jet'); ?>');
                            }
                        }).fail(function() {
                            alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                        });
                    }
                });
            }
        });
    }
    
    /**
     * Update individual order card without page reload
     */
    function updateOrderCard(cardUpdates) {
        const $card = $(`.oj-order-card[data-order-id="${cardUpdates.order_id}"]`);
        
        if ($card.length) {
            // Update status badge
            const $statusBadge = $card.find('.oj-status-badge');
            $statusBadge.text(cardUpdates.status_badge_text);
            $statusBadge.removeClass('processing pending completed ready');
            $statusBadge.addClass(cardUpdates.status_badge_class);
            
            // ✅ FIX: Update ONLY the first button (action button)
            const $actionBtn = $card.find('.oj-action-btn:first');
            $actionBtn.text(cardUpdates.button_text);
            $actionBtn.removeClass('oj-mark-ready oj-complete-order oj-thermal-print oj-close-table');
            $actionBtn.addClass(cardUpdates.button_class);
            
            // ✅ FIX: Set data attributes and bind events directly
            if (cardUpdates.button_class === 'oj-complete-order') {
                // Set data attributes for Complete Order functionality
                $actionBtn.attr('data-order-id', cardUpdates.order_id);
                $actionBtn.attr('data-type', 'individual');
                
                // 🔥 CRITICAL: Remove ALL original handlers and attributes
                $actionBtn.removeAttr('onclick');           // Remove onclick handlers
                $actionBtn.removeAttr('formaction');        // Remove form actions
                $actionBtn.removeAttr('form');              // Remove form associations
                $actionBtn.attr('type', 'button');          // Change from submit to button
                $actionBtn.prop('disabled', false);         // Ensure button is enabled
                
                // Remove ALL existing click handlers first
                $actionBtn.off('click');
                
                // Bind Complete Order functionality directly
                $actionBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('🔥 DIRECT Complete Order click handler triggered!');
                    console.log('Button element:', this);
                    console.log('Button classes:', $(this).attr('class'));
                    console.log('Button data:', $(this).data());
                    
                    const orderId = $(this).data('order-id');
                    const orderType = $(this).data('type');
                    
                    console.log('Order ID from direct handler:', orderId);
                    console.log('Order Type from direct handler:', orderType);
                    
                    if (!orderId) {
                        console.error('❌ No order ID found in direct handler');
                        return;
                    }
                    
                    console.log('✅ Calling showCompleteOrderModalDirect...');
                    // Trigger the payment modal
                    showCompleteOrderModalDirect(orderId, orderType);
                });
                
                // Also test if the button is clickable
                console.log('🔍 Button after binding:', $actionBtn[0]);
                console.log('🔍 Button clickable test:', $actionBtn.is(':visible'), $actionBtn.prop('disabled'));
                
                // Test click handler removed - not needed anymore
                
                // Test if there are any CSS issues preventing clicks
                $actionBtn.css({
                    'pointer-events': 'auto',
                    'cursor': 'pointer',
                    'z-index': '9999'
                });
                
                console.log('Complete Order button updated:', {
                    orderId: cardUpdates.order_id,
                    buttonClass: cardUpdates.button_class,
                    buttonText: cardUpdates.button_text
                });
            } else if (cardUpdates.button_class === 'oj-close-table') {
                // Set data attributes for Close Table functionality
                $actionBtn.attr('data-table', cardUpdates.table_number || '');
                $actionBtn.attr('data-order-id', cardUpdates.order_id);
                
                // 🔥 CRITICAL: Remove ALL original handlers and attributes
                $actionBtn.removeAttr('onclick');
                $actionBtn.removeAttr('formaction');
                $actionBtn.removeAttr('form');
                $actionBtn.attr('type', 'button');
                $actionBtn.prop('disabled', false);
                
                // Remove ALL existing click handlers first
                $actionBtn.off('click');
                
                // Bind Close Table functionality directly
                $actionBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const tableNumber = $(this).data('table');
                    const orderId = $(this).data('order-id');
                    
                    console.log('🏢 Close Table clicked for order:', orderId, 'table:', tableNumber);
                    
                    if (confirm('<?php _e('Close this table and generate consolidated invoice?', 'orders-jet'); ?>')) {
                        // Show payment method selection
                        const paymentMethods = [
                            {value: 'cash', text: '<?php _e('Cash', 'orders-jet'); ?>'},
                            {value: 'card', text: '<?php _e('Card', 'orders-jet'); ?>'},
                            {value: 'online', text: '<?php _e('Online Payment', 'orders-jet'); ?>'}
                        ];
                        
                        let paymentOptions = '';
                        paymentMethods.forEach(method => {
                            paymentOptions += `<option value="${method.value}">${method.text}</option>`;
                        });
                        
                        const modal = $(`
                            <div class="oj-payment-modal-overlay">
                                <div class="oj-payment-modal">
                                    <h3><?php _e('Close Table', 'orders-jet'); ?> ${tableNumber}</h3>
                                    <p><?php _e('Select payment method:', 'orders-jet'); ?></p>
                                    <select class="oj-payment-method">
                                        ${paymentOptions}
                                    </select>
                                    <div class="oj-modal-actions">
                                        <button class="button button-primary oj-confirm-close">
                                            <?php _e('Confirm', 'orders-jet'); ?>
                                        </button>
                                        <button class="button oj-cancel-close">
                                            <?php _e('Cancel', 'orders-jet'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `);
                        
                        $('body').append(modal);
                        
                        modal.find('.oj-confirm-close').on('click', function() {
                            const paymentMethod = modal.find('.oj-payment-method').val();
                            modal.remove();
                            
                            $.post(ajaxurl, {
                                action: 'oj_close_table_group',
                                table_number: tableNumber,
                                payment_method: paymentMethod,
                                nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                            }, function(response) {
                                if (response.success) {
                                    // Replace child order cards with combined order card
                                    replaceWithCombinedOrder(response.data.card_updates, response.data.combined_order);
                                    updateFilterCounts();
                                    showSuccessNotification(response.data.message);
                                } else if (response.data && response.data.show_confirmation) {
                                    const confirmMessage = response.data.message + '\n\n<?php _e('Click OK to continue or Cancel to keep the table open.', 'orders-jet'); ?>';
                                    
                                    if (confirm(confirmMessage)) {
                                        $.post(ajaxurl, {
                                            action: 'oj_close_table_group',
                                            table_number: tableNumber,
                                            payment_method: paymentMethod,
                                            force_close: 'true',
                                            nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                                        }, function(retryResponse) {
                                            if (retryResponse.success) {
                                                // Replace child order cards with combined order card
                                                replaceWithCombinedOrder(retryResponse.data.card_updates, retryResponse.data.combined_order);
                                                updateFilterCounts();
                                                showSuccessNotification(retryResponse.data.message);
                                            } else {
                                                alert(retryResponse.data.message || '<?php _e('Error occurred during table closure', 'orders-jet'); ?>');
                                            }
                                        }).fail(function() {
                                            alert('<?php _e('Network error occurred during table closure', 'orders-jet'); ?>');
                                        });
                                    }
                                } else {
                                    alert(response.data.message || '<?php _e('Error occurred', 'orders-jet'); ?>');
                                }
                            }).fail(function() {
                                alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                            });
                        });
                        
                        modal.find('.oj-cancel-close').on('click', function() {
                            modal.remove();
                        });
                    }
                });
                
                console.log('Close Table button updated:', {
                    orderId: cardUpdates.order_id,
                    tableNumber: cardUpdates.table_number,
                    buttonClass: cardUpdates.button_class,
                    buttonText: cardUpdates.button_text
                });
            } else if (cardUpdates.button_class === 'oj-print-invoice') {
                // Set data attributes for Print Invoice functionality
                $actionBtn.attr('data-invoice-url', cardUpdates.invoice_url || '');
                $actionBtn.attr('data-order-id', cardUpdates.order_id);
                
                // Bind Print Invoice functionality with auto-print
                $actionBtn.off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const invoiceUrl = $(this).data('invoice-url');
                    const orderId = $(this).data('order-id');
                    
                    if (invoiceUrl) {
                        console.log('🖨️ Opening invoice with direct print for order:', orderId);
                        
                        // Create hidden iframe for direct printing
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.style.position = 'absolute';
                        iframe.style.left = '-9999px';
                        iframe.style.top = '-9999px';
                        iframe.style.width = '1px';
                        iframe.style.height = '1px';
                        iframe.src = invoiceUrl;
                        document.body.appendChild(iframe);
                        
                        // Set up error handling
                        let printAttempted = false;
                        const printTimeout = setTimeout(() => {
                            if (!printAttempted) {
                                console.error('❌ Print timeout - invoice failed to load');
                                showPrintError('Invoice failed to load. Please try again.');
                                cleanupIframe();
                            }
                        }, 10000); // 10 second timeout
                        
                        iframe.onload = function() {
                            console.log('✅ Invoice loaded, preparing to print...');
                            setTimeout(() => {
                                try {
                                    printAttempted = true;
                                    clearTimeout(printTimeout);
                                    
                                    // Attempt to print
                                    iframe.contentWindow.print();
                                    console.log('🖨️ Print dialog opened successfully');
                                    
                                    // Clean up iframe after printing
                                    setTimeout(() => {
                                        cleanupIframe();
                                    }, 1000);
                                    
                                } catch (error) {
                                    console.error('❌ Print failed:', error);
                                    showPrintError('Print dialog could not be opened. Please try again.');
                                    cleanupIframe();
                                }
                            }, 500); // Small delay to ensure content loads
                        };
                        
                        iframe.onerror = function() {
                            console.error('❌ Invoice failed to load');
                            clearTimeout(printTimeout);
                            showPrintError('Invoice could not be loaded. Please check the URL and try again.');
                            cleanupIframe();
                        };
                        
                        // Cleanup function
                        function cleanupIframe() {
                            if (iframe && iframe.parentNode) {
                                document.body.removeChild(iframe);
                                console.log('🧹 Iframe cleaned up');
                            }
                        }
                        
                        // Show error message function
                        function showPrintError(message) {
                            // Create a more user-friendly error notification
                            const errorNotification = $(`
                                <div class="oj-error-notification">
                                    <span>❌ ${message}</span>
                                    <button class="oj-error-close">×</button>
                                </div>
                            `);
                            
                            $('body').append(errorNotification);
                            
                            // Auto-remove after 5 seconds
                            setTimeout(() => {
                                errorNotification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }, 5000);
                            
                            // Manual close button
                            errorNotification.find('.oj-error-close').on('click', function() {
                                errorNotification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                        }
                        
                        // Update button to "Paid?" after print dialog opens
                        setTimeout(() => {
                            updateOrderCard({
                                order_id: orderId,
                                button_text: 'Paid?',
                                button_class: 'oj-confirm-payment',
                                status_badge_text: 'WAITING PAYMENT',
                                status_badge_class: 'waiting-payment'
                            });
                        }, 1000);
                    }
                });
                
                console.log('Print Invoice button updated:', {
                    orderId: cardUpdates.order_id,
                    invoiceUrl: cardUpdates.invoice_url
                });
            } else if (cardUpdates.button_class === 'oj-confirm-payment') {
                // Set data attributes for Payment Confirmation functionality
                $actionBtn.attr('data-order-id', cardUpdates.order_id);
                $actionBtn.attr('data-type', 'individual');
                
                // Bind Payment Confirmation functionality
                $actionBtn.off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const orderId = $(this).data('order-id');
                    const orderType = $(this).data('type');
                    
                    console.log('💰 Payment confirmation clicked for order:', orderId);
                    
                    if (confirm('<?php _e('Confirm payment received for this order?', 'orders-jet'); ?>')) {
                        // Call payment confirmation AJAX
                        $.post(ajaxurl, {
                            action: 'oj_confirm_payment_received',
                            order_id: orderId,
                            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                console.log('✅ Payment confirmed, removing card');
                                // Remove card with animation
                                removeOrderCard(orderId);
                                // Update filter counts
                                updateFilterCounts();
                            } else {
                                alert(response.data.message || '<?php _e('Error confirming payment', 'orders-jet'); ?>');
                            }
                        }).fail(function() {
                            alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
                        });
                    }
                });
                
                console.log('Paid? button updated:', {
                    orderId: cardUpdates.order_id,
                    buttonClass: cardUpdates.button_class,
                    buttonText: cardUpdates.button_text
                });
            }
            
            // Add smooth animation
            $card.addClass('oj-card-updated');
            setTimeout(() => $card.removeClass('oj-card-updated'), 1000);
        }
    }
    
    /**
     * Show Complete Order Modal (direct binding for AJAX updated buttons)
     */
    function showCompleteOrderModalDirect(orderId, orderType) {
        console.log('showCompleteOrderModalDirect called:', orderId, orderType);
        
        // Show payment method selection
        const paymentMethods = [
            {value: 'cash', text: '<?php _e('Cash', 'orders-jet'); ?>'},
            {value: 'card', text: '<?php _e('Card', 'orders-jet'); ?>'},
            {value: 'online', text: '<?php _e('Online Payment', 'orders-jet'); ?>'}
        ];
        
        let paymentOptions = '';
        paymentMethods.forEach(method => {
            paymentOptions += `<option value="${method.value}">${method.text}</option>`;
        });
        
        const modal = $(`
            <div class="oj-payment-modal-overlay">
                <div class="oj-payment-modal">
                    <h3><?php _e('Complete Order', 'orders-jet'); ?> #${orderId}</h3>
                    <p><?php _e('Select payment method:', 'orders-jet'); ?></p>
                    <select class="oj-payment-method">
                        ${paymentOptions}
                    </select>
                    <div class="oj-modal-actions">
                        <button class="button button-primary oj-confirm-complete">
                            <?php _e('Complete Order', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-cancel-complete">
                            <?php _e('Cancel', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Confirm completion
        modal.find('.oj-confirm-complete').on('click', function() {
            const paymentMethod = modal.find('.oj-payment-method').val();
            modal.remove();
            
            $.post(ajaxurl, {
                action: 'oj_complete_individual_order',
                order_id: orderId,
                payment_method: paymentMethod,
                nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    // Update card without page reload
                    updateOrderCard(response.data.card_updates);
                    // Update filter counts
                    updateFilterCounts();
                    // No success modal - card updates directly to "Print Invoice" button
                } else {
                    alert(response.data.message || '<?php _e('Error occurred', 'orders-jet'); ?>');
                }
            }).fail(function() {
                alert('<?php _e('Network error occurred', 'orders-jet'); ?>');
            });
        });
        
        // Cancel
        modal.find('.oj-cancel-complete').on('click', function() {
            modal.remove();
        });
    }
    
    /**
     * Remove individual order card after payment confirmation
     */
    function removeOrderCard(orderId) {
        const $card = $(`.oj-order-card[data-order-id="${orderId}"]`);
        if ($card.length) {
            console.log('🗑️ Removing order card:', orderId);
            $card.addClass('oj-card-removing');
            setTimeout(() => {
                $card.fadeOut(300, function() {
                    $(this).remove();
                    console.log('✅ Order card removed:', orderId);
                });
            }, 500);
        }
    }
    
    /**
     * Remove table order cards after table closure
     */
    function removeTableOrderCards(orderIds) {
        orderIds.forEach(orderId => {
            const $card = $(`.oj-order-card[data-order-id="${orderId}"]`);
            if ($card.length) {
                $card.addClass('oj-card-removing');
                setTimeout(() => $card.fadeOut(300, function() {
                    $(this).remove();
                }), 500);
            }
        });
    }
    
    /**
     * Show success notification
     */
    function showSuccessNotification(message) {
        const notification = $(`
            <div class="oj-success-notification">
                <span>✅ ${message}</span>
            </div>
        `);
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Update filter counts after AJAX operations
     */
    function updateFilterCounts() {
        console.log('Updating filter counts...');
        
        // Get current filter counts from the page
        const currentFilter = $('.oj-filter-btn.active').data('filter');
        
        // Update counts based on current filter
        $.post(ajaxurl, {
            action: 'oj_get_filter_counts',
            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const counts = response.data;
                
                // Update filter tab counts
                $('.oj-filter-btn[data-filter="all"] .oj-filter-count').text(counts.all);
                $('.oj-filter-btn[data-filter="active"] .oj-filter-count').text(counts.active);
                $('.oj-filter-btn[data-filter="processing"] .oj-filter-count').text(counts.processing);
                $('.oj-filter-btn[data-filter="pending"] .oj-filter-count').text(counts.pending);
                $('.oj-filter-btn[data-filter="dinein"] .oj-filter-count').text(counts.dinein);
                $('.oj-filter-btn[data-filter="takeaway"] .oj-filter-count').text(counts.takeaway);
                $('.oj-filter-btn[data-filter="delivery"] .oj-filter-count').text(counts.delivery);
                $('.oj-filter-btn[data-filter="completed"] .oj-filter-count').text(counts.completed);
                
                console.log('Filter counts updated:', counts);
            }
        }).fail(function() {
            console.log('Failed to update filter counts');
        });
    }
    
    /**
     * Show success modal with thermal print option for individual orders
     */
    function showSuccessModalWithThermalPrint(orderId, thermalInvoiceUrl) {
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3>✅ <?php _e('Order Completed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Order', 'orders-jet'); ?> #${orderId} <?php _e('has been completed.', 'orders-jet'); ?></p>
                    <div class="oj-modal-actions">
                        <button class="button button-primary oj-thermal-print-btn" data-url="${thermalInvoiceUrl}">
                            🖨️ <?php _e('Print Thermal Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle thermal print
        modal.find('.oj-thermal-print-btn').on('click', function() {
            const invoiceUrl = $(this).data('url');
            window.open(invoiceUrl, '_blank');
            modal.remove();
        });
        
        // Handle close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
        });
    }
    
    /**
     * Show success modal with thermal print option for table closure
     */
    function showTableSuccessModalWithThermalPrint(tableNumber, data) {
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3>✅ <?php _e('Table Closed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Table', 'orders-jet'); ?> ${tableNumber} <?php _e('has been closed and consolidated.', 'orders-jet'); ?></p>
                    <div class="oj-modal-actions">
                        <button class="button button-primary oj-thermal-print-btn" data-url="${data.thermal_invoice_url}">
                            🖨️ <?php _e('Print Thermal Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle thermal print
        modal.find('.oj-thermal-print-btn').on('click', function() {
            const invoiceUrl = $(this).data('url');
            window.open(invoiceUrl, '_blank');
            modal.remove();
        });
        
        // Handle close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
        });
    }
    
    // Handle thermal print button clicks (for existing and dynamically updated completed orders)
    $(document).on('click', '.oj-thermal-print', function() {
        const invoiceUrl = $(this).data('invoice-url');
        if (invoiceUrl) {
            window.open(invoiceUrl, '_blank');
        }
    });
});
</script>

<style>

/* Simple Modal Styles */
.oj-payment-modal-overlay,
.oj-success-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.oj-payment-modal,
.oj-success-modal {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.oj-payment-modal h3,
.oj-success-modal h3 {
    margin-top: 0;
    color: #1d2327;
    font-size: 20px;
    margin-bottom: 16px;
}

.oj-payment-method {
    width: 100%;
    padding: 12px;
    margin: 16px 0;
    border: 2px solid #c3c4c7;
    border-radius: 8px;
    font-size: 14px;
}

.oj-modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 12px;
    justify-content: center;
}

.oj-modal-actions .button {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.oj-modal-actions .button-primary {
    background: #2271b1;
    color: white;
}

.oj-modal-actions .button-primary:hover {
    background: #1e5a8a;
}

/* Success Notification */
.oj-success-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #4caf50;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    font-weight: 600;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Card Update Animations */
.oj-card-updated {
    animation: cardUpdatePulse 1s ease-in-out;
}

@keyframes cardUpdatePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); background: #e8f5e8; }
    100% { transform: scale(1); }
}

.oj-card-removing {
    animation: cardRemoveSlide 0.5s ease-in-out forwards;
}

@keyframes cardRemoveSlide {
    0% { transform: translateX(0); opacity: 1; }
    100% { transform: translateX(-100%); opacity: 0; }
}
</style>
