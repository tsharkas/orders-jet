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

// Single query for all orders with WooFood order types
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = 20;

// Debug: Check what orders exist
$debug_orders = wc_get_orders(array('status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'), 'limit' => -1));
foreach ($debug_orders as $debug_order) {
    $method = $debug_order->get_meta('exwf_odmethod');
    error_log("DEBUG Order #{$debug_order->get_id()}: status={$debug_order->get_status()}, method={$method}");
}

$all_orders = wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'),
    'limit' => $limit,
    'offset' => $offset,
    'orderby' => 'date_modified',
    'order' => 'DESC',
    'meta_query' => array(
        array(
            'key' => 'exwf_odmethod',
            'compare' => 'EXISTS'
        )
    )
));

// Count orders for filter tabs using WooFood order types
$processing_count = count(wc_get_orders(array(
    'status' => 'wc-processing',
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'compare' => 'EXISTS'))
)));

$pending_count = count(wc_get_orders(array(
    'status' => 'wc-pending-payment', 
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'compare' => 'EXISTS'))
)));

$completed_count = count(wc_get_orders(array(
    'status' => 'wc-completed',
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'compare' => 'EXISTS'))
)));

$active_count = $processing_count + $pending_count;
$all_count = $active_count + $completed_count;

// Count by order type (WooFood system)
$dinein_count = count(wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'),
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'value' => 'dinein'))
)));

$takeaway_count = count(wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'),
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'value' => 'takeaway'))
)));

$delivery_count = count(wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'),
    'limit' => -1,
    'meta_query' => array(array('key' => 'exwf_odmethod', 'value' => 'delivery'))
)));
?>

