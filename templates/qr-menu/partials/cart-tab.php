<?php
/**
 * QR Menu Cart Tab Partial
 * Cart display and management
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="cart-tab" class="tab-content">
    
    <div class="cart-header">
        <h3 class="cart-title">
            <span class="cart-icon">🛒</span>
            <?php _e('Your Cart', 'orders-jet'); ?>
        </h3>
    </div>
    
    <div class="cart-content">
        
        <!-- Cart Items -->
        <div id="cart-items" class="cart-items">
            <!-- Cart items will be populated by JavaScript -->
            <div class="empty-cart" id="empty-cart-message">
                <div class="empty-cart-icon">🛒</div>
                <h4><?php _e('Your cart is empty', 'orders-jet'); ?></h4>
                <p><?php _e('Add items from the menu to get started', 'orders-jet'); ?></p>
                <button class="back-to-menu-btn" data-tab="menu" type="button">
                    <?php _e('Browse Menu', 'orders-jet'); ?>
                </button>
            </div>
        </div>
        
        <!-- Cart Summary -->
        <div id="cart-summary" class="cart-summary" style="display: none;">
            
            <div class="summary-row subtotal-row">
                <span class="summary-label"><?php _e('Subtotal', 'orders-jet'); ?></span>
                <span id="cart-subtotal" class="summary-value">0.00 EGP</span>
            </div>
            
            <div class="summary-row tax-row">
                <span class="summary-label"><?php _e('Tax', 'orders-jet'); ?></span>
                <span id="cart-tax" class="summary-value">0.00 EGP</span>
            </div>
            
            <div class="summary-row total-row">
                <span class="summary-label"><?php _e('Total', 'orders-jet'); ?></span>
                <span id="cart-total" class="summary-value">0.00 EGP</span>
            </div>
            
        </div>
        
    </div>
    
    <div class="cart-actions" id="cart-actions" style="display: none;">
        
        <button id="clear-cart" class="cart-action-btn secondary" type="button">
            <span class="btn-icon">🗑️</span>
            <span class="btn-text"><?php _e('Clear Cart', 'orders-jet'); ?></span>
        </button>
        
        <button id="place-order" class="cart-action-btn primary" type="button">
            <span class="btn-icon">📝</span>
            <span class="btn-text"><?php _e('Place Order', 'orders-jet'); ?></span>
        </button>
        
    </div>
    
</div>

<!-- Order Confirmation Modal -->
<div id="order-confirmation-modal" class="order-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Confirm Order', 'orders-jet'); ?></h3>
            <button class="modal-close" type="button">✕</button>
        </div>
        
        <div class="modal-body">
            <div class="order-summary">
                <h4><?php _e('Order Summary', 'orders-jet'); ?></h4>
                <div id="confirmation-items" class="confirmation-items"></div>
                <div class="confirmation-total">
                    <strong id="confirmation-total-amount"></strong>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="modal-btn secondary" data-action="cancel" type="button">
                <?php _e('Cancel', 'orders-jet'); ?>
            </button>
            <button class="modal-btn primary" data-action="confirm" type="button">
                <?php _e('Confirm Order', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>
