<?php
/**
 * QR Menu History Tab Partial
 * Order history and payment
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="history-tab" class="tab-content">
    
    <div class="history-header">
        <h3 class="history-title">
            <span class="history-icon">📋</span>
            <?php _e('Order History', 'orders-jet'); ?>
        </h3>
        
        <button id="refresh-history" class="refresh-btn" type="button">
            <span class="refresh-icon">🔄</span>
            <span class="refresh-text"><?php _e('Refresh', 'orders-jet'); ?></span>
        </button>
    </div>
    
    <div class="history-content">
        
        <!-- Order History List -->
        <div id="order-history" class="order-history">
            <!-- Order history will be populated by JavaScript -->
            <div class="loading-history" id="loading-history">
                <div class="loading-spinner"></div>
                <p><?php _e('Loading order history...', 'orders-jet'); ?></p>
            </div>
        </div>
        
        <!-- Table Total -->
        <div id="table-total-section" class="table-total-section" style="display: none;">
            
            <div class="total-header">
                <h4><?php _e('Table Total', 'orders-jet'); ?></h4>
            </div>
            
            <div class="total-breakdown">
                <div class="total-row">
                    <span class="total-label"><?php _e('Subtotal', 'orders-jet'); ?></span>
                    <span id="table-subtotal" class="total-value">0.00 EGP</span>
                </div>
                
                <div class="total-row">
                    <span class="total-label"><?php _e('Tax', 'orders-jet'); ?></span>
                    <span id="table-tax" class="total-value">0.00 EGP</span>
                </div>
                
                <div class="total-row final-total">
                    <span class="total-label"><?php _e('Total Amount', 'orders-jet'); ?></span>
                    <span id="table-total" class="total-value">0.00 EGP</span>
                </div>
            </div>
            
            <div class="payment-actions">
                <button id="pay-now" class="pay-now-btn" type="button">
                    <span class="btn-icon">💳</span>
                    <span class="btn-text"><?php _e('Request Invoice & Pay', 'orders-jet'); ?></span>
                </button>
            </div>
            
        </div>
        
        <!-- Empty State -->
        <div class="empty-history" id="empty-history-message" style="display: none;">
            <div class="empty-history-icon">📋</div>
            <h4><?php _e('No orders yet', 'orders-jet'); ?></h4>
            <p><?php _e('Your order history will appear here', 'orders-jet'); ?></p>
            <button class="back-to-menu-btn" data-tab="menu" type="button">
                <?php _e('Start Ordering', 'orders-jet'); ?>
            </button>
        </div>
        
    </div>
    
</div>

<!-- Payment Method Modal -->
<div id="payment-modal" class="payment-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Select Payment Method', 'orders-jet'); ?></h3>
            <button class="modal-close" type="button">✕</button>
        </div>
        
        <div class="modal-body">
            <div class="payment-methods">
                
                <label class="payment-method">
                    <input type="radio" name="payment_method" value="cash" checked>
                    <div class="method-content">
                        <span class="method-icon">💵</span>
                        <div class="method-details">
                            <strong><?php _e('Cash Payment', 'orders-jet'); ?></strong>
                            <small><?php _e('Pay with cash at the counter', 'orders-jet'); ?></small>
                        </div>
                    </div>
                </label>
                
                <label class="payment-method">
                    <input type="radio" name="payment_method" value="card">
                    <div class="method-content">
                        <span class="method-icon">💳</span>
                        <div class="method-details">
                            <strong><?php _e('Card Payment', 'orders-jet'); ?></strong>
                            <small><?php _e('Pay with credit/debit card', 'orders-jet'); ?></small>
                        </div>
                    </div>
                </label>
                
                <label class="payment-method">
                    <input type="radio" name="payment_method" value="online">
                    <div class="method-content">
                        <span class="method-icon">🌐</span>
                        <div class="method-details">
                            <strong><?php _e('Online Payment', 'orders-jet'); ?></strong>
                            <small><?php _e('Pay online with Stripe', 'orders-jet'); ?></small>
                        </div>
                    </div>
                </label>
                
            </div>
            
            <div class="payment-total">
                <div class="total-display">
                    <span class="total-label"><?php _e('Total to Pay:', 'orders-jet'); ?></span>
                    <span id="payment-total-amount" class="total-amount">0.00 EGP</span>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="modal-btn secondary" data-action="cancel" type="button">
                <?php _e('Cancel', 'orders-jet'); ?>
            </button>
            <button class="modal-btn primary" data-action="process-payment" type="button">
                <?php _e('Process Payment', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Payment Success Modal -->
<div id="payment-success-modal" class="success-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header success">
            <div class="success-icon">✅</div>
            <h3><?php _e('Payment Successful!', 'orders-jet'); ?></h3>
        </div>
        
        <div class="modal-body">
            <div class="success-message">
                <p><?php _e('Your payment has been processed successfully.', 'orders-jet'); ?></p>
                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Amount Paid:', 'orders-jet'); ?></span>
                        <span id="paid-amount" class="detail-value">0.00 EGP</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Payment Method:', 'orders-jet'); ?></span>
                        <span id="paid-method" class="detail-value">Cash</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="modal-btn primary" data-action="close" type="button">
                <?php _e('Close', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>
