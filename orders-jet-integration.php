<?php
/**
 * Plugin Name: Orders Jet Integration
 * Plugin URI: https://ordersjet.site
 * Description: Custom integration for Orders Jet restaurant management system. Extends WooCommerce Food plugin with table management, QR code menus, and contactless ordering.
 * Version: 1.0.0
 * Author: Orders Jet
 * Author URI: https://ordersjet.site
 * Text Domain: orders-jet
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ORDERS_JET_VERSION', '1.0.0');
define('ORDERS_JET_PLUGIN_FILE', __FILE__);
define('ORDERS_JET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ORDERS_JET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ORDERS_JET_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Orders Jet Integration Class
 */
class Orders_Jet_Integration {
    
    /**
     * Single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main Orders Jet Integration Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Flush rewrite rules on plugin update
        add_action('upgrader_process_complete', array($this, 'maybe_flush_rewrite_rules'), 10, 2);
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('orders-jet', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Add rewrite rules for table menu and invoice
        $this->add_rewrite_rules();
        
        // Add admin menu for rewrite rules management
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        if (!class_exists('EX_WooFood')) {
            add_action('admin_notices', array($this, 'woo_food_missing_notice'));
            return;
        }
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-user-roles.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-admin-dashboard.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-rest-api.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-table-management.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-ajax-handlers.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-shortcodes.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-helpers.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-assets.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-woofood-analyzer.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-woofood-integration.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-menu-analyzer.php';
        include_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-menu-integration.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize user roles
        new Orders_Jet_User_Roles();
        
        // Initialize admin dashboard
        new Orders_Jet_Admin_Dashboard();
        
        // Initialize table management
        new Orders_Jet_Table_Management();
        
        // Initialize AJAX handlers
        new Orders_Jet_AJAX_Handlers();
        
        // Initialize shortcodes
        new Orders_Jet_Shortcodes();
        
        // Initialize assets manager
        new Orders_Jet_Assets();
    }
    
    /**
     * Add rewrite rules for table menu and invoice
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^table-menu/?$', 'index.php?oj_table_menu=1', 'top');
        add_rewrite_rule('^table-invoice/?$', 'index.php?oj_table_invoice=1', 'top');
        add_rewrite_tag('%oj_table_menu%', '([^&]+)');
        add_rewrite_tag('%oj_table_invoice%', '([^&]+)');
        
        // Handle template redirect
        add_action('template_redirect', array($this, 'handle_template_requests'));
        
        // Also try direct URL handling as backup
        add_action('init', array($this, 'handle_direct_urls'));
    }
    
    /**
     * Handle direct URLs as backup
     */
    public function handle_direct_urls() {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check for table-invoice URL
        if (strpos($request_uri, '/table-invoice/') !== false) {
            $this->display_table_invoice();
            exit;
        }
        
        // Check for table-menu URL
        if (strpos($request_uri, '/table-menu/') !== false) {
            $this->display_table_menu();
            exit;
        }
    }
    
    /**
     * Handle template requests
     */
    public function handle_template_requests() {
        if (get_query_var('oj_table_menu')) {
            // Include the QR menu template
            $this->display_table_menu();
            exit;
        } elseif (get_query_var('oj_table_invoice')) {
            // Include the invoice template
            $this->display_table_invoice();
            exit;
        }
    }
    
    /**
     * Display table menu
     */
    public function display_table_menu() {
        // Get table number from URL parameter
        $table_number = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';
        
        if (empty($table_number)) {
            wp_die(__('Table number is required.', 'orders-jet'), __('Error', 'orders-jet'), array('response' => 400));
        }
        
        // Get table ID
        $table_id = oj_get_table_id_by_number($table_number);
        
        if (!$table_id) {
            wp_die(__('Invalid table number. Please check your QR code.', 'orders-jet'), __('Error', 'orders-jet'), array('response' => 404));
        }
        
        // Get table information
        $table_capacity = get_post_meta($table_id, '_oj_table_capacity', true);
        $table_status = get_post_meta($table_id, '_oj_table_status', true);
        $table_location = get_post_meta($table_id, '_oj_table_location', true);
        
        // Check if table is available
        if ($table_status !== 'available' && $table_status !== 'occupied') {
            wp_die(__('This table is currently not available for ordering.', 'orders-jet'), __('Table Not Available', 'orders-jet'), array('response' => 403));
        }
        
        // Get current order for this table
        $current_order = oj_get_current_table_order($table_number);
        
        // Get menu items (WooCommerce products)
        $menu_items = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        // Get product categories for filtering
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        // Enqueue required assets manually
        $this->enqueue_table_menu_assets($table_number);
        
        // Set page title
        add_filter('wp_title', function($title) use ($table_number) {
            return sprintf(__('Table %s Menu', 'orders-jet'), $table_number) . ' - ' . get_bloginfo('name');
        });
        
        // Include the QR menu template
        include ORDERS_JET_PLUGIN_DIR . 'templates/qr-menu.php';
    }
    
    /**
     * Display table invoice
     */
    public function display_table_invoice() {
        // Include the invoice template
        include ORDERS_JET_PLUGIN_DIR . 'templates/table-invoice.php';
    }
    
    /**
     * Add admin menu for rewrite rules management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=oj_table',
            __('Rewrite Rules', 'orders-jet'),
            __('Rewrite Rules', 'orders-jet'),
            'manage_options',
            'orders-jet-rewrite-rules',
            array($this, 'rewrite_rules_admin_page')
        );
    }
    
    /**
     * Admin page for rewrite rules management
     */
    public function rewrite_rules_admin_page() {
        if (isset($_POST['flush_rewrite_rules']) && wp_verify_nonce($_POST['_wpnonce'], 'flush_rewrite_rules')) {
            $this->flush_rewrite_rules();
            echo '<div class="notice notice-success"><p>' . __('Rewrite rules flushed successfully!', 'orders-jet') . '</p></div>';
        }
        
        // Check if rewrite rules are working
        $menu_rules = get_option('rewrite_rules');
        $table_menu_working = isset($menu_rules['^table-menu/?$']);
        $table_invoice_working = isset($menu_rules['^table-invoice/?$']);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Orders Jet - Rewrite Rules', 'orders-jet'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Rewrite Rules Status', 'orders-jet'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Table Menu URL', 'orders-jet'); ?></th>
                        <td>
                            <code>/table-menu/</code>
                            <?php if ($table_menu_working): ?>
                                <span style="color: green;">✓ <?php _e('Working', 'orders-jet'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('Not Working', 'orders-jet'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Table Invoice URL', 'orders-jet'); ?></th>
                        <td>
                            <code>/table-invoice/</code>
                            <?php if ($table_invoice_working): ?>
                                <span style="color: green;">✓ <?php _e('Working', 'orders-jet'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('Not Working', 'orders-jet'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php if (!$table_menu_working || !$table_invoice_working): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Some rewrite rules are not working. Click the button below to flush rewrite rules.', 'orders-jet'); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('flush_rewrite_rules'); ?>
                    <input type="submit" name="flush_rewrite_rules" class="button button-primary" value="<?php _e('Flush Rewrite Rules', 'orders-jet'); ?>">
                </form>
            </div>
            
            <div class="card">
                <h2><?php _e('Test URLs', 'orders-jet'); ?></h2>
                <p><?php _e('Test these URLs to make sure they work:', 'orders-jet'); ?></p>
                <ul>
                    <li><a href="<?php echo home_url('/table-menu/?table=T01'); ?>" target="_blank"><?php echo home_url('/table-menu/?table=T01'); ?></a></li>
                    <li><a href="<?php echo home_url('/table-invoice/?table=T01&payment_method=cash'); ?>" target="_blank"><?php echo home_url('/table-invoice/?table=T01&payment_method=cash'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Enqueue table menu assets
     */
    private function enqueue_table_menu_assets($table_number) {
        // Enqueue WordPress and WooCommerce styles
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('woocommerce-general');
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Enqueue QR menu styles
        wp_enqueue_style(
            'orders-jet-qr-menu',
            ORDERS_JET_PLUGIN_URL . 'assets/css/qr-menu.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        // Enqueue QR menu JavaScript
        wp_enqueue_script(
            'orders-jet-qr-menu',
            ORDERS_JET_PLUGIN_URL . 'assets/js/qr-menu.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('orders-jet-qr-menu', 'OrdersJetQRMenu', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_table_nonce'),
            'tableNumber' => $table_number,
            'strings' => array(
                'loading' => __('Loading...', 'orders-jet'),
                'error' => __('An error occurred', 'orders-jet'),
                'success' => __('Success!', 'orders-jet'),
                'confirm' => __('Are you sure?', 'orders-jet'),
                'addToCart' => __('Add to Cart', 'orders-jet'),
                'removeFromCart' => __('Remove from Cart', 'orders-jet'),
                'updateCart' => __('Update Cart', 'orders-jet'),
                'checkout' => __('Checkout', 'orders-jet'),
                'placeOrder' => __('Place Order', 'orders-jet'),
                'orderPlaced' => __('Order placed successfully!', 'orders-jet'),
                'orderFailed' => __('Failed to place order. Please try again.', 'orders-jet')
            )
        ));
        
        // Add inline script to ensure initialization
        wp_add_inline_script('orders-jet-qr-menu', '
            jQuery(document).ready(function($) {
                console.log("Orders Jet: Scripts loaded via rewrite rule");
                if (typeof OrdersJetQRMenu !== "undefined") {
                    console.log("Orders Jet: OrdersJetQRMenu found, initializing...");
                    OrdersJetQRMenu.init({
                        tableNumber: "' . esc_js($table_number) . '",
                        tableId: null,
                        ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                        nonce: "' . wp_create_nonce('oj_table_nonce') . '"
                    });
                } else {
                    console.error("Orders Jet: OrdersJetQRMenu not found!");
                }
            });
        ');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        $this->flush_rewrite_rules();
        
        // Create database tables if needed
        $this->create_tables();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove custom user roles
        Orders_Jet_User_Roles::remove_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Maybe flush rewrite rules on plugin update
     */
    public function maybe_flush_rewrite_rules($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins']) && is_array($options['plugins'])) {
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin === ORDERS_JET_PLUGIN_BASENAME) {
                        flush_rewrite_rules();
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        // Add any custom database tables here if needed
        // For now, we'll use WordPress post types and meta
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . __('Orders Jet Integration', 'orders-jet') . '</strong> ' . __('requires WooCommerce to be installed and active.', 'orders-jet') . '</p></div>';
    }
    
    /**
     * WooCommerce Food missing notice
     */
    public function woo_food_missing_notice() {
        echo '<div class="error"><p><strong>' . __('Orders Jet Integration', 'orders-jet') . '</strong> ' . __('requires WooCommerce Food plugin to be installed and active.', 'orders-jet') . '</p></div>';
    }
}

/**
 * Returns the main instance of Orders Jet Integration
 */
function Orders_Jet() {
    return Orders_Jet_Integration::instance();
}

// Initialize the plugin
Orders_Jet();

/**
 * Set Orders Jet order status for all new orders
 * This ensures delivery orders placed from outside the table cart also get the required meta field
 */
add_action('woocommerce_new_order', 'oj_set_default_order_status');
function oj_set_default_order_status($order_id) {
    $order = wc_get_order($order_id);
    if ($order && !$order->get_meta('_oj_order_status')) {
        // Set default status based on order type
        $order_type = oj_get_order_type($order);
        $default_status = 'placed';
        
        // Log the order status setting for debugging
        error_log('Orders Jet: Setting default order status for order #' . $order_id . ' - Type: ' . $order_type . ', Status: ' . $default_status);
        
        $order->update_meta_data('_oj_order_status', $default_status);
        $order->save();
        
        error_log('Orders Jet: Successfully set order status for order #' . $order_id);
    }
}

/**
 * Set Orders Jet order status for existing orders that don't have it
 * This handles orders that were created before the hook was added
 */
add_action('wp_loaded', 'oj_set_existing_orders_status');
function oj_set_existing_orders_status() {
    // Only run this once per day to avoid performance issues
    $last_run = get_option('oj_last_status_update', 0);
    if (current_time('timestamp') - $last_run < 86400) { // 24 hours
        return;
    }
    
    // Get orders without _oj_order_status meta field
    $orders = wc_get_orders(array(
        'limit' => 100, // Process in batches
        'status' => array('pending', 'processing', 'on-hold', 'completed'),
        'meta_query' => array(
            array(
                'key' => '_oj_order_status',
                'compare' => 'NOT EXISTS'
            )
        )
    ));
    
    if (!empty($orders)) {
        foreach ($orders as $order) {
            $order_type = oj_get_order_type($order);
            $default_status = 'placed';
            
            $order->update_meta_data('_oj_order_status', $default_status);
            $order->save();
            
            error_log('Orders Jet: Retroactively set order status for order #' . $order->get_id() . ' - Type: ' . $order_type . ', Status: ' . $default_status);
        }
        
        // Update last run time
        update_option('oj_last_status_update', current_time('timestamp'));
        
        error_log('Orders Jet: Retroactively processed ' . count($orders) . ' orders without _oj_order_status');
    }
}