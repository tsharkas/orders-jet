/**
 * Orders Jet - Admin JavaScript
 * Handles admin interface interactions
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Debug: Check if admin variables are loaded (temporary for testing)
    console.log('Orders Jet Admin: Script loaded');
    console.log('Orders Jet Admin: OrdersJetAdmin object:', typeof OrdersJetAdmin !== 'undefined' ? 'FOUND' : 'NOT FOUND');
    if (typeof OrdersJetAdmin !== 'undefined') {
        console.log('Orders Jet Admin: OrdersJetAdmin content:', OrdersJetAdmin);
    }
    console.log('Orders Jet Admin: ojExpressData object:', typeof ojExpressData !== 'undefined' ? 'FOUND' : 'NOT FOUND');
    if (typeof ojExpressData !== 'undefined') {
        console.log('Orders Jet Admin: ojExpressData content:', ojExpressData);
    }
    console.log('Orders Jet Admin: oj_admin object:', typeof oj_admin !== 'undefined' ? 'FOUND' : 'NOT FOUND');
    
    // Use OrdersJetAdmin on dashboard pages, fallback to oj_admin on table management pages
    var adminConfig = typeof OrdersJetAdmin !== 'undefined' ? OrdersJetAdmin : 
                      (typeof oj_admin !== 'undefined' ? oj_admin : null);
    
    console.log('Orders Jet Admin: Using config:', adminConfig);
    
    // Auto-refresh dashboards every 30 seconds
    var dashboardRefreshInterval;
    
    // Check if we're on a dashboard page
    console.log('Orders Jet Admin: Checking URL for dashboard page...');
    console.log('Orders Jet Admin: Current URL:', window.location.href);
    var isDashboardPage = window.location.href.indexOf('orders-jet') !== -1;
    console.log('Orders Jet Admin: Is dashboard page:', isDashboardPage);
    
    if (isDashboardPage) {
        console.log('Orders Jet Admin: Starting auto-refresh...');
        startDashboardAutoRefresh();
    } else {
        console.log('Orders Jet Admin: Not a dashboard page, auto-refresh disabled');
    }
    
    function startDashboardAutoRefresh() {
        // Refresh every 30 seconds
        dashboardRefreshInterval = setInterval(function() {
            refreshDashboard();
        }, 30000);
        
        console.log('Orders Jet Admin: Auto-refresh started (30s interval)');
    }
    
    function stopDashboardAutoRefresh() {
        if (dashboardRefreshInterval) {
            clearInterval(dashboardRefreshInterval);
        }
    }
    
    function refreshDashboard() {
        console.log('Orders Jet Admin: Refreshing dashboard...');
        
        // Show subtle refresh indicator
        var $refreshIndicator = $('<div class="oj-refresh-indicator" style="position: fixed; top: 32px; right: 20px; background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; z-index: 9999;">Updating...</div>');
        $('body').append($refreshIndicator);
        
        // Check if we're on express dashboard
        var isExpressDashboard = window.location.href.indexOf('orders-jet-express') !== -1;
        
        console.log('Orders Jet Admin: Express dashboard detected:', isExpressDashboard);
        console.log('Orders Jet Admin: ojExpressData available:', typeof ojExpressData !== 'undefined');
        console.log('Orders Jet Admin: OrdersJetAdmin available:', typeof OrdersJetAdmin !== 'undefined');
        
        if (isExpressDashboard && (typeof ojExpressData !== 'undefined' || typeof OrdersJetAdmin !== 'undefined')) {
            console.log('Orders Jet Admin: Using AJAX refresh');
            // Use AJAX refresh for express dashboard
            refreshExpressDashboardAjax($refreshIndicator);
        } else {
            console.log('Orders Jet Admin: Falling back to page reload');
            // Fallback to page reload for other dashboards
            setTimeout(function() {
                window.location.reload();
            }, 500);
        }
    }
    
    function refreshExpressDashboardAjax($indicator) {
        // Use ojExpressData if available, fallback to OrdersJetAdmin
        var ajaxConfig = typeof ojExpressData !== 'undefined' ? ojExpressData : OrdersJetAdmin;
        
        console.log('Orders Jet Admin: Using AJAX config:', ajaxConfig);
        
        $.ajax({
            url: ajaxConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_refresh_dashboard',
                nonce: ajaxConfig.nonces.dashboard,
                page: 'orders-jet-express'
            },
            success: function(response) {
                if (response.success) {
                    // Update orders grid
                    $('.oj-orders-grid').html(response.data.orders_html);
                    
                    // Update filter counts
                    if (response.data.filter_counts) {
                        updateExpressFilterCounts(response.data.filter_counts);
                    }
                    
                    // Re-apply current filter
                    var activeFilter = $('.oj-filter-btn.active').data('filter') || 'active';
                    $('.oj-filter-btn[data-filter="' + activeFilter + '"]').trigger('click');
                    
                    console.log('Orders Jet Admin: Dashboard refreshed via AJAX at', response.data.timestamp);
                } else {
                    // Fallback to page reload
                    window.location.reload();
                }
            },
            error: function(xhr, status, error) {
                // Fallback to page reload
                window.location.reload();
            },
            complete: function() {
                // Remove refresh indicator
                $indicator.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
    }
    
    function updateExpressFilterCounts(counts) {
        // Update filter button counts
        $('.oj-filter-btn[data-filter="active"] .oj-count').text(counts.active || 0);
        $('.oj-filter-btn[data-filter="processing"] .oj-count').text(counts.processing || 0);
        $('.oj-filter-btn[data-filter="pending"] .oj-count').text(counts.pending || 0);
        $('.oj-filter-btn[data-filter="dinein"] .oj-count').text(counts.dinein || 0);
        $('.oj-filter-btn[data-filter="takeaway"] .oj-count').text(counts.takeaway || 0);
        $('.oj-filter-btn[data-filter="delivery"] .oj-count').text(counts.delivery || 0);
        $('.oj-filter-btn[data-filter="food_kitchen"] .oj-count').text(counts.food_kitchen || 0);
        $('.oj-filter-btn[data-filter="beverage_kitchen"] .oj-count').text(counts.beverage_kitchen || 0);
    }
    
    // Manual refresh button
    $(document).on('click', '.oj-refresh-dashboard', function(e) {
        e.preventDefault();
        refreshDashboard();
    });
    
    // Regenerate QR Code
    $(document).on('click', '.oj-regenerate-qr', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var tableId = $button.data('table-id');
        var $container = $button.closest('.oj-qr-code-container');
        var $img = $container.find('img');
        var $downloadLink = $container.find('a[download]');
        
        // Show loading state
        $button.prop('disabled', true).text('Regenerating...');
        
        $.ajax({
            url: adminConfig.ajax_url || adminConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_regenerate_qr_code',
                table_id: tableId,
                nonce: adminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update QR code image
                    $img.attr('src', response.data.qr_code_url + '&t=' + new Date().getTime());
                    
                    // Update download link
                    $downloadLink.attr('href', response.data.qr_code_url);
                    
                    showNotification('QR code regenerated successfully!', 'success');
                } else {
                    showNotification('Failed to regenerate QR code: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Error regenerating QR code. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Regenerate QR Code');
            }
        });
    });
    
    // Show notification
    function showNotification(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    // Table status updates
    $(document).on('change', 'select[name="oj_table_status"]', function() {
        var $select = $(this);
        var newStatus = $select.val();
        var $row = $select.closest('tr');
        var $badge = $row.find('.oj-status-badge');
        
        // Update badge class
        $badge.removeClass('oj-status-available oj-status-occupied oj-status-reserved oj-status-maintenance');
        $badge.addClass('oj-status-' + newStatus);
        
        // Update badge text
        var statusLabels = {
            'available': 'Available',
            'occupied': 'Occupied',
            'reserved': 'Reserved',
            'maintenance': 'Maintenance'
        };
        $badge.text(statusLabels[newStatus] || newStatus);
    });
    
    // Auto-save table number changes
    $(document).on('blur', 'input[name="oj_table_number"]', function() {
        var $input = $(this);
        var tableNumber = $input.val();
        var postId = $('#post_ID').val();
        
        if (tableNumber && postId) {
            // Update QR code URL
            var $qrUrl = $('.oj-qr-code-url input');
            if ($qrUrl.length) {
                $qrUrl.val(window.location.origin + '/table-menu/?table=' + encodeURIComponent(tableNumber));
            }
        }
    });
    
    // Copy QR URL to clipboard
    $(document).on('click', '.oj-qr-code-url input', function() {
        $(this).select();
        document.execCommand('copy');
        showNotification('QR URL copied to clipboard!', 'success');
    });
    
    // Mark order ready (Kitchen Dashboard)
    $(document).on('click', '.oj-complete-order', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        
        if (!orderId) {
            showNotification('Order ID not found. Please refresh and try again.', 'error');
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>Marking Ready...');
        
        $.ajax({
            url: adminConfig.ajax_url || adminConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                nonce: adminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Order #' + orderId + ' marked as ready!', 'success');
                    
                    // Remove the order row from the kitchen view
                    $button.closest('tr').fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if no more orders
                        if ($('.wp-list-table tbody tr').length === 0) {
                            $('.wp-list-table tbody').html('<tr><td colspan="4" style="text-align: center; padding: 40px; color: #666;"><span class="dashicons dashicons-yes-alt" style="font-size: 48px; margin-bottom: 15px; color: #00a32a;"></span><br><h3>All Orders Complete!</h3><p>No pending orders in the kitchen queue.</p></td></tr>');
                        }
                    });
                } else {
                    showNotification('Failed to mark order ready: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotification('Error marking order ready. Please try again.', 'error');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Mark order delivered (Manager Dashboard)
    $(document).on('click', '.oj-deliver-order', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        
        if (!orderId) {
            showNotification('Order ID not found. Please refresh and try again.', 'error');
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; font-size: 14px; vertical-align: middle; margin-right: 4px;"></span>Delivering...');
        
        $.ajax({
            url: adminConfig.ajax_url || adminConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_mark_order_delivered',
                order_id: orderId,
                nonce: adminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Order #' + orderId + ' marked as delivered!', 'success');
                    
                    // Remove the order row from the ready orders view
                    $button.closest('tr').fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if no more ready orders
                        if ($('.oj-dashboard-ready-orders tbody tr').length === 0) {
                            $('.oj-dashboard-ready-orders tbody').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;"><span class="dashicons dashicons-yes-alt" style="font-size: 32px; margin-bottom: 10px; color: #00a32a;"></span><br><p>No orders ready for pickup.</p></td></tr>');
                        }
                    });
                } else {
                    showNotification('Failed to mark order delivered: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotification('Error marking order delivered. Please try again.', 'error');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Flush rewrite rules
    $(document).on('click', '.oj-flush-rewrite-rules', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Flushing...');
        
        $.ajax({
            url: adminConfig.ajax_url || adminConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_flush_rewrite_rules',
                nonce: adminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Rewrite rules flushed successfully!', 'success');
                } else {
                    showNotification('Failed to flush rewrite rules: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Error flushing rewrite rules. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Flush Rewrite Rules');
            }
        });
    });
});
