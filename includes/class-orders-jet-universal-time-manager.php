<?php
/**
 * Orders Jet - Universal Time Manager
 * Robust, unified timestamp management system for restaurant operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class OJ_Universal_Time_Manager {
    
    /**
     * SINGLE SOURCE OF TRUTH for all time operations
     * All methods return Unix timestamps in restaurant's local timezone
     */
    
    // ============ CORE TIME FUNCTIONS ============
    
    /**
     * Get current restaurant time as Unix timestamp (local timezone)
     * This is our MASTER clock - everything references this
     */
    public static function now() {
        return current_time('timestamp'); // WordPress handles timezone
    }
    
    /**
     * Get current restaurant time in any format
     */
    public static function now_formatted($format = 'Y-m-d H:i:s') {
        return current_time($format);
    }
    
    // ============ PARSING FUNCTIONS ============
    
    /**
     * Parse ANY timestamp to restaurant local time
     * Handles: Unix timestamps, date strings, WooFood formats, WordPress dates
     */
    public static function parse_to_local_timestamp($input, $input_type = 'auto') {
        if (empty($input)) return 0;
        
        switch ($input_type) {
            case 'unix_local':
                // Already local Unix timestamp (like WooFood)
                return intval($input);
                
            case 'unix_utc':
                // UTC Unix timestamp, convert to local
                return intval($input) + (get_option('gmt_offset') * HOUR_IN_SECONDS);
                
            case 'wordpress_utc':
                // WordPress UTC date string, convert to local
                return strtotime(get_date_from_gmt($input, 'Y-m-d H:i:s'));
                
            case 'woofood_combined':
                // "October 14, 2025 11:30 PM" - parse as local
                return strtotime($input);
                
            case 'auto':
            default:
                // Smart detection
                if (is_numeric($input)) {
                    // Unix timestamp - assume local if reasonable, UTC if not
                    $timestamp = intval($input);
                    $now = self::now();
                    
                    // If timestamp is way off, it's probably UTC
                    if (abs($timestamp - $now) > abs(($timestamp + get_option('gmt_offset') * HOUR_IN_SECONDS) - $now)) {
                        return $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS);
                    }
                    return $timestamp;
                } else {
                    // String - try parsing as local first
                    $local_timestamp = strtotime($input);
                    if ($local_timestamp !== false) {
                        return $local_timestamp;
                    }
                    
                    // Fallback: try as UTC and convert
                    return strtotime(get_date_from_gmt($input, 'Y-m-d H:i:s'));
                }
        }
    }
    
    // ============ WOOFOOD SPECIFIC ============
    
    /**
     * Parse WooFood delivery data to local timestamp
     * Handles all WooFood formats consistently
     */
    public static function parse_woofood_delivery($date, $time, $unix = null) {
        // Priority: Unix timestamp (most reliable)
        if (!empty($unix)) {
            return self::parse_to_local_timestamp($unix, 'unix_local');
        }
        
        // Fallback: Combine date and time strings
        if (!empty($date) && !empty($time)) {
            $combined = $date . ' ' . $time;
            return self::parse_to_local_timestamp($combined, 'woofood_combined');
        }
        
        return 0;
    }
    
    // ============ CALCULATION FUNCTIONS ============
    
    /**
     * Calculate time difference in seconds
     * Both inputs must be local timestamps
     */
    public static function diff_seconds($timestamp1, $timestamp2) {
        return $timestamp1 - $timestamp2;
    }
    
    /**
     * Calculate time remaining until target
     */
    public static function time_remaining($target_timestamp) {
        $now = self::now();
        $diff = $target_timestamp - $now;
        
        error_log('Universal Time Manager DEBUG: Target: ' . $target_timestamp . ' (' . date('Y-m-d H:i:s', $target_timestamp) . '), Now: ' . $now . ' (' . date('Y-m-d H:i:s', $now) . '), Diff: ' . $diff . ' seconds');
        
        if ($diff <= 0) {
            return array(
                'status' => $diff < -1800 ? 'overdue' : 'due_now', // 30min grace
                'seconds' => 0,
                'text' => $diff < -1800 ? 'OVERDUE' : 'DUE NOW',
                'short_text' => $diff < -1800 ? 'OVERDUE' : 'NOW',
                'class' => $diff < -1800 ? 'oj-time-overdue' : 'oj-time-now'
            );
        }
        
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        // Determine urgency class
        $class = 'oj-time-normal';
        if ($diff <= 1800) { // 30 minutes
            $class = 'oj-time-urgent';
        } elseif ($diff <= 3600) { // 1 hour
            $class = 'oj-time-soon';
        }
        
        return array(
            'status' => 'remaining',
            'seconds' => $diff,
            'hours' => $hours,
            'minutes' => $minutes,
            'text' => $hours > 0 ? "{$hours}h {$minutes}m left" : "{$minutes}m left",
            'short_text' => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
            'class' => $class
        );
    }
    
    // ============ DISPLAY FUNCTIONS ============
    
    /**
     * Format timestamp for display
     */
    public static function format($timestamp, $format = 'Y-m-d H:i:s') {
        if (empty($timestamp)) return '';
        return date($format, $timestamp);
    }
    
    /**
     * Format for JavaScript (ISO string)
     */
    public static function format_for_js($timestamp) {
        if (empty($timestamp)) return '';
        return date('c', $timestamp); // ISO 8601
    }
    
    // ============ ORDER SPECIFIC ============
    
    /**
     * Get order creation time as local timestamp
     */
    public static function get_order_created_timestamp($order) {
        $utc_date = $order->get_date_created()->format('Y-m-d H:i:s');
        return self::parse_to_local_timestamp($utc_date, 'wordpress_utc');
    }
    
    /**
     * Get order delivery time as local timestamp
     */
    public static function get_order_delivery_timestamp($order) {
        $date = $order->get_meta('exwfood_date_deli');
        $time = $order->get_meta('exwfood_time_deli');
        $unix = $order->get_meta('exwfood_datetime_deli_unix');
        
        return self::parse_woofood_delivery($date, $time, $unix);
    }
    
    /**
     * Get comprehensive order time analysis
     */
    public static function analyze_order_timing($order) {
        $created_timestamp = self::get_order_created_timestamp($order);
        $delivery_timestamp = self::get_order_delivery_timestamp($order);
        $now = self::now();
        
        $table_number = $order->get_meta('_oj_table_number');
        $delivery_date = $order->get_meta('exwfood_date_deli');
        $delivery_time = $order->get_meta('exwfood_time_deli');
        
        // Determine order type and display info
        if (!empty($table_number)) {
            $order_type = 'dinein';
            $display_label = 'DINE IN';
            $display_time = self::format($created_timestamp, 'g:i A');
        } elseif (!empty($delivery_date) && !empty($delivery_time)) {
            $order_type = 'pickup_timed';
            $display_time = self::format($delivery_timestamp, 'g:i A');
            $display_date = self::format($delivery_timestamp, 'M j');
            
            $today = self::format($now, 'Y-m-d');
            $order_date = self::format($delivery_timestamp, 'Y-m-d');
            
            if ($order_date === $today) {
                $display_label = 'PICK UP ' . $display_time;
            } else {
                $display_label = 'PICK UP ' . $display_date . ' ' . $display_time;
            }
        } else {
            $order_type = 'pickup';
            $display_label = 'PICK UP';
            $display_time = self::format($created_timestamp, 'g:i A');
        }
        
        return array(
            'order_type' => $order_type,
            'display_label' => $display_label,
            'display_time' => $display_time,
            'created_timestamp' => $created_timestamp,
            'delivery_timestamp' => $delivery_timestamp,
            'is_today' => ($delivery_timestamp > 0) ? (self::format($delivery_timestamp, 'Y-m-d') === self::format($now, 'Y-m-d')) : true,
            'is_upcoming' => ($delivery_timestamp > 0) ? ($delivery_timestamp > $now) : false,
            'countdown' => ($delivery_timestamp > 0) ? self::time_remaining($delivery_timestamp) : null
        );
    }
    
    /**
     * Get countdown data for JavaScript
     */
    public static function get_countdown_data($target_timestamp) {
        if (empty($target_timestamp)) return null;
        
        return array(
            'target_timestamp' => $target_timestamp,
            'current_timestamp' => self::now(),
            'target_iso' => self::format_for_js($target_timestamp),
            'current_iso' => self::format_for_js(self::now()),
            'diff_seconds' => $target_timestamp - self::now()
        );
    }
    
    /**
     * Debug information for troubleshooting
     */
    public static function debug_info() {
        $now = self::now();
        
        return array(
            'wp_timezone' => wp_timezone_string(),
            'wp_offset' => get_option('gmt_offset'),
            'server_time' => date('Y-m-d H:i:s'),
            'local_time' => self::now_formatted('Y-m-d H:i:s'),
            'local_timestamp' => $now,
            'utc_timestamp' => time(),
            'difference_hours' => get_option('gmt_offset')
        );
    }
}
