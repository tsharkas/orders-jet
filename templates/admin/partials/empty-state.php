<?php
/**
 * Empty State Partial - No Active Orders
 * Reusable component for displaying empty state message
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="oj-empty-state">
    <div class="oj-empty-icon">🎉</div>
    <h3><?php _e('No Active Orders', 'orders-jet'); ?></h3>
    <p><?php _e('All caught up! No orders need attention right now.', 'orders-jet'); ?></p>
</div>
