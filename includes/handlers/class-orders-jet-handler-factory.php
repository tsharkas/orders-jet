<?php
declare(strict_types=1);
/**
 * Orders Jet - Handler Factory Class
 * Centralized factory for creating and managing handler instances
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Handler_Factory {
    
    private static $instances = array();
    private $tax_service;
    private $kitchen_service;
    private $notification_service;
    
    public function __construct($tax_service, $kitchen_service, $notification_service) {
        $this->tax_service = $tax_service;
        $this->kitchen_service = $kitchen_service;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Get Order Submission Handler instance
     */
    public function get_order_submission_handler() {
        if (!isset(self::$instances['order_submission'])) {
            self::$instances['order_submission'] = new Orders_Jet_Order_Submission_Handler(
                $this->tax_service,
                $this->notification_service
            );
        }
        return self::$instances['order_submission'];
    }
    
    /**
     * Get Table Closure Handler instance
     */
    public function get_table_closure_handler() {
        if (!isset(self::$instances['table_closure'])) {
            self::$instances['table_closure'] = new Orders_Jet_Table_Closure_Handler(
                $this->tax_service,
                $this->kitchen_service
            );
        }
        return self::$instances['table_closure'];
    }
    
    /**
     * Get Table Query Handler instance
     */
    public function get_table_query_handler() {
        if (!isset(self::$instances['table_query'])) {
            self::$instances['table_query'] = new Orders_Jet_Table_Query_Handler();
        }
        return self::$instances['table_query'];
    }
    
    /**
     * Get Product Details Handler instance
     */
    public function get_product_details_handler() {
        if (!isset(self::$instances['product_details'])) {
            self::$instances['product_details'] = new Orders_Jet_Product_Details_Handler();
        }
        return self::$instances['product_details'];
    }
    
    /**
     * Get Dashboard Analytics Handler instance
     */
    public function get_dashboard_analytics_handler() {
        if (!isset(self::$instances['dashboard_analytics'])) {
            self::$instances['dashboard_analytics'] = new Orders_Jet_Dashboard_Analytics_Handler();
        }
        return self::$instances['dashboard_analytics'];
    }
    
    /**
     * Get Individual Order Completion Handler instance
     */
    public function get_individual_order_completion_handler() {
        if (!isset(self::$instances['individual_order_completion'])) {
            self::$instances['individual_order_completion'] = new Orders_Jet_Individual_Order_Completion_Handler(
                $this->tax_service
            );
        }
        return self::$instances['individual_order_completion'];
    }
    
    /**
     * Get Kitchen Management Handler instance
     */
    public function get_kitchen_management_handler() {
        if (!isset(self::$instances['kitchen_management'])) {
            self::$instances['kitchen_management'] = new Orders_Jet_Kitchen_Management_Handler(
                $this->kitchen_service,
                $this->notification_service
            );
        }
        return self::$instances['kitchen_management'];
    }
    
    /**
     * Get Invoice Generation Handler instance
     */
    public function get_invoice_generation_handler() {
        if (!isset(self::$instances['invoice_generation'])) {
            self::$instances['invoice_generation'] = new Orders_Jet_Invoice_Generation_Handler();
        }
        return self::$instances['invoice_generation'];
    }
    
    /**
     * Clear all cached instances (useful for testing)
     */
    public static function clear_instances() {
        self::$instances = array();
    }
}
