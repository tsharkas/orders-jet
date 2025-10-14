<?php
/**
 * Orders Jet - Manager Screen Navigation Component
 * Shared navigation for all manager pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'manager-screen';

// Navigation items
$nav_items = array(
    'manager-overview' => array(
        'title' => __('Overview', 'orders-jet'),
        'icon' => 'dashicons-chart-bar',
        'description' => __('Dashboard & Analytics', 'orders-jet')
    ),
    'manager-orders' => array(
        'title' => __('Orders Management', 'orders-jet'),
        'icon' => 'dashicons-clipboard',
        'description' => __('Manage Orders & Kitchen', 'orders-jet')
    ),
    'manager-tables' => array(
        'title' => __('Tables Management', 'orders-jet'),
        'icon' => 'dashicons-grid-view',
        'description' => __('Table Layout & Assignments', 'orders-jet')
    ),
    'manager-staff' => array(
        'title' => __('Staff Management', 'orders-jet'),
        'icon' => 'dashicons-groups',
        'description' => __('Staff & Schedules', 'orders-jet')
    ),
    'manager-reports' => array(
        'title' => __('Reports', 'orders-jet'),
        'icon' => 'dashicons-chart-line',
        'description' => __('Analytics & Insights', 'orders-jet')
    ),
    'manager-settings' => array(
        'title' => __('Settings', 'orders-jet'),
        'icon' => 'dashicons-admin-settings',
        'description' => __('System Configuration', 'orders-jet')
    )
);

// Handle manager-screen redirect to overview
if ($current_page === 'manager-screen') {
    $current_page = 'manager-overview';
}
?>

<div class="manager-navigation-wrapper">
    <!-- Main Header -->
    <div class="manager-header">
        <div class="manager-header-content">
            <div class="manager-header-left">
                <h1 class="manager-title">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php _e('Manager Screen', 'orders-jet'); ?>
                </h1>
                <p class="manager-subtitle">
                    <?php echo sprintf(__('Welcome back, %s!', 'orders-jet'), wp_get_current_user()->display_name); ?>
                </p>
            </div>
            <div class="manager-header-right">
                <div class="manager-quick-stats">
                       <div class="quick-stat">
                           <span class="stat-value"><?php echo OJ_Time_Helper::get_local_time('g:i A'); ?></span>
                           <span class="stat-label"><?php _e('Current Time', 'orders-jet'); ?></span>
                       </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="manager-navigation">
        <div class="manager-nav-tabs">
            <?php foreach ($nav_items as $page_slug => $item) : ?>
                <a href="<?php echo admin_url('admin.php?page=' . $page_slug); ?>" 
                   class="manager-nav-tab <?php echo ($current_page === $page_slug) ? 'active' : ''; ?>">
                    <span class="nav-icon dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                    <div class="nav-content">
                        <span class="nav-title"><?php echo esc_html($item['title']); ?></span>
                        <span class="nav-description"><?php echo esc_html($item['description']); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Manager Navigation Styles */
.manager-navigation-wrapper {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    margin: 0 0 20px 0;
    padding: 0;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.manager-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    width: 100%;
    box-sizing: border-box;
}

.manager-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.manager-title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: white;
}

.manager-title .dashicons {
    font-size: 28px;
    vertical-align: middle;
    margin-right: 8px;
}

.manager-subtitle {
    margin: 3px 0 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.manager-quick-stats {
    display: flex;
    gap: 15px;
}

.quick-stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 16px;
    font-weight: 600;
}

.stat-label {
    display: block;
    font-size: 11px;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.manager-navigation {
    background: white;
    padding: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.manager-nav-tabs {
    display: flex;
    width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 0 20px;
    box-sizing: border-box;
}

.manager-nav-tabs::-webkit-scrollbar {
    display: none;
}

.manager-nav-tab {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    text-decoration: none;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.manager-nav-tab:hover {
    color: #333;
    background: #f8f9fa;
    text-decoration: none;
}

.manager-nav-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: #f8f9fa;
}

.nav-icon {
    font-size: 18px;
    margin-right: 8px;
    flex-shrink: 0;
}

.nav-content {
    display: flex;
    flex-direction: column;
}

.nav-title {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.2;
}

.nav-description {
    font-size: 11px;
    color: #999;
    margin-top: 1px;
}

.manager-nav-tab.active .nav-description {
    color: #667eea;
}

/* Responsive Design */
@media (max-width: 768px) {
    .manager-header-content {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .manager-quick-stats {
        gap: 10px;
    }
    
    .manager-nav-tabs {
        padding: 0 15px;
    }
    
    .manager-nav-tab {
        padding: 10px 12px;
    }
    
    .nav-description {
        display: none;
    }
}

@media (max-width: 480px) {
    .manager-navigation-wrapper {
        margin: 0 0 20px 0;
    }
    
    .manager-header {
        padding: 12px 15px;
    }
    
    .manager-title {
        font-size: 20px;
    }
    
    .manager-nav-tabs {
        padding: 0 10px;
    }
    
    .manager-nav-tab {
        padding: 8px 10px;
    }
    
    .nav-title {
        font-size: 12px;
    }
}
</style>
