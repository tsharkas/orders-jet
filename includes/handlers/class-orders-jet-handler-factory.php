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
     * Clear all cached instances (useful for testing)
     */
    public static function clear_instances() {
        self::$instances = array();
    }
}
