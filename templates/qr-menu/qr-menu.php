<?php
/**
 * QR Menu Template - Refactored Clean Implementation
 * 
 * This is the new modular QR menu template that replaces the monolithic version.
 * Uses handler/service architecture for better maintainability.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get table number from URL
$table_number = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';

if (empty($table_number)) {
    wp_die(__('Table parameter is required', 'orders-jet'));
}

try {
    // Initialize QR Menu Handler
    $qr_menu_handler = new Orders_Jet_QR_Menu_Handler();
    
    // Get complete menu data
    $menu_data = $qr_menu_handler->get_complete_menu_data($table_number);
    
    // Extract data for template use
    $table_data = $menu_data['table'];
    $categories = $menu_data['categories'];
    $products = $menu_data['products'];
    
} catch (Exception $e) {
    wp_die($e->getMessage());
}

// Set template variables for partials
$template_vars = array(
    'table_number' => $table_number,
    'table_data' => $table_data,
    'categories' => $categories,
    'products' => $products
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#c41e3a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title><?php echo sprintf(__('Table %s Menu', 'orders-jet'), $table_number); ?> - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
</head>
<body class="qr-menu-body">
    <div class="qr-menu-container">
        
        <?php 
        // Include header partial
        include locate_template('qr-menu/partials/header.php') ?: __DIR__ . '/partials/header.php';
        ?>
        
        <?php 
        // Include navigation partial
        include locate_template('qr-menu/partials/navigation.php') ?: __DIR__ . '/partials/navigation.php';
        ?>
        
        <div class="qr-menu-content">
            
            <?php 
            // Include menu tab partial
            include locate_template('qr-menu/partials/menu-tab.php') ?: __DIR__ . '/partials/menu-tab.php';
            ?>
            
            <?php 
            // Include cart tab partial
            include locate_template('qr-menu/partials/cart-tab.php') ?: __DIR__ . '/partials/cart-tab.php';
            ?>
            
            <?php 
            // Include history tab partial
            include locate_template('qr-menu/partials/history-tab.php') ?: __DIR__ . '/partials/history-tab.php';
            ?>
            
        </div>
        
        <!-- Floating Cart Bar -->
        <div id="floating-cart" class="floating-cart" style="display: none;">
            <div class="floating-cart-content">
                <span class="floating-cart-icon">🛒</span>
                <span class="floating-cart-text"><?php _e('Cart', 'orders-jet'); ?></span>
                <span id="floating-cart-total" class="floating-cart-total">0.00 EGP</span>
            </div>
        </div>
        
    </div>
    
    <!-- JavaScript Variables -->
    <script>
        window.ojQrMenu = {
            tableNumber: '<?php echo esc_js($table_number); ?>',
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>',
            strings: {
                addToCart: '<?php echo esc_js(__('Add to Cart', 'orders-jet')); ?>',
                cart: '<?php echo esc_js(__('Cart', 'orders-jet')); ?>',
                loading: '<?php echo esc_js(__('Loading...', 'orders-jet')); ?>',
                error: '<?php echo esc_js(__('Error occurred', 'orders-jet')); ?>',
                success: '<?php echo esc_js(__('Success', 'orders-jet')); ?>'
            }
        };
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
