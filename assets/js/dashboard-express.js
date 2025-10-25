/**
 * Orders Express Dashboard JavaScript
 * Lightning fast active orders management with AJAX filtering
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // ========================================================================
    // PERFORMANCE OPTIMIZATION - Cache frequently used elements
    // ========================================================================
    
    var $document = $(document);
    var $body = $('body');
    
    // ========================================================================
    // AJAX FILTERING - Client-side for instant response
    // ========================================================================
    
    $document.on('click', '.oj-filter-btn', function() {
        const filter = $(this).data('filter');
        const $cards = $('.oj-order-card');
        
        // Update active filter button
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter cards with smooth animation
        $cards.each(function() {
            const $card = $(this);
            const status = $card.attr('data-status');
            const method = $card.attr('data-method');
            const kitchenType = $card.attr('data-kitchen-type');
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
    });
    
    // ========================================================================
    // ORDER MANAGEMENT - Reuse existing AJAX handlers
    // ========================================================================
    
    // Mark Ready - Change processing → pending
    // Mark Order Ready - Enhanced with dual kitchen support
    $document.on('click', '.oj-mark-ready, .oj-mark-ready-food, .oj-mark-ready-beverage', function() {
        const orderId = $(this).data('order-id');
        const kitchenType = $(this).data('kitchen') || 'food';
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        $btn.prop('disabled', true).html('⏳ Marking...');
        
        $.ajax({
            url: ojExpressData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_mark_order_ready',
                order_id: orderId,
                kitchen_type: kitchenType,
                nonce: ojExpressData.nonces.dashboard
            },
            success: function(response) {
                if (response.success && response.data.card_updates) {
                    const updates = response.data.card_updates;
                    
                    // Update status badge with kitchen-aware content
                    if (updates.status_badge_html) {
                        $card.find('.oj-status-badge').replaceWith(updates.status_badge_html);
                    }
                    
                    // Update yellow status badge with dynamic kitchen status
                    if (updates.status_text && updates.status_icon && updates.status_class) {
                        var $statusBadge = $card.find('.oj-status-badge');
                        $statusBadge.html(updates.status_icon + ' ' + updates.status_text);
                        $statusBadge.removeClass('ready partial kitchen completed').addClass(updates.status_class);
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
                            $btn.html('🍕✅ Food Ready').prop('disabled', true);
                        } else {
                            $btn.html('🥤✅ Bev. Ready').prop('disabled', true);
                        }
                        showExpressNotification(`✅ ${kitchenType.charAt(0).toUpperCase() + kitchenType.slice(1)} ready! ${updates.button_text}`, 'success');
                        
                        // Update filter counts for partial ready
                        updateExpressFilterCounts();
                    } else {
                        // Fully ready - replace all buttons with completion button
                        const tableNumber = $card.attr('data-table-number');
                        let newButton;
                        if (tableNumber && tableNumber !== '') {
                            newButton = `<button class="oj-action-btn primary oj-close-table" data-order-id="${orderId}" data-table-number="${tableNumber}">🍽️ ${ojExpressData.i18n.closeTable}</button>`;
                        } else {
                            newButton = `<button class="oj-action-btn primary oj-complete-order" data-order-id="${orderId}">✅ Complete</button>`;
                        }
                        
                        // Replace all kitchen buttons with completion button
                        $card.find('.oj-card-actions').html(newButton);
                        
                        showExpressNotification('✅ Order fully ready!', 'success');
                    }
                    
                    // Add update animation
                    $card.addClass('oj-card-updated');
                    setTimeout(() => $card.removeClass('oj-card-updated'), 1000);
                    
                    // Update filter counts
                    updateExpressFilterCounts();
                    
                } else {
                    $btn.prop('disabled', false);
                    // Restore original button text based on kitchen type
                    if (kitchenType === 'food') {
                        $btn.html('🍕 Food Ready');
                    } else if (kitchenType === 'beverages') {
                        $btn.html('🥤 Bev. Ready');
                    } else {
                        $btn.html('🔥 Mark Ready');
                    }
                    showExpressNotification('❌ Failed to mark order ready', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                // Restore original button text
                if (kitchenType === 'food') {
                    $btn.html('🍕 Food Ready');
                } else if (kitchenType === 'beverages') {
                    $btn.html('🥤 Bev. Ready');
                } else {
                    $btn.html('🔥 Mark Ready');
                }
                showExpressNotification('❌ ' + ojExpressData.i18n.connectionError, 'error');
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
            $btn.prop('disabled', true).html('⏳ Completing...');
            
            $.ajax({
                url: ojExpressData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_complete_individual_order',
                    order_id: orderId,
                    payment_method: paymentMethod,
                    nonce: ojExpressData.nonces.dashboard
                },
                success: function(response) {
                    if (response.success && response.data.card_updates) {
                        // Update to Print Invoice state
                        $btn.removeClass('oj-complete-order')
                            .addClass('oj-print-invoice')
                            .html('🖨️ Print Invoice')
                            .prop('disabled', false)
                            .attr('data-invoice-url', response.data.card_updates.invoice_url);
                        
                        showExpressNotification('✅ Order completed! Print invoice for payment.', 'success');
                        
                        // Update filter counts after completion
                        updateExpressFilterCounts();
                    } else {
                        $btn.prop('disabled', false).html('✅ Complete');
                        showExpressNotification('❌ Failed to complete order', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('✅ Complete');
                    showExpressNotification('❌ ' + ojExpressData.i18n.connectionError, 'error');
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
                        .html('💰 ' + ojExpressData.i18n.paid);
                    
                    setTimeout(() => iframe.remove(), 1000);
                } catch (e) {
                    showExpressNotification('❌ ' + ojExpressData.i18n.printFailed + ' ' + e.message, 'error');
                    iframe.remove();
                }
            }, 500);
        });
        
        iframe.on('error', function() {
            showExpressNotification('❌ ' + ojExpressData.i18n.failedToLoadInvoice, 'error');
            iframe.remove();
        });
    });
    
    // Confirm Payment - Final step
    $(document).on('click', '.oj-confirm-payment', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        
        $btn.prop('disabled', true).html('⏳ ' + ojExpressData.i18n.confirming);
        
        $.ajax({
            url: ojExpressData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_confirm_payment_received',
                order_id: orderId,
                nonce: ojExpressData.nonces.dashboard
            },
            success: function(response) {
                if (response.success) {
                    // Slide out card
                    $card.addClass('oj-card-removing');
                    setTimeout(() => {
                        $card.remove();
                        showExpressNotification('✅ ' + ojExpressData.i18n.paymentConfirmed, 'success');
                        
                        // Update filter counts after card removal
                        updateExpressFilterCounts();
                    }, 500);
                } else {
                    $btn.prop('disabled', false).html('💰 ' + ojExpressData.i18n.paid);
                    showExpressNotification('❌ ' + ojExpressData.i18n.failedToConfirmPayment, 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('💰 ' + ojExpressData.i18n.paid);
                showExpressNotification('❌ ' + ojExpressData.i18n.connectionError, 'error');
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
            $btn.prop('disabled', true).html('⏳ ' + ojExpressData.i18n.closing);
            
            $.ajax({
                url: ojExpressData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_close_table_group',
                    table_number: tableNumber,
                    payment_method: paymentMethod,
                    nonce: ojExpressData.nonces.table_order
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
                            
                            showExpressNotification('✅ ' + ojExpressData.i18n.tableClosed, 'success');
                            
                            // Update filter counts after table closure
                            updateExpressFilterCounts();
                        }, 500);
                    } else if (response.data && response.data.show_confirmation) {
                        // Handle processing orders confirmation - same as main orders page
                        const confirmMessage = response.data.message + '\n\n' + ojExpressData.i18n.clickToContinue;
                        
                        if (confirm(confirmMessage)) {
                            // User confirmed - retry with force_close flag
                            $btn.prop('disabled', true).html('⏳ ' + ojExpressData.i18n.forceClosing);
                            
                            $.ajax({
                                url: ojExpressData.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'oj_close_table_group',
                                    table_number: tableNumber,
                                    payment_method: paymentMethod,
                                    force_close: 'true',
                                    nonce: ojExpressData.nonces.table_order
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
                                            
                                            showExpressNotification('✅ ' + ojExpressData.i18n.tableForceClose, 'success');
                                        }, 500);
                                    } else {
                                        $btn.prop('disabled', false).html('🍽️ ' + ojExpressData.i18n.closeTable);
                                        showExpressNotification('❌ ' + ojExpressData.i18n.failedToForceClose, 'error');
                                    }
                                },
                                error: function() {
                                    $btn.prop('disabled', false).html('🍽️ ' + ojExpressData.i18n.closeTable);
                                    showExpressNotification('❌ ' + ojExpressData.i18n.connectionErrorForceClose, 'error');
                                }
                            });
                        } else {
                            // User cancelled - restore button
                            $btn.prop('disabled', false).html('🍽️ ' + ojExpressData.i18n.closeTable);
                        }
                    } else {
                        $btn.prop('disabled', false).html('🍽️ ' + ojExpressData.i18n.closeTable);
                        const errorMessage = response.data && response.data.message ? response.data.message : ojExpressData.i18n.failedToCloseTable;
                        showExpressNotification('❌ ' + errorMessage, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('🍽️ ' + ojExpressData.i18n.closeTable);
                    showExpressNotification('❌ ' + ojExpressData.i18n.connectionError, 'error');
                }
            });
        });
    });
    
    // View Order Details
    $(document).on('click', '.oj-view-order', function() {
        const orderId = $(this).data('order-id');
        const url = ojExpressData.adminUrl + '?post=' + orderId + '&action=edit';
        window.open(url, '_blank');
    });
    
    // ========================================================================
    // HELPER FUNCTIONS
    // ========================================================================
    
    function showExpressPaymentModal(orderId, callback) {
        // Remove any existing modals first
        $('.oj-success-modal-overlay').remove();
        
        const modal = $(`
            <div class="oj-success-modal-overlay">
                <div class="oj-success-modal">
                    <h3>${ojExpressData.i18n.paymentMethod}</h3>
                    <p>${ojExpressData.i18n.howPaid}</p>
                    <div class="oj-payment-buttons">
                        <button class="oj-payment-btn cash" data-method="cash">
                            💵 ${ojExpressData.i18n.cash}
                        </button>
                        <button class="oj-payment-btn card" data-method="card">
                            💳 ${ojExpressData.i18n.card}
                        </button>
                        <button class="oj-payment-btn other" data-method="other">
                            📱 ${ojExpressData.i18n.other}
                        </button>
                    </div>
                    <button class="oj-modal-close">✕</button>
                </div>
            </div>
        `);
        
        $body.append(modal);
        
        // Use direct event binding for this specific modal instance
        modal.find('.oj-payment-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const method = $(this).data('method');
            modal.remove();
            if (typeof callback === 'function') {
                callback(method);
            }
        });
        
        modal.find('.oj-modal-close').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if (e.target === this[0]) {
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
        
        $body.append(notification);
        
        // Use direct event binding for this specific notification
        notification.find('.oj-notification-close').on('click', function() {
            notification.remove();
        });
        
        setTimeout(() => notification.remove(), 5000);
    }
    
    // Update filter counts based on current visible cards
    function updateExpressFilterCounts() {
        const counts = {
            active: 0,
            processing: 0,
            pending: 0,
            dinein: 0,
            takeaway: 0,
            delivery: 0,
            food_kitchen: 0,
            beverage_kitchen: 0
        };
        
        // Count all visible cards
        $('.oj-order-card').each(function() {
            const $card = $(this);
            const status = $card.attr('data-status');
            const method = $card.attr('data-method');
            const kitchenType = $card.attr('data-kitchen-type');
            
            // Count active orders
            if (status === 'processing' || status === 'pending') {
                counts.active++;
            }
            
            // Count by status
            if (status === 'processing') {
                counts.processing++;
            } else if (status === 'pending') {
                counts.pending++;
            }
            
            // Count by method
            if (method === 'dinein') {
                counts.dinein++;
            } else if (method === 'takeaway') {
                counts.takeaway++;
            } else if (method === 'delivery') {
                counts.delivery++;
            }
            
            // Count by kitchen type - mixed orders count in both kitchens
            if (kitchenType === 'food' || kitchenType === 'mixed') {
                counts.food_kitchen++;
            }
            if (kitchenType === 'beverages' || kitchenType === 'mixed') {
                counts.beverage_kitchen++;
            }
        });
        
        // Update filter button counts
        $('.oj-filter-btn[data-filter="active"] .oj-filter-count').text(counts.active);
        $('.oj-filter-btn[data-filter="processing"] .oj-filter-count').text(counts.processing);
        $('.oj-filter-btn[data-filter="pending"] .oj-filter-count').text(counts.pending);
        $('.oj-filter-btn[data-filter="dinein"] .oj-filter-count').text(counts.dinein);
        $('.oj-filter-btn[data-filter="takeaway"] .oj-filter-count').text(counts.takeaway);
        $('.oj-filter-btn[data-filter="delivery"] .oj-filter-count').text(counts.delivery);
        $('.oj-filter-btn[data-filter="food-kitchen"] .oj-filter-count').text(counts.food_kitchen);
        $('.oj-filter-btn[data-filter="beverage-kitchen"] .oj-filter-count').text(counts.beverage_kitchen);
        
        // Update header stats
        $('.oj-stat-item:nth-child(1) .oj-stat-number').text(counts.processing);
        $('.oj-stat-item:nth-child(2) .oj-stat-number').text(counts.pending);
        $('.oj-stat-item:nth-child(3) .oj-stat-number').text(counts.active);
    }
    
    function createExpressCombinedOrderCard(combinedOrder) {
        return $(`
            <div class="oj-order-card oj-combined-card" 
                 data-order-id="${combinedOrder.order_id}" 
                 data-status="pending" 
                 data-method="dinein" 
                 data-table-number="${combinedOrder.table_number}">
                 
                <!-- Row 1: Order number + Type badges -->
                <div class="oj-card-row-1">
                    <div class="oj-order-header">
                        <span class="oj-view-icon oj-view-order" data-order-id="${combinedOrder.order_id}" title="${ojExpressData.i18n.viewOrderDetails}">👁️</span>
                        <span class="oj-table-ref">${combinedOrder.table_number}</span>
                        <span class="oj-order-number">#${combinedOrder.order_number}</span>
                    </div>
                    <div class="oj-type-badges">
                        <span class="oj-type-badge dinein">🍽️ ${ojExpressData.i18n.dinein}</span>
                        <span class="oj-combined-badge">🔗 ${ojExpressData.i18n.combined}</span>
                    </div>
                </div>

                <!-- Row 2: Time + Status -->
                <div class="oj-card-row-2">
                    <span class="oj-order-time">${combinedOrder.date}</span>
                    <span class="oj-status-badge ready">✅ ${ojExpressData.i18n.ready}</span>
                </div>

                <!-- Row 3: Customer + Price -->
                <div class="oj-card-row-3">
                    <span class="oj-customer-name">Table ${combinedOrder.table_number} Guest</span>
                    <span class="oj-order-total">${combinedOrder.total}</span>
                </div>

                <!-- Row 4: Item count -->
                <div class="oj-card-row-4">
                    <span class="oj-item-count">${combinedOrder.item_count} items</span>
                </div>

                <!-- Row 5: Item details -->
                <div class="oj-card-row-5">
                    <div class="oj-items-list">
                        ${combinedOrder.items.map(item => `${item.quantity}x ${item.name}`).join(' ')}
                    </div>
                </div>
                
                <!-- Row 6: Action buttons -->
                <div class="oj-card-actions">
                    <button class="oj-action-btn primary oj-print-invoice" data-order-id="${combinedOrder.order_id}" data-invoice-url="${combinedOrder.invoice_url}">
                        🖨️ Print Invoice
                    </button>
                </div>
            </div>
        `);
    }
});
