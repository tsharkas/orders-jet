<?php
/**
 * Debug WooFood Time Parsing Issue
 */

// WordPress bootstrap
if (!defined('ABSPATH')) {
    exit;
}

// Test the exact values from your order
$delivery_date = '2025-10-14';  // From order details
$delivery_time = '11:30 PM';   // From order details

echo "<h2>Debug WooFood Time Parsing Issue</h2>";
echo "<p><strong>Input Values:</strong></p>";
echo "<ul>";
echo "<li>Delivery Date: " . $delivery_date . "</li>";
echo "<li>Delivery Time: " . $delivery_time . "</li>";
echo "</ul>";

echo "<h3>Current Parsing Results:</h3>";

// Test current parsing methods
if (class_exists('OJ_Time_Helper')) {
    echo "<h4>Using OJ_Time_Helper methods:</h4>";
    
    $parsed_date = OJ_Time_Helper::parse_woofood_date($delivery_date);
    $parsed_time = OJ_Time_Helper::parse_woofood_time($delivery_time, 'H:i:s');
    $combined_datetime = $parsed_date . ' ' . $parsed_time;
    
    echo "<ul>";
    echo "<li>Parsed Date: " . $parsed_date . "</li>";
    echo "<li>Parsed Time: " . $parsed_time . "</li>";
    echo "<li>Combined DateTime: " . $combined_datetime . "</li>";
    echo "</ul>";
    
    // Test the full analysis
    $analysis = OJ_Time_Helper::analyze_woofood_delivery($delivery_date, $delivery_time, null);
    
    echo "<h4>Full Analysis Results:</h4>";
    echo "<ul>";
    echo "<li>Local Date: " . $analysis['local_date'] . "</li>";
    echo "<li>Local Time: " . $analysis['local_time'] . "</li>";
    echo "<li>Local DateTime: " . $analysis['local_datetime'] . "</li>";
    echo "<li>Display Time: " . $analysis['display_time'] . "</li>";
    echo "<li>Display Date: " . $analysis['display_date'] . "</li>";
    echo "<li>Display DateTime: " . $analysis['display_datetime'] . "</li>";
    echo "<li>Is Today: " . ($analysis['is_today'] ? 'YES' : 'NO') . "</li>";
    echo "<li>Is Upcoming: " . ($analysis['is_upcoming'] ? 'YES' : 'NO') . "</li>";
    echo "</ul>";
}

echo "<h3>Step-by-Step Debugging:</h3>";

// Step 1: Test individual parsing
echo "<h4>1. Individual Parsing:</h4>";
$date_timestamp = strtotime($delivery_date);
$time_timestamp = strtotime($delivery_time);

echo "<ul>";
echo "<li>Date strtotime('$delivery_date'): " . $date_timestamp . " → " . date('Y-m-d H:i:s', $date_timestamp) . "</li>";
echo "<li>Time strtotime('$delivery_time'): " . $time_timestamp . " → " . date('Y-m-d H:i:s', $time_timestamp) . "</li>";
echo "</ul>";

// Step 2: Test combined parsing
echo "<h4>2. Combined Parsing:</h4>";
$combined_string = $delivery_date . ' ' . $delivery_time;
$combined_timestamp = strtotime($combined_string);

echo "<ul>";
echo "<li>Combined String: '$combined_string'</li>";
echo "<li>Combined Timestamp: " . $combined_timestamp . " → " . date('Y-m-d H:i:s', $combined_timestamp) . "</li>";
echo "</ul>";

// Step 3: Test timezone conversion
echo "<h4>3. Timezone Conversion:</h4>";
echo "<ul>";
echo "<li>WordPress Timezone: " . wp_timezone_string() . "</li>";
echo "<li>GMT Offset: " . get_option('gmt_offset') . " hours</li>";
echo "<li>Server Time: " . date('Y-m-d H:i:s') . "</li>";
echo "<li>WordPress Local Time: " . current_time('Y-m-d H:i:s') . "</li>";
echo "</ul>";

// Step 4: Test correct parsing
echo "<h4>4. Correct Parsing Method:</h4>";
$correct_combined = $delivery_date . ' ' . $delivery_time;
$correct_timestamp = strtotime($correct_combined);
$correct_local = get_date_from_gmt(gmdate('Y-m-d H:i:s', $correct_timestamp), 'Y-m-d H:i:s');

echo "<ul>";
echo "<li>Correct Combined: '$correct_combined'</li>";
echo "<li>Correct Timestamp: " . $correct_timestamp . "</li>";
echo "<li>Correct Local Time: " . $correct_local . "</li>";
echo "<li>Display Format: " . date('M j, g:i A', strtotime($correct_local)) . "</li>";
echo "</ul>";

// Add admin menu item
add_action('admin_menu', 'oj_debug_time_menu');

function oj_debug_time_menu() {
    add_submenu_page(
        'tools.php',
        'Debug Time Parsing',
        'Debug Time Parsing',
        'manage_options',
        'debug-time-parsing',
        'oj_debug_time_page'
    );
}

function oj_debug_time_page() {
    ?>
    <div class="wrap">
        <?php
        // Include the debug output here
        include __FILE__;
        ?>
    </div>
    <?php
}
?>
