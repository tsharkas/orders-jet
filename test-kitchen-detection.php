<?php
/**
 * Test Kitchen Field Detection
 * Run this after adding Kitchen custom field to products
 */

// Load WordPress
require_once('wp-load.php');

echo "<h2>🍕🥤 Kitchen Field Detection Test</h2>";

// Get some recent products to test
$products = wc_get_products(array(
    'limit' => 10,
    'status' => 'publish'
));

if (empty($products)) {
    echo "<p>❌ No products found</p>";
    exit;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Product ID</th><th>Product Name</th><th>Kitchen Field</th><th>Detected Type</th></tr>";

foreach ($products as $product) {
    $product_id = $product->get_id();
    $product_name = $product->get_name();
    
    // Check for Kitchen custom field
    $kitchen_field = get_post_meta($product_id, 'Kitchen', true);
    
    // Determine kitchen type
    if (!empty($kitchen_field)) {
        $kitchen_type = strtolower(trim($kitchen_field));
    } else {
        $kitchen_type = 'food (default)';
    }
    
    // Color coding
    $color = '';
    if ($kitchen_field === 'food') {
        $color = 'background: #ff6b35; color: white;';
    } elseif ($kitchen_field === 'beverages') {
        $color = 'background: #4ecdc4; color: white;';
    } else {
        $color = 'background: #f8f9fa; color: #666;';
    }
    
    echo "<tr>";
    echo "<td>#{$product_id}</td>";
    echo "<td>{$product_name}</td>";
    echo "<td>" . ($kitchen_field ?: 'Not set') . "</td>";
    echo "<td style='{$color}'>{$kitchen_type}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>📋 Instructions:</h3>";
echo "<ol>";
echo "<li><strong>Add Kitchen field</strong> to products: Name='Kitchen', Value='food' or 'beverages'</li>";
echo "<li><strong>Refresh this page</strong> to see the detection working</li>";
echo "<li><strong>Test mixed order</strong>: Create order with 1 food + 1 beverage product</li>";
echo "</ol>";

echo "<p><strong>Expected Results:</strong></p>";
echo "<ul>";
echo "<li>🍕 Food products: Kitchen='food'</li>";
echo "<li>🥤 Beverage products: Kitchen='beverages'</li>";
echo "<li>🍽️ Mixed orders: Will show dual kitchen workflow</li>";
echo "</ul>";
?>
