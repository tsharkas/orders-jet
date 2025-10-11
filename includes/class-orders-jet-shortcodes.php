<?php
/**
 * Orders Jet - Shortcodes Class
 * Handles shortcodes for QR menu, table list, and kitchen display
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Shortcodes {
    
    public function __construct() {
        add_shortcode('orders_jet_qr_menu', array($this, 'qr_menu_shortcode'));
        add_shortcode('orders_jet_table_list', array($this, 'table_list_shortcode'));
        add_shortcode('orders_jet_kitchen_display', array($this, 'kitchen_display_shortcode'));
    }
    
    /**
     * QR Menu shortcode
     */
    public function qr_menu_shortcode($atts) {
        $atts = shortcode_atts(array(
            'table' => '',
        ), $atts);
        
        // Get table number from URL parameter or shortcode attribute
        $table_number = !empty($atts['table']) ? $atts['table'] : (isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '');
        
        if (empty($table_number)) {
            return '<div class="oj-error-message"><h2>' . __('Table Not Specified', 'orders-jet') . '</h2><p>' . __('Please specify a table number.', 'orders-jet') . '</p></div>';
        }
        
        // Get table ID
        $table_id = $this->get_table_id_by_number($table_number);
        
        if (!$table_id) {
            return '<div class="oj-error-message"><h2>' . __('Invalid Table', 'orders-jet') . '</h2><p>' . __('This table number is not valid. Please check your QR code.', 'orders-jet') . '</p></div>';
        }
        
        // Get table information
        $table_capacity = get_post_meta($table_id, '_oj_table_capacity', true);
        $table_status = get_post_meta($table_id, '_oj_table_status', true);
        $table_location = get_post_meta($table_id, '_oj_table_location', true);
        
        // Check if table is available
        if ($table_status !== 'available' && $table_status !== 'occupied') {
            return '<div class="oj-error-message"><h2>' . __('Table Not Available', 'orders-jet') . '</h2><p>' . __('This table is currently not available for ordering.', 'orders-jet') . '</p></div>';
        }
        
        // Get current order for this table
        $current_order = $this->get_current_table_order($table_number);
        
        // Start output buffering
        ob_start();
        
        // Include the QR menu template
        include ORDERS_JET_PLUGIN_DIR . 'templates/qr-menu.php';
        
        return ob_get_clean();
    }
    
    /**
     * Table list shortcode (for staff)
     */
    public function table_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'zone' => '',
        ), $atts);
        
        $args = array(
            'post_type' => 'oj_table',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_oj_table_number'
        );
        
        if (!empty($atts['zone'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'oj_table_zone',
                    'field' => 'slug',
                    'terms' => $atts['zone']
                )
            );
        }
        
        $tables = get_posts($args);
        
        if (empty($tables)) {
            return '<div class="oj-no-tables"><p>' . __('No tables found.', 'orders-jet') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="oj-table-list">
            <div class="oj-table-grid">
                <?php foreach ($tables as $table): ?>
                    <?php
                    $table_id = $table->ID;
                    $table_number = get_post_meta($table_id, '_oj_table_number', true);
                    $capacity = get_post_meta($table_id, '_oj_table_capacity', true);
                    $status = get_post_meta($table_id, '_oj_table_status', true);
                    $location = get_post_meta($table_id, '_oj_table_location', true);
                    $current_order = $this->get_current_table_order($table_number);
                    ?>
                    <div class="oj-table-card oj-status-<?php echo esc_attr($status); ?>" data-table-id="<?php echo $table_id; ?>">
                        <div class="oj-table-header">
                            <h3><?php echo sprintf(__('Table %s', 'orders-jet'), $table_number); ?></h3>
                            <span class="oj-table-status"><?php echo esc_html(ucfirst($status)); ?></span>
                        </div>
                        <div class="oj-table-details">
                            <p><strong><?php _e('Capacity:', 'orders-jet'); ?></strong> <?php echo $capacity; ?> <?php _e('people', 'orders-jet'); ?></p>
                            <?php if ($location): ?>
                                <p><strong><?php _e('Location:', 'orders-jet'); ?></strong> <?php echo esc_html($location); ?></p>
                            <?php endif; ?>
                            <?php if ($current_order): ?>
                                <p><strong><?php _e('Current Order:', 'orders-jet'); ?></strong> #<?php echo $current_order->get_order_number(); ?></p>
                                <p><strong><?php _e('Order Total:', 'orders-jet'); ?></strong> <?php echo $current_order->get_formatted_order_total(); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="oj-table-actions">
                            <button class="oj-table-action-btn oj-view-orders" data-table-id="<?php echo $table_id; ?>">
                                <?php _e('View Orders', 'orders-jet'); ?>
                            </button>
                            <button class="oj-table-action-btn oj-update-status" data-table-id="<?php echo $table_id; ?>" data-current-status="<?php echo esc_attr($status); ?>">
                                <?php _e('Update Status', 'orders-jet'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Kitchen display shortcode
     */
    public function kitchen_display_shortcode($atts) {
        $atts = shortcode_atts(array(
            'status' => 'processing',
        ), $atts);
        
        // Get orders with the specified status
        $orders = wc_get_orders(array(
            'status' => $atts['status'],
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_oj_order_method',
                    'value' => 'dinein',
                    'compare' => '='
                )
            )
        ));
        
        if (empty($orders)) {
            return '<div class="oj-no-orders"><p>' . __('No orders found.', 'orders-jet') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="oj-kitchen-display">
            <div class="oj-kitchen-header">
                <h2><?php _e('Kitchen Display', 'orders-jet'); ?></h2>
                <div class="oj-kitchen-controls">
                    <button class="oj-refresh-orders"><?php _e('Refresh', 'orders-jet'); ?></button>
                    <select class="oj-order-status-filter">
                        <option value="processing" <?php selected($atts['status'], 'processing'); ?>><?php _e('Processing', 'orders-jet'); ?></option>
                        <option value="on-hold" <?php selected($atts['status'], 'on-hold'); ?>><?php _e('On Hold', 'orders-jet'); ?></option>
                    </select>
                </div>
            </div>
            <div class="oj-orders-grid">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $table_number = $order->get_meta('_oj_table_number');
                    $order_time = $order->get_date_created();
                    $items = $order->get_items();
                    ?>
                    <div class="oj-order-card" data-order-id="<?php echo $order->get_id(); ?>">
                        <div class="oj-order-header">
                            <h3><?php echo sprintf(__('Order #%s', 'orders-jet'), $order->get_order_number()); ?></h3>
                            <span class="oj-order-time"><?php echo $order_time->format('H:i'); ?></span>
                        </div>
                        <div class="oj-order-details">
                            <p><strong><?php _e('Table:', 'orders-jet'); ?></strong> <?php echo $table_number; ?></p>
                            <p><strong><?php _e('Total:', 'orders-jet'); ?></strong> <?php echo $order->get_formatted_order_total(); ?></p>
                        </div>
                        <div class="oj-order-items">
                            <?php foreach ($items as $item): ?>
                                <div class="oj-order-item">
                                    <span class="oj-item-quantity"><?php echo $item->get_quantity(); ?>x</span>
                                    <span class="oj-item-name"><?php echo $item->get_name(); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($order->get_customer_note()): ?>
                            <div class="oj-order-notes">
                                <strong><?php _e('Notes:', 'orders-jet'); ?></strong> <?php echo esc_html($order->get_customer_note()); ?>
                            </div>
                        <?php endif; ?>
                        <div class="oj-order-actions">
                            <button class="oj-order-action-btn oj-mark-ready" data-order-id="<?php echo $order->get_id(); ?>">
                                <?php _e('Mark Ready', 'orders-jet'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get table ID by number
     */
    private function get_table_id_by_number($table_number) {
        $posts = get_posts(array(
            'post_type' => 'oj_table',
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        return !empty($posts) ? $posts[0]->ID : false;
    }
    
    /**
     * Get current order for table
     */
    private function get_current_table_order($table_number) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold'),
            'limit' => 1,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        return !empty($orders) ? $orders[0] : false;
    }
}

