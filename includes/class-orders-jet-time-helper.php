<?php
/**
 * Orders Jet - Time Helper Class
 * Handles timezone-aware time operations for restaurant management
 */

if (!defined('ABSPATH')) {
    exit;
}

class OJ_Time_Helper {
    
    /**
     * Get current local time in restaurant timezone
     */
    public static function get_local_time($format = 'Y-m-d H:i:s') {
        return current_time($format); // Uses WordPress timezone setting
    }
    
    /**
     * Get current local date
     */
    public static function get_local_date($format = 'Y-m-d') {
        return current_time($format);
    }
    
    /**
     * Parse WooFood date format to local timezone
     * Handles: "October 13, 2025" -> "2025-10-13"
     */
    public static function parse_woofood_date($woofood_date) {
        if (empty($woofood_date)) return '';
        
        // Convert "October 13, 2025" to Y-m-d format
        $timestamp = strtotime($woofood_date);
        if ($timestamp === false) return '';
        
        // Return date part only (no timezone conversion needed for date)
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Parse WooFood time format 
     * Handles: "11:30 PM" -> "23:30" or display format
     */
    public static function parse_woofood_time($woofood_time, $format = 'H:i') {
        if (empty($woofood_time)) return '';
        
        // Convert "11:30 PM" to 24-hour format
        $timestamp = strtotime($woofood_time);
        if ($timestamp === false) return '';
        
        return date($format, $timestamp);
    }
    
    /**
     * Parse WooFood date and time together (most accurate)
     * Handles: "October 13, 2025" + "11:30 PM" -> local timezone datetime
     * Also handles: "2025-10-14" + "11:30 PM" -> local timezone datetime
     */
    public static function parse_woofood_datetime($woofood_date, $woofood_time) {
        if (empty($woofood_date) || empty($woofood_time)) return '';
        
        // Combine date and time for accurate parsing
        $combined_string = $woofood_date . ' ' . $woofood_time;
        $timestamp = strtotime($combined_string);
        
        if ($timestamp === false) return '';
        
        // IMPORTANT: Don't apply timezone conversion if the input is already in local format
        // WooFood typically stores in UTC, but some formats might already be local
        
        // Check if this looks like a UTC timestamp that needs conversion
        $utc_datetime = gmdate('Y-m-d H:i:s', $timestamp);
        $local_datetime = get_date_from_gmt($utc_datetime, 'Y-m-d H:i:s');
        
        // For debugging - let's return the original parsed time for now
        // This will help us see what's actually happening
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Use WooFood Unix timestamp (most reliable!)
     * This is timezone-aware and most accurate
     */
    public static function parse_woofood_unix($unix_timestamp, $format = 'Y-m-d H:i:s') {
        if (empty($unix_timestamp)) return '';
        
        // Convert Unix timestamp to local timezone
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $unix_timestamp), $format);
    }
    
    /**
     * Compare WooFood delivery time with current local time
     * Returns comprehensive comparison data
     */
    public static function analyze_woofood_delivery($delivery_date, $delivery_time, $unix_timestamp = null) {
        $local_now = self::get_local_time('Y-m-d H:i:s');
        $local_today = self::get_local_date('Y-m-d');
        
        error_log('Orders Jet Time Helper DEBUG: Input - Date: "' . $delivery_date . '", Time: "' . $delivery_time . '", Unix: ' . ($unix_timestamp ?: 'NONE'));
        
        // Prefer Unix timestamp if available (most accurate)
        if (!empty($unix_timestamp)) {
            $delivery_local_datetime = self::parse_woofood_unix($unix_timestamp, 'Y-m-d H:i:s');
            $delivery_local_date = self::parse_woofood_unix($unix_timestamp, 'Y-m-d');
            $delivery_local_time = self::parse_woofood_unix($unix_timestamp, 'H:i:s');
            error_log('Orders Jet Time Helper DEBUG: Using Unix timestamp - Result: ' . $delivery_local_datetime);
        } else {
            // Use combined parsing for accurate timezone conversion
            $delivery_local_datetime = self::parse_woofood_datetime($delivery_date, $delivery_time);
            error_log('Orders Jet Time Helper DEBUG: Using combined parsing - Result: ' . $delivery_local_datetime);
            
            if (!empty($delivery_local_datetime)) {
                $delivery_local_date = date('Y-m-d', strtotime($delivery_local_datetime));
                $delivery_local_time = date('H:i:s', strtotime($delivery_local_datetime));
            } else {
                // Fallback to individual parsing if combined fails
                $delivery_local_date = self::parse_woofood_date($delivery_date);
                $delivery_local_time = self::parse_woofood_time($delivery_time, 'H:i:s');
                $delivery_local_datetime = $delivery_local_date . ' ' . $delivery_local_time;
                error_log('Orders Jet Time Helper DEBUG: Using fallback parsing - Result: ' . $delivery_local_datetime);
            }
        }
        
        return array(
            'is_today' => ($delivery_local_date === $local_today),
            'is_upcoming' => (strtotime($delivery_local_datetime) > strtotime($local_now)),
            'is_past' => (strtotime($delivery_local_datetime) < strtotime($local_now)),
            'local_date' => $delivery_local_date,
            'local_time' => $delivery_local_time,
            'local_datetime' => $delivery_local_datetime,
            'display_time' => date('g:i A', strtotime($delivery_local_datetime)),
            'display_date' => date('M j', strtotime($delivery_local_datetime)),
            'display_datetime' => date('M j, g:i A', strtotime($delivery_local_datetime)),
            'formatted_for_js' => $delivery_local_date, // For JavaScript date comparisons
        );
    }
    
