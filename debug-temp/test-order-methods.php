<?php
/**
 * DEBUG: Test Order Method Detection
 * Simple file to debug what's actually stored in the database
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// WordPress bootstrap - try different paths
if (file_exists('../../../wp-load.php')) {
    require_once('../../../wp-load.php');
} elseif (file_exists('../../../../wp-load.php')) {
    require_once('../../../../wp-load.php');
} elseif (file_exists('../../../../../wp-load.php')) {
    require_once('../../../../../wp-load.php');
} else {
    die('WordPress not found. Please access this file through WordPress admin or adjust the path.');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Check if WooCommerce is loaded
if (!function_exists('wc_get_orders')) {
    die('WooCommerce not found or not active');
}

echo "<h1>🔍 Order Method Debug Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .order-debug { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
    .meta-field { background: #fff; padding: 5px; margin: 2px 0; border-left: 3px solid #0073aa; }
    .detected { color: green; font-weight: bold; }
    .fallback { color: orange; font-weight: bold; }
    .empty { color: red; }
</style>";

// Get active orders
$active_orders = wc_get_orders(array(
    'status' => array('wc-pending', 'wc-processing'),
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'objects'
));

echo "<h2>📊 Found " . count($active_orders) . " Active Orders</h2>";

foreach ($active_orders as $order) {
    $order_id = $order->get_id();
    $order_number = $order->get_order_number();
    $status = $order->get_status();
    
    echo "<div class='order-debug'>";
    echo "<h3>Order #{$order_number} (ID: {$order_id}) - Status: {$status}</h3>";
    
    // Check all possible meta fields
    $exwf_method = $order->get_meta('exwf_odmethod');
    $oj_method = $order->get_meta('_oj_order_method');
    $oj_type = $order->get_meta('_oj_order_type');
    $table_number = $order->get_meta('_oj_table_number');
    
    // Also check directly from database
    $db_exwf = get_post_meta($order_id, 'exwf_odmethod', true);
    $db_oj_method = get_post_meta($order_id, '_oj_order_method', true);
    $db_oj_type = get_post_meta($order_id, '_oj_order_type', true);
    $db_table = get_post_meta($order_id, '_oj_table_number', true);
    
    echo "<h4>📋 Meta Fields (via WooCommerce):</h4>";
    echo "<div class='meta-field'>exwf_odmethod: " . ($exwf_method ? "<span class='detected'>'{$exwf_method}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_order_method: " . ($oj_method ? "<span class='detected'>'{$oj_method}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_order_type: " . ($oj_type ? "<span class='detected'>'{$oj_type}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_table_number: " . ($table_number ? "<span class='detected'>'{$table_number}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    
    echo "<h4>💾 Meta Fields (Direct Database):</h4>";
    echo "<div class='meta-field'>exwf_odmethod: " . ($db_exwf ? "<span class='detected'>'{$db_exwf}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_order_method: " . ($db_oj_method ? "<span class='detected'>'{$db_oj_method}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_order_type: " . ($db_oj_type ? "<span class='detected'>'{$db_oj_type}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    echo "<div class='meta-field'>_oj_table_number: " . ($db_table ? "<span class='detected'>'{$db_table}'</span>" : "<span class='empty'>EMPTY</span>") . "</div>";
    
    // Test fallback logic
    echo "<h4>🔄 Fallback Logic Test:</h4>";
    
    // OLD METHOD (WooCommerce API - potentially cached/wrong)
    $old_detected_method = $exwf_method;
    $old_detection_source = "exwf_odmethod (WooCommerce API)";
    
    if (empty($old_detected_method)) {
        if (!empty($table_number)) {
            $old_detected_method = 'dinein';
            $old_detection_source = "table number fallback";
        } else {
            // Address comparison
            $billing_address = $order->get_billing_address_1();
            $shipping_address = $order->get_shipping_address_1();
            
            echo "<div class='meta-field'>Billing Address: " . ($billing_address ? "'{$billing_address}'" : "EMPTY") . "</div>";
            echo "<div class='meta-field'>Shipping Address: " . ($shipping_address ? "'{$shipping_address}'" : "EMPTY") . "</div>";
            
            if (!empty($shipping_address) && $shipping_address !== $billing_address) {
                $old_detected_method = 'delivery';
                $old_detection_source = "address comparison (different addresses)";
            } else {
                $old_detected_method = 'takeaway';
                $old_detection_source = "default fallback";
            }
        }
    }
    
    // NEW METHOD (Direct Database - reliable)
    $new_detected_method = $db_exwf; // Use direct database value
    $new_detection_source = "exwf_odmethod (Direct Database)";
    
    if (empty($new_detected_method)) {
        if (!empty($db_table)) {
            $new_detected_method = 'dinein';
            $new_detection_source = "table number fallback (DB)";
        } else {
            // Address comparison (same as before)
            if (!empty($shipping_address) && $shipping_address !== $billing_address) {
                $new_detected_method = 'delivery';
                $new_detection_source = "address comparison (different addresses)";
            } else {
                $new_detected_method = 'takeaway';
                $new_detection_source = "default fallback";
            }
        }
    }
    
    echo "<div class='meta-field'><strong>❌ OLD METHOD (WooCommerce API): <span class='detected'>{$old_detected_method}</span></strong> - {$old_detection_source}</div>";
    echo "<div class='meta-field'><strong>✅ NEW METHOD (Direct Database): <span class='detected'>{$new_detected_method}</span></strong> - {$new_detection_source}</div>";
    
    if ($old_detected_method !== $new_detected_method) {
        echo "<div class='meta-field' style='background: #ffcccc; border-left-color: #cc0000;'><strong>⚠️ MISMATCH DETECTED!</strong> Old method gives different result than new method.</div>";
    } else {
        echo "<div class='meta-field' style='background: #ccffcc; border-left-color: #00cc00;'><strong>✅ METHODS MATCH</strong> Both give same result.</div>";
    }
    
    // TEST THE PROPOSED FIX
    echo "<h4>🧪 TESTING PROPOSED FIX:</h4>";
    
    // Proposed fix function test
    function test_proposed_fix($order) {
        // Use direct database query instead of WooCommerce API to avoid caching issues
        $order_id = $order->get_id();
        $order_method = get_post_meta($order_id, 'exwf_odmethod', true);
        
        // If no exwf_odmethod, determine from other meta with better logic
        if (empty($order_method)) {
            $table_number_check = get_post_meta($order_id, '_oj_table_number', true);
            
            if (!empty($table_number_check)) {
                $order_method = 'dinein';
            } else {
                // Check if it's a delivery order by looking at shipping vs billing
                $billing_address = $order->get_billing_address_1();
                $shipping_address = $order->get_shipping_address_1();
                
                // If shipping address exists and differs from billing, likely delivery
                if (!empty($shipping_address) && $shipping_address !== $billing_address) {
                    $order_method = 'delivery';
                } else {
                    // Default to takeaway
                    $order_method = 'takeaway';
                }
            }
        }
        
        return $order_method;
    }
    
    $fixed_method = test_proposed_fix($order);
    echo "<div class='meta-field'><strong>🔧 PROPOSED FIX RESULT: <span class='detected'>{$fixed_method}</span></strong></div>";
    
    // Compare with current methods
    if ($fixed_method === $new_detected_method) {
        echo "<div class='meta-field' style='background: #ccffcc; border-left-color: #00cc00;'><strong>✅ FIX MATCHES DATABASE METHOD</strong> - Proposed fix gives same result as direct database method.</div>";
    } else {
        echo "<div class='meta-field' style='background: #ffffcc; border-left-color: #cccc00;'><strong>⚠️ FIX DIFFERS FROM DATABASE</strong> - Proposed fix gives different result.</div>";
    }
    
    if ($fixed_method !== $old_detected_method) {
        echo "<div class='meta-field' style='background: #e6f3ff; border-left-color: #0066cc;'><strong>🎯 FIX WILL CHANGE RESULT</strong> - From '{$old_detected_method}' to '{$fixed_method}'</div>";
    } else {
        echo "<div class='meta-field' style='background: #f0f0f0; border-left-color: #666;'><strong>➡️ NO CHANGE</strong> - Fix gives same result as current method.</div>";
    }
    
    // Show all meta for this order
    echo "<h4>🗂️ All Order Meta (for reference):</h4>";
    $all_meta = get_post_meta($order_id);
    echo "<details><summary>Click to expand all meta fields</summary>";
    echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 12px;'>";
    foreach ($all_meta as $key => $values) {
        echo "{$key}: " . print_r($values, true) . "\n";
    }
    echo "</pre></details>";
    
    echo "</div>";
}

echo "<h2>🎯 Summary</h2>";
echo "<p>This debug shows exactly what meta fields are stored and how the detection logic works.</p>";
echo "<p><strong>Look for:</strong></p>";
echo "<ul>";
echo "<li>Are exwf_odmethod values actually stored?</li>";
echo "<li>Are the addresses different for delivery orders?</li>";
echo "<li>Is the fallback logic working correctly?</li>";
echo "</ul>";
?>
