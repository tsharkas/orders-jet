<?php
declare(strict_types=1);
/**
 * Orders Jet - Dashboard Analytics Handler Class
 * Handles dashboard filter counts and analytics data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Dashboard_Analytics_Handler {
    
    /**
     * Get filter counts for dashboard
     * 
     * @return array Filter counts data
     * @throws Exception On processing errors
     */
    public function get_filter_counts() {
        // Get counts for each filter
        $counts = array();
        
        // All orders (processing, pending, completed)
        $all_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending', 'wc-completed'),
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['all'] = count($all_orders);
        
        // Active orders (processing, pending)
        $active_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['active'] = count($active_orders);
        
        // Processing orders
        $processing_orders = wc_get_orders(array(
            'status' => 'wc-processing',
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['processing'] = count($processing_orders);
        
        // Pending orders
        $pending_orders = wc_get_orders(array(
            'status' => 'wc-pending',
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['pending'] = count($pending_orders);
        
        // Completed orders
        $completed_orders = wc_get_orders(array(
            'status' => 'wc-completed',
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['completed'] = count($completed_orders);
        
        // Dine-in orders (processing, pending)
        $dinein_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'dinein',
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_table_number',
                    'compare' => 'EXISTS'
                )
            ),
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['dinein'] = count($dinein_orders);
        
        // Takeaway orders (processing, pending)
        $takeaway_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'takeaway',
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_table_number',
                    'value' => '',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['takeaway'] = count($takeaway_orders);
        
        // Delivery orders (processing, pending)
        $delivery_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'delivery',
                    'compare' => '='
                )
            ),
            'limit' => -1,
            'return' => 'ids'
        ));
        $counts['delivery'] = count($delivery_orders);
        
        return $counts;
    }
    
    /**
     * Get order counts by status
     * 
     * @param array $statuses Order statuses to count
     * @return int Order count
     */
    private function get_order_count_by_status($statuses) {
        $orders = wc_get_orders(array(
            'status' => $statuses,
            'limit' => -1,
            'return' => 'ids'
        ));
        
        return count($orders);
    }
    
    /**
     * Get order counts by order method
     * 
     * @param string $method Order method (dinein, takeaway, delivery)
     * @param array $statuses Order statuses to include
     * @return int Order count
     */
    private function get_order_count_by_method($method, $statuses = array('wc-processing', 'wc-pending')) {
        $meta_query = array();
        
        switch ($method) {
            case 'dinein':
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'dinein',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_oj_table_number',
                        'compare' => 'EXISTS'
                    )
                );
                break;
                
            case 'takeaway':
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'takeaway',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_oj_table_number',
                        'value' => '',
                        'compare' => 'NOT EXISTS'
                    )
                );
                break;
                
            case 'delivery':
                $meta_query = array(
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'delivery',
                        'compare' => '='
                    )
                );
                break;
        }
        
        $orders = wc_get_orders(array(
            'status' => $statuses,
            'meta_query' => $meta_query,
            'limit' => -1,
            'return' => 'ids'
        ));
        
        return count($orders);
    }
}
