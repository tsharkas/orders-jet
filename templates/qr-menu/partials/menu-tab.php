<?php
/**
 * QR Menu Tab Partial
 * Product grid and categories display
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get template variables
$categories = $template_vars['categories'] ?? array();
$products = $template_vars['products'] ?? array();
?>

<div id="menu-tab" class="tab-content active">
    
    <!-- Category Filters -->
    <div class="category-filters">
        <button class="category-btn active" data-category="all" type="button">
            <?php _e('All', 'orders-jet'); ?>
        </button>
        
        <?php foreach ($categories as $category): ?>
        <button class="category-btn" data-category="<?php echo esc_attr($category->slug); ?>" type="button">
            <?php echo esc_html($category->name); ?>
        </button>
        <?php endforeach; ?>
    </div>
    
    <!-- Menu Grid -->
    <div class="menu-grid" id="menu-grid">
        
        <?php foreach ($categories as $category): ?>
        <div class="category-section" id="category-<?php echo esc_attr($category->slug); ?>">
            <h3 class="category-title"><?php echo esc_html($category->name); ?></h3>
            
            <div class="products-grid">
                <?php 
                // Get products for this category
                $category_products = array_filter($products, function($product) use ($category) {
                    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
                    return in_array($category->slug, $product_categories);
                });
                
                foreach ($category_products as $product): 
                    $image_url = wp_get_attachment_image_url($product->get_image_id(), 'medium');
                    $image_alt = get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true);
                ?>
                
                <div class="menu-item" 
                     data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                     data-category="<?php echo esc_attr($category->slug); ?>">
                     
                    <div class="item-image">
                        <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" 
                             alt="<?php echo esc_attr($image_alt ?: $product->get_name()); ?>"
                             loading="lazy">
                        <?php else: ?>
                        <div class="no-image">
                            <span class="no-image-icon">🍽️</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$product->is_in_stock()): ?>
                        <div class="out-of-stock-overlay">
                            <span><?php _e('Out of Stock', 'orders-jet'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-content">
                        <h4 class="item-name"><?php echo esc_html($product->get_name()); ?></h4>
                        
                        <?php if ($product->get_short_description()): ?>
                        <p class="item-description">
                            <?php echo wp_kses_post($product->get_short_description()); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="item-footer">
                            <span class="item-price">
                                <?php echo wc_price($product->get_price()); ?>
                            </span>
                            
                            <?php if ($product->is_in_stock()): ?>
                            <button class="add-to-cart-btn" type="button">
                                <span class="btn-icon">➕</span>
                                <span class="btn-text"><?php _e('Add', 'orders-jet'); ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
    </div>
    
</div>

<!-- Product Popup -->
<div id="product-popup" class="product-popup" style="display: none;">
    <div class="popup-overlay"></div>
    <div class="popup-content">
        <div class="popup-header">
            <button id="popup-close" class="popup-close" type="button">✕</button>
        </div>
        
        <div class="popup-body">
            <div class="popup-image">
                <img id="popup-image" src="" alt="">
            </div>
            
            <div class="popup-details">
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
                <div id="popup-price" class="popup-price"></div>
                
                <!-- Variations will be inserted here -->
                <div id="popup-variations" class="popup-variations"></div>
                
                <!-- Add-ons will be inserted here -->
                <div id="popup-addons" class="popup-addons"></div>
                
                <!-- Notes -->
                <div class="popup-notes">
                    <label for="popup-notes-input"><?php _e('Special Notes', 'orders-jet'); ?></label>
                    <textarea id="popup-notes-input" placeholder="<?php esc_attr_e('Any special requests...', 'orders-jet'); ?>"></textarea>
                </div>
                
                <!-- Quantity -->
                <div class="popup-quantity">
                    <label><?php _e('Quantity', 'orders-jet'); ?></label>
                    <div class="quantity-controls">
                        <button id="quantity-minus" type="button">−</button>
                        <input id="quantity-input" type="number" value="1" min="1">
                        <button id="quantity-plus" type="button">+</button>
                    </div>
                </div>
                
                <!-- Add to Cart Button -->
                <button id="popup-add-to-cart" class="popup-add-to-cart" type="button">
                    <?php _e('Add to Cart', 'orders-jet'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