    /**
     * Smart order type detection with timezone awareness
     */
    public static function get_smart_order_type($order) {
        $table_number = $order->get_meta('_oj_table_number');
        $delivery_date = $order->get_meta('exwfood_date_deli');
        $delivery_time = $order->get_meta('exwfood_time_deli');
        $unix_timestamp = $order->get_meta('exwfood_datetime_deli_unix');
        
        // Table order = Dine In
        if (!empty($table_number)) {
            return array(
                'type' => 'dinein',
                'label' => 'DINE IN',
                'icon' => '🍽️',
                'class' => 'oj-order-type-dinein'
            );
        }
        
        // Pickup with delivery time = Timed Pickup
        if (!empty($delivery_date) && !empty($delivery_time)) {
            $analysis = self::analyze_woofood_delivery($delivery_date, $delivery_time, $unix_timestamp);
            
            if ($analysis['is_today']) {
                $label = 'PICK UP ' . $analysis['display_time'];
            } else {
                $label = 'PICK UP ' . $analysis['display_date'] . ' ' . $analysis['display_time'];
            }
            
            return array(
                'type' => 'pickup_timed',
                'label' => $label,
                'icon' => '🕒',
                'class' => $analysis['is_upcoming'] ? 'oj-order-type-pickup-upcoming' : 'oj-order-type-pickup-timed',
                'analysis' => $analysis
            );
        }
        
        // Regular pickup (no specific time)
        return array(
            'type' => 'pickup',
            'label' => 'PICK UP',
            'icon' => '🥡',
            'class' => 'oj-order-type-pickup'
        );
    }
    
    /**
     * Convert order post date to local timezone for display
     */
    public static function get_order_display_time($post_date) {
        // Convert UTC post date to local timezone
        return get_date_from_gmt($post_date, 'g:i A');
    }
    
    /**
     * Calculate time remaining until delivery
     * Returns human-readable time difference for countdown
     */
    public static function get_time_remaining($delivery_date, $delivery_time, $unix_timestamp = null) {
        $analysis = self::analyze_woofood_delivery($delivery_date, $delivery_time, $unix_timestamp);
        
        if ($analysis['is_past']) {
            return array(
                'status' => 'overdue',
                'text' => 'OVERDUE',
                'short_text' => 'OVERDUE',
                'class' => 'oj-time-overdue',
                'seconds' => 0
            );
        }
        
        $local_now = self::get_local_time('Y-m-d H:i:s');
        $delivery_timestamp = strtotime($analysis['local_datetime']);
        $current_timestamp = strtotime($local_now);
        
        $diff_seconds = $delivery_timestamp - $current_timestamp;
        
        if ($diff_seconds <= 0) {
            return array(
                'status' => 'now',
                'text' => 'DUE NOW',
                'short_text' => 'NOW',
                'class' => 'oj-time-now',
                'seconds' => 0
            );
        }
        
        // Calculate time components
        $hours = floor($diff_seconds / 3600);
        $minutes = floor(($diff_seconds % 3600) / 60);
        
        // Format display text
        if ($hours > 0) {
            if ($minutes > 0) {
                $text = $hours . 'h ' . $minutes . 'm left';
                $short_text = $hours . 'h ' . $minutes . 'm';
            } else {
                $text = $hours . 'h left';
                $short_text = $hours . 'h';
            }
        } else {
            $text = $minutes . 'm left';
            $short_text = $minutes . 'm';
        }
        
        // Determine urgency class
        $class = 'oj-time-normal';
        if ($diff_seconds <= 1800) { // 30 minutes
            $class = 'oj-time-urgent';
        } elseif ($diff_seconds <= 3600) { // 1 hour
            $class = 'oj-time-soon';
        }
        
        return array(
            'status' => 'remaining',
            'text' => $text,
            'short_text' => $short_text,
            'class' => $class,
            'seconds' => $diff_seconds,
            'hours' => $hours,
            'minutes' => $minutes
        );
    }
    
    /**
     * Get countdown data for JavaScript timers
     */
    public static function get_countdown_data($delivery_date, $delivery_time, $unix_timestamp = null) {
        $analysis = self::analyze_woofood_delivery($delivery_date, $delivery_time, $unix_timestamp);
        $local_now = self::get_local_time('Y-m-d H:i:s');
        
        $delivery_timestamp = strtotime($analysis['local_datetime']);
        $current_timestamp = strtotime($local_now);
        
        return array(
            'target_timestamp' => $delivery_timestamp,
            'current_timestamp' => $current_timestamp,
            'target_iso' => date('c', $delivery_timestamp), // ISO format for JS
            'current_iso' => date('c', $current_timestamp),
            'diff_seconds' => $delivery_timestamp - $current_timestamp,
            'is_past' => ($delivery_timestamp < $current_timestamp)
        );
    }
    
    /**
     * Get timezone info for debugging
     */
    public static function get_timezone_info() {
        return array(
            'wp_timezone' => wp_timezone_string(),
            'wp_offset' => get_option('gmt_offset'),
            'server_time' => date('Y-m-d H:i:s'),
            'local_time' => self::get_local_time('Y-m-d H:i:s'),
            'difference' => 'Local is ' . get_option('gmt_offset') . ' hours ahead of UTC'
        );
    }
}