<div class="wrap oj-manager-orders">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="oj-page-title"><?php _e('Orders Management', 'orders-jet'); ?></h1>
    </div>
    
    <!-- Filter Tabs -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="active">
            <?php _e('Active Orders', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $active_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            🍳 <?php _e('Kitchen', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $processing_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pending">
            ✅ <?php _e('Ready', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $pending_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="dinein">
            🏢 <?php _e('Table', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $dinein_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="takeaway">
            📦 <?php _e('Pickup', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $takeaway_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="delivery">
            🚚 <?php _e('Delivery', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $delivery_count; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="completed">
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
                $order_method = $order->get_meta('exwf_odmethod'); // WooFood order method: 'dinein', 'takeaway', 'delivery'
                $table_number = $order->get_meta('_oj_table_number');
                $customer_name = $order->get_meta('_oj_customer_name') ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $status = $order->get_status();
                $total = $order->get_total();
                $date_modified = $order->get_date_modified();
                $items_count = $order->get_item_count();
                
                // Status mapping for display
                $status_class = $status;
                $status_text = '';
                $status_icon = '';
                
                if ($status === 'processing') {
                    $status_text = __('Cooking', 'orders-jet');
                    $status_class = 'cooking';
                    $status_icon = '🍳';
                } elseif ($status === 'pending-payment') {
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
                                🕐 <?php echo $date_modified ? $date_modified->format('g:i A') : ''; ?>
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
                            
                        <?php elseif ($status === 'pending-payment') : ?>
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
    
    <!-- Load More Button -->
    <?php if (count($all_orders) >= $limit) : ?>
        <div class="oj-load-more-container">
            <button class="oj-load-more-btn" data-offset="<?php echo $offset + $limit; ?>">
                <?php _e('Load More Orders', 'orders-jet'); ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Filter Logic
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active filter
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show/Hide order cards based on filter
        $('.oj-order-card').each(function() {
            const $card = $(this);
            const status = $card.data('status');
            const orderType = $card.data('order-type');
            let show = false;
            
            switch(filter) {
                case 'active':
                    show = (status === 'processing' || status === 'pending-payment');
                    break;
                case 'processing':
                    show = (status === 'processing');
                    break;
                case 'pending':
                    show = (status === 'pending-payment');
                    break;
                case 'completed':
                    show = (status === 'completed');
                    break;
                case 'dinein':
                    show = (orderType === 'dinein');
                    break;
                case 'takeaway':
                    show = (orderType === 'takeaway');
                    break;
                case 'delivery':
                    show = (orderType === 'delivery');
                    break;
            }
            
            $card.toggle(show);
        });
        
        // Show empty state if no cards visible
        const visibleCards = $('.oj-order-card:visible').length;
        if (visibleCards === 0) {
            if ($('.oj-empty-state').length === 0) {
                $('#oj-orders-grid').append(`
                    <div class="oj-empty-state">
                        <div class="oj-empty-icon">📋</div>
                        <div class="oj-empty-title"><?php _e('No Orders Found', 'orders-jet'); ?></div>
                        <div class="oj-empty-message"><?php _e('No orders match the selected filter.', 'orders-jet'); ?></div>
                    </div>
                `);
            }
        } else {
            $('.oj-empty-state').remove();
        }
    });
    
    // Mark Ready
    $('.oj-mark-ready').on('click', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        
        if (confirm('<?php _e('Mark this order as ready?', 'orders-jet'); ?>')) {
            $btn.prop('disabled', true).text('<?php _e('Processing...', 'orders-jet'); ?>');
            
            $.post(ajaxurl, {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('oj_mark_ready'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php _e('Error occurred', 'orders-jet'); ?>');
                    $btn.prop('disabled', false).text('<?php _e('Mark Ready', 'orders-jet'); ?>');
                }
            });
        }
    });
    
    // Complete Order
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        const orderType = $(this).data('type');
        
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
            
            $.post(ajaxurl, {
                action: 'oj_complete_order',
                order_id: orderId,
                payment_method: paymentMethod,
                nonce: '<?php echo wp_create_nonce('oj_complete_order'); ?>'
            }, function(response) {
                modal.remove();
                if (response.success) {
                    showSuccessModal(orderId);
                } else {
                    alert(response.data || '<?php _e('Error occurred', 'orders-jet'); ?>');
                }
            });
        });
        
        // Cancel
        modal.find('.oj-cancel-complete').on('click', function() {
            modal.remove();
        });
    });
    
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
                
                $.post(ajaxurl, {
                    action: 'oj_close_table',
                    table_number: tableNumber,
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('oj_close_table'); ?>'
                }, function(response) {
                    modal.remove();
                    if (response.success) {
                        showTableSuccessModal(tableNumber, response.data);
                    } else {
                        alert(response.data || '<?php _e('Error occurred', 'orders-jet'); ?>');
                    }
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
    
    // Load More Orders
    $('.oj-load-more-btn').on('click', function() {
        const offset = $(this).data('offset');
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e('Loading...', 'orders-jet'); ?>');
        
        $.post(ajaxurl, {
            action: 'oj_load_more_orders',
            offset: offset,
            nonce: '<?php echo wp_create_nonce('oj_load_more'); ?>'
        }, function(response) {
            if (response.success && response.data.html) {
                $('#oj-orders-grid').append(response.data.html);
                
                if (response.data.has_more) {
                    $button.data('offset', offset + 20).prop('disabled', false).text('<?php _e('Load More Orders', 'orders-jet'); ?>');
                } else {
                    $button.remove();
                }
            } else {
                $button.prop('disabled', false).text('<?php _e('Load More Orders', 'orders-jet'); ?>');
            }
        });
    });
    
    // Success Modal (after order completion)
    function showSuccessModal(orderId) {
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3>✅ <?php _e('Order Completed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Order', 'orders-jet'); ?> #${orderId} <?php _e('has been completed.', 'orders-jet'); ?></p>
                    <div class="oj-modal-actions">
                        <button class="button button-primary oj-print-invoice" data-order-id="${orderId}">
                            🖨️ <?php _e('View/Print Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Print invoice
        modal.find('.oj-print-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_get_order_invoice&order_id=' + orderId + '&print=1&nonce=<?php echo wp_create_nonce('oj_get_invoice'); ?>';
            window.open(invoiceUrl, '_blank');
            modal.remove();
            location.reload();
        });
        
        // Close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
            location.reload();
        });
    }
    
    // Table Success Modal (after table closure)
    function showTableSuccessModal(tableNumber, responseData) {
        const consolidatedOrderId = responseData.consolidated_order_id;
        
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3>✅ <?php _e('Table Closed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Table', 'orders-jet'); ?> #${tableNumber} <?php _e('has been closed.', 'orders-jet'); ?></p>
                    ${consolidatedOrderId ? `<p><small><?php _e('Order ID:', 'orders-jet'); ?> #${consolidatedOrderId}</small></p>` : ''}
                    <div class="oj-modal-actions">
                        <button class="button button-primary oj-print-table-invoice" data-order-id="${consolidatedOrderId}">
                            🖨️ <?php _e('View/Print Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Print invoice
        modal.find('.oj-print-table-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            if (orderId) {
                const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=oj_get_order_invoice&order_id=' + orderId + '&print=1&nonce=<?php echo wp_create_nonce('oj_get_invoice'); ?>';
                window.open(invoiceUrl, '_blank');
            }
            modal.remove();
            location.reload();
        });
        
        // Close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
            location.reload();
        });
    }
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
</style>
