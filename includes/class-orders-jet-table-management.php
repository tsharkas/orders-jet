<?php
/**
 * Orders Jet - Table Management Class
 * Handles table post type, meta boxes, and QR code generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Table_Management {
    
    public function __construct() {
        add_action('init', array($this, 'register_table_post_type'), 20);
        add_action('init', array($this, 'register_table_taxonomies'), 20);
        add_action('admin_init', array($this, 'add_table_meta_boxes'));
        add_action('save_post_oj_table', array($this, 'save_table_meta'));
        add_action('save_post', array($this, 'save_table_meta')); // Fallback hook
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_filter('manage_oj_table_posts_columns', array($this, 'add_table_columns'));
        add_action('manage_oj_table_posts_custom_column', array($this, 'display_table_columns'), 10, 2);
        add_action('wp_ajax_oj_regenerate_qr_code', array($this, 'regenerate_qr_code_ajax'));
        add_action('wp_ajax_oj_flush_rewrite_rules', array($this, 'flush_rewrite_rules_ajax'));
        
        // WooFood location integration
        add_action('add_meta_boxes', array($this, 'add_woofood_location_metabox'));
        
        error_log('Orders Jet: Table Management class initialized with WooFood integration');
    }
    
    /**
     * Register table post type
     */
    public function register_table_post_type() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $labels = array(
            'name' => __('Tables', 'orders-jet'),
            'singular_name' => __('Table', 'orders-jet'),
            'menu_name' => __('Table Management', 'orders-jet'),
            'add_new' => __('Add New Table', 'orders-jet'),
            'add_new_item' => __('Add New Table', 'orders-jet'),
            'edit_item' => __('Edit Table', 'orders-jet'),
            'new_item' => __('New Table', 'orders-jet'),
            'view_item' => __('View Table', 'orders-jet'),
            'search_items' => __('Search Tables', 'orders-jet'),
            'not_found' => __('No tables found', 'orders-jet'),
            'not_found_in_trash' => __('No tables found in trash', 'orders-jet'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=product',
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 56,
            'menu_icon' => 'dashicons-grid-view',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('oj_table', $args);

        // Debug: Check if post type was registered
        if (post_type_exists('oj_table')) {
            error_log('Orders Jet: Table post type registered successfully');
        } else {
            error_log('Orders Jet: Failed to register table post type');
        }
    }
    
    /**
     * Register table taxonomies
     */
    public function register_table_taxonomies() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Table Zones
        $zone_labels = array(
            'name' => __('Table Zones', 'orders-jet'),
            'singular_name' => __('Table Zone', 'orders-jet'),
            'menu_name' => __('Table Zones', 'orders-jet'),
            'search_items' => __('Search Table Zones', 'orders-jet'),
            'all_items' => __('All Table Zones', 'orders-jet'),
            'edit_item' => __('Edit Table Zone', 'orders-jet'),
            'update_item' => __('Update Table Zone', 'orders-jet'),
            'add_new_item' => __('Add New Table Zone', 'orders-jet'),
            'new_item_name' => __('New Table Zone Name', 'orders-jet'),
        );

        register_taxonomy('oj_table_zone', 'oj_table', array(
            'labels' => $zone_labels,
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=product',
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => false,
        ));

        // Debug: Check if taxonomy was registered
        if (taxonomy_exists('oj_table_zone')) {
            error_log('Orders Jet: Table zone taxonomy registered successfully');
        } else {
            error_log('Orders Jet: Failed to register table zone taxonomy');
        }
    }
    
    /**
     * Add table meta boxes
     */
    public function add_table_meta_boxes() {
        if (!is_admin() || !class_exists('WooCommerce') || !post_type_exists('oj_table')) {
            return;
        }
        
        add_meta_box(
            'oj_table_details',
            __('Table Details', 'orders-jet'),
            array($this, 'table_details_meta_box'),
            'oj_table',
            'normal',
            'high'
        );
        
        add_meta_box(
            'oj_table_qr_code',
            __('QR Code', 'orders-jet'),
            array($this, 'table_qr_code_meta_box'),
            'oj_table',
            'side',
            'high'
        );
    }
    
    /**
     * Table details meta box
     */
    public function table_details_meta_box($post) {
        wp_nonce_field('oj_table_meta', 'oj_table_meta_nonce');
        
        $table_number = get_post_meta($post->ID, '_oj_table_number', true);
        $capacity = get_post_meta($post->ID, '_oj_table_capacity', true);
        $status = get_post_meta($post->ID, '_oj_table_status', true);
        $location = get_post_meta($post->ID, '_oj_table_location', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="oj_table_number"><?php _e('Table Number', 'orders-jet'); ?></label></th>
                <td><input type="text" id="oj_table_number" name="oj_table_number" value="<?php echo esc_attr($table_number); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="oj_table_capacity"><?php _e('Capacity', 'orders-jet'); ?></label></th>
                <td><input type="number" id="oj_table_capacity" name="oj_table_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" max="20" /></td>
            </tr>
            <tr>
                <th><label for="oj_table_status"><?php _e('Status', 'orders-jet'); ?></label></th>
                <td>
                    <select id="oj_table_status" name="oj_table_status">
                        <option value="available" <?php selected($status, 'available'); ?>><?php _e('Available', 'orders-jet'); ?></option>
                        <option value="occupied" <?php selected($status, 'occupied'); ?>><?php _e('Occupied', 'orders-jet'); ?></option>
                        <option value="reserved" <?php selected($status, 'reserved'); ?>><?php _e('Reserved', 'orders-jet'); ?></option>
                        <option value="maintenance" <?php selected($status, 'maintenance'); ?>><?php _e('Maintenance', 'orders-jet'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="oj_table_location"><?php _e('Location', 'orders-jet'); ?></label></th>
                <td><input type="text" id="oj_table_location" name="oj_table_location" value="<?php echo esc_attr($location); ?>" class="regular-text" placeholder="<?php _e('e.g., Terrace, Corner, Window', 'orders-jet'); ?>" /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Table QR code meta box
     */
    public function table_qr_code_meta_box($post) {
        $table_number = get_post_meta($post->ID, '_oj_table_number', true);
        $qr_code_url = get_post_meta($post->ID, '_oj_table_qr_code', true);
        
        if (!$qr_code_url && $table_number) {
            $qr_code_url = $this->generate_qr_code($post->ID, $table_number);
        }
        
        ?>
        <div class="oj-qr-code-container">
            <?php if ($qr_code_url): ?>
                <div class="oj-qr-code-image">
                    <img src="<?php echo esc_url($qr_code_url); ?>" alt="<?php echo sprintf(__('QR Code for Table %s', 'orders-jet'), $table_number); ?>" style="max-width: 100%; height: auto;" />
                </div>
                <div class="oj-qr-code-actions">
                    <a href="<?php echo esc_url($qr_code_url); ?>" download="table-<?php echo esc_attr($table_number); ?>-qr.png" class="button button-secondary">
                        <?php _e('Download QR Code', 'orders-jet'); ?>
                    </a>
                    <button type="button" class="button button-secondary oj-regenerate-qr" data-table-id="<?php echo $post->ID; ?>">
                        <?php _e('Regenerate QR Code', 'orders-jet'); ?>
                    </button>
                    <button type="button" class="button button-primary oj-flush-rewrite-rules">
                        <?php _e('Flush Rewrite Rules', 'orders-jet'); ?>
                    </button>
                </div>
                <div class="oj-qr-code-url">
                    <label><?php _e('Menu URL:', 'orders-jet'); ?></label>
                    <input type="text" value="<?php echo esc_attr(home_url('/table-menu/?table=' . $table_number)); ?>" readonly class="widefat" onclick="this.select();" />
                </div>
            <?php else: ?>
                <p><?php _e('Save the table with a table number to generate QR code.', 'orders-jet'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save table meta data
     */
    public function save_table_meta($post_id) {
        error_log("Orders Jet: save_table_meta called for post ID: {$post_id}");
        
        if (get_post_type($post_id) !== 'oj_table') {
            error_log("Orders Jet: Post type is not oj_table, skipping");
            return;
        }
        
        if (!isset($_POST['oj_table_meta_nonce']) || !wp_verify_nonce($_POST['oj_table_meta_nonce'], 'oj_table_meta')) {
            error_log("Orders Jet: Nonce verification failed");
            return;
        }
        
        // Handle WooFood location nonce separately
        $woofood_location_nonce_valid = true;
        if (isset($_POST['oj_woofood_location_nonce'])) {
            $woofood_location_nonce_valid = wp_verify_nonce($_POST['oj_woofood_location_nonce'], 'oj_woofood_location_nonce');
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log("Orders Jet: Autosave in progress, skipping");
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            error_log("Orders Jet: User doesn't have permission to edit post");
            return;
        }
        
        error_log("Orders Jet: All checks passed, proceeding with save");
        
        // Save table meta data
        $meta_fields = array(
            'oj_table_number' => '_oj_table_number',
            'oj_table_capacity' => '_oj_table_capacity',
            'oj_table_status' => '_oj_table_status',
            'oj_table_location' => '_oj_table_location'
        );
        
        // Add WooFood location if nonce is valid
        if ($woofood_location_nonce_valid && class_exists('EX_WooFood')) {
            $meta_fields['oj_woofood_location_id'] = '_oj_woofood_location_id';
        }
        
        foreach ($meta_fields as $form_field => $meta_key) {
            if (isset($_POST[$form_field])) {
                $value = sanitize_text_field($_POST[$form_field]);
                update_post_meta($post_id, $meta_key, $value);
                
                // Debug: Log the save process
                error_log("Orders Jet: Saving meta field - {$form_field} => {$meta_key} = {$value}");
            } else {
                error_log("Orders Jet: Form field not found - {$form_field}");
            }
        }
        
        // Generate QR code if table number is set
        $table_number = get_post_meta($post_id, '_oj_table_number', true);
        error_log("Orders Jet: Table number retrieved: {$table_number}");
        
        if ($table_number) {
            error_log("Orders Jet: Generating QR code for table: {$table_number}");
            $qr_code_url = $this->generate_qr_code($post_id, $table_number);
            error_log("Orders Jet: QR code generated: {$qr_code_url}");
        } else {
            error_log("Orders Jet: No table number found, skipping QR code generation");
        }
    }
    
    /**
     * Generate QR code for table
     */
    public function generate_qr_code($table_id, $table_number) {
        $qr_url = home_url('/table-menu/?table=' . $table_number);
        error_log("Orders Jet: QR URL: {$qr_url}");
        
        // Use QR Server API (free alternative to Google Charts)
        $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_url);
        error_log("Orders Jet: QR Code URL: {$qr_code_url}");
        
        // Save QR code URL to meta
        $saved = update_post_meta($table_id, '_oj_table_qr_code', $qr_code_url);
        error_log("Orders Jet: QR code meta saved: " . ($saved ? 'true' : 'false'));
        
        return $qr_code_url;
    }
    
    /**
     * Regenerate QR code via AJAX
     */
    public function regenerate_qr_code_ajax() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_id = intval($_POST['table_id']);
        $table_number = get_post_meta($table_id, '_oj_table_number', true);
        
        if (!$table_number) {
            wp_send_json_error(array('message' => __('Table number not found', 'orders-jet')));
        }
        
        $qr_code_url = $this->generate_qr_code($table_id, $table_number);
        
        wp_send_json_success(array(
            'qr_code_url' => $qr_code_url,
            'message' => __('QR code regenerated successfully', 'orders-jet')
        ));
    }
    
    /**
     * Flush rewrite rules via AJAX
     */
    public function flush_rewrite_rules_ajax() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
        }
        
        flush_rewrite_rules();
        
        wp_send_json_success(array(
            'message' => __('Rewrite rules flushed successfully', 'orders-jet')
        ));
    }
    
    /**
     * Add custom columns to table list
     */
    public function add_table_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['oj_table_number'] = __('Table Number', 'orders-jet');
        $new_columns['oj_table_capacity'] = __('Capacity', 'orders-jet');
        $new_columns['oj_table_status'] = __('Status', 'orders-jet');
        $new_columns['oj_table_location'] = __('Location', 'orders-jet');
        
        // Add WooFood location column if WooFood is active
        if (class_exists('EX_WooFood')) {
            $new_columns['oj_woofood_location'] = __('WooFood Location', 'orders-jet');
        }
        
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Display custom column content
     */
    public function display_table_columns($column, $post_id) {
        switch ($column) {
            case 'oj_table_number':
                echo esc_html(get_post_meta($post_id, '_oj_table_number', true));
                break;
            case 'oj_table_capacity':
                echo esc_html(get_post_meta($post_id, '_oj_table_capacity', true));
                break;
            case 'oj_table_status':
                $status = get_post_meta($post_id, '_oj_table_status', true);
                $status_labels = array(
                    'available' => __('Available', 'orders-jet'),
                    'occupied' => __('Occupied', 'orders-jet'),
                    'reserved' => __('Reserved', 'orders-jet'),
                    'maintenance' => __('Maintenance', 'orders-jet')
                );
                $label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
                echo '<span class="oj-status-badge oj-status-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
                break;
            case 'oj_table_location':
                echo esc_html(get_post_meta($post_id, '_oj_table_location', true));
                break;
            case 'oj_woofood_location':
                $location_id = get_post_meta($post_id, '_oj_woofood_location_id', true);
                if ($location_id && class_exists('EX_WooFood')) {
                    $location = get_term($location_id, 'exwoofood_loc');
                    if ($location && !is_wp_error($location)) {
                        echo '<span class="oj-woofood-location">' . esc_html($location->name) . '</span>';
                    } else {
                        echo '<span class="oj-no-location">' . __('Invalid Location', 'orders-jet') . '</span>';
                    }
                } else {
                    echo '<span class="oj-no-location">' . __('No Location', 'orders-jet') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'edit.php' || get_post_type() !== 'oj_table') {
            return;
        }
        
        wp_enqueue_style('orders-jet-admin', ORDERS_JET_PLUGIN_URL . 'assets/css/admin.css', array(), ORDERS_JET_VERSION);
        wp_enqueue_script('orders-jet-admin', ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ORDERS_JET_VERSION, true);
        
        wp_localize_script('orders-jet-admin', 'oj_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_table_nonce')
        ));
    }
    
    /**
     * Add WooFood location metabox to table edit screen
     */
    public function add_woofood_location_metabox() {
        // Only add if WooFood is active
        if (!class_exists('EX_WooFood')) {
            return;
        }
        
        add_meta_box(
            'oj_woofood_location',
            __('WooFood Location', 'orders-jet'),
            array($this, 'render_woofood_location_metabox'),
            'oj_table',
            'side',
            'high'
        );
    }
    
    /**
     * Render WooFood location metabox
     */
    public function render_woofood_location_metabox($post) {
        // Get WooFood locations
        $woofood_locations = get_terms(array(
            'taxonomy' => 'exwoofood_loc',
            'hide_empty' => false
        ));
        
        $selected_location = get_post_meta($post->ID, '_oj_woofood_location_id', true);
        
        wp_nonce_field('oj_woofood_location_nonce', 'oj_woofood_location_nonce');
        
        echo '<div class="oj-woofood-location-field">';
        
        if ($woofood_locations && !is_wp_error($woofood_locations)) {
            echo '<label for="oj_woofood_location_id">' . __('Select Location:', 'orders-jet') . '</label>';
            echo '<select name="oj_woofood_location_id" id="oj_woofood_location_id" style="width: 100%; margin-top: 5px;">';
            echo '<option value="">' . __('-- Select Location --', 'orders-jet') . '</option>';
            
            foreach ($woofood_locations as $location) {
                $selected = selected($selected_location, $location->term_id, false);
                echo '<option value="' . esc_attr($location->term_id) . '" ' . $selected . '>';
                echo esc_html($location->name);
                echo '</option>';
            }
            echo '</select>';
            
            if ($selected_location) {
                $location = get_term($selected_location, 'exwoofood_loc');
                if ($location && !is_wp_error($location)) {
                    echo '<div class="oj-location-info" style="margin-top: 10px; padding: 10px; background: #f0f9ff; border: 1px solid #0073aa; border-radius: 4px;">';
                    echo '<strong>' . __('Current Location:', 'orders-jet') . '</strong><br>';
                    echo esc_html($location->name);
                    if ($location->description) {
                        echo '<br><small>' . esc_html($location->description) . '</small>';
                    }
                    echo '</div>';
                }
            }
            
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __('No WooFood locations found. Please create locations in WooFood settings first.', 'orders-jet') . '</p>';
            echo '</div>';
        }
        
        echo '<div class="oj-location-help" style="margin-top: 10px; font-size: 12px; color: #666;">';
        echo '<strong>' . __('Note:', 'orders-jet') . '</strong> ';
        echo __('Assigning a location will filter the menu to show only products available at this location.', 'orders-jet');
        echo '</div>';
        
        echo '</div>';
    }
}
