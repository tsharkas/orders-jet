<?php
/**
 * WooFood Analyzer Class
 * 
 * Analyzes WooFood plugin structure, database schema, hooks, and integration points
 * 
 * @package Orders_Jet_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_WooFood_Analyzer {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_analyzer_menu'));
        add_action('wp_ajax_oj_run_woofood_analysis', array($this, 'ajax_run_analysis'));
    }
    
    /**
     * Add analyzer menu to admin
     */
    public function add_analyzer_menu() {
        add_submenu_page(
            'orders-jet-manager',
            __('WooFood Analyzer', 'orders-jet'),
            __('WooFood Analyzer', 'orders-jet'),
            'manage_options',
            'orders-jet-woofood-analyzer',
            array($this, 'render_analyzer_page')
        );
    }
    
    /**
     * Render analyzer page
     */
    public function render_analyzer_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WooFood Structure Analyzer', 'orders-jet'); ?></h1>
            
            <div class="oj-analyzer-container">
                <div class="oj-analyzer-section">
                    <h2><?php _e('System Status', 'orders-jet'); ?></h2>
                    <div class="oj-status-grid">
                        <?php $this->render_system_status(); ?>
                    </div>
                </div>
                
                <div class="oj-analyzer-section">
                    <h2><?php _e('Analysis Tools', 'orders-jet'); ?></h2>
                    <div class="oj-tools-grid">
                        <button type="button" class="button button-primary" onclick="runAnalysis('database')">
                            <?php _e('Analyze Database Structure', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runAnalysis('hooks')">
                            <?php _e('Detect Hooks & Filters', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runAnalysis('classes')">
                            <?php _e('Analyze Classes & Methods', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="runAnalysis('api')">
                            <?php _e('Test API Endpoints', 'orders-jet'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="runAnalysis('full')">
                            <?php _e('Run Full Analysis', 'orders-jet'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="oj-analyzer-section">
                    <h2><?php _e('Analysis Results', 'orders-jet'); ?></h2>
                    <div id="oj-analysis-results" class="oj-results-container">
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
        function runAnalysis(type) {
            const resultsContainer = document.getElementById('oj-analysis-results');
            resultsContainer.innerHTML = '<div class="oj-loading">Running ' + type + ' analysis...</div>';
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_run_woofood_analysis',
                    analysis_type: type,
                    nonce: '<?php echo wp_create_nonce('oj_woofood_analysis'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultsContainer.innerHTML = response.data.results;
                    } else {
                        resultsContainer.innerHTML = 'Error: ' + response.data.message;
                    }
                },
                error: function() {
                    resultsContainer.innerHTML = 'AJAX Error: Could not complete analysis.';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        $status_items = array(
            'WordPress' => array(
                'active' => true,
                'version' => get_bloginfo('version')
            ),
            'WooCommerce' => array(
                'active' => class_exists('WooCommerce'),
                'version' => class_exists('WooCommerce') ? WC()->version : 'Not installed'
            ),
            'WooFood' => array(
                'active' => class_exists('EX_WooFood'),
                'version' => class_exists('EX_WooFood') ? 'Detected' : 'Not installed'
            ),
            'Orders Jet' => array(
                'active' => class_exists('Orders_Jet_Integration'),
                'version' => defined('ORDERS_JET_VERSION') ? ORDERS_JET_VERSION : 'Unknown'
            )
        );
        
        foreach ($status_items as $name => $status) {
            $class = $status['active'] ? 'active' : 'inactive';
            $icon = $status['active'] ? '✅' : '❌';
            
            echo "<div class='oj-status-item {$class}'>";
            echo "<strong>{$icon} {$name}</strong><br>";
            echo "Version: {$status['version']}";
            echo "</div>";
        }
    }
    
    /**
     * AJAX handler for running analysis
     */
    public function ajax_run_analysis() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'oj_woofood_analysis')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $analysis_type = sanitize_text_field($_POST['analysis_type']);
        $results = '';
        
        switch ($analysis_type) {
            case 'database':
                $results = $this->analyze_database_structure();
                break;
            case 'hooks':
                $results = $this->detect_hooks_and_filters();
                break;
            case 'classes':
                $results = $this->analyze_classes_and_methods();
                break;
            case 'api':
                $results = $this->test_api_endpoints();
                break;
            case 'full':
                $results = $this->run_full_analysis();
                break;
            default:
                $results = 'Invalid analysis type';
        }
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * Analyze database structure
     */
    public function analyze_database_structure() {
        global $wpdb;
        
        $output = "=== WOOFOOD DATABASE STRUCTURE ANALYSIS ===\n\n";
        
        // 1. Find WooFood custom post types
        $output .= "1. CUSTOM POST TYPES:\n";
        $post_types = $wpdb->get_results("
            SELECT DISTINCT post_type, COUNT(*) as count
            FROM {$wpdb->posts} 
            WHERE post_type LIKE '%exwf%' OR post_type LIKE '%woofood%' OR post_type LIKE '%food%'
            GROUP BY post_type
            ORDER BY count DESC
        ");
        
        if ($post_types) {
            foreach ($post_types as $pt) {
                $output .= "   - {$pt->post_type} ({$pt->count} posts)\n";
            }
        } else {
            $output .= "   No WooFood-specific post types found\n";
        }
        
        // 2. Find WooFood meta fields
        $output .= "\n2. META FIELDS:\n";
        $meta_fields = $wpdb->get_results("
            SELECT DISTINCT meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '%exwf%' OR meta_key LIKE '%exwo%' OR meta_key LIKE '%woofood%'
            GROUP BY meta_key
            ORDER BY count DESC
            LIMIT 50
        ");
        
        if ($meta_fields) {
            foreach ($meta_fields as $field) {
                $output .= "   - {$field->meta_key} ({$field->count} entries)\n";
            }
        } else {
            $output .= "   No WooFood-specific meta fields found\n";
        }
        
        // 3. Find WooFood options
        $output .= "\n3. WORDPRESS OPTIONS:\n";
        $options = $wpdb->get_results("
            SELECT option_name, CHAR_LENGTH(option_value) as value_length
            FROM {$wpdb->options} 
            WHERE option_name LIKE '%exwf%' OR option_name LIKE '%woofood%'
            ORDER BY option_name
        ");
        
        if ($options) {
            foreach ($options as $option) {
                $output .= "   - {$option->option_name} ({$option->value_length} chars)\n";
            }
        } else {
            $output .= "   No WooFood-specific options found\n";
        }
        
        // 4. Analyze WooCommerce product meta for WooFood
        $output .= "\n4. WOOCOMMERCE PRODUCT ANALYSIS:\n";
        $product_meta = $wpdb->get_results("
            SELECT pm.meta_key, COUNT(*) as count, 
                   AVG(CHAR_LENGTH(pm.meta_value)) as avg_length
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'product' 
            AND (pm.meta_key LIKE '%exwf%' OR pm.meta_key LIKE '%exwo%' OR pm.meta_key LIKE '%food%')
            GROUP BY pm.meta_key
            ORDER BY count DESC
        ");
        
        if ($product_meta) {
            foreach ($product_meta as $meta) {
                $output .= "   - {$meta->meta_key}: {$meta->count} products (avg {$meta->avg_length} chars)\n";
            }
        } else {
            $output .= "   No WooFood product meta found\n";
        }
        
        // 5. Analyze WooCommerce order meta for WooFood
        $output .= "\n5. WOOCOMMERCE ORDER ANALYSIS:\n";
        $order_meta = $wpdb->get_results("
            SELECT pm.meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order' 
            AND (pm.meta_key LIKE '%exwf%' OR pm.meta_key LIKE '%exwo%' OR pm.meta_key LIKE '%delivery%' OR pm.meta_key LIKE '%pickup%')
            GROUP BY pm.meta_key
            ORDER BY count DESC
        ");
        
        if ($order_meta) {
            foreach ($order_meta as $meta) {
                $output .= "   - {$meta->meta_key}: {$meta->count} orders\n";
            }
        } else {
            $output .= "   No WooFood order meta found\n";
        }
        
        // 6. Check for custom tables
        $output .= "\n6. CUSTOM TABLES:\n";
        $custom_tables = $wpdb->get_results("
            SHOW TABLES LIKE '%woofood%' 
            UNION 
            SHOW TABLES LIKE '%exwf%'
        ");
        
        if ($custom_tables) {
            foreach ($custom_tables as $table) {
                $table_name = array_values((array)$table)[0];
                $output .= "   - {$table_name}\n";
            }
        } else {
            $output .= "   No WooFood custom tables found\n";
        }
        
        return $output;
    }
    
    /**
     * Detect hooks and filters
     */
    public function detect_hooks_and_filters() {
        global $wp_filter;
        
        $output = "=== WOOFOOD HOOKS AND FILTERS ANALYSIS ===\n\n";
        
        $woofood_hooks = array();
        $woofood_filters = array();
        
        // Search through all registered hooks
        foreach ($wp_filter as $hook_name => $hook_data) {
            if (strpos($hook_name, 'exwf') !== false || 
                strpos($hook_name, 'woofood') !== false ||
                strpos($hook_name, 'food') !== false) {
                
                $callbacks = array();
                foreach ($hook_data->callbacks as $priority => $priority_callbacks) {
                    foreach ($priority_callbacks as $callback) {
                        if (is_array($callback['function'])) {
                            if (is_object($callback['function'][0])) {
                                $callbacks[] = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                            } else {
                                $callbacks[] = $callback['function'][0] . '::' . $callback['function'][1];
                            }
                        } else {
                            $callbacks[] = $callback['function'];
                        }
                    }
                }
                
                if (strpos($hook_name, 'filter') !== false || strpos($hook_name, 'get_') !== false) {
                    $woofood_filters[$hook_name] = $callbacks;
                } else {
                    $woofood_hooks[$hook_name] = $callbacks;
                }
            }
        }
        
        $output .= "1. ACTION HOOKS:\n";
        if (!empty($woofood_hooks)) {
            foreach ($woofood_hooks as $hook => $callbacks) {
                $output .= "   - {$hook}\n";
                foreach ($callbacks as $callback) {
                    $output .= "     └─ {$callback}\n";
                }
            }
        } else {
            $output .= "   No WooFood action hooks detected\n";
        }
        
        $output .= "\n2. FILTER HOOKS:\n";
        if (!empty($woofood_filters)) {
            foreach ($woofood_filters as $hook => $callbacks) {
                $output .= "   - {$hook}\n";
                foreach ($callbacks as $callback) {
                    $output .= "     └─ {$callback}\n";
                }
            }
        } else {
            $output .= "   No WooFood filter hooks detected\n";
        }
        
        // Check for common WooCommerce hooks that WooFood might use
        $output .= "\n3. WOOCOMMERCE INTEGRATION HOOKS:\n";
        $wc_hooks = array(
            'woocommerce_checkout_process',
            'woocommerce_checkout_order_processed',
            'woocommerce_order_status_changed',
            'woocommerce_add_to_cart',
            'woocommerce_cart_item_name',
            'woocommerce_order_item_meta_end'
        );
        
        foreach ($wc_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $woofood_callbacks = array();
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $callback_name = '';
                        if (is_array($callback['function'])) {
                            if (is_object($callback['function'][0])) {
                                $class_name = get_class($callback['function'][0]);
                                if (strpos($class_name, 'WooFood') !== false || 
                                    strpos($class_name, 'EXWF') !== false ||
                                    strpos($class_name, 'EX_') !== false) {
                                    $callback_name = $class_name . '::' . $callback['function'][1];
                                }
                            }
                        }
                        if ($callback_name) {
                            $woofood_callbacks[] = $callback_name;
                        }
                    }
                }
                if (!empty($woofood_callbacks)) {
                    $output .= "   - {$hook}:\n";
                    foreach ($woofood_callbacks as $callback) {
                        $output .= "     └─ {$callback}\n";
                    }
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Analyze classes and methods
     */
    public function analyze_classes_and_methods() {
        $output = "=== WOOFOOD CLASSES AND METHODS ANALYSIS ===\n\n";
        
        // Get all declared classes
        $declared_classes = get_declared_classes();
        $woofood_classes = array();
        
        foreach ($declared_classes as $class) {
            if (strpos($class, 'WooFood') !== false || 
                strpos($class, 'EXWF') !== false ||
                strpos($class, 'EX_WooFood') !== false ||
                strpos($class, 'EX_') !== false) {
                $woofood_classes[] = $class;
            }
        }
        
        $output .= "1. DETECTED WOOFOOD CLASSES:\n";
        if (!empty($woofood_classes)) {
            foreach ($woofood_classes as $class) {
                $output .= "   - {$class}\n";
                
                try {
                    $reflection = new ReflectionClass($class);
                    
                    // Public methods
                    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                    if (!empty($methods)) {
                        $output .= "     Public Methods:\n";
                        foreach ($methods as $method) {
                            if (!$method->isConstructor() && !$method->isDestructor()) {
                                $output .= "       └─ {$method->getName()}()\n";
                            }
                        }
                    }
                    
                    // Properties
                    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
                    if (!empty($properties)) {
                        $output .= "     Public Properties:\n";
                        foreach ($properties as $property) {
                            $output .= "       └─ \${$property->getName()}\n";
                        }
                    }
                    
                    // Constants
                    $constants = $reflection->getConstants();
                    if (!empty($constants)) {
                        $output .= "     Constants:\n";
                        foreach ($constants as $name => $value) {
                            $output .= "       └─ {$name} = " . var_export($value, true) . "\n";
                        }
                    }
                    
                } catch (Exception $e) {
                    $output .= "     Error analyzing class: " . $e->getMessage() . "\n";
                }
                
                $output .= "\n";
            }
        } else {
            $output .= "   No WooFood classes detected\n";
        }
        
        // Check for specific WooFood main class
        $output .= "2. MAIN WOOFOOD CLASS ANALYSIS:\n";
        if (class_exists('EX_WooFood')) {
            $output .= "   EX_WooFood class found!\n";
            
            try {
                $reflection = new ReflectionClass('EX_WooFood');
                $output .= "   File: " . $reflection->getFileName() . "\n";
                
                // Check different singleton patterns
                if ($reflection->hasMethod('instance')) {
                    $output .= "   Pattern: Singleton (instance method)\n";
                } elseif ($reflection->hasMethod('get_instance')) {
                    $output .= "   Pattern: Singleton (get_instance method)\n";
                } else {
                    $output .= "   Pattern: Regular class (no singleton)\n";
                }
                
                // Key methods
                $key_methods = array('init', 'load', 'setup', 'register_hooks', 'get_locations', 'process_order', '__construct');
                $output .= "   Available key methods:\n";
                foreach ($key_methods as $method) {
                    if ($reflection->hasMethod($method)) {
                        $output .= "     ✅ {$method}()\n";
                    } else {
                        $output .= "     ❌ {$method}()\n";
                    }
                }
                
                // List all public methods
                $public_methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                $output .= "   All public methods (" . count($public_methods) . " total):\n";
                foreach (array_slice($public_methods, 0, 15) as $method) {
                    if (!$method->isConstructor() && !$method->isDestructor()) {
                        $output .= "     - " . $method->getName() . "()\n";
                    }
                }
                if (count($public_methods) > 15) {
                    $output .= "     ... and " . (count($public_methods) - 15) . " more methods\n";
                }
                
            } catch (Exception $e) {
                $output .= "   Error: " . $e->getMessage() . "\n";
            }
        } else {
            $output .= "   EX_WooFood class not found\n";
        }
        
        // Check for global functions
        $output .= "\n3. WOOFOOD GLOBAL FUNCTIONS:\n";
        $functions = get_defined_functions()['user'];
        $woofood_functions = array();
        
        foreach ($functions as $function) {
            if (strpos($function, 'exwf') !== false || 
                strpos($function, 'woofood') !== false ||
                strpos($function, 'food_') !== false) {
                $woofood_functions[] = $function;
            }
        }
        
        if (!empty($woofood_functions)) {
            foreach ($woofood_functions as $function) {
                $output .= "   - {$function}()\n";
            }
        } else {
            $output .= "   No WooFood global functions detected\n";
        }
        
        return $output;
    }
    
    /**
     * Test API endpoints
     */
    public function test_api_endpoints() {
        $output = "=== WOOFOOD API ENDPOINTS ANALYSIS ===\n\n";
        
        // Test common REST API endpoints
        $endpoints_to_test = array(
            '/wp-json/exwf/v1/locations',
            '/wp-json/exwf/v1/menu',
            '/wp-json/exwf/v1/orders',
            '/wp-json/woofood/v1/locations',
            '/wp-json/woofood/v1/menu',
            '/wp-json/wc/v3/products?woofood=true',
        );
        
        $output .= "1. REST API ENDPOINT TESTING:\n";
        
        foreach ($endpoints_to_test as $endpoint) {
            $url = home_url($endpoint);
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                $output .= "   ❌ {$endpoint}: Error - " . $response->get_error_message() . "\n";
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $output .= "   " . ($status_code == 200 ? "✅" : "❌") . " {$endpoint}: HTTP {$status_code}\n";
                
                if ($status_code == 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    if ($data) {
                        $output .= "       └─ Response: " . substr($body, 0, 100) . "...\n";
                    }
                }
            }
        }
        
        // Check WooCommerce REST API extensions
        $output .= "\n2. WOOCOMMERCE API EXTENSIONS:\n";
        
        // Check if WooFood extends WC REST API
        global $wp_rest_server;
        if ($wp_rest_server) {
            $routes = $wp_rest_server->get_routes();
            $woofood_routes = array();
            
            foreach ($routes as $route => $handlers) {
                if (strpos($route, 'exwf') !== false || 
                    strpos($route, 'woofood') !== false ||
                    strpos($route, 'food') !== false) {
                    $woofood_routes[] = $route;
                }
            }
            
            if (!empty($woofood_routes)) {
                foreach ($woofood_routes as $route) {
                    $output .= "   - {$route}\n";
                }
            } else {
                $output .= "   No WooFood-specific routes found\n";
            }
        }
        
        // Check AJAX endpoints
        $output .= "\n3. AJAX ENDPOINTS:\n";
        $ajax_actions = array(
            'exwf_get_locations',
            'exwf_update_order',
            'woofood_get_menu',
            'woofood_process_order'
        );
        
        foreach ($ajax_actions as $action) {
            // Check if action is registered
            if (has_action("wp_ajax_{$action}") || has_action("wp_ajax_nopriv_{$action}")) {
                $output .= "   ✅ {$action}: Registered\n";
            } else {
                $output .= "   ❌ {$action}: Not registered\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Run full analysis
     */
    public function run_full_analysis() {
        $output = "=== COMPLETE WOOFOOD ANALYSIS REPORT ===\n";
        $output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $output .= $this->analyze_database_structure() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->detect_hooks_and_filters() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->analyze_classes_and_methods() . "\n\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->test_api_endpoints() . "\n\n";
        
        // Summary and recommendations
        $output .= str_repeat("=", 60) . "\n";
        $output .= "=== INTEGRATION RECOMMENDATIONS ===\n\n";
        
        if (class_exists('EX_WooFood')) {
            $output .= "✅ WooFood is active and ready for integration\n";
            $output .= "📋 Next Steps:\n";
            $output .= "   1. Implement WooFood location integration\n";
            $output .= "   2. Hook into WooFood order processing\n";
            $output .= "   3. Extend WooFood delivery/pickup with table service\n";
            $output .= "   4. Create unified order management system\n";
        } else {
            $output .= "❌ WooFood not detected\n";
            $output .= "📋 Required Actions:\n";
            $output .= "   1. Install and activate WooFood plugin\n";
            $output .= "   2. Configure WooFood settings\n";
            $output .= "   3. Re-run this analysis\n";
        }
        
        return $output;
    }
}

// Initialize the analyzer
new Orders_Jet_WooFood_Analyzer();
