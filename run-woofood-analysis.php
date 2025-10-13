<?php
/**
 * WooFood Analysis Runner
 * 
 * Simple script to run WooFood analysis and display results
 * Place this file in WordPress root and run via browser or CLI
 */

// Try to load WordPress
if (file_exists('wp-load.php')) {
    require_once 'wp-load.php';
} elseif (file_exists('../wp-load.php')) {
    require_once '../wp-load.php';
} elseif (file_exists('../../wp-load.php')) {
    require_once '../../wp-load.php';
} else {
    die('WordPress not found. Please place this file in WordPress root directory.');
}

// Set content type for browser viewing
if (!defined('WP_CLI') || !WP_CLI) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== ORDERS JET WOOFOOD ANALYSIS ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Site URL: " . home_url() . "\n\n";

// Check system status
echo "1. SYSTEM STATUS CHECK:\n";
echo str_repeat("-", 40) . "\n";

$checks = array(
    'WordPress' => defined('ABSPATH'),
    'WooCommerce' => class_exists('WooCommerce'),
    'WooFood (EX_WooFood)' => class_exists('EX_WooFood'),
    'Orders Jet Plugin' => class_exists('Orders_Jet_Integration'),
    'Orders Jet Analyzer' => class_exists('Orders_Jet_WooFood_Analyzer')
);

foreach ($checks as $component => $status) {
    $icon = $status ? '✅' : '❌';
    $text = $status ? 'ACTIVE' : 'NOT FOUND';
    echo "{$icon} {$component}: {$text}\n";
    
    if ($component === 'WooCommerce' && $status) {
        echo "   └─ Version: " . WC()->version . "\n";
    }
}

echo "\n";

// If WooFood analyzer is available, run the analysis
if (class_exists('Orders_Jet_WooFood_Analyzer')) {
    echo "2. RUNNING WOOFOOD ANALYSIS:\n";
    echo str_repeat("=", 60) . "\n\n";
    
    try {
        $analyzer = new Orders_Jet_WooFood_Analyzer();
        
        // Run full analysis
        $results = $analyzer->run_full_analysis();
        echo $results;
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} else {
    echo "2. ANALYSIS UNAVAILABLE:\n";
    echo str_repeat("-", 40) . "\n";
    echo "Orders Jet WooFood Analyzer class not found.\n";
    echo "Please ensure the Orders Jet plugin is active.\n\n";
    
    // Basic manual checks
    echo "3. MANUAL CHECKS:\n";
    echo str_repeat("-", 40) . "\n";
    
    if (class_exists('EX_WooFood')) {
        echo "✅ WooFood detected! Running basic checks...\n\n";
        
        // Check for WooFood methods
        $woofood_methods = get_class_methods('EX_WooFood');
        echo "EX_WooFood methods (" . count($woofood_methods) . " total):\n";
        foreach (array_slice($woofood_methods, 0, 10) as $method) {
            echo "   - {$method}()\n";
        }
        if (count($woofood_methods) > 10) {
            echo "   ... and " . (count($woofood_methods) - 10) . " more\n";
        }
        echo "\n";
        
        // Check database for WooFood data
        global $wpdb;
        
        echo "Database checks:\n";
        
        // Check for WooFood meta fields
        $meta_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '%exwf%' OR meta_key LIKE '%exwo%'
        ");
        echo "   - WooFood meta fields: {$meta_count} entries\n";
        
        // Check for WooFood options
        $options_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '%exwf%' OR option_name LIKE '%woofood%'
        ");
        echo "   - WooFood options: {$options_count} entries\n";
        
        // Check for products with WooFood data
        $products_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND (pm.meta_key LIKE '%exwf%' OR pm.meta_key LIKE '%exwo%')
        ");
        echo "   - Products with WooFood data: {$products_count} products\n";
        
    } else {
        echo "❌ WooFood not detected.\n";
        echo "Please install and activate the WooFood plugin first.\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Analysis complete! Check results above.\n";

if (class_exists('Orders_Jet_WooFood_Analyzer')) {
    echo "\nFor detailed analysis, visit:\n";
    echo admin_url('admin.php?page=orders-jet-woofood-analyzer') . "\n";
}
?>
