<?php
/**
 * Orders Jet Delivery Time Manager
 * 
 * Handles delivery/pickup time management using custom order fields
 * This provides a unified approach for all order types
 */

if (!defined('ABSPATH')) {
    exit;
}

class OJ_Delivery_Time_Manager {
    
    /**
     * Set delivery/pickup time for an order
     * 
     * @param WC_Order $order The WooCommerce order
     * @param string $date_time The delivery/pickup date and time (Y-m-d H:i:s format)
     * @param string $type The order type: 'delivery', 'pickup', 'dinein'
     */
    public static function set_delivery_time($order, $date_time, $type = 'pickup') {
        if (!$order || !$date_time) {
            return false;
        }
        
        // Validate and normalize the date_time
        $timestamp = strtotime($date_time);
        if (!$timestamp) {
            error_log("OJ Delivery Time Manager: Invalid date_time format: {$date_time}");
            return false;
        }
        
        // Store in consistent format
        $normalized_datetime = date('Y-m-d H:i:s', $timestamp);
        
        // Set our custom fields
        $order->update_meta_data('_oj_delivery_time', $normalized_datetime);
        $order->update_meta_data('_oj_delivery_timestamp', $timestamp);
        $order->update_meta_data('_oj_order_method', $type);
        
        // Log the action
        error_log("OJ Delivery Time Manager: Set {$type} time for Order #{$order->get_id()}: {$normalized_datetime} (timestamp: {$timestamp})");
        
        return true;
    }
    
    /**
     * Get delivery/pickup time for an order
     * 
     * @param WC_Order $order The WooCommerce order
     * @return array|false Array with 'datetime', 'timestamp', 'formatted' or false if not set
     */
    public static function get_delivery_time($order) {
        if (!$order) {
            return false;
        }
        
        // First try our custom field
        $delivery_time = $order->get_meta('_oj_delivery_time');
        $delivery_timestamp = $order->get_meta('_oj_delivery_timestamp');
        
        if ($delivery_time && $delivery_timestamp) {
            return array(
                'datetime' => $delivery_time,
                'timestamp' => intval($delivery_timestamp),
                'formatted' => date('M j, g:i A', intval($delivery_timestamp)),
                'date_only' => date('M j', intval($delivery_timestamp)),
                'time_only' => date('g:i A', intval($delivery_timestamp))
            );
        }
        
        // Fallback to WooFood fields for existing orders
        $woofood_date = $order->get_meta('exwfood_date_deli');
        $woofood_time = $order->get_meta('exwfood_time_deli');
        $woofood_unix = $order->get_meta('exwfood_datetime_deli_unix');
        
        if ($woofood_unix) {
            $timestamp = intval($woofood_unix);
            return array(
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'timestamp' => $timestamp,
                'formatted' => date('M j, g:i A', $timestamp),
                'date_only' => date('M j', $timestamp),
                'time_only' => date('g:i A', $timestamp)
            );
        }
        
        if ($woofood_date && $woofood_time) {
            $combined = $woofood_date . ' ' . $woofood_time;
            $timestamp = strtotime($combined);
            if ($timestamp) {
                return array(
                    'datetime' => date('Y-m-d H:i:s', $timestamp),
                    'timestamp' => $timestamp,
                    'formatted' => date('M j, g:i A', $timestamp),
                    'date_only' => date('M j', $timestamp),
                    'time_only' => date('g:i A', $timestamp)
                );
            }
        }
        
        return false;
    }
    
    /**
     * Check if order has delivery/pickup time
     * 
     * @param WC_Order $order The WooCommerce order
     * @return bool
     */
    public static function has_delivery_time($order) {
        return self::get_delivery_time($order) !== false;
    }
    
    /**
     * Get time remaining until delivery/pickup
     * 
     * @param WC_Order $order The WooCommerce order
     * @return array|false Array with countdown info or false
     */
    public static function get_time_remaining($order) {
        $delivery_info = self::get_delivery_time($order);
        if (!$delivery_info) {
            return false;
        }
        
        $current_time = current_time('timestamp');
        $delivery_timestamp = $delivery_info['timestamp'];
        
        $diff_seconds = $delivery_timestamp - $current_time;
        
        // Calculate days, hours and minutes
        $days = floor(abs($diff_seconds) / 86400); // 86400 seconds in a day
        $hours = floor((abs($diff_seconds) % 86400) / 3600);
        $minutes = floor((abs($diff_seconds) % 3600) / 60);
        
        // Determine status
        $status = 'upcoming';
        $class = 'oj-countdown-upcoming';
        
        if ($diff_seconds < 0) {
            $status = 'overdue';
            $class = 'oj-countdown-overdue';
        } elseif ($diff_seconds < 1800) { // Less than 30 minutes
            $status = 'urgent';
            $class = 'oj-countdown-urgent';
        } elseif ($diff_seconds < 3600) { // Less than 1 hour
            $status = 'soon';
            $class = 'oj-countdown-soon';
        }
        
        // Format text with days support
        if ($days > 0) {
            if ($hours > 0) {
                $text = $days . 'D ' . $hours . 'H ' . $minutes . 'M';
                $short_text = $days . 'D ' . $hours . 'H';
            } else {
                $text = $days . 'D ' . $minutes . 'M';
                $short_text = $days . 'D ' . $minutes . 'M';
            }
        } elseif ($hours > 0) {
            $text = $hours . 'H ' . $minutes . 'M';
            $short_text = $hours . 'H ' . $minutes . 'M';
        } else {
            $text = $minutes . 'M';
            $short_text = $minutes . 'M';
        }
        
        if ($status === 'overdue') {
            $text = 'OVERDUE ' . $text;
            $short_text = 'LATE';
        }
        
        return array(
            'diff_seconds' => $diff_seconds,
            'hours' => $hours,
            'minutes' => $minutes,
            'status' => $status,
            'class' => $class,
            'text' => $text,
            'short_text' => $short_text,
            'current_time' => $current_time,
            'delivery_time' => $delivery_timestamp
        );
    }
    
    /**
     * Import WooFood delivery time to our custom fields
     * This helps migrate existing orders to our system
     * 
     * @param WC_Order $order The WooCommerce order
     * @return bool Success status
     */
    public static function import_woofood_time($order) {
        if (!$order) {
            return false;
        }
        
        // Skip if we already have our custom field
        if ($order->get_meta('_oj_delivery_time')) {
            return true;
        }
        
        $delivery_info = self::get_delivery_time($order);
        if ($delivery_info) {
            $order->update_meta_data('_oj_delivery_time', $delivery_info['datetime']);
            $order->update_meta_data('_oj_delivery_timestamp', $delivery_info['timestamp']);
            $order->save();
            
            error_log("OJ Delivery Time Manager: Imported WooFood time for Order #{$order->get_id()}: {$delivery_info['datetime']}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Set delivery time from WooFood format
     * Helper function for WooFood integration
     * 
     * @param WC_Order $order The WooCommerce order
     * @param string $woofood_date WooFood date format
     * @param string $woofood_time WooFood time format
     * @param string $type Order type
     */
    public static function set_from_woofood($order, $woofood_date, $woofood_time, $type = 'pickup') {
        if (!$order || !$woofood_date || !$woofood_time) {
            return false;
        }
        
        $combined = $woofood_date . ' ' . $woofood_time;
        $timestamp = strtotime($combined);
        
        if ($timestamp) {
            $datetime = date('Y-m-d H:i:s', $timestamp);
            return self::set_delivery_time($order, $datetime, $type);
        }
        
        return false;
    }
}
