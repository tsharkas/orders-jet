<?php
/**
 * QR Menu Header Partial
 * Displays table information and location details
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get template variables
$table_number = $template_vars['table_number'] ?? '';
$table_data = $template_vars['table_data'] ?? array();
?>

<div class="qr-menu-header">
    <div class="table-info">
        <h1 class="table-title">
            <?php echo sprintf(__('Table %s', 'orders-jet'), esc_html($table_number)); ?>
        </h1>
        
        <?php if (!empty($table_data['capacity'])): ?>
        <p class="table-capacity">
            <?php echo sprintf(__('Capacity: %d people', 'orders-jet'), intval($table_data['capacity'])); ?>
        </p>
        <?php endif; ?>
        
        <?php if (!empty($table_data['woofood_location'])): ?>
        <div class="location-info">
            <div class="location-content">
                <span class="location-icon">📍</span>
                <div class="location-details">
                    <strong class="location-name">
                        <?php echo esc_html($table_data['woofood_location']['name']); ?>
                    </strong>
                    <?php if (!empty($table_data['woofood_location']['description'])): ?>
                    <small class="location-description">
                        <?php echo esc_html($table_data['woofood_location']['description']); ?>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php 
    // Hook for additional header content
    do_action('oj_qr_menu_header', $table_data);
    ?>
</div>
