<?php
/**
 * Orders Jet - Manager Orders Management Template (Fresh Clean Version)
 * Simple, clean order management interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Include the manager navigation
include ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-navigation.php';

// Get current date
$today_formatted = date('F j, Y');

// Clean order retrieval
$all_orders = array();
$processing_orders = array();
$ready_orders = array();

// Get orders using WooCommerce native function
if (function_exists('wc_get_orders')) {
    // Get processing orders (cooking)
    $processing_wc_orders = wc_get_orders(array(
        'status' => 'processing',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // Get ready orders (pending - awaiting payment)
    $ready_wc_orders = wc_get_orders(array(
        'status' => 'pending',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // Process processing orders
    foreach ($processing_wc_orders as $order) {
        $processing_orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'customer' => $order->get_billing_first_name() ?: 'Guest',
            'table' => $order->get_meta('_oj_table_number') ?: '',
            'date' => $order->get_date_created()->format('H:i'),
            'type' => !empty($order->get_meta('_oj_table_number')) ? 'table' : 'pickup'
        );
    }
    
    // Process ready orders
    foreach ($ready_wc_orders as $order) {
        $ready_orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'customer' => $order->get_billing_first_name() ?: 'Guest',
            'table' => $order->get_meta('_oj_table_number') ?: '',
            'date' => $order->get_date_created()->format('H:i'),
            'type' => !empty($order->get_meta('_oj_table_number')) ? 'table' : 'pickup'
        );
    }
    
    // Combine all orders
    $all_orders = array_merge($processing_orders, $ready_orders);
}

// Calculate statistics
$total_orders = count($all_orders);
$processing_count = count($processing_orders);
$ready_count = count($ready_orders);
$table_orders = array_filter($all_orders, function($order) { return $order['type'] === 'table'; });
$pickup_orders = array_filter($all_orders, function($order) { return $order['type'] === 'pickup'; });

?>

<div class="wrap oj-manager-orders">
    
    <!-- Header -->
    <div class="oj-header">
        <h1>
            <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Orders Management', 'orders-jet'); ?>
        </h1>
        <p><?php echo sprintf(__('Manage all restaurant orders - %s', 'orders-jet'), $today_formatted); ?></p>
        <button onclick="location.reload()" class="button">
                <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh', 'orders-jet'); ?>
    </button>
    </div>
    
    
    <!-- Statistics -->
    <div class="oj-stats">
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label"><?php _e('Active Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo count($table_orders); ?></div>
            <div class="stat-label"><?php _e('Table Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo count($pickup_orders); ?></div>
            <div class="stat-label"><?php _e('Pickup Orders', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $processing_count; ?></div>
            <div class="stat-label"><?php _e('In Kitchen', 'orders-jet'); ?></div>
            </div>
        <div class="oj-stat-card">
            <div class="stat-number"><?php echo $ready_count; ?></div>
            <div class="stat-label"><?php _e('Ready Orders', 'orders-jet'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <?php _e('All Orders', 'orders-jet'); ?> (<?php echo $total_orders; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            <?php _e('In Kitchen', 'orders-jet'); ?> (<?php echo $processing_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="ready">
            <?php _e('Ready Orders', 'orders-jet'); ?> (<?php echo $ready_count; ?>)
        </button>
        <button class="oj-filter-btn" data-filter="table">
            <?php _e('Table Orders', 'orders-jet'); ?> (<?php echo count($table_orders); ?>)
        </button>
        <button class="oj-filter-btn" data-filter="pickup">
            <?php _e('Pickup Orders', 'orders-jet'); ?> (<?php echo count($pickup_orders); ?>)
        </button>
    </div>
    
    <!-- Orders Table -->
    <div class="oj-orders-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Order #', 'orders-jet'); ?></th>
                    <th><?php _e('Customer', 'orders-jet'); ?></th>
                    <th><?php _e('Type', 'orders-jet'); ?></th>
                    <th><?php _e('Status', 'orders-jet'); ?></th>
                    <th><?php _e('Total', 'orders-jet'); ?></th>
                    <th><?php _e('Time', 'orders-jet'); ?></th>
                    <th><?php _e('Actions', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_orders)) : ?>
                    <?php foreach ($all_orders as $order) : ?>
                        <tr class="oj-order-row" 
                            data-status="<?php echo esc_attr($order['status']); ?>"
                            data-type="<?php echo esc_attr($order['type']); ?>">
                            
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            
                            <td><?php echo esc_html($order['customer']); ?></td>
                            
                            <td>
                                <?php if ($order['type'] === 'table') : ?>
                                    🍽️ <?php echo sprintf(__('Table %s', 'orders-jet'), $order['table']); ?>
                                    <?php else : ?>
                                    🥡 <?php _e('Pickup', 'orders-jet'); ?>
                                    <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($order['status'] === 'processing') : ?>
                                    <span class="oj-status cooking">🍳 <?php _e('Cooking', 'orders-jet'); ?></span>
                                <?php elseif ($order['status'] === 'pending') : ?>
                                    <span class="oj-status ready">✅ <?php _e('Ready', 'orders-jet'); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td><?php echo wc_price($order['total']); ?></td>
                            
                            <td><?php echo $order['date']; ?></td>
                            
                            <td>
                                <?php if ($order['status'] === 'pending') : ?>
                                    <?php if ($order['type'] === 'table') : ?>
                                        <button class="button button-primary oj-close-table" 
                                                data-order-id="<?php echo $order['id']; ?>"
                                                data-table="<?php echo esc_attr($order['table']); ?>">
                                            <?php _e('Close Table', 'orders-jet'); ?>
                                    </button>
                                <?php else : ?>
                                        <button class="button button-primary oj-complete-order" 
                                                data-order-id="<?php echo $order['id']; ?>">
                                            <?php _e('Complete Order', 'orders-jet'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="oj-status-note"><?php _e('In Kitchen', 'orders-jet'); ?></span>
                                    <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
        <?php else : ?>
                    <tr>
                        <td colspan="7" class="oj-no-orders">
                            <?php _e('No active orders found.', 'orders-jet'); ?>
                        </td>
                    </tr>
        <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<style>
.oj-manager-orders {
    max-width: 1200px;
}

.oj-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.oj-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.oj-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.oj-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.oj-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    }
    
    .oj-filter-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.3s;
}

.oj-filter-btn:hover,
.oj-filter-btn.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.oj-orders-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.oj-order-row.hidden {
    display: none;
}

.oj-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.oj-status.cooking {
    background: #fff3cd;
    color: #856404;
}

.oj-status.ready {
    background: #d1edff;
    color: #0c5460;
}

.oj-status-note {
    color: #666;
    font-style: italic;
}

.oj-no-orders {
    text-align: center;
    padding: 40px;
    color: #666;
}

/* Modal Styles */
.oj-payment-modal-overlay,
.oj-invoice-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
}

