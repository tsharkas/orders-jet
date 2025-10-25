<?php
/**
 * QR Menu Navigation Partial
 * Tab navigation for Menu | Cart | History
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="qr-menu-navigation">
    <div class="nav-tabs">
        <button class="nav-tab active" data-tab="menu" type="button">
            <span class="tab-icon">🍽️</span>
            <span class="tab-text"><?php _e('Menu', 'orders-jet'); ?></span>
        </button>
        
        <button class="nav-tab" data-tab="cart" type="button">
            <span class="tab-icon">🛒</span>
            <span class="tab-text"><?php _e('Cart', 'orders-jet'); ?></span>
            <span id="cart-badge" class="tab-badge" style="display: none;">0</span>
        </button>
        
        <button class="nav-tab" data-tab="history" type="button">
            <span class="tab-icon">📋</span>
            <span class="tab-text"><?php _e('Order History', 'orders-jet'); ?></span>
        </button>
    </div>
</div>
