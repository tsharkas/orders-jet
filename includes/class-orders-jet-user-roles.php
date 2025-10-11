<?php
/**
 * Orders Jet - User Roles Class
 * Manages custom user roles and capabilities for restaurant staff
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_User_Roles {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register roles on plugin activation
        register_activation_hook(ORDERS_JET_PLUGIN_FILE, array($this, 'register_roles'));
        
        // Add role management hooks
        add_action('admin_menu', array($this, 'add_staff_management_menu'));
        add_action('admin_init', array($this, 'handle_role_assignment'));
    }
    
    /**
     * Register custom user roles
     */
    public function register_roles() {
        // Manager Role - Full system access
        add_role('oj_manager', __('Restaurant Manager', 'orders-jet'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            
            // Orders Jet specific capabilities
            'manage_oj_staff' => true,
            'view_oj_reports' => true,
            'manage_oj_tables' => true,
            'manage_oj_orders' => true,
            'close_oj_tables' => true,
            'view_oj_financials' => true,
            'assign_oj_waiters' => true,
            'view_oj_analytics' => true,
            'configure_oj_system' => true,
            
            // Access to all dashboards
            'access_oj_manager_dashboard' => true,
            'access_oj_kitchen_dashboard' => true,
            'access_oj_waiter_dashboard' => true,
        ));
        
        // Kitchen Role - Kitchen operations only
        add_role('oj_kitchen', __('Kitchen Staff', 'orders-jet'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            
            // Kitchen specific capabilities
            'view_oj_kitchen_orders' => true,
            'update_oj_order_status' => true,
            'view_oj_kitchen_display' => true,
            'mark_oj_order_preparing' => true,
            'mark_oj_order_ready' => true,
            'view_oj_order_details' => true,
            
            // Dashboard access
            'access_oj_kitchen_dashboard' => true,
        ));
        
        // Waiter Role - Front-of-house operations
        add_role('oj_waiter', __('Waiter', 'orders-jet'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            
            // Waiter specific capabilities
            'claim_oj_tables' => true,
            'view_oj_assigned_tables' => true,
            'deliver_oj_orders' => true,
            'collect_oj_payments' => true,
            'generate_oj_invoices' => true,
            'close_oj_table_session' => true,
            'view_oj_order_details' => true,
            'update_oj_table_status' => true,
            
            // Dashboard access
            'access_oj_waiter_dashboard' => true,
        ));
        
        error_log('Orders Jet: Custom user roles registered successfully');
    }
    
    /**
     * Remove custom roles on plugin deactivation
     */
    public static function remove_roles() {
        remove_role('oj_manager');
        remove_role('oj_kitchen');
        remove_role('oj_waiter');
        
        error_log('Orders Jet: Custom user roles removed');
    }
    
    /**
     * Get role capabilities
     */
    public function get_role_capabilities($role_slug) {
        $capabilities = array(
            'oj_manager' => array(
                'manage_oj_staff',
                'view_oj_reports',
                'manage_oj_tables',
                'manage_oj_orders',
                'close_oj_tables',
                'view_oj_financials',
                'assign_oj_waiters',
                'view_oj_analytics',
                'configure_oj_system',
                'access_oj_manager_dashboard',
                'access_oj_kitchen_dashboard',
                'access_oj_waiter_dashboard',
            ),
            'oj_kitchen' => array(
                'view_oj_kitchen_orders',
                'update_oj_order_status',
                'view_oj_kitchen_display',
                'mark_oj_order_preparing',
                'mark_oj_order_ready',
                'view_oj_order_details',
                'access_oj_kitchen_dashboard',
            ),
            'oj_waiter' => array(
                'claim_oj_tables',
                'view_oj_assigned_tables',
                'deliver_oj_orders',
                'collect_oj_payments',
                'generate_oj_invoices',
                'close_oj_table_session',
                'view_oj_order_details',
                'update_oj_table_status',
                'access_oj_waiter_dashboard',
            ),
        );
        
        return isset($capabilities[$role_slug]) ? $capabilities[$role_slug] : array();
    }
    
    /**
     * Check if current user has capability
     */
    public function check_user_capability($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Get user's Orders Jet role
     */
    public function get_user_oj_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $roles = $user->roles;
        
        // Check for Orders Jet roles
        if (in_array('oj_manager', $roles)) {
            return 'oj_manager';
        } elseif (in_array('oj_kitchen', $roles)) {
            return 'oj_kitchen';
        } elseif (in_array('oj_waiter', $roles)) {
            return 'oj_waiter';
        }
        
        return false;
    }
    
    /**
     * Assign role to user
     */
    public function assign_user_role($user_id, $role_slug) {
        // Verify role exists
        if (!in_array($role_slug, array('oj_manager', 'oj_kitchen', 'oj_waiter'))) {
            return new WP_Error('invalid_role', __('Invalid role specified', 'orders-jet'));
        }
        
        // Check permission
        if (!current_user_can('manage_oj_staff') && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to assign roles', 'orders-jet'));
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found', 'orders-jet'));
        }
        
        // Remove existing Orders Jet roles
        $user->remove_role('oj_manager');
        $user->remove_role('oj_kitchen');
        $user->remove_role('oj_waiter');
        
        // Add new role
        $user->add_role($role_slug);
        
        // Store role in user meta for quick access
        update_user_meta($user_id, '_oj_staff_role', $role_slug);
        
        error_log("Orders Jet: Assigned role {$role_slug} to user {$user_id}");
        
        return true;
    }
    
    /**
     * Get all staff users
     */
    public function get_all_staff($role = null) {
        $args = array(
            'fields' => array('ID', 'display_name', 'user_email'),
        );
        
        if ($role) {
            $args['role__in'] = array($role);
        } else {
            $args['role__in'] = array('oj_manager', 'oj_kitchen', 'oj_waiter');
        }
        
        $users = get_users($args);
        
        // Add role information
        foreach ($users as &$user) {
            $user_obj = get_userdata($user->ID);
            $user->oj_role = $this->get_user_oj_role($user->ID);
            $user->oj_role_name = $this->get_role_display_name($user->oj_role);
        }
        
        return $users;
    }
    
    /**
     * Get role display name
     */
    public function get_role_display_name($role_slug) {
        $names = array(
            'oj_manager' => __('Manager', 'orders-jet'),
            'oj_kitchen' => __('Kitchen Staff', 'orders-jet'),
            'oj_waiter' => __('Waiter', 'orders-jet'),
        );
        
        return isset($names[$role_slug]) ? $names[$role_slug] : __('Unknown', 'orders-jet');
    }
    
    /**
     * Add staff management menu
     */
    public function add_staff_management_menu() {
        // Only for managers and admins
        if (!current_user_can('manage_oj_staff') && !current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'edit.php?post_type=oj_table',
            __('Staff Management', 'orders-jet'),
            __('Staff Management', 'orders-jet'),
            'manage_oj_staff',
            'orders-jet-staff',
            array($this, 'render_staff_management_page')
        );
    }
    
    /**
     * Render staff management page
     */
    public function render_staff_management_page() {
        if (!current_user_can('manage_oj_staff') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Get all WordPress users
        $all_users = get_users(array('fields' => array('ID', 'display_name', 'user_email')));
        $staff_users = $this->get_all_staff();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Staff Management', 'orders-jet'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Current Staff', 'orders-jet'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'orders-jet'); ?></th>
                            <th><?php _e('Email', 'orders-jet'); ?></th>
                            <th><?php _e('Role', 'orders-jet'); ?></th>
                            <th><?php _e('Actions', 'orders-jet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_users)): ?>
                            <tr>
                                <td colspan="4"><?php _e('No staff members found.', 'orders-jet'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_users as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html($user->oj_role_name); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('oj_remove_staff_role'); ?>
                                            <input type="hidden" name="action" value="remove_staff_role">
                                            <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                            <button type="submit" class="button button-small"><?php _e('Remove Role', 'orders-jet'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Assign Staff Role', 'orders-jet'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('oj_assign_staff_role'); ?>
                    <input type="hidden" name="action" value="assign_staff_role">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="user_id"><?php _e('Select User', 'orders-jet'); ?></label>
                            </th>
                            <td>
                                <select name="user_id" id="user_id" required>
                                    <option value=""><?php _e('-- Select User --', 'orders-jet'); ?></option>
                                    <?php foreach ($all_users as $user): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>">
                                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="role_slug"><?php _e('Role', 'orders-jet'); ?></label>
                            </th>
                            <td>
                                <select name="role_slug" id="role_slug" required>
                                    <option value=""><?php _e('-- Select Role --', 'orders-jet'); ?></option>
                                    <option value="oj_manager"><?php _e('Manager', 'orders-jet'); ?></option>
                                    <option value="oj_kitchen"><?php _e('Kitchen Staff', 'orders-jet'); ?></option>
                                    <option value="oj_waiter"><?php _e('Waiter', 'orders-jet'); ?></option>
                                </select>
                                <p class="description">
                                    <strong><?php _e('Manager:', 'orders-jet'); ?></strong> <?php _e('Full system access, staff management, reports', 'orders-jet'); ?><br>
                                    <strong><?php _e('Kitchen:', 'orders-jet'); ?></strong> <?php _e('View and update order status, kitchen display', 'orders-jet'); ?><br>
                                    <strong><?php _e('Waiter:', 'orders-jet'); ?></strong> <?php _e('Table management, order delivery, payment collection', 'orders-jet'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Assign Role', 'orders-jet'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle role assignment form submission
     */
    public function handle_role_assignment() {
        // Assign role
        if (isset($_POST['action']) && $_POST['action'] === 'assign_staff_role') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'oj_assign_staff_role')) {
                wp_die(__('Security check failed', 'orders-jet'));
            }
            
            $user_id = intval($_POST['user_id']);
            $role_slug = sanitize_text_field($_POST['role_slug']);
            
            $result = $this->assign_user_role($user_id, $role_slug);
            
            if (is_wp_error($result)) {
                add_settings_error('oj_staff', 'role_assignment_failed', $result->get_error_message(), 'error');
            } else {
                add_settings_error('oj_staff', 'role_assignment_success', __('Role assigned successfully', 'orders-jet'), 'success');
            }
            
            set_transient('oj_staff_admin_notices', get_settings_errors('oj_staff'), 30);
            
            wp_redirect(admin_url('edit.php?post_type=oj_table&page=orders-jet-staff'));
            exit;
        }
        
        // Remove role
        if (isset($_POST['action']) && $_POST['action'] === 'remove_staff_role') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'oj_remove_staff_role')) {
                wp_die(__('Security check failed', 'orders-jet'));
            }
            
            $user_id = intval($_POST['user_id']);
            $user = get_userdata($user_id);
            
            if ($user) {
                $user->remove_role('oj_manager');
                $user->remove_role('oj_kitchen');
                $user->remove_role('oj_waiter');
                delete_user_meta($user_id, '_oj_staff_role');
                
                add_settings_error('oj_staff', 'role_removal_success', __('Role removed successfully', 'orders-jet'), 'success');
            }
            
            set_transient('oj_staff_admin_notices', get_settings_errors('oj_staff'), 30);
            
            wp_redirect(admin_url('edit.php?post_type=oj_table&page=orders-jet-staff'));
            exit;
        }
        
        // Display notices
        if ($notices = get_transient('oj_staff_admin_notices')) {
            foreach ($notices as $notice) {
                add_settings_error('oj_staff', $notice['code'], $notice['message'], $notice['type']);
            }
            delete_transient('oj_staff_admin_notices');
        }
    }
}

// Global helper functions
function oj_get_user_role($user_id = null) {
    $roles = new Orders_Jet_User_Roles();
    return $roles->get_user_oj_role($user_id);
}

function oj_user_can($capability) {
    return current_user_can($capability);
}

function oj_get_staff_users($role = null) {
    $roles = new Orders_Jet_User_Roles();
    return $roles->get_all_staff($role);
}