.oj-payment-modal,
.oj-invoice-modal {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    text-align: center;
}

.oj-payment-modal h3,
.oj-invoice-modal h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.oj-payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.oj-payment-btn {
    padding: 15px 20px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.oj-payment-btn:hover {
    border-color: #2271b1;
    background: #f0f6fc;
    transform: translateY(-2px);
}

.oj-modal-actions,
.oj-invoice-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.oj-invoice-actions .button {
    margin: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Filter functionality
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter orders
        $('.oj-order-row').each(function() {
            const $row = $(this);
            const status = $row.data('status');
            const type = $row.data('type');
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'processing':
                    show = status === 'processing';
                    break;
                case 'ready':
                    show = status === 'pending';
                    break;
                case 'table':
                    show = type === 'table';
                    break;
                case 'pickup':
                    show = type === 'pickup';
                    break;
            }
            
            if (show) {
                $row.removeClass('hidden');
            } else {
                $row.addClass('hidden');
            }
        });
    });
    
    // Close table action with payment method selection
    $('.oj-close-table').on('click', function() {
        const orderId = $(this).data('order-id');
        const table = $(this).data('table');
        
        if (confirm('<?php _e('Close this table and complete all orders?', 'orders-jet'); ?>')) {
            // Show payment method modal for table
            showPaymentMethodModal(orderId, 'table', table);
        }
    });
    
    // Complete order action with payment method selection
    $('.oj-complete-order').on('click', function() {
        const orderId = $(this).data('order-id');
        
        // Show payment method modal
        showPaymentMethodModal(orderId, 'individual');
    });
    
    // Payment method modal functionality
    function showPaymentMethodModal(orderId, orderType, tableNumber = null) {
        const modal = $(`
            <div class="oj-payment-modal-overlay">
                <div class="oj-payment-modal">
                    <h3><?php _e('Complete Order', 'orders-jet'); ?> #${orderId}</h3>
                    <p><?php _e('Select payment method:', 'orders-jet'); ?></p>
                    <div class="oj-payment-methods">
                        <button class="oj-payment-btn" data-method="cash">
                            💰 <?php _e('Cash', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn" data-method="card">
                            💳 <?php _e('Card', 'orders-jet'); ?>
                        </button>
                        <button class="oj-payment-btn" data-method="digital">
                            📱 <?php _e('Digital Payment', 'orders-jet'); ?>
                        </button>
                    </div>
                    <div class="oj-modal-actions">
                        <button class="button oj-cancel-payment"><?php _e('Cancel', 'orders-jet'); ?></button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle payment method selection
        modal.find('.oj-payment-btn').on('click', function() {
            const paymentMethod = $(this).data('method');
            completeOrderWithPayment(orderId, paymentMethod, orderType, tableNumber);
            modal.remove();
        });
        
        // Handle cancel
        modal.find('.oj-cancel-payment').on('click', function() {
            modal.remove();
        });
        
        // Close on overlay click
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    function completeOrderWithPayment(orderId, paymentMethod, orderType, tableNumber = null) {
        const actionName = orderType === 'table' ? 'oj_close_table' : 'oj_complete_individual_order';
        
        let requestData = {
            action: actionName,
            payment_method: paymentMethod
        };
        
        // Add appropriate ID parameter and nonce
        if (orderType === 'table') {
            requestData.table_number = tableNumber;
            requestData.nonce = '<?php echo wp_create_nonce('oj_table_order'); ?>';
        } else {
            requestData.order_id = orderId;
            requestData.nonce = '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>';
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    // Show success message and invoice option
                    if (orderType === 'table') {
                        showTableInvoiceModal(tableNumber, response.data);
                    } else {
                        showInvoiceModal(orderId, response.data);
                    }
                } else {
                    alert(response.data.message || '<?php _e('Error completing order', 'orders-jet'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Network error. Please try again.', 'orders-jet'); ?>');
            }
        });
    }
    
    function showInvoiceModal(orderId, responseData) {
        const modal = $(`
            <div class="oj-invoice-modal-overlay">
                <div class="oj-invoice-modal">
                    <h3>✅ <?php _e('Order Completed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Order', 'orders-jet'); ?> #${orderId} <?php _e('has been completed.', 'orders-jet'); ?></p>
                    <div class="oj-invoice-actions">
                        <button class="button button-primary oj-view-invoice" data-order-id="${orderId}">
                            📄 <?php _e('View PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-print-invoice" data-order-id="${orderId}">
                            🖨️ <?php _e('Print PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-download-invoice" data-order-id="${orderId}">
                            💾 <?php _e('Download PDF', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle view PDF invoice
        modal.find('.oj-view-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Use WooCommerce PDF Invoices plugin URL for HTML view
            const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderId + '&output=html';
            window.open(invoiceUrl, '_blank');
        });
        
        // Handle print PDF invoice
        modal.find('.oj-print-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Direct PDF for printing
            const pdfUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderId + '&output=pdf';
            
            // Create hidden iframe for printing
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = pdfUrl;
            document.body.appendChild(iframe);
            
            iframe.onload = function() {
                try {
                    // Try to print the PDF directly
                    iframe.contentWindow.print();
                } catch (e) {
                    // Fallback: open in new window for manual printing
                    window.open(pdfUrl, '_blank');
                }
                // Remove iframe after printing
                setTimeout(() => {
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                }, 2000);
            };
            
            // Fallback if iframe fails to load
            iframe.onerror = function() {
                window.open(pdfUrl, '_blank');
                if (document.body.contains(iframe)) {
                    document.body.removeChild(iframe);
                }
            };
        });
        
        // Handle download PDF invoice
        modal.find('.oj-download-invoice').on('click', function() {
            const orderId = $(this).data('order-id');
            // Force download PDF
            const downloadUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderId + '&output=pdf&force_download=1';
            
            // Create temporary link for download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'invoice-' + orderId + '.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // Handle close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
            location.reload();
        });
        
        // Close on overlay click
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
                location.reload();
            }
        });
    }
    
    function showTableInvoiceModal(tableNumber, responseData) {
        // Extract order IDs from response data for table orders
        let orderIds = [];
        if (responseData && responseData.order_ids) {
            orderIds = responseData.order_ids;
        }
        
        const modal = $(`
            <div class="oj-invoice-modal-overlay">
                <div class="oj-invoice-modal">
                    <h3>✅ <?php _e('Table Closed Successfully!', 'orders-jet'); ?></h3>
                    <p><?php _e('Table', 'orders-jet'); ?> #${tableNumber} <?php _e('has been closed and invoice generated.', 'orders-jet'); ?></p>
                    ${orderIds.length > 0 ? `<p><small><?php _e('Orders:', 'orders-jet'); ?> ${orderIds.join(', ')}</small></p>` : ''}
                    <div class="oj-invoice-actions">
                        <button class="button button-primary oj-view-table-invoice" data-table="${tableNumber}" data-orders="${orderIds.join(',')}">
                            📄 <?php _e('View PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-print-table-invoice" data-table="${tableNumber}" data-orders="${orderIds.join(',')}">
                            🖨️ <?php _e('Print PDF Invoice', 'orders-jet'); ?>
                        </button>
                        <button class="button button-secondary oj-download-table-invoice" data-table="${tableNumber}" data-orders="${orderIds.join(',')}">
                            💾 <?php _e('Download PDF', 'orders-jet'); ?>
                        </button>
                        <button class="button oj-close-success">
                            <?php _e('Close', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle view PDF invoice for table
        modal.find('.oj-view-table-invoice').on('click', function() {
            const orderIds = $(this).data('orders');
            if (orderIds) {
                // Use WooCommerce PDF Invoices plugin URL for multiple orders (table invoice)
                const invoiceUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderIds + '&output=html';
                window.open(invoiceUrl, '_blank');
            } else {
                alert('<?php _e('No orders found for this table.', 'orders-jet'); ?>');
            }
        });
        
        // Handle print PDF invoice for table
        modal.find('.oj-print-table-invoice').on('click', function() {
            const orderIds = $(this).data('orders');
            if (orderIds) {
                // Direct PDF for printing multiple orders
                const pdfUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderIds + '&output=pdf';
                
                // Create hidden iframe for printing
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = pdfUrl;
                document.body.appendChild(iframe);
                
                iframe.onload = function() {
                    try {
                        // Try to print the PDF directly
                        iframe.contentWindow.print();
                    } catch (e) {
                        // Fallback: open in new window for manual printing
                        window.open(pdfUrl, '_blank');
                    }
                    // Remove iframe after printing
                    setTimeout(() => {
                        if (document.body.contains(iframe)) {
                            document.body.removeChild(iframe);
                        }
                    }, 2000);
                };
                
                // Fallback if iframe fails to load
                iframe.onerror = function() {
                    window.open(pdfUrl, '_blank');
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                };
            } else {
                alert('<?php _e('No orders found for this table.', 'orders-jet'); ?>');
            }
        });
        
        // Handle download PDF invoice for table
        modal.find('.oj-download-table-invoice').on('click', function() {
            const tableNumber = $(this).data('table');
            const orderIds = $(this).data('orders');
            if (orderIds) {
                // Force download PDF for table
                const downloadUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' + orderIds + '&output=pdf&force_download=1';
                
                // Create temporary link for download
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'table-' + tableNumber + '-invoice.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert('<?php _e('No orders found for this table.', 'orders-jet'); ?>');
            }
        });
        
        // Handle close
        modal.find('.oj-close-success').on('click', function() {
            modal.remove();
            location.reload();
        });
        
        // Close on overlay click
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
                location.reload();
            }
        });
    }
    
});
</script>
