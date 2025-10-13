<?php
/**
 * WooFood Menu Integration
 * 
 * Integrates WooFood's menu management system with Orders Jet table system
 * 
 * @package Orders_Jet_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Menu_Integration {
    
    /**
     * WooFood instance
     */
    private $woofood_instance = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_menu_integration'));
        add_action('admin_init', array($this, 'setup_admin_hooks'));
    }
    
    /**
     * Initialize menu integration
     */
    public function init_menu_integration() {
        // Check if WooFood is available
        if (!$this->is_woofood_active()) {
            return;
        }
        
        // Get WooFood instance
        $this->woofood_instance = $this->get_woofood_instance();
        
        // Setup integration hooks
        $this->setup_hooks();
    }
    
    /**
     * Setup integration hooks
     */
    private function setup_hooks() {
        // Menu display hooks
        add_filter('oj_qr_menu_products', array($this, 'enhance_menu_products'), 10, 2);
        add_filter('oj_qr_menu_categories', array($this, 'enhance_menu_categories'), 10, 2);
        
        // Menu management hooks
        add_action('oj_table_menu_before_products', array($this, 'add_menu_filters'));
        add_action('oj_table_menu_after_products', array($this, 'add_menu_enhancements'));
        
        // Staff menu management
        add_action('oj_admin_menu_management', array($this, 'add_staff_menu_controls'));
        
        // AJAX handlers for menu management
        add_action('wp_ajax_oj_update_menu_availability', array($this, 'ajax_update_menu_availability'));
        add_action('wp_ajax_oj_get_menu_analytics', array($this, 'ajax_get_menu_analytics'));
        add_action('wp_ajax_oj_filter_menu_by_location', array($this, 'ajax_filter_menu_by_location'));
    }
    
    /**
     * Setup admin hooks
     */
    public function setup_admin_hooks() {
        // Add menu management to product edit screen
        add_action('add_meta_boxes', array($this, 'add_menu_management_metabox'));
        add_action('save_post_product', array($this, 'save_menu_management_meta'));
        
        // Add menu columns to product list
        add_filter('manage_edit-product_columns', array($this, 'add_menu_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'display_menu_columns'), 10, 2);
    }
    
    /**
     * Enhance menu products with WooFood data
     */
    public function enhance_menu_products($products, $table_id) {
        if (!$this->is_woofood_active() || empty($products)) {
            return $products;
        }
        
        $enhanced_products = array();
        
        foreach ($products as $product) {
            $enhanced_product = $this->enhance_single_product($product, $table_id);
            $enhanced_products[] = $enhanced_product;
        }
        
        return $enhanced_products;
    }
    
    /**
     * Enhance single product with WooFood data
     */
    private function enhance_single_product($product, $table_id) {
        $product_id = $product->get_id();
        
        // Get WooFood specific data
        $woofood_data = array(
            'min_quantity' => get_post_meta($product_id, 'exwf_minquantity', true),
            'max_quantity' => get_post_meta($product_id, 'exwf_maxquantity', true),
            'options' => get_post_meta($product_id, 'exwo_options', true),
            'locations' => wp_get_post_terms($product_id, 'exwoofood_loc'),
            'menu_categories' => wp_get_post_terms($product_id, 'exfood_menu'),
            'availability_status' => $this->check_product_availability($product_id, $table_id)
        );
        
        // Add WooFood data to product object
        $product->woofood_data = $woofood_data;
        
        return $product;
    }
    
    /**
     * Check product availability for table
     */
    private function check_product_availability($product_id, $table_id) {
        // Get table location
        $table_woofood_location = get_post_meta($table_id, '_oj_woofood_location_id', true);
        
        if (!$table_woofood_location) {
            return true; // Available if no location restriction
        }
        
        // Check if product is available in table's location
        $product_locations = wp_get_post_terms($product_id, 'exwoofood_loc', array('fields' => 'ids'));
        
        if (empty($product_locations)) {
            return true; // Available if no location assigned to product
        }
        
        return in_array($table_woofood_location, $product_locations);
    }
    
    /**
     * Enhance menu categories
     */
    public function enhance_menu_categories($categories, $table_id) {
        if (!$this->is_woofood_active()) {
            return $categories;
        }
        
        // Get WooFood menu categories
        $woofood_menu_cats = get_terms(array(
            'taxonomy' => 'exfood_menu',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if ($woofood_menu_cats && !is_wp_error($woofood_menu_cats)) {
            // Merge with existing categories
            $enhanced_categories = array_merge($categories, $woofood_menu_cats);
            
            // Remove duplicates and sort
            $enhanced_categories = array_unique($enhanced_categories, SORT_REGULAR);
            
            return $enhanced_categories;
        }
        
        return $categories;
    }
    
    /**
     * Add menu filters to QR menu
     */
    public function add_menu_filters($table_id) {
        $table_woofood_location = get_post_meta($table_id, '_oj_woofood_location_id', true);
        
        if (!$table_woofood_location) {
            return;
        }
        
        $location = get_term($table_woofood_location, 'exwoofood_loc');
        
        if (!$location || is_wp_error($location)) {
            return;
        }
        
        ?>
        <div class="oj-menu-filters" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #333;">
                <?php _e('Menu Filters', 'orders-jet'); ?>
            </h4>
            
            <div class="oj-filter-buttons" style="display: flex; flex-wrap: wrap; gap: 8px;">
                <button type="button" class="oj-filter-btn active" data-filter="all" 
                        style="padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer;">
                    <?php _e('All Items', 'orders-jet'); ?>
                </button>
                
                <?php
                // Get menu categories for this location
                $menu_categories = $this->get_location_menu_categories($table_woofood_location);
                
                if ($menu_categories) {
                    foreach ($menu_categories as $category) {
                        echo '<button type="button" class="oj-filter-btn" data-filter="cat-' . esc_attr($category->term_id) . '"';
                        echo ' style="padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer;">';
                        echo esc_html($category->name);
                        echo '</button>';
                    }
                }
                ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.oj-filter-btn');
            const products = document.querySelectorAll('.product-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    
                    // Filter products
                    products.forEach(product => {
                        if (filter === 'all') {
                            product.style.display = 'block';
                        } else {
                            const productCategories = product.dataset.categories || '';
                            if (productCategories.includes(filter)) {
                                product.style.display = 'block';
                            } else {
                                product.style.display = 'none';
                            }
                        }
                    });
                });
            });
        });
        </script>
        
        <style>
        .oj-filter-btn:hover {
            background: #f0f0f0 !important;
        }
        .oj-filter-btn.active {
            background: #0073aa !important;
            color: white !important;
            border-color: #0073aa !important;
        }
        </style>
        <?php
    }
    
    /**
     * Get menu categories for location
     */
    private function get_location_menu_categories($location_id) {
        // Get products in this location
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'exwoofood_loc',
                    'field' => 'term_id',
                    'terms' => $location_id
                )
            )
        ));
        
        if (empty($products)) {
            return array();
        }
        
        // Get all categories for these products
        $category_ids = array();
        foreach ($products as $product) {
            $product_cats = wp_get_post_terms($product->ID, 'product_cat', array('fields' => 'ids'));
            $category_ids = array_merge($category_ids, $product_cats);
        }
        
        $category_ids = array_unique($category_ids);
        
        if (empty($category_ids)) {
            return array();
        }
        
        return get_terms(array(
            'taxonomy' => 'product_cat',
            'include' => $category_ids,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Add menu enhancements after products
     */
    public function add_menu_enhancements($table_id) {
        ?>
        <div class="oj-menu-enhancements" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <h4><?php _e('Menu Information', 'orders-jet'); ?></h4>
            <p style="margin: 5px 0; color: #666; font-size: 14px;">
                <?php _e('Menu items are filtered based on your table location and current availability.', 'orders-jet'); ?>
            </p>
            
            <?php
            // Show location-specific menu info
            $table_woofood_location = get_post_meta($table_id, '_oj_woofood_location_id', true);
            if ($table_woofood_location) {
                $location = get_term($table_woofood_location, 'exwoofood_loc');
                if ($location && !is_wp_error($location)) {
                    echo '<p style="margin: 5px 0; color: #0073aa; font-size: 14px;">';
                    echo sprintf(__('Showing menu for: %s', 'orders-jet'), '<strong>' . esc_html($location->name) . '</strong>');
                    echo '</p>';
                }
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Add menu management metabox to products
     */
    public function add_menu_management_metabox() {
        if (!$this->is_woofood_active()) {
            return;
        }
        
        add_meta_box(
            'oj_menu_management',
            __('Orders Jet Menu Management', 'orders-jet'),
            array($this, 'render_menu_management_metabox'),
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Render menu management metabox
     */
    public function render_menu_management_metabox($post) {
        wp_nonce_field('oj_menu_management_nonce', 'oj_menu_management_nonce');
        
        $product_id = $post->ID;
        
        // Get current menu settings
        $menu_availability = get_post_meta($product_id, '_oj_menu_availability', true);
        $menu_priority = get_post_meta($product_id, '_oj_menu_priority', true);
        $menu_featured = get_post_meta($product_id, '_oj_menu_featured', true);
        
        ?>
        <div class="oj-menu-management-fields">
            <p>
                <label for="oj_menu_availability">
                    <strong><?php _e('Menu Availability', 'orders-jet'); ?></strong>
                </label>
                <select name="oj_menu_availability" id="oj_menu_availability" style="width: 100%;">
                    <option value="available" <?php selected($menu_availability, 'available'); ?>>
                        <?php _e('Available', 'orders-jet'); ?>
                    </option>
                    <option value="unavailable" <?php selected($menu_availability, 'unavailable'); ?>>
                        <?php _e('Temporarily Unavailable', 'orders-jet'); ?>
                    </option>
                    <option value="seasonal" <?php selected($menu_availability, 'seasonal'); ?>>
                        <?php _e('Seasonal Item', 'orders-jet'); ?>
                    </option>
                </select>
            </p>
            
            <p>
                <label for="oj_menu_priority">
                    <strong><?php _e('Menu Priority', 'orders-jet'); ?></strong>
                </label>
                <select name="oj_menu_priority" id="oj_menu_priority" style="width: 100%;">
                    <option value="normal" <?php selected($menu_priority, 'normal'); ?>>
                        <?php _e('Normal', 'orders-jet'); ?>
                    </option>
                    <option value="high" <?php selected($menu_priority, 'high'); ?>>
                        <?php _e('High Priority', 'orders-jet'); ?>
                    </option>
                    <option value="featured" <?php selected($menu_priority, 'featured'); ?>>
                        <?php _e('Featured Item', 'orders-jet'); ?>
                    </option>
                </select>
            </p>
            
            <p>
                <label for="oj_menu_featured">
                    <input type="checkbox" name="oj_menu_featured" id="oj_menu_featured" value="1" 
                           <?php checked($menu_featured, '1'); ?>>
                    <?php _e('Show as Featured in QR Menu', 'orders-jet'); ?>
                </label>
            </p>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <strong><?php _e('WooFood Integration', 'orders-jet'); ?></strong>
                <p style="font-size: 12px; color: #666; margin: 5px 0;">
                    <?php _e('This product is enhanced with WooFood features including locations, add-ons, and advanced options.', 'orders-jet'); ?>
                </p>
                
                <?php
                // Show WooFood locations
                $woofood_locations = wp_get_post_terms($product_id, 'exwoofood_loc');
                if ($woofood_locations && !is_wp_error($woofood_locations)) {
                    echo '<p style="font-size: 12px;">';
                    echo '<strong>' . __('Available Locations:', 'orders-jet') . '</strong><br>';
                    $location_names = array_map(function($loc) { return $loc->name; }, $woofood_locations);
                    echo implode(', ', $location_names);
                    echo '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save menu management meta
     */
    public function save_menu_management_meta($post_id) {
        // Verify nonce
        if (!isset($_POST['oj_menu_management_nonce']) || 
            !wp_verify_nonce($_POST['oj_menu_management_nonce'], 'oj_menu_management_nonce')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save menu management fields
        $fields = array(
            'oj_menu_availability' => '_oj_menu_availability',
            'oj_menu_priority' => '_oj_menu_priority',
            'oj_menu_featured' => '_oj_menu_featured'
        );
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Add menu columns to product list
     */
    public function add_menu_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Add menu columns after product name
            if ($key === 'name') {
                $new_columns['oj_menu_availability'] = __('Menu Status', 'orders-jet');
                $new_columns['oj_woofood_locations'] = __('Locations', 'orders-jet');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display menu columns
     */
    public function display_menu_columns($column, $post_id) {
        switch ($column) {
            case 'oj_menu_availability':
                $availability = get_post_meta($post_id, '_oj_menu_availability', true);
                $priority = get_post_meta($post_id, '_oj_menu_priority', true);
                $featured = get_post_meta($post_id, '_oj_menu_featured', true);
                
                $status_class = 'available';
                $status_text = __('Available', 'orders-jet');
                
                if ($availability === 'unavailable') {
                    $status_class = 'unavailable';
                    $status_text = __('Unavailable', 'orders-jet');
                } elseif ($availability === 'seasonal') {
                    $status_class = 'seasonal';
                    $status_text = __('Seasonal', 'orders-jet');
                }
                
                echo '<span class="oj-menu-status oj-status-' . esc_attr($status_class) . '">';
                echo esc_html($status_text);
                echo '</span>';
                
                if ($priority === 'featured' || $featured === '1') {
                    echo '<br><span class="oj-featured-badge">⭐ ' . __('Featured', 'orders-jet') . '</span>';
                }
                break;
                
            case 'oj_woofood_locations':
                $locations = wp_get_post_terms($post_id, 'exwoofood_loc');
                if ($locations && !is_wp_error($locations)) {
                    $location_names = array_map(function($loc) { return $loc->name; }, $locations);
                    echo implode(', ', $location_names);
                } else {
                    echo '<span style="color: #999;">' . __('All Locations', 'orders-jet') . '</span>';
                }
                break;
        }
    }
    
    /**
     * AJAX: Update menu availability
     */
    public function ajax_update_menu_availability() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'oj_menu_management') || 
            !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $availability = sanitize_text_field($_POST['availability']);
        
        update_post_meta($product_id, '_oj_menu_availability', $availability);
        
        wp_send_json_success(array(
            'message' => __('Menu availability updated', 'orders-jet'),
            'product_id' => $product_id,
            'availability' => $availability
        ));
    }
    
    /**
     * AJAX: Get menu analytics
     */
    public function ajax_get_menu_analytics() {
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        
        $analytics = $this->get_menu_analytics($location_id);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Get menu analytics
     */
    private function get_menu_analytics($location_id = 0) {
        global $wpdb;
        
        $analytics = array(
            'total_products' => 0,
            'available_products' => 0,
            'featured_products' => 0,
            'popular_categories' => array(),
            'location_stats' => array()
        );
        
        // Base query for products
        $base_query = "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
        ";
        
        // Add location filter if specified
        if ($location_id) {
            $base_query .= $wpdb->prepare("
                AND p.ID IN (
                    SELECT object_id FROM {$wpdb->term_relationships}
                    WHERE term_taxonomy_id = %d
                )
            ", $location_id);
        }
        
        $products = $wpdb->get_results($base_query);
        $analytics['total_products'] = count($products);
        
        // Count available and featured products
        foreach ($products as $product) {
            $availability = get_post_meta($product->ID, '_oj_menu_availability', true);
            $featured = get_post_meta($product->ID, '_oj_menu_featured', true);
            
            if ($availability !== 'unavailable') {
                $analytics['available_products']++;
            }
            
            if ($featured === '1') {
                $analytics['featured_products']++;
            }
        }
        
        return $analytics;
    }
    
    /**
     * Check if WooFood is active
     */
    private function is_woofood_active() {
        return class_exists('EX_WooFood');
    }
    
    /**
     * Get WooFood instance
     */
    private function get_woofood_instance() {
        if (!$this->is_woofood_active()) {
            return null;
        }
        
        // Try different methods to get WooFood instance
        if (method_exists('EX_WooFood', 'instance')) {
            return EX_WooFood::instance();
        } elseif (method_exists('EX_WooFood', 'get_instance')) {
            return EX_WooFood::get_instance();
        }
        
        // If no singleton pattern, return null
        return null;
    }
}

// Initialize the menu integration
new Orders_Jet_Menu_Integration();
