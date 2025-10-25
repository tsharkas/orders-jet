<?php
declare(strict_types=1);
/**
 * Orders Jet - QR Menu Handler Class
 * Handles main QR menu logic and coordination
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_QR_Menu_Handler {
    
    private $menu_service;
    private $cart_service;
    private $session_service;
    
    public function __construct($menu_service = null, $cart_service = null, $session_service = null) {
        // Services will be injected when we create the service layer
        $this->menu_service = $menu_service;
        $this->cart_service = $cart_service;
        $this->session_service = $session_service;
    }
    
    /**
     * Get complete table data for QR menu initialization
     * 
     * @param string $table_number The table number
     * @return array Table data with menu information
     * @throws Exception If table not found or invalid
     */
    public function get_table_data($table_number) {
        if (empty($table_number)) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        // Get table ID
        $table_id = $this->get_table_id_by_number($table_number);
        if (!$table_id) {
            throw new Exception(__('Table not found', 'orders-jet'));
        }
        
        // Get table meta data
        $table_data = array(
            'table_id' => $table_id,
            'table_number' => $table_number,
            'capacity' => get_post_meta($table_id, '_oj_table_capacity', true),
            'location' => get_post_meta($table_id, '_oj_table_location', true),
            'status' => get_post_meta($table_id, '_oj_table_status', true),
            'woofood_location_id' => get_post_meta($table_id, '_oj_woofood_location_id', true)
        );
        
        // Get WooFood location details if assigned
        if ($table_data['woofood_location_id'] && class_exists('EX_WooFood')) {
            $woofood_location = get_term($table_data['woofood_location_id'], 'exwoofood_loc');
            if ($woofood_location && !is_wp_error($woofood_location)) {
                $table_data['woofood_location'] = array(
                    'id' => $woofood_location->term_id,
                    'name' => $woofood_location->name,
                    'description' => $woofood_location->description
                );
            }
        }
        
        return $table_data;
    }
    
    /**
     * Get menu categories for the table
     * 
     * @param int $table_id The table ID
     * @return array Menu categories
     */
    public function get_menu_categories($table_id) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($categories)) {
            return array();
        }
        
        // Apply filters for menu integration
        if (class_exists('Orders_Jet_Menu_Integration')) {
            $categories = apply_filters('oj_qr_menu_categories', $categories, $table_id);
        }
        
        return $categories;
    }
    
    /**
     * Get products filtered by WooFood location
     * 
     * @param int|null $woofood_location_id WooFood location ID
     * @return array Products array
     */
    public function get_products_by_location($woofood_location_id = null) {
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
        
        // Apply filters for menu integration
        if (class_exists('Orders_Jet_Menu_Integration')) {
            $products = apply_filters('oj_qr_menu_products', $products, $woofood_location_id);
        }
        
        return $products;
    }
    
    /**
     * Validate table access and status
     * 
     * @param string $table_number The table number
     * @return bool True if table is accessible
     * @throws Exception If table is not accessible
     */
    public function validate_table_access($table_number) {
        $table_data = $this->get_table_data($table_number);
        
        // Check table status
        if ($table_data['status'] === 'maintenance') {
            throw new Exception(__('Table is under maintenance', 'orders-jet'));
        }
        
        return true;
    }
    
    /**
     * Get complete menu data for QR menu
     * 
     * @param string $table_number The table number
     * @return array Complete menu data
     */
    public function get_complete_menu_data($table_number) {
        // Validate table access
        $this->validate_table_access($table_number);
        
        // Get table data
        $table_data = $this->get_table_data($table_number);
        
        // Get categories
        $categories = $this->get_menu_categories($table_data['table_id']);
        
        // Get products
        $products = $this->get_products_by_location($table_data['woofood_location_id']);
        
        return array(
            'table' => $table_data,
            'categories' => $categories,
            'products' => $products
        );
    }
    
    /**
     * Get table ID by table number
     * 
     * @param string $table_number The table number
     * @return int|null Table ID or null if not found
     */
    private function get_table_id_by_number($table_number) {
        if (function_exists('oj_get_table_id_by_number')) {
            return oj_get_table_id_by_number($table_number);
        }
        
        // Fallback method
        $tables = get_posts(array(
            'post_type' => 'oj_table',
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        
        return !empty($tables) ? $tables[0]->ID : null;
    }
}
