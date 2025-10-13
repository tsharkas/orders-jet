<?php
/**
 * Standalone WooFood Analyzer
 * 
 * Upload this file to WordPress root and access directly
 * URL: https://testdash.ordersjet.site/woofood-analyzer-standalone.php
 */

// Load WordPress
require_once 'wp-load.php';

// Security check - only allow logged in admins
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. Please log in as administrator.');
}

// Set content type
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WooFood Analyzer - <?php bloginfo('name'); ?></title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0; 
            padding: 20px; 
            background: #f1f1f1; 
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            border-bottom: 2px solid #0073aa; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .status-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 15px; 
            margin: 20px 0; 
        }
        .status-item { 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            background: #f9f9f9; 
        }
        .status-item.active { 
            border-color: #00a32a; 
            background: #f0f9f0; 
        }
        .status-item.inactive { 
            border-color: #d63638; 
            background: #f9f0f0; 
        }
        .btn { 
            background: #0073aa; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            margin: 10px 10px 10px 0;
        }
        .btn:hover { 
            background: #005a87; 
        }
        .results { 
            background: #f8f9fa; 
            border: 1px solid #e1e5e9; 
            padding: 20px; 
            border-radius: 4px; 
            margin-top: 20px; 
            font-family: 'Courier New', monospace; 
            white-space: pre-wrap; 
            max-height: 600px; 
            overflow-y: auto;
        }
        .loading { 
            text-align: center; 
            padding: 40px; 
            color: #666; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 WooFood Structure Analyzer</h1>
            <p>Comprehensive analysis of WooFood plugin integration opportunities</p>
            <p><strong>Site:</strong> <?php echo home_url(); ?> | <strong>User:</strong> <?php echo wp_get_current_user()->display_name; ?></p>
        </div>

        <div class="section">
            <h2>System Status</h2>
            <div class="status-grid">
                <?php
                $status_items = array(
                    'WordPress' => array(
                        'active' => true,
                        'version' => get_bloginfo('version')
                    ),
                    'WooCommerce' => array(
                        'active' => class_exists('WooCommerce'),
                        'version' => class_exists('WooCommerce') ? WC()->version : 'Not installed'
                    ),
                    'WooFood' => array(
                        'active' => class_exists('EX_WooFood'),
                        'version' => class_exists('EX_WooFood') ? 'Detected' : 'Not installed'
                    ),
                    'Orders Jet' => array(
                        'active' => class_exists('Orders_Jet_Integration'),
                        'version' => defined('ORDERS_JET_VERSION') ? ORDERS_JET_VERSION : 'Unknown'
                    )
                );
                
                foreach ($status_items as $name => $status) {
                    $class = $status['active'] ? 'active' : 'inactive';
                    $icon = $status['active'] ? '✅' : '❌';
                    
                    echo "<div class='status-item {$class}'>";
                    echo "<strong>{$icon} {$name}</strong><br>";
                    echo "Version: {$status['version']}";
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <div class="section">
            <h2>Analysis Tools</h2>
            <button class="btn" onclick="runAnalysis('database')">Analyze Database Structure</button>
            <button class="btn" onclick="runAnalysis('hooks')">Detect Hooks & Filters</button>
            <button class="btn" onclick="runAnalysis('classes')">Analyze Classes & Methods</button>
            <button class="btn" onclick="runAnalysis('api')">Test API Endpoints</button>
            <button class="btn" onclick="runAnalysis('full')" style="background: #d63638;">Run Full Analysis</button>
        </div>

        <div class="section">
            <h2>Analysis Results</h2>
            <div id="results" class="results">
                Click an analysis button above to see results here.
            </div>
        </div>
    </div>

    <script>
    function runAnalysis(type) {
        const resultsDiv = document.getElementById('results');
        resultsDiv.innerHTML = '<div class="loading">Running ' + type + ' analysis...</div>';
        
        <?php if (class_exists('Orders_Jet_WooFood_Analyzer')): ?>
        
        // Use AJAX if analyzer class is available
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resultsDiv.innerHTML = response.data.results;
                        } else {
                            resultsDiv.innerHTML = 'Error: ' + response.data.message;
                        }
                    } catch (e) {
                        resultsDiv.innerHTML = 'Error parsing response: ' + xhr.responseText;
                    }
                } else {
                    resultsDiv.innerHTML = 'AJAX Error: ' + xhr.status + ' - ' + xhr.statusText;
                }
            }
        };
        
        xhr.send('action=oj_run_woofood_analysis&analysis_type=' + type + '&nonce=<?php echo wp_create_nonce('oj_woofood_analysis'); ?>');
        
        <?php else: ?>
        
        // Fallback to page reload with analysis
        window.location.href = '?run_analysis=' + type;
        
        <?php endif; ?>
    }
    </script>
</body>
</html>

<?php
// Handle direct analysis requests
if (isset($_GET['run_analysis']) && class_exists('Orders_Jet_WooFood_Analyzer')) {
    echo '<script>document.getElementById("results").innerHTML = "Running analysis...";</script>';
    
    try {
        $analyzer = new Orders_Jet_WooFood_Analyzer();
        $analysis_type = sanitize_text_field($_GET['run_analysis']);
        
        switch ($analysis_type) {
            case 'database':
                $results = $analyzer->analyze_database_structure();
                break;
            case 'hooks':
                $results = $analyzer->detect_hooks_and_filters();
                break;
            case 'classes':
                $results = $analyzer->analyze_classes_and_methods();
                break;
            case 'api':
                $results = $analyzer->test_api_endpoints();
                break;
            case 'full':
                $results = $analyzer->run_full_analysis();
                break;
            default:
                $results = 'Invalid analysis type';
        }
        
        echo '<script>document.getElementById("results").innerHTML = ' . json_encode($results) . ';</script>';
        
    } catch (Exception $e) {
        echo '<script>document.getElementById("results").innerHTML = "Error: ' . addslashes($e->getMessage()) . '";</script>';
    }
}
?>
