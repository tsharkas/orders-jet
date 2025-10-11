<?php
/**
 * Orders Jet - Assets Manager Class
 * Handles enqueuing of scripts and styles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_head', array($this, 'add_meta_tags'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on table menu pages
        if (!isset($_GET['table']) || empty($_GET['table'])) {
            return;
        }
        
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
            'tableNumber' => sanitize_text_field($_GET['table']),
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
                console.log("Orders Jet: Scripts loaded, checking for OrdersJetQRMenu...");
                if (typeof OrdersJetQRMenu !== "undefined") {
                    console.log("Orders Jet: OrdersJetQRMenu found, initializing...");
                    // Initialize with table data
                    var tableNumber = "' . sanitize_text_field($_GET['table']) . '";
                    var tableId = null; // Will be set by template
                    OrdersJetQRMenu.init({
                        tableNumber: tableNumber,
                        tableId: tableId,
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
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on table management pages
        if ($hook !== 'edit.php' || get_post_type() !== 'oj_table') {
            return;
        }
        
        wp_enqueue_style(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        wp_enqueue_script(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        wp_localize_script('orders-jet-admin', 'oj_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_table_nonce')
        ));
    }
    
    /**
     * Add meta tags for mobile optimization
     */
    public function add_meta_tags() {
        // Only add on table menu pages
        if (!isset($_GET['table']) || empty($_GET['table'])) {
            return;
        }
        
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">' . "\n";
        echo '<meta name="theme-color" content="#c41e3a">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
    }
}

