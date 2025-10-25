<?php
/**
 * Table Menu Template - Clean Implementation
 * 
 * This template provides a clean, working implementation of the table menu
 * without conflicts with the main plugin scripts.
 */

// Get table data
$table_number = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';
$table_id = function_exists('oj_get_table_id_by_number') ? oj_get_table_id_by_number($table_number) : null;

if (!$table_number || !$table_id) {
    wp_die(__('Table not found', 'orders-jet'));
}

// Get table meta
$table_capacity = get_post_meta($table_id, '_oj_table_capacity', true);
$table_location = get_post_meta($table_id, '_oj_table_location', true);
$table_status = get_post_meta($table_id, '_oj_table_status', true);

// Get WooFood location integration
$woofood_location_id = get_post_meta($table_id, '_oj_woofood_location_id', true);
$woofood_location = null;
if ($woofood_location_id && class_exists('EX_WooFood')) {
    $woofood_location = get_term($woofood_location_id, 'exwoofood_loc');
}

// Get menu categories
$categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Get products - filter by WooFood location if assigned
$product_args = array(
    'limit' => -1,
    'status' => 'publish',
    'orderby' => 'menu_order',
    'order' => 'ASC'
);

// Add WooFood location filter if table is assigned to a location
if ($woofood_location_id) {
    $product_args['tax_query'] = array(
        array(
            'taxonomy' => 'exwoofood_loc',
            'field'    => 'term_id',
            'terms'    => $woofood_location_id,
        )
    );
}

$products = wc_get_products($product_args);

// Apply menu integration enhancements
if (class_exists('Orders_Jet_Menu_Integration')) {
    $products = apply_filters('oj_qr_menu_products', $products, $table_id);
    $categories = apply_filters('oj_qr_menu_categories', $categories, $table_id);
}
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

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo sprintf(__('Table %s', 'orders-jet'), $table_number); ?></h1>
            <p><?php echo sprintf(__('Capacity: %d people', 'orders-jet'), $table_capacity); ?></p>
            <?php if ($woofood_location && !is_wp_error($woofood_location)): ?>
            <div class="location-info" style="background: #f0f9ff; border: 1px solid #0073aa; border-radius: 8px; padding: 10px; margin-top: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">📍</span>
                    <div>
                        <strong style="color: #0073aa;"><?php echo esc_html($woofood_location->name); ?></strong>
                        <?php if ($woofood_location->description): ?>
                        <br><small style="color: #666;"><?php echo esc_html($woofood_location->description); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php
        // Add menu filters and enhancements
        do_action('oj_table_menu_before_products', $table_id);
        ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="menu"><?php _e('Menu', 'orders-jet'); ?></button>
            <button class="tab-btn" data-tab="cart"><?php _e('Cart', 'orders-jet'); ?></button>
            <button class="tab-btn" data-tab="history"><?php _e('Order History', 'orders-jet'); ?></button>
        </div>
        
        <!-- Menu Tab -->
        <div id="menu-tab" class="tab-content">
            <!-- Category Filters -->
            <div class="category-filters">
                <button class="category-btn active" data-category="all"><?php _e('All', 'orders-jet'); ?></button>
                <?php foreach ($categories as $category): ?>
                    <button class="category-btn" data-category="<?php echo esc_attr($category->slug); ?>">
                        <?php echo esc_html($category->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Menu Grid -->
            <div class="menu-grid" id="menu-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-section" id="category-<?php echo esc_attr($category->slug); ?>">
                        <h3 class="category-section-title"><?php echo esc_html($category->name); ?></h3>
                        <?php 
                        $category_products = get_posts(array(
                            'post_type' => 'product',
                            'posts_per_page' => -1,
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'product_cat',
                                    'field' => 'slug',
                                    'terms' => $category->slug,
                                ),
                            ),
                        ));
                        
                        foreach ($category_products as $post):
                            $product = wc_get_product($post->ID);
                            
                            // Get WooFood menu management data
                            $menu_availability = get_post_meta($product->get_id(), '_oj_menu_availability', true);
                            $menu_priority = get_post_meta($product->get_id(), '_oj_menu_priority', true);
                            $menu_featured = get_post_meta($product->get_id(), '_oj_menu_featured', true);
                            
                            // Check if product is available
                            $is_available = ($menu_availability !== 'unavailable');
                            $is_featured = ($menu_featured === '1' || $menu_priority === 'featured');
                            
                            // Skip unavailable products unless admin
                            if (!$is_available && !current_user_can('manage_options')) {
                                continue;
                            }
                            
                            $item_classes = 'menu-item';
                            if (!$is_available) $item_classes .= ' menu-item-unavailable';
                            if ($is_featured) $item_classes .= ' menu-item-featured';
                        ?>
                            <div class="<?php echo esc_attr($item_classes); ?>" 
                                 data-product-id="<?php echo $product->get_id(); ?>" 
                                 data-categories="<?php echo implode(',', wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'))); ?>"
                                 data-availability="<?php echo esc_attr($menu_availability ?: 'available'); ?>">
                                
                                <?php if ($is_featured): ?>
                                <div class="menu-item-badge featured-badge">
                                    ⭐ <?php _e('Featured', 'orders-jet'); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!$is_available): ?>
                                <div class="menu-item-badge unavailable-badge">
                                    <?php _e('Temporarily Unavailable', 'orders-jet'); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="menu-item-image">
                                    <?php if ($product->get_image_id()): ?>
                                        <img class="lazy-image" 
                                             data-src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'medium'); ?>" 
                                             alt="<?php echo esc_attr($product->get_name()); ?>"
                                             loading="lazy">
                                        <div class="lazy-placeholder"><?php _e('Loading...', 'orders-jet'); ?></div>
                                    <?php else: ?>
                                        <span><?php _e('No Image', 'orders-jet'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="menu-item-content">
                                    <h3 class="menu-item-title"><?php echo esc_html($product->get_name()); ?></h3>
                                    <p class="menu-item-description"><?php echo esc_html($product->get_short_description()); ?></p>
                                    <div class="menu-item-price"><?php echo $product->get_price_html(); ?></div>
                                    
                                    <?php
                                    // Show WooFood locations if available
                                    $woofood_locations = wp_get_post_terms($product->get_id(), 'exwoofood_loc');
                                    if ($woofood_locations && !is_wp_error($woofood_locations) && count($woofood_locations) > 1):
                                    ?>
                                    <div class="menu-item-locations">
                                        <small style="color: #666;">
                                            📍 <?php echo implode(', ', array_map(function($loc) { return $loc->name; }, $woofood_locations)); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="menu-item-hint">
                                        <?php if ($is_available): ?>
                                            <?php _e('Tap to view details', 'orders-jet'); ?>
                                        <?php else: ?>
                                            <?php _e('Currently unavailable', 'orders-jet'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php
            // Add menu enhancements after products
            do_action('oj_table_menu_after_products', $table_id);
            ?>
        </div>
        
        <!-- Cart Tab -->
        <div id="cart-tab" class="tab-content cart-content">
            <div class="cart-total">
                <?php _e('Total:', 'orders-jet'); ?> <span id="cart-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
            </div>
            
            <div id="cart-items">
                <!-- Cart items will be populated by JavaScript -->
            </div>
            
            <div class="cart-actions">
                <button class="cart-btn cart-btn-secondary" id="clear-cart"><?php _e('Clear Cart', 'orders-jet'); ?></button>
                <button class="cart-btn cart-btn-primary" id="place-order"><?php _e('Place Order', 'orders-jet'); ?></button>
            </div>
        </div>
        
        <!-- Order History Tab -->
        <div id="history-tab" class="tab-content history-content">
            <div class="history-header">
                <h3><?php _e('Your Orders', 'orders-jet'); ?></h3>
                <div class="table-total">
                    <?php _e('Table Total:', 'orders-jet'); ?> <span id="table-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
                </div>
            </div>
            
            <div id="order-history">
                <!-- Order history will be populated by JavaScript -->
            </div>
            
                    <div class="history-actions">
                        <div class="pay-section">
                            <div class="invoice-total">
                                <span class="invoice-label"><?php _e('Invoice Total:', 'orders-jet'); ?></span>
                                <span class="invoice-amount" id="invoice-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
                            </div>
                            <button class="cart-btn cart-btn-primary" id="pay-now"><?php _e('Ask for Invoice', 'orders-jet'); ?></button>
                        </div>
                    </div>
        </div>
    </div>
    
    <!-- Popup -->
    <div class="popup-overlay" id="product-popup">
        <div class="popup-content">
            <div class="popup-header">
                <button class="popup-close" id="popup-close">&times;</button>
            </div>
            <div class="popup-body">
                <div class="popup-image" id="popup-image">
                    <img src="" alt="" style="display: none;">
                    <span><?php _e('No Image', 'orders-jet'); ?></span>
                </div>
                <h2 class="popup-title" id="popup-title"></h2>
                <p class="popup-description" id="popup-description"></p>
                
                <div class="popup-price-section">
                    <div class="popup-price" id="popup-price"></div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" id="quantity-minus">-</button>
                        <input type="number" class="quantity-input" id="quantity-input" value="1" min="1">
                        <button class="quantity-btn" id="quantity-plus">+</button>
                    </div>
                </div>
                
                <!-- Food Information Section -->
                <div class="popup-section" id="food-info-section" style="display: none;">
                    <label><?php _e('Food Information', 'orders-jet'); ?></label>
                    <div id="food-info-content"></div>
                </div>
                
                <!-- Variations Section -->
                <div class="popup-section" id="variations-section" style="display: none;">
                    <label><?php _e('Options', 'orders-jet'); ?></label>
                    <div id="variations-content"></div>
                </div>
                
                <!-- Add-ons Section -->
                <div class="popup-section" id="addons-section" style="display: none;">
                    <label><?php _e('Add-ons', 'orders-jet'); ?></label>
                    <div id="addons-content"></div>
                </div>
                
                <div class="popup-section">
                    <textarea class="notes-textarea" id="popup-notes" placeholder="<?php _e('Any special requests or notes...', 'orders-jet'); ?>"></textarea>
                </div>
                
                <div class="popup-actions">
                    <button class="popup-btn popup-btn-secondary" id="popup-back"><?php _e('Back to Menu', 'orders-jet'); ?></button>
                    <button class="popup-btn popup-btn-primary" id="popup-add-to-cart"><?php _e('Add to Cart', 'orders-jet'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bottom Sticky Cart Bar -->
    <div class="floating-cart" id="floating-cart">
        <div class="floating-cart-left">
            <div class="floating-cart-items" id="floating-cart-items">0 items</div>
            <div class="floating-cart-total" id="floating-cart-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></div>
        </div>
        <div class="floating-cart-right">
            <button class="floating-cart-btn" id="floating-cart-btn">
                🛒 <?php _e('View Cart', 'orders-jet'); ?>
            </button>
        </div>
    </div>
    
    
    <?php wp_footer(); ?>
</body>
</html>