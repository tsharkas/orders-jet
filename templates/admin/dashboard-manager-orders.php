<?php
/**
 * Manager Orders Dashboard - Simple & Flat Design
 * No table grouping - direct order display
 * 
 * @package Orders_Jet
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue simple CSS
wp_enqueue_style('oj-manager-orders', ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders.css', array(), ORDERS_JET_VERSION);

// Single query for all orders (lazy load 20 by 20) sorted by date_modified for FIFO
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = 20;

$all_orders = wc_get_orders(array(
    'status' => array('wc-processing', 'wc-pending', 'wc-completed'),
    'limit' => $limit,
    'offset' => $offset,
    'orderby' => 'date_modified',
    'order' => 'DESC',
    'meta_query' => array(
        array(
            'key' => '_oj_order_source',
            'compare' => 'EXISTS'
        )
    )
));

// Count orders for filters
$processing_count = count(wc_get_orders(array(
    'status' => 'wc-processing',
    'limit' => -1,
    'meta_query' => array(array('key' => '_oj_order_source', 'compare' => 'EXISTS'))
)));

$pending_count = count(wc_get_orders(array(
    'status' => 'wc-pending', 
    'limit' => -1,
    'meta_query' => array(array('key' => '_oj_order_source', 'compare' => 'EXISTS'))
)));

$completed_count = count(wc_get_orders(array(
    'status' => 'wc-completed',
    'limit' => -1,
    'meta_query' => array(array('key' => '_oj_order_source', 'compare' => 'EXISTS'))
)));

$active_count = $processing_count + $pending_count;
$all_count = $active_count + $completed_count;

// Count by type
$pickup_count = 0;
$table_count = 0;
foreach ($all_orders as $order) {
    $order_type = get_post_meta($order->get_id(), '_oj_order_type', true);
    if ($order_type === 'pickup') {
        $pickup_count++;
    } elseif ($order_type === 'table') {
        $table_count++;
    }
}
?>

<div class="wrap oj-manager-orders">
    <h1><?php _e('Orders Management', 'orders-jet'); ?></h1>
    
    <!-- Simple Horizontal Filters -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <?php _e('All Orders', 'orders-jet'); ?> (<?php echo $all_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="active">
            <?php _e('Active Orders', 'orders-jet'); ?> (<?php echo $active_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            <?php _e('Kitchen', 'orders-jet'); ?> (<?php echo $processing_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pending">
            <?php _e('Ready', 'orders-jet'); ?> (<?php echo $pending_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="table">
            <?php _e('Tables', 'orders-jet'); ?> (<?php echo $table_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pickup">
            <?php _e('Pickup', 'orders-jet'); ?> (<?php echo $pickup_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="completed">
            <?php _e('Completed', 'orders-jet'); ?> (<?php echo $completed_count; ?>)
        </button>
    </div>

    <!-- Simple Orders Table -->
    <div class="oj-orders-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Order', 'orders-jet'); ?></th>
                    <th><?php _e('Customer', 'orders-jet'); ?></th>
                    <th><?php _e('Type', 'orders-jet'); ?></th>
                    <th><?php _e('Status', 'orders-jet'); ?></th>
                    <th><?php _e('Items', 'orders-jet'); ?></th>
                    <th><?php _e('Total', 'orders-jet'); ?></th>
                    <th><?php _e('Time', 'orders-jet'); ?></th>
                    <th><?php _e('Actions', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_orders)) : ?>
                    <tr>
                        <td colspan="8" class="oj-text-center">
                            <?php _e('No orders found.', 'orders-jet'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($all_orders as $order) : 
                        $order_id = $order->get_id();
                        $order_type = get_post_meta($order_id, '_oj_order_type', true) ?: 'pickup';
                        $table_number = get_post_meta($order_id, '_oj_table_number', true);
                        $customer_name = get_post_meta($order_id, '_oj_customer_name', true) ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        $status = $order->get_status();
                        $total = $order->get_total();
                        $date_modified = $order->get_date_modified();
                        $items_count = $order->get_item_count();
                        
                        // Status mapping
                        $status_class = $status;
                        if ($status === 'processing') {
                            $status_text = __('Kitchen', 'orders-jet');
                        } elseif ($status === 'pending') {
                            $status_text = __('Ready', 'orders-jet');
                        } elseif ($status === 'completed') {
                            $status_text = __('Completed', 'orders-jet');
                        } else {
                            $status_text = ucfirst($status);
                        }
                        
                        // Type display
                        $type_display = $order_type === 'table' ? 
                            sprintf(__('Table %s', 'orders-jet'), $table_number) : 
                            __('Pickup', 'orders-jet');
                        $type_class = 'oj-type-' . $order_type;
                    ?>
                        <tr class="oj-order-row" 
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-status="<?php echo esc_attr($status); ?>"
                            data-type="<?php echo esc_attr($order_type); ?>">
                            
                            <!-- Order Number -->
                            <td>
                                <span class="oj-order-number">#<?php echo $order_id; ?></span>
                            </td>
                            
                            <!-- Customer -->
                            <td>
                                <?php echo esc_html($customer_name); ?>
                            </td>
                            
                            <!-- Type -->
                            <td>
                                <span class="<?php echo esc_attr($type_class); ?>">
                                    <?php echo esc_html($type_display); ?>
                                </span>
                            </td>
                            
                            <!-- Status -->
                            <td>
                                <span class="oj-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            
                            <!-- Items Count -->
                            <td>
                                <?php echo $items_count; ?> <?php _e('items', 'orders-jet'); ?>
                            </td>
                            
                            <!-- Total -->
                            <td>
                                <?php echo wc_price($total); ?>
                            </td>
                            
                            <!-- Time -->
                            <td>
                                <?php echo $date_modified ? $date_modified->format('H:i') : ''; ?>
                            </td>
                            
                            <!-- Actions -->
                            <td class="oj-actions">
                                <!-- View Order -->
                                <button class="button oj-view-order" 
                                        data-order-id="<?php echo esc_attr($order_id); ?>">
                                    👁️ <?php _e('View', 'orders-jet'); ?>
                                </button>
                                
                                <?php if ($status === 'completed') : ?>
                                    <!-- Thermal Print Invoice -->
                                    <button class="button oj-invoice-print" 
                                            data-order-id="<?php echo esc_attr($order_id); ?>">
                                        🖨️ <?php _e('Print', 'orders-jet'); ?>
                                    </button>
                                    
                                <?php else : ?>
                                    <!-- Mark Ready (if processing) -->
                                    <?php if ($status === 'processing') : ?>
                                        <button class="button oj-mark-ready" 
                                                data-order-id="<?php echo esc_attr($order_id); ?>">
                                            ✅ <?php _e('Ready', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Complete Order (if ready) -->
                                    <?php if ($status === 'pending') : ?>
                                        <button class="button oj-complete-order" 
                                                data-order-id="<?php echo esc_attr($order_id); ?>"
                                                data-type="<?php echo esc_attr($order_type); ?>"
                                                data-table="<?php echo esc_attr($table_number); ?>">
                                            ✅ <?php _e('Complete', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Close Table (for table orders if ready) -->
                                    <?php if ($order_type === 'table' && $status === 'pending') : ?>
                                        <button class="button oj-close-table" 
                                                data-table="<?php echo esc_attr($table_number); ?>"
                                                data-order-id="<?php echo esc_attr($order_id); ?>">
                                            🏢 <?php _e('Close Table', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Load More Button -->
        <?php if (count($all_orders) >= $limit) : ?>
            <div class="oj-load-more-container">
                <button class="button oj-load-more" data-offset="<?php echo $offset + $limit; ?>">
                    <?php _e('Load More Orders', 'orders-jet'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Simple Filter Logic
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active filter
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show/Hide orders based on filter
        $('.oj-order-row').each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const type = $row.data('type');
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'active':
                    show = (status === 'processing' || status === 'pending');
                    break;
                case 'processing':
                    show = (status === 'processing');
                    break;
                case 'pending':
                    show = (status === 'pending');
                    break;
                case 'completed':
                    show = (status === 'completed');
                    break;
                case 'pickup':
                    show = (type === 'pickup');
                    break;
                case 'table':
                    show = (type === 'table');
                    break;
            }
            
            $row.toggle(show);
        });
    });
    
    // Mark Ready
    $('.oj-mark-ready').on('click', function() {
        const orderId = $(this).data('order-id');
        
        if (confirm('<?php _e('Mark this order as ready?', 'orders-jet'); ?>')) {
            $.post(ajaxurl, {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('oj_mark_ready'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php _e('Error occurred', 'orders-jet'); ?>');
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
    $('.oj-load-more').on('click', function() {
        const offset = $(this).data('offset');
        const $button = $(this);
        
        $button.text('<?php _e('Loading...', 'orders-jet'); ?>').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'oj_load_more_orders',
            offset: offset,
            nonce: '<?php echo wp_create_nonce('oj_load_more'); ?>'
        }, function(response) {
            if (response.success && response.data.html) {
                $('.oj-orders-table tbody').append(response.data.html);
                
                if (response.data.has_more) {
                    $button.data('offset', offset + 20).text('<?php _e('Load More Orders', 'orders-jet'); ?>').prop('disabled', false);
                } else {
                    $button.remove();
                }
            } else {
                $button.text('<?php _e('Load More Orders', 'orders-jet'); ?>').prop('disabled', false);
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
    padding: 20px;
    border-radius: 4px;
    max-width: 400px;
    width: 90%;
    text-align: center;
}

.oj-payment-modal h3,
.oj-success-modal h3 {
    margin-top: 0;
    color: #333;
}

.oj-payment-method {
    width: 100%;
    padding: 8px;
    margin: 10px 0;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
}

.oj-modal-actions {
    margin-top: 15px;
}

.oj-modal-actions .button {
    margin: 0 5px;
}
</style>
