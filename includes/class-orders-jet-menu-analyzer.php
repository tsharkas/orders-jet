<?php
/**
 * WooFood Menu System Analyzer
 * 
 * Analyzes WooFood's menu management capabilities and integration points
 * 
 * @package Orders_Jet_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Menu_Analyzer {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_analyzer_menu'));
        add_action('wp_ajax_oj_run_menu_analysis', array($this, 'ajax_run_menu_analysis'));
    }
    
    /**
     * Add menu analyzer to admin
     */
    public function add_menu_analyzer_menu() {
        add_submenu_page(
            'orders-jet-woofood-analyzer',
            __('Menu System Analyzer', 'orders-jet'),
            __('Menu System', 'orders-jet'),
            'manage_options',
            'orders-jet-menu-analyzer',
            array($this, 'render_menu_analyzer_page')
        );
    }
    
    /**
     * Render menu analyzer page
     */
    public function render_menu_analyzer_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WooFood Menu System Analyzer', 'orders-jet'); ?></h1>
            
            <div class="oj-analyzer-container">
                <div class="oj-analyzer-section">
                    <h2><?php _e('Menu System Status', 'orders-jet'); ?></h2>
                    <div class="oj-status-grid">
                        <?php $this->render_menu_system_status(); ?>
                    </div>
                </div>
                
                <div class="oj-analyzer-section">
                    <h2><?php _e('Menu Analysis Tools', 'orders-jet'); ?></h2>
                    <div class="oj-tools-grid">
                        <button type="button" class="button button-primary" onclick="runMenuAnalysis('taxonomies')">
                            <?php _e('Analyze Menu Taxonomies', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runMenuAnalysis('categories')">
                            <?php _e('Analyze Menu Categories', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runMenuAnalysis('products')">
                            <?php _e('Analyze Menu Products', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runMenuAnalysis('display')">
                            <?php _e('Analyze Menu Display', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="runMenuAnalysis('full')">
                            <?php _e('Run Full Menu Analysis', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="oj-analyzer-section">
                    <h2><?php _e('Menu Analysis Results', 'orders-jet'); ?></h2>
                    <div id="oj-menu-analysis-results" class="oj-results-container">
                        <p><?php _e('Click an analysis button above to see results here.', 'orders-jet'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .oj-analyzer-container {
            max-width: 1200px;
        }
        .oj-analyzer-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        .oj-status-grid, .oj-tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .oj-status-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .oj-status-item.active {
            border-color: #00a32a;
            background: #f0f9f0;
        }
        .oj-status-item.inactive {
            border-color: #d63638;
            background: #f9f0f0;
        }
        .oj-results-container {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            padding: 20px;
            border-radius: 4px;
            min-height: 300px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
        .oj-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        </style>
        
        <script>
        function runMenuAnalysis(type) {
            const resultsContainer = document.getElementById('oj-menu-analysis-results');
            resultsContainer.innerHTML = '<div class="oj-loading">Running ' + type + ' menu analysis...</div>';
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_run_menu_analysis',
                    analysis_type: type,
                    nonce: '<?php echo wp_create_nonce('oj_menu_analysis'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultsContainer.innerHTML = response.data.results;
                    } else {
                        resultsContainer.innerHTML = 'Error: ' + response.data.message;
                    }
                },
                error: function() {
                    resultsContainer.innerHTML = 'AJAX Error: Could not complete menu analysis.';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render menu system status
     */
    private function render_menu_system_status() {
        $status_items = array(
            'WooCommerce Products' => array(
                'active' => class_exists('WooCommerce'),
                'count' => class_exists('WooCommerce') ? wp_count_posts('product')->publish : 0
            ),
            'Product Categories' => array(
                'active' => taxonomy_exists('product_cat'),
                'count' => wp_count_terms('product_cat')
            ),
            'WooFood Menu System' => array(
                'active' => class_exists('EX_WooFood'),
                'count' => class_exists('EX_WooFood') ? 'Detected' : 'Not Available'
            ),
            'WooFood Locations' => array(
                'active' => taxonomy_exists('exwoofood_loc'),
                'count' => taxonomy_exists('exwoofood_loc') ? wp_count_terms('exwoofood_loc') : 0
            )
        );
        
        foreach ($status_items as $name => $status) {
            $class = $status['active'] ? 'active' : 'inactive';
            $icon = $status['active'] ? '✅' : '❌';
            
            echo "<div class='oj-status-item {$class}'>";
            echo "<strong>{$icon} {$name}</strong><br>";
            echo "Count/Status: {$status['count']}";
            echo "</div>";
        }
    }
    
    /**
     * AJAX handler for menu analysis
     */
    public function ajax_run_menu_analysis() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'oj_menu_analysis')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $analysis_type = sanitize_text_field($_POST['analysis_type']);
        $results = '';
        
        switch ($analysis_type) {
            case 'taxonomies':
                $results = $this->analyze_menu_taxonomies();
                break;
            case 'categories':
                $results = $this->analyze_menu_categories();
                break;
            case 'products':
                $results = $this->analyze_menu_products();
                break;
            case 'display':
                $results = $this->analyze_menu_display();
                break;
            case 'full':
                $results = $this->run_full_menu_analysis();
                break;
            default:
                $results = 'Invalid menu analysis type';
        }
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * Analyze menu taxonomies
     */
    public function analyze_menu_taxonomies() {
        global $wpdb;
        
        $output = "=== WOOFOOD MENU TAXONOMIES ANALYSIS ===\n\n";
        
        // 1. Standard WooCommerce taxonomies
        $output .= "1. WOOCOMMERCE TAXONOMIES:\n";
        $wc_taxonomies = array('product_cat', 'product_tag', 'product_type');
        
        foreach ($wc_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $count = wp_count_terms($taxonomy);
                $output .= "   ✅ {$taxonomy}: {$count} terms\n";
                
                // Get sample terms
                $terms = get_terms(array('taxonomy' => $taxonomy, 'number' => 5));
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $output .= "      - {$term->name} (ID: {$term->term_id})\n";
                    }
                }
            } else {
                $output .= "   ❌ {$taxonomy}: Not found\n";
            }
        }
        
        // 2. WooFood specific taxonomies
        $output .= "\n2. WOOFOOD TAXONOMIES:\n";
        $woofood_taxonomies = array('exwoofood_loc', 'exfood_menu', 'exfood_cat');
        
        foreach ($woofood_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $count = wp_count_terms($taxonomy);
                $output .= "   ✅ {$taxonomy}: {$count} terms\n";
                
                // Get sample terms
                $terms = get_terms(array('taxonomy' => $taxonomy, 'number' => 5));
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $output .= "      - {$term->name} (ID: {$term->term_id})\n";
                        if ($term->description) {
                            $output .= "        Description: " . substr($term->description, 0, 50) . "...\n";
                        }
                    }
                }
            } else {
                $output .= "   ❌ {$taxonomy}: Not found\n";
            }
        }
        
        // 3. Custom taxonomies related to food/menu
        $output .= "\n3. CUSTOM MENU TAXONOMIES:\n";
        $all_taxonomies = get_taxonomies(array('public' => true), 'objects');
        $menu_related = array();
        
        foreach ($all_taxonomies as $taxonomy) {
            if (strpos($taxonomy->name, 'menu') !== false || 
                strpos($taxonomy->name, 'food') !== false ||
                strpos($taxonomy->name, 'restaurant') !== false) {
                $menu_related[] = $taxonomy;
            }
        }
        
        if ($menu_related) {
            foreach ($menu_related as $taxonomy) {
                $count = wp_count_terms($taxonomy->name);
                $output .= "   - {$taxonomy->name}: {$count} terms\n";
                $output .= "     Label: {$taxonomy->label}\n";
                if ($taxonomy->description) {
                    $output .= "     Description: {$taxonomy->description}\n";
                }
            }
        } else {
            $output .= "   No custom menu-related taxonomies found\n";
        }
        
        return $output;
    }
    
    /**
     * Analyze menu categories
     */
    public function analyze_menu_categories() {
        $output = "=== WOOFOOD MENU CATEGORIES ANALYSIS ===\n\n";
        
        // 1. Product categories analysis
        $output .= "1. PRODUCT CATEGORIES STRUCTURE:\n";
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        if ($categories && !is_wp_error($categories)) {
            $output .= "   Total Categories: " . count($categories) . "\n\n";
            
            foreach ($categories as $category) {
                $output .= "   📁 {$category->name} (ID: {$category->term_id})\n";
                $output .= "      Slug: {$category->slug}\n";
                $output .= "      Count: {$category->count} products\n";
                
                if ($category->parent) {
                    $parent = get_term($category->parent, 'product_cat');
                    if ($parent && !is_wp_error($parent)) {
                        $output .= "      Parent: {$parent->name}\n";
                    }
                }
                
                if ($category->description) {
                    $output .= "      Description: " . substr($category->description, 0, 100) . "...\n";
                }
                
                // Check for category meta
                $category_meta = get_term_meta($category->term_id);
                if ($category_meta) {
                    $output .= "      Meta Fields:\n";
                    foreach ($category_meta as $key => $value) {
                        if (strpos($key, 'exwf') !== false || strpos($key, 'woofood') !== false) {
                            $output .= "        - {$key}: " . maybe_serialize($value[0]) . "\n";
                        }
                    }
                }
                
                $output .= "\n";
            }
        } else {
            $output .= "   No product categories found\n";
        }
        
        // 2. WooFood menu categories
        if (taxonomy_exists('exfood_menu')) {
            $output .= "2. WOOFOOD MENU CATEGORIES:\n";
            $menu_cats = get_terms(array(
                'taxonomy' => 'exfood_menu',
                'hide_empty' => false
            ));
            
            if ($menu_cats && !is_wp_error($menu_cats)) {
                foreach ($menu_cats as $menu_cat) {
                    $output .= "   🍽️ {$menu_cat->name} (ID: {$menu_cat->term_id})\n";
                    $output .= "      Slug: {$menu_cat->slug}\n";
                    $output .= "      Count: {$menu_cat->count} items\n";
                    
                    // Check for WooFood specific meta
                    $menu_meta = get_term_meta($menu_cat->term_id);
                    if ($menu_meta) {
                        foreach ($menu_meta as $key => $value) {
                            $output .= "      Meta: {$key} = " . substr(maybe_serialize($value[0]), 0, 50) . "\n";
                        }
                    }
                    $output .= "\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Analyze menu products
     */
    public function analyze_menu_products() {
        $output = "=== WOOFOOD MENU PRODUCTS ANALYSIS ===\n\n";
        
        // 1. Product types analysis
        $output .= "1. PRODUCT TYPES:\n";
        $product_types = wp_count_posts('product');
        $output .= "   Total Products: {$product_types->publish} published\n";
        $output .= "   Draft Products: {$product_types->draft}\n";
        $output .= "   Private Products: {$product_types->private}\n\n";
        
        // 2. WooFood enhanced products
        $output .= "2. WOOFOOD ENHANCED PRODUCTS:\n";
        global $wpdb;
        
        $woofood_products = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_key LIKE '%exwf%' OR pm.meta_key LIKE '%exwo%')
        ");
        
        $output .= "   Products with WooFood data: {$woofood_products}\n";
        
        // 3. Sample WooFood product analysis
        $sample_products = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_key LIKE '%exwf%' OR pm.meta_key LIKE '%exwo%')
            LIMIT 5
        ");
        
        if ($sample_products) {
            $output .= "\n3. SAMPLE WOOFOOD PRODUCTS:\n";
            foreach ($sample_products as $product) {
                $output .= "   🍕 {$product->post_title} (ID: {$product->ID})\n";
                
                // Get WooFood meta for this product
                $product_meta = get_post_meta($product->ID);
                foreach ($product_meta as $key => $value) {
                    if (strpos($key, 'exwf') !== false || strpos($key, 'exwo') !== false) {
                        $output .= "      {$key}: " . substr(maybe_serialize($value[0]), 0, 100) . "\n";
                    }
                }
                
                // Get product categories
                $categories = wp_get_post_terms($product->ID, 'product_cat');
                if ($categories) {
                    $cat_names = array_map(function($cat) { return $cat->name; }, $categories);
                    $output .= "      Categories: " . implode(', ', $cat_names) . "\n";
                }
                
                // Get WooFood locations
                $locations = wp_get_post_terms($product->ID, 'exwoofood_loc');
                if ($locations) {
                    $loc_names = array_map(function($loc) { return $loc->name; }, $locations);
                    $output .= "      Locations: " . implode(', ', $loc_names) . "\n";
                }
                
                $output .= "\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Analyze menu display options
     */
    public function analyze_menu_display() {
        $output = "=== WOOFOOD MENU DISPLAY ANALYSIS ===\n\n";
        
        // 1. WooFood shortcodes
        $output .= "1. WOOFOOD SHORTCODES:\n";
        if (function_exists('exwoofood_shortcode_list')) {
            $output .= "   ✅ exwoofood_shortcode_list - Menu list display\n";
        }
        if (function_exists('exwoofood_shortcode_grid')) {
            $output .= "   ✅ exwoofood_shortcode_grid - Menu grid display\n";
        }
        if (function_exists('exwoofood_shortcode_menu_group')) {
            $output .= "   ✅ exwoofood_shortcode_menu_group - Menu group display\n";
        }
        
        // 2. Menu display options
        $output .= "\n2. MENU DISPLAY OPTIONS:\n";
        $woofood_options = get_option('exwoofood_options', array());
        if ($woofood_options) {
            $output .= "   WooFood Options Found:\n";
            foreach ($woofood_options as $key => $value) {
                if (strpos($key, 'menu') !== false || strpos($key, 'display') !== false) {
                    $output .= "      {$key}: " . substr(maybe_serialize($value), 0, 50) . "\n";
                }
            }
        }
        
        // 3. Menu styling options
        $output .= "\n3. MENU STYLING:\n";
        $custom_css = get_option('exwoofood_custom_css', '');
        if ($custom_css) {
            $output .= "   Custom CSS found: " . strlen($custom_css) . " characters\n";
        } else {
            $output .= "   No custom CSS found\n";
        }
        
        return $output;
    }
    
    /**
     * Run full menu analysis
     */
    public function run_full_menu_analysis() {
        $output = "=== COMPLETE WOOFOOD MENU SYSTEM ANALYSIS ===\n";
        $output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $output .= $this->analyze_menu_taxonomies() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->analyze_menu_categories() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->analyze_menu_products() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->analyze_menu_display() . "\n\n";
        
        // Integration recommendations
        $output .= str_repeat("=", 60) . "\n";
        $output .= "=== MENU INTEGRATION RECOMMENDATIONS ===\n\n";
        
        if (class_exists('EX_WooFood')) {
            $output .= "✅ WooFood menu system is active and ready for integration\n";
            $output .= "📋 Recommended Integration Steps:\n";
            $output .= "   1. Integrate WooFood menu categories with table system\n";
            $output .= "   2. Enhance QR menu with WooFood display options\n";
            $output .= "   3. Add location-based menu management\n";
            $output .= "   4. Implement real-time menu updates\n";
            $output .= "   5. Create staff menu management interface\n";
        } else {
            $output .= "❌ WooFood not detected\n";
            $output .= "📋 Required Actions:\n";
            $output .= "   1. Install and activate WooFood plugin\n";
            $output .= "   2. Configure WooFood menu settings\n";
            $output .= "   3. Re-run this analysis\n";
        }
        
        return $output;
    }
}

// Initialize the menu analyzer
new Orders_Jet_Menu_Analyzer();
