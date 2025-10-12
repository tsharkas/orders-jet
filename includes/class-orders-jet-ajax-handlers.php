<?php
/**
 * Orders Jet - AJAX Handlers Class
 * Handles AJAX requests for table ordering system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_AJAX_Handlers {
    
    public function __construct() {
        // AJAX handlers for logged in users
        add_action('wp_ajax_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_oj_get_table_orders', array($this, 'get_table_orders'));
        add_action('wp_ajax_oj_close_table', array($this, 'close_table'));
        
        // AJAX handlers for non-logged in users (guests)
        add_action('wp_ajax_nopriv_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_nopriv_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_nopriv_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_nopriv_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_nopriv_oj_get_table_orders', array($this, 'get_table_orders'));
        add_action('wp_ajax_nopriv_oj_close_table', array($this, 'close_table'));
    }
    
    /**
     * Submit table order (contactless)
     */
    public function submit_table_order() {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // Don't display errors, log them instead
        
        // Log the incoming request
        error_log('Orders Jet: ========== ORDER SUBMISSION START ==========');
        error_log('Orders Jet: Order submission request received');
        error_log('Orders Jet: POST data: ' . print_r($_POST, true));
        error_log('Orders Jet: REQUEST data: ' . print_r($_REQUEST, true));
        error_log('Orders Jet: User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
        error_log('Orders Jet: Current user ID: ' . get_current_user_id());
        
        try {
            check_ajax_referer('oj_table_order', 'nonce');
            
            // We'll handle pricing manually without relying on WooCommerce auto-calculation
        
        // Handle both old and new data formats
        $table_id = 0; // Default value
        
        if (isset($_POST['order_data'])) {
            // New format - JSON data
            $order_data = json_decode(stripslashes($_POST['order_data']), true);
            $table_number = sanitize_text_field($order_data['table_number']);
            $items = $order_data['items'];
            $total = floatval($order_data['total']);
            
            error_log('Orders Jet: Received order data - Total from frontend: ' . $total);
            error_log('Orders Jet: Number of items: ' . count($items));
        } else {
            // Old format - individual fields (backward compatibility)
            $table_number = sanitize_text_field($_POST['table_number']);
            $table_id = intval($_POST['table_id'] ?? 0);
            $special_requests = sanitize_textarea_field($_POST['special_requests'] ?? '');
            $cart_items = $_POST['cart_items'] ?? array();
            
            // Convert old format to new format with backward compatibility
            $items = array();
            foreach ($cart_items as $item) {
                // Handle both new and old cart formats
                $converted_item = array(
                    'product_id' => intval($item['product_id'] ?? $item['id'] ?? 0),
                    'variation_id' => intval($item['variation_id'] ?? 0),
                    'name' => sanitize_text_field($item['name'] ?? ''),
                    'quantity' => intval($item['quantity'] ?? 1),
                    'notes' => sanitize_text_field($item['notes'] ?? ''),
                    'add_ons' => array()
                );
                
                // Handle add-ons (support both old 'addons' and new 'add_ons' format)
                if (!empty($item['add_ons'])) {
                    $converted_item['add_ons'] = $item['add_ons'];
                } elseif (!empty($item['addons'])) {
                    // Convert old addons format to new format
                    foreach ($item['addons'] as $addon) {
                        $converted_item['add_ons'][] = array(
                            'id' => $addon['id'] ?? uniqid(),
                            'name' => $addon['name'] ?? 'Add-on',
                            'price' => floatval($addon['price'] ?? 0),
                            'quantity' => intval($addon['quantity'] ?? 1)
                        );
                    }
                }
                
                // Handle old variations format (complex object structure)
                if (!empty($item['variations']) && $converted_item['variation_id'] == 0) {
                    foreach ($item['variations'] as $variation_data) {
                        if (isset($variation_data['variation_id']) && $variation_data['variation_id'] > 0) {
                            $converted_item['variation_id'] = intval($variation_data['variation_id']);
                            break;
                        }
                    }
                }
                
                $items[] = $converted_item;
            }
        }
        
        // CRITICAL FIX: Get table ID from table number
        if (empty($table_id) || $table_id == 0) {
            $table_id = oj_get_table_id_by_number($table_number);
            error_log('Orders Jet: Retrieved table ID from table number: ' . $table_id);
        }
        
        // Validate required fields
        if (empty($table_number) || empty($items)) {
            wp_send_json_error(array('message' => __('Table number and cart items are required', 'orders-jet')));
        }
        
        // Create WooCommerce order
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            error_log('Orders Jet: Failed to create WooCommerce order: ' . $order->get_error_message());
            wp_send_json_error(array('message' => __('Failed to create order: ' . $order->get_error_message(), 'orders-jet')));
        }
        
        if (!$order) {
            error_log('Orders Jet: Order creation returned null');
            wp_send_json_error(array('message' => __('Failed to create order: Unknown error', 'orders-jet')));
        }
        
        // Add items to order
        foreach ($items as $item) {
            $product_id = intval($item['product_id']);
            $variation_id = intval($item['variation_id'] ?? 0);
            $quantity = intval($item['quantity']);
            $notes = sanitize_text_field($item['notes'] ?? '');
            $add_ons = $item['add_ons'] ?? array();
            
            // Get product
            if ($variation_id > 0) {
                $product = wc_get_product($variation_id);
            } else {
                $product = wc_get_product($product_id);
            }
            
            if (!$product) {
                error_log('Orders Jet: Product not found for ID: ' . $product_id);
                continue;
            }
            
            // Calculate price using WooCommerce native methods
            $base_price = $product->get_price();
            $addon_total = 0;
            
            // Calculate add-ons total
            if (!empty($add_ons)) {
                foreach ($add_ons as $addon) {
                    $addon_price = floatval($addon['price'] ?? 0);
                    $addon_quantity = intval($addon['quantity'] ?? 1);
                    $addon_total += $addon_price * $addon_quantity;
                }
            }
            
            $total_price = $base_price + $addon_total;
            
            // Add product to order using WooCommerce native method
            $item_id = $order->add_product($product, $quantity, array(
                'variation' => ($variation_id > 0) ? $product->get_variation_attributes() : array(),
                'totals' => array(
                    'subtotal' => $total_price * $quantity,
                    'total' => $total_price * $quantity,
                )
            ));
            
            if ($item_id) {
                // Get the order item that was just added
                $order_item = $order->get_item($item_id);
                
                error_log('Orders Jet: Added item using WooCommerce native method - Product: ' . $product->get_name() . ', Total: ' . ($total_price * $quantity));
                
                // Add item notes if any
                if (!empty($notes)) {
                    $order_item->add_meta_data('_oj_item_notes', $notes);
                }
                
                // Store add-ons in WooCommerce-compatible format
                if (!empty($add_ons)) {
                    $addon_names = array();
                    foreach ($add_ons as $addon) {
                        $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                        $addon_price = floatval($addon['price'] ?? 0);
                        $addon_quantity = intval($addon['quantity'] ?? 1);
                        $addon_value = sanitize_text_field($addon['value'] ?? '');
                        
                        if ($addon_quantity > 1) {
                            $addon_names[] = $addon_name . ' × ' . $addon_quantity . ' (+' . wc_price($addon_price * $addon_quantity) . ')';
                        } elseif (!empty($addon_value)) {
                            $addon_names[] = $addon_name . ': ' . $addon_value;
                        } else {
                            $addon_names[] = $addon_name . ' (+' . wc_price($addon_price) . ')';
                        }
                    }
                    
                    $order_item->add_meta_data('_oj_item_addons', implode(', ', $addon_names));
                    $order_item->add_meta_data('_oj_addons_data', $add_ons);
                }
                
                // Store base price for order history display
                $order_item->add_meta_data('_oj_base_price', $base_price);
                
                // Save item meta data
                $order_item->save();
            } else {
                error_log('Orders Jet: Failed to add product to order: ' . $product_id);
            }
        }
        
        // Set order meta data (contactless - no customer details)
        $order->set_billing_first_name('Table ' . $table_number);
        $order->set_billing_last_name('Guest');
        $order->set_billing_phone('N/A');
        $order->set_billing_email('table' . $table_number . '@restaurant.local');
        
        // Set table information
        // Check if this is the first order for this table in this session
        $is_new_session = $this->is_new_table_session($table_number);
        
        // Generate or get existing session ID for this table
        $session_id = $this->get_or_create_table_session($table_number);
        
        $order->update_meta_data('_oj_table_number', $table_number);
        $order->update_meta_data('_oj_table_id', $table_id ?? 0);
        $order->update_meta_data('_oj_order_method', 'dinein');
        $order->update_meta_data('_oj_contactless_order', 'yes');
        $order->update_meta_data('_oj_order_total', $total);
        $order->update_meta_data('_oj_order_timestamp', current_time('mysql'));
        $order->update_meta_data('_oj_session_id', $session_id);
        $order->update_meta_data('_oj_session_start', $is_new_session ? 'yes' : 'no');
        
        // Set order status
        $order->set_status('processing');
        
        // Save order first to ensure all items are saved
        $order_id = $order->save();
        error_log('Orders Jet: Order saved with ID: ' . $order_id);
        
        // Verify the meta data was saved
        $saved_table_number = $order->get_meta('_oj_table_number');
        error_log('Orders Jet: Saved table number meta: ' . $saved_table_number);
        
        // Also check directly in database
        $db_table_number = get_post_meta($order_id, '_oj_table_number', true);
        error_log('Orders Jet: Database table number meta: ' . $db_table_number);
        
        // Log totals before any calculation
        error_log('Orders Jet: Order total before calculation: ' . $order->get_total());
        error_log('Orders Jet: Expected total from frontend: ' . $total);
        
        // Calculate the actual sum of line items to verify
        $calculated_total = 0;
        foreach ($order->get_items() as $item) {
            $line_total = $item->get_total();
            $calculated_total += $line_total;
            error_log('Orders Jet: Line item total: ' . $line_total . ' for ' . $item->get_name());
        }
        error_log('Orders Jet: Calculated total from line items: ' . $calculated_total);
        
        // Use the calculated total if it's correct, otherwise use the frontend total
        $final_total = ($calculated_total > 0) ? $calculated_total : $total;
        
        // Set order totals using WooCommerce meta data
        $order->update_meta_data('_order_total', $final_total);
        $order->update_meta_data('_order_subtotal', $final_total);
        $order->update_meta_data('_order_tax', 0);
        $order->update_meta_data('_order_shipping', 0);
        $order->update_meta_data('_order_shipping_tax', 0);
        $order->update_meta_data('_order_discount', 0);
        $order->update_meta_data('_order_discount_tax', 0);
        
        // Also set the totals using WooCommerce methods
        $order->set_total($final_total);
        
        // Force WooCommerce to recognize the total by updating the database directly
        global $wpdb;
        $wpdb->update(
            $wpdb->postmeta,
            array('meta_value' => $final_total),
            array(
                'post_id' => $order->get_id(),
                'meta_key' => '_order_total'
            )
        );
        
        // Save order with manual totals
        $order->save();
        
        // Log final totals
        error_log('Orders Jet: Final order total: ' . $order->get_total());
        error_log('Orders Jet: Final order subtotal: ' . $order->get_subtotal());
        
        // Update table status to occupied (CRITICAL FIX: Always try to update)
        if ($table_id > 0) {
            update_post_meta($table_id, '_oj_table_status', 'occupied');
            error_log('Orders Jet: Table ' . $table_number . ' (ID: ' . $table_id . ') status updated to occupied');
        } else {
            // If we still don't have table_id, try to get it again and create if needed
            error_log('Orders Jet: WARNING - Table ID still 0, attempting to find/create table: ' . $table_number);
            $table_id = oj_get_table_id_by_number($table_number);
            if ($table_id > 0) {
                update_post_meta($table_id, '_oj_table_status', 'occupied');
                error_log('Orders Jet: Table ' . $table_number . ' (ID: ' . $table_id . ') status updated to occupied (second attempt)');
            } else {
                error_log('Orders Jet: ERROR - Could not find table with number: ' . $table_number);
            }
        }
        
        // Send notification to staff (only if method exists)
        if (method_exists($this, 'send_order_notification')) {
            $this->send_order_notification($order);
        }
        
        // Clear any WooCommerce cache for this order
        wp_cache_delete($order->get_id(), 'posts');
        wp_cache_delete($order->get_id(), 'post_meta');
        
        // Final verification - check if order was actually saved
        error_log('Orders Jet: ========== ORDER SAVED VERIFICATION ==========');
        error_log('Orders Jet: Order ID: ' . $order->get_id());
        error_log('Orders Jet: Order Number: ' . $order->get_order_number());
        error_log('Orders Jet: Order Status: ' . $order->get_status());
        error_log('Orders Jet: Order Total: ' . $order->get_total());
        error_log('Orders Jet: Table Number Meta: ' . $order->get_meta('_oj_table_number'));
        error_log('Orders Jet: Table ID Meta: ' . $order->get_meta('_oj_table_id'));
        error_log('Orders Jet: Order Method Meta: ' . $order->get_meta('_oj_order_method'));
        error_log('Orders Jet: Contactless Order Meta: ' . $order->get_meta('_oj_contactless_order'));
        
        // Verify order exists in database
        $saved_order = wc_get_order($order->get_id());
        if ($saved_order) {
            error_log('Orders Jet: Order verification successful - order exists in database');
            error_log('Orders Jet: Saved order total: ' . $saved_order->get_total());
            error_log('Orders Jet: Saved order table number: ' . $saved_order->get_meta('_oj_table_number'));
        } else {
            error_log('Orders Jet: ERROR - Order verification failed - order NOT found in database');
        }
        
        error_log('Orders Jet: ========== ORDER SUBMISSION COMPLETE ==========');
        
        wp_send_json_success(array(
            'message' => __('Order placed successfully', 'orders-jet'),
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total' => $final_total
        ));
        
        } catch (Exception $e) {
            error_log('Orders Jet: Order submission error: ' . $e->getMessage());
            error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => __('Order submission failed: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    /**
     * Get table status
     */
    public function get_table_status() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $table_id = $this->get_table_id_by_number($table_number);
        
        if (!$table_id) {
            wp_send_json_error(array('message' => __('Table not found', 'orders-jet')));
        }
        
        $status = get_post_meta($table_id, '_oj_table_status', true);
        $capacity = get_post_meta($table_id, '_oj_table_capacity', true);
        $location = get_post_meta($table_id, '_oj_table_location', true);
        
        wp_send_json_success(array(
            'table_id' => $table_id,
            'status' => $status,
            'capacity' => $capacity,
            'location' => $location
        ));
    }
    
    /**
     * Get table ID by number (AJAX)
     */
    public function get_table_id_by_number_ajax() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $table_id = $this->get_table_id_by_number($table_number);
        
        if ($table_id) {
            wp_send_json_success(array('table_id' => $table_id));
        } else {
            wp_send_json_error(array('message' => __('Table not found', 'orders-jet')));
        }
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
     * Send order notification to staff
     */
    private function send_order_notification($order) {
        // Get restaurant email (you can customize this)
        $restaurant_email = get_option('admin_email');
        $table_number = $order->get_meta('_oj_table_number');
        
        $subject = sprintf(__('New Order from Table %s', 'orders-jet'), $table_number);
        $message = sprintf(__('A new order has been placed from Table %s. Order #%s', 'orders-jet'), $table_number, $order->get_order_number());
        
        // Add order details
        $message .= "\n\n" . __('Order Details:', 'orders-jet') . "\n";
        $message .= __('Order Number:', 'orders-jet') . ' ' . $order->get_order_number() . "\n";
        $message .= __('Table:', 'orders-jet') . ' ' . $table_number . "\n";
        $message .= __('Total:', 'orders-jet') . ' ' . $order->get_formatted_order_total() . "\n";
        
        if ($order->get_customer_note()) {
            $message .= __('Special Requests:', 'orders-jet') . ' ' . $order->get_customer_note() . "\n";
        }
        
        $message .= "\n" . __('View Order:', 'orders-jet') . ' ' . admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        
        wp_mail($restaurant_email, $subject, $message);
    }
    
    /**
     * Get product details including add-ons and food information
     */
    public function get_product_details() {
        try {
            check_ajax_referer('oj_product_details', 'nonce');
            
            $product_id = intval($_POST['product_id']);
            
            if (!$product_id) {
                wp_send_json_error(array('message' => __('Invalid product ID', 'orders-jet')));
            }
            
            $product = wc_get_product($product_id);
            
            if (!$product) {
                wp_send_json_error(array('message' => __('Product not found', 'orders-jet')));
            }
        
        // Debug: Get ALL meta fields for this product to see what's available
        $all_meta = get_post_meta($product_id);
        $debug_info = array();
        
        // Show ALL meta fields, not just food-related ones
        foreach ($all_meta as $key => $value) {
            // Only show non-empty values and limit to reasonable size
            if (!empty($value) && !is_array($value) || (is_array($value) && count($value) < 50)) {
                $debug_info[$key] = $value;
            }
        }
        
        $response_data = array(
            'food_info' => array(),
            'addons' => array(),
            'variations' => array(),
            'debug' => $debug_info // Temporary debug info
        );
        
        // Check for Exfood plugin exwo_options field (serialized data)
        $exwo_options = get_post_meta($product_id, 'exwo_options', true);
        if ($exwo_options) {
            // Parse the serialized data
            $options_data = maybe_unserialize($exwo_options);
            if (is_array($options_data)) {
                foreach ($options_data as $option) {
                    if (isset($option['_name']) && !empty($option['_name'])) {
                        $addon = array(
                            'id' => $option['_id'] ?? uniqid(),
                            'name' => $option['_name'],
                            'type' => isset($option['_type']) ? $option['_type'] : 'checkbox',
                            'required' => !empty($option['_required']),
                            'min_selections' => intval($option['_min_op'] ?? 0),
                            'max_selections' => intval($option['_max_op'] ?? 0),
                            'min_opqty' => intval($option['_min_opqty'] ?? 0),
                            'max_opqty' => intval($option['_max_opqty'] ?? 0),
                            'enb_qty' => !empty($option['_enb_qty']),
                            'enb_img' => !empty($option['_enb_img']),
                            'display_type' => isset($option['_display_type']) ? $option['_display_type'] : '',
                            'price' => isset($option['_price']) ? floatval($option['_price']) : 0,
                            'price_type' => isset($option['_price_type']) ? $option['_price_type'] : '',
                            'options' => array()
                        );
                        
                        // Process options if they exist
                        if (isset($option['_options']) && is_array($option['_options'])) {
                            foreach ($option['_options'] as $key => $opt) {
                                if (isset($opt['name']) && !empty($opt['name'])) {
                                    $addon['options'][] = array(
                                        'id' => $key,
                                        'name' => $opt['name'],
                                        'price' => isset($opt['price']) ? floatval($opt['price']) : 0,
                                        'type' => isset($opt['type']) ? $opt['type'] : '',
                                        'def' => isset($opt['def']) ? $opt['def'] : '',
                                        'dis' => isset($opt['dis']) ? $opt['dis'] : '',
                                        'min' => isset($opt['min']) ? intval($opt['min']) : 0,
                                        'max' => isset($opt['max']) ? intval($opt['max']) : 0,
                                        'image' => isset($opt['image']) ? $opt['image'] : ''
                                    );
                                }
                            }
                        }
                        
                        $response_data['addons'][] = $addon;
                    }
                }
            }
        }
        
        // Get WooCommerce Food plugin data
        // Check for WooCommerce Food plugin meta fields
        $food_meta_fields = array(
            '_food_info', '_food_nutrition', '_food_allergens', '_food_calories',
            '_food_prep_time', '_food_cooking_time', '_food_serving_size'
        );
        
        foreach ($food_meta_fields as $field) {
            $value = get_post_meta($product_id, $field, true);
            if ($value) {
                $field_name = ucwords(str_replace(array('_food_', '_'), array('', ' '), $field));
                $response_data['food_info'][$field_name] = $value;
            }
        }
        
        // Check for WooCommerce Food plugin add-ons and options
        $food_addons = get_post_meta($product_id, '_food_addons', true);
        if ($food_addons && is_array($food_addons)) {
            foreach ($food_addons as $addon) {
                $response_data['addons'][] = array(
                    'id' => $addon['id'] ?? uniqid(),
                    'name' => $addon['name'] ?? '',
                    'price' => wc_price($addon['price'] ?? 0),
                    'required' => $addon['required'] ?? false,
                    'max_selections' => $addon['max_selections'] ?? null
                );
            }
        }
        
        // Check for WooCommerce Food plugin options/groups
        $food_options = get_post_meta($product_id, '_food_options', true);
        if ($food_options && is_array($food_options)) {
            foreach ($food_options as $option_group) {
                $response_data['addons'][] = array(
                    'id' => $option_group['id'] ?? uniqid(),
                    'name' => $option_group['title'] ?? $option_group['name'] ?? '',
                    'price' => '',
                    'required' => $option_group['required'] ?? false,
                    'max_selections' => $option_group['max_selections'] ?? null,
                    'options' => $option_group['options'] ?? array()
                );
            }
        }
        
        // Check for Exfood plugin specific fields (based on debug info showing exwf_ prefix)
        $exfood_fields = array(
            'exwf_addons', 'exwf_options', 'exwf_extra_items', 'exwf_product_addons',
            '_exwf_addons', '_exwf_options', '_exwf_extra_items', '_exwf_product_addons',
            'exwf_product_data', '_exwf_product_data'
        );
        
        foreach ($exfood_fields as $field) {
            $addon_data = get_post_meta($product_id, $field, true);
            if ($addon_data && is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    $response_data['addons'][] = array(
                        'id' => $addon['id'] ?? uniqid(),
                        'name' => $addon['title'] ?? $addon['name'] ?? '',
                        'price' => isset($addon['price']) ? wc_price($addon['price']) : '',
                        'required' => $addon['required'] ?? false,
                        'max_selections' => $addon['max_selections'] ?? null,
                        'options' => $addon['options'] ?? array()
                    );
                }
            }
        }
        
        // Alternative: Check for common WooCommerce Food plugin field names
        $alternative_fields = array(
            '_woo_food_addons', '_exfood_addons', '_food_extra_options',
            '_product_addons', '_additional_options', '_food_customization'
        );
        
        foreach ($alternative_fields as $field) {
            $addon_data = get_post_meta($product_id, $field, true);
            if ($addon_data && is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    $response_data['addons'][] = array(
                        'id' => $addon['id'] ?? uniqid(),
                        'name' => $addon['title'] ?? $addon['name'] ?? '',
                        'price' => isset($addon['price']) ? wc_price($addon['price']) : '',
                        'required' => $addon['required'] ?? false,
                        'max_selections' => $addon['max_selections'] ?? null,
                        'options' => $addon['options'] ?? array()
                    );
                }
            }
        }
        
        // Get product variations - Use WooCommerce standard approach
        if ($product->is_type('variable')) {
            try {
                error_log('Orders Jet: Processing variable product with ID: ' . $product_id);
                
                // Use WooCommerce's get_available_variations() method
                $available_variations = $product->get_available_variations();
                error_log('Orders Jet: Found ' . count($available_variations) . ' available variations');
                
                // Get attributes
                $attributes = $product->get_variation_attributes();
                error_log('Orders Jet: Product attributes: ' . print_r($attributes, true));
                
                foreach ($attributes as $attribute_name => $options) {
                    $attribute_label = wc_attribute_label($attribute_name);
                    $variation_options = array();
                    
                    error_log('Orders Jet: Processing attribute: ' . $attribute_name . ' with options: ' . implode(', ', $options));
                    
                    foreach ($options as $option) {
                        $variation_price = 0;
                        $variation_id = 0;
                        
                        // Find matching variation in available_variations
                        foreach ($available_variations as $variation_data) {
                            $variation_attributes = $variation_data['attributes'];
                            
                            // Check if this variation matches the current option
                            foreach ($variation_attributes as $attr_key => $attr_value) {
                                // Handle different attribute key formats
                                $possible_keys = array(
                                    $attribute_name,
                                    'attribute_' . $attribute_name,
                                    'attribute_pa_' . $attribute_name,
                                    'pa_' . $attribute_name
                                );
                                
                                if (in_array($attr_key, $possible_keys) && $attr_value === $option) {
                                    $variation_price = $variation_data['display_price'];
                                    $variation_id = $variation_data['variation_id'];
                                    error_log('Orders Jet: MATCH! Found variation for ' . $option . ' - Price: ' . $variation_price . ', ID: ' . $variation_id);
                                    break 2;
                                }
                            }
                        }
                        
                        if ($variation_price == 0) {
                            error_log('Orders Jet: No variation found for attribute ' . $attribute_name . ' = ' . $option);
                        }
                        
                        $variation_options[] = array(
                            'value' => $option,
                            'label' => $option,
                            'price' => $variation_price,
                            'variation_id' => $variation_id,
                            'price_display' => $variation_price > 0 ? wc_price($variation_price) : ''
                        );
                    }
                    
                    $response_data['variations'][$attribute_label] = $variation_options;
                }
            } catch (Exception $e) {
                error_log('Orders Jet: Error processing variations: ' . $e->getMessage());
                $response_data['variations'] = array();
            }
        }
        
        // Alternative: Check for custom WooCommerce Food plugin fields
        if (empty($response_data['addons'])) {
            // Check for custom add-ons meta
            $custom_addons = get_post_meta($product_id, '_food_addons', true);
            if ($custom_addons && is_array($custom_addons)) {
                foreach ($custom_addons as $addon) {
                    $response_data['addons'][] = array(
                        'id' => $addon['id'] ?? uniqid(),
                        'name' => $addon['name'] ?? '',
                        'price' => wc_price($addon['price'] ?? 0),
                        'required' => $addon['required'] ?? false
                    );
                }
            }
        }
        
        // Check for WooCommerce Product Add-ons plugin
        if (empty($response_data['addons']) && function_exists('WC_Product_Addons')) {
            $addon_data = WC_Product_Addons()->get_product_addons($product_id);
            if ($addon_data) {
                foreach ($addon_data as $addon) {
                    foreach ($addon['options'] as $option) {
                        $response_data['addons'][] = array(
                            'id' => $addon['id'] . '_' . $option['label'],
                            'name' => $option['label'],
                            'price' => wc_price($option['price']),
                            'required' => $addon['required']
                        );
                    }
                }
            }
        }
        
        // Check for WooCommerce Food plugin specific structure
        // Look for common WooCommerce Food plugin patterns
        $food_plugin_fields = array(
            '_woo_food_product_data', '_exfood_product_data', '_food_product_data',
            '_product_food_options', '_food_customization_options', '_food_extra_items'
        );
        
        foreach ($food_plugin_fields as $field) {
            $data = get_post_meta($product_id, $field, true);
            if ($data && is_array($data)) {
                // Process the data structure based on common patterns
                if (isset($data['addons']) || isset($data['options']) || isset($data['extras'])) {
                    $addon_sections = $data['addons'] ?? $data['options'] ?? $data['extras'] ?? array();
                    
                    foreach ($addon_sections as $section) {
                        $response_data['addons'][] = array(
                            'id' => $section['id'] ?? uniqid(),
                            'name' => $section['title'] ?? $section['name'] ?? '',
                            'price' => '',
                            'required' => $section['required'] ?? false,
                            'max_selections' => $section['max_selections'] ?? null,
                            'options' => $section['options'] ?? array()
                        );
                    }
                }
            }
        }
        
        // Check if WooCommerce Food plugin has a specific function
        if (function_exists('exfood_get_product_addons')) {
            $addons = exfood_get_product_addons($product_id);
            if ($addons) {
                foreach ($addons as $addon) {
                    $response_data['addons'][] = array(
                        'id' => $addon['id'] ?? uniqid(),
                        'name' => $addon['name'] ?? '',
                        'price' => isset($addon['price']) ? wc_price($addon['price']) : '',
                        'required' => $addon['required'] ?? false
                    );
                }
            }
        }
        
        // Additional debug: Check if WooCommerce Food plugin is active
        $active_plugins = get_option('active_plugins');
        $food_plugins = array();
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'food') !== false || strpos($plugin, 'restaurant') !== false) {
                $food_plugins[] = $plugin;
            }
        }
        
        $response_data['debug']['active_food_plugins'] = $food_plugins;
        $response_data['debug']['product_type'] = $product->get_type();
        $response_data['debug']['product_attributes'] = $product->get_attributes();
        
        wp_send_json_success($response_data);
        
        } catch (Exception $e) {
            error_log('Orders Jet: Error in get_product_details: ' . $e->getMessage());
            error_log('Orders Jet: Error trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Error loading product details: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get table orders for order history
     */
    public function get_table_orders() {
        check_ajax_referer('oj_table_order', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        
        error_log('Orders Jet: Getting orders for table: ' . $table_number);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // Check table status to determine which orders to show
        $table_id = oj_get_table_id_by_number($table_number);
        $table_status = $table_id ? get_post_meta($table_id, '_oj_table_status', true) : '';
        
        // If table is available, only show pending/processing orders (new session)
        // If table is occupied, show all orders (current session)
        if ($table_status === 'available') {
            $post_statuses = array('wc-pending', 'wc-processing', 'wc-on-hold');
            error_log('Orders Jet: Table is available - showing only pending orders for new session');
        } else {
            $post_statuses = array(
                'wc-pending',
                'wc-processing', 
                'wc-on-hold',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed'
            );
            error_log('Orders Jet: Table is occupied - showing all orders for current session');
        }
        
        // Get orders for this table using WooCommerce's proper method
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => $post_statuses,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        error_log('Orders Jet: Query args: ' . print_r($args, true));
        
        // Also try to get all recent orders to debug
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array(
                'wc-pending',
                'wc-processing', 
                'wc-on-hold',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed'
            ),
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        error_log('Orders Jet: Recent orders count: ' . count($recent_orders));
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $table_meta = $order->get_meta('_oj_table_number');
                $total = $order->get_total();
                $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                error_log('Orders Jet: Recent order #' . $order->get_id() . ' - Table: "' . $table_meta . '" - Status: ' . $order->get_status() . ' - Total: ' . $total . ' - Billing: ' . $billing_name);
            }
        }
        
        // Specifically check for order ID 214
        $test_order = wc_get_order(214);
        if ($test_order) {
            error_log('Orders Jet: Found order 214 - Status: ' . $test_order->get_status() . ' - Table: "' . $test_order->get_meta('_oj_table_number') . '" - Total: ' . $test_order->get_total());
        } else {
            error_log('Orders Jet: Order 214 NOT FOUND');
        }
        
        $orders = get_posts($args);
        $order_data = array();
        $total_amount = 0;
        
        error_log('Orders Jet: Found ' . count($orders) . ' orders for table ' . $table_number);
        
        // Log the results of the main query
        error_log('Orders Jet: Main query found ' . count($orders) . ' orders for table ' . $table_number);
        
        // If no orders found with get_posts, try WooCommerce's native method
        if (count($orders) == 0 && function_exists('wc_get_orders')) {
            error_log('Orders Jet: Trying WooCommerce native wc_get_orders method...');
            
            // Convert post statuses to WooCommerce statuses for fallback query
            $wc_statuses = array();
            foreach ($post_statuses as $status) {
                $wc_statuses[] = str_replace('wc-', '', $status);
            }
            
            $wc_orders = wc_get_orders(array(
                'status' => $wc_statuses,
                'meta_key' => '_oj_table_number',
                'meta_value' => $table_number,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            error_log('Orders Jet: WooCommerce native method found ' . count($wc_orders) . ' orders');
            
            if (count($wc_orders) > 0) {
                // Convert WC_Order objects to post objects for consistency
                $orders = array();
                foreach ($wc_orders as $wc_order) {
                    $post = get_post($wc_order->get_id());
                    if ($post) {
                        $orders[] = $post;
                    }
                }
                error_log('Orders Jet: Converted ' . count($orders) . ' WooCommerce orders to post objects');
            }
        }
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) continue;
            
            $order_items = array();
            foreach ($order->get_items() as $item) {
                // Get basic item info
                $item_data = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => wc_price($item->get_total()),
                    'unit_price' => wc_price($item->get_total() / $item->get_quantity()),
                    'base_price' => 0, // Will be set below for variant products
                    'variations' => array(),
                    'addons' => array(),
                    'notes' => ''
                );
                
                // Get variations using WooCommerce native methods first
                $product = $item->get_product();
                if ($product && $product->is_type('variation')) {
                    // For variation products, get variation attributes directly
                    $variation_attributes = $product->get_variation_attributes();
                    foreach ($variation_attributes as $attribute_name => $attribute_value) {
                        if (!empty($attribute_value)) {
                            // Clean attribute name and get proper label
                            $clean_attribute_name = str_replace('attribute_', '', $attribute_name);
                            $attribute_label = wc_attribute_label($clean_attribute_name);
                            $item_data['variations'][$attribute_label] = $attribute_value;
                        }
                    }
                }
                
                // Get item meta data for add-ons, notes, and custom variations
                $item_meta = $item->get_meta_data();
                foreach ($item_meta as $meta) {
                    $meta_key = $meta->key;
                    $meta_value = $meta->value;
                    
                    // Get add-ons (prefer structured data if available)
                    if ($meta_key === '_oj_addons_data' && is_array($meta_value)) {
                        $item_data['addons'] = array_map(function($addon) {
                            return $addon['name'] . ' (+' . wc_price($addon['price']) . ')';
                        }, $meta_value);
                    } elseif ($meta_key === '_oj_item_addons' && empty($item_data['addons'])) {
                        $addons = explode(', ', $meta_value);
                        $item_data['addons'] = array_map(function($addon) {
                            return strip_tags($addon);
                        }, $addons);
                    }
                    
                    // Get custom variations (for non-variation products)
                    if ($meta_key === '_oj_variations_data' && is_array($meta_value) && empty($item_data['variations'])) {
                        foreach ($meta_value as $variation) {
                            $item_data['variations'][$variation['name']] = $variation['value'] ?? $variation['name'];
                        }
                    } elseif ($meta_key === '_oj_item_variations' && empty($item_data['variations'])) {
                        // Parse the old format as fallback
                        $variations = explode(', ', $meta_value);
                        foreach ($variations as $variation_string) {
                            if (preg_match('/^(.+?)\s*\(\+/', $variation_string, $matches)) {
                                $item_data['variations'][$matches[1]] = $matches[1];
                            }
                        }
                    }
                    
                    // Also check for standard WooCommerce variation attributes in meta (fallback)
                    if (empty($item_data['variations']) && (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0)) {
                        $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                        $attribute_label = wc_attribute_label($attribute_name);
                        $item_data['variations'][$attribute_label] = $meta_value;
                    }
                    
                    // Get notes
                    if ($meta_key === '_oj_item_notes') {
                        $item_data['notes'] = $meta_value;
                    }
                }
                
                // Try to get the correct base price from stored order data
                $base_price_found = false;
                
                // Check if we stored the original variant price in meta data
                $stored_base_price = $item->get_meta('_oj_base_price');
                if ($stored_base_price) {
                    $item_data['base_price'] = floatval($stored_base_price);
                    $base_price_found = true;
                    error_log('Orders Jet: Using stored base price: ' . $item_data['base_price']);
                }
                
                // If no stored price, try to calculate from current data
                if (!$base_price_found) {
                    $product = $item->get_product();
                    
                    // Debug logging
                    error_log('Orders Jet: Product ID: ' . ($product ? $product->get_id() : 'null'));
                    error_log('Orders Jet: Product type: ' . ($product ? $product->get_type() : 'null'));
                    error_log('Orders Jet: Item total: ' . $item->get_total());
                    error_log('Orders Jet: Item quantity: ' . $item->get_quantity());
                    
                    // Check if this is a variation product
                    if ($product && $product->is_type('variation')) {
                        $variation_price = $product->get_price();
                        $item_data['base_price'] = $variation_price;
                        $base_price_found = true;
                        error_log('Orders Jet: Variation product, price: ' . $variation_price);
                        
                        // Get variation attributes
                        $variation_attributes = $product->get_variation_attributes();
                        foreach ($variation_attributes as $attribute_name => $attribute_value) {
                            if (!empty($attribute_value)) {
                                $attribute_label = wc_attribute_label($attribute_name);
                                $item_data['variations'][$attribute_label] = $attribute_value;
                            }
                        }
                    } else {
                        // For non-variation products, calculate base price by subtracting add-ons
                        $addon_total = 0;
                        if (!empty($item_data['addons'])) {
                            foreach ($item_data['addons'] as $addon_string) {
                                // Extract price from add-on string like "Extra 2 (+100.00 EGP)"
                                preg_match('/\(([^)]+)\)/', $addon_string, $matches);
                                if (isset($matches[1])) {
                                    $price_string = $matches[1];
                                    preg_match('/[\d,]+\.?\d*/', $price_string, $price_matches);
                                    if (isset($price_matches[0])) {
                                        $addon_price = floatval(str_replace(',', '.', $price_matches[0]));
                                        $addon_total += $addon_price * $item->get_quantity();
                                    }
                                }
                            }
                        }
                        
                        $item_total = $item->get_total();
                        $base_price = ($item_total - $addon_total) / $item->get_quantity();
                        $item_data['base_price'] = $base_price;
                        $base_price_found = true;
                        
                        error_log('Orders Jet: Calculated base price: ' . $base_price);
                        error_log('Orders Jet: Item total: ' . $item_total);
                        error_log('Orders Jet: Addon total: ' . $addon_total);
                    }
                }
                
                error_log('Orders Jet: Final base_price: ' . $item_data['base_price']);
                
                $order_items[] = $item_data;
            }
            
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => wc_price($order->get_total()),
                'items' => $order_items,
                'date' => $order->get_date_created()->format('Y-m-d H:i:s')
            );
            
            $total_amount += $order->get_total();
        }
        
        // Prepare debug information
        $debug_info = array();
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $debug_info[] = array(
                    'id' => $order->get_id(),
                    'table' => $order->get_meta('_oj_table_number'),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'billing' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
                );
            }
        }
        
        wp_send_json_success(array(
            'orders' => $order_data,
            'total' => $total_amount,
            'debug' => array(
                'searched_table' => $table_number,
                'recent_orders' => $debug_info
            )
        ));
    }
    
    /**
     * Close table and generate invoice
     */
    public function close_table() {
        check_ajax_referer('oj_table_order', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // Get table ID
        $table_id = oj_get_table_id_by_number($table_number);
        if (!$table_id) {
            wp_send_json_error(array('message' => __('Table not found', 'orders-jet')));
        }
        
        // Get all orders for this table using proper WooCommerce statuses
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => array(
                'wc-pending',
                'wc-processing', 
                'wc-on-hold',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed'
            ),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        );
        
        $orders = get_posts($args);
        $total_amount = 0;
        
        error_log('Orders Jet: Close table - Found ' . count($orders) . ' orders for table ' . $table_number);
        
        // If no orders found with get_posts, try WooCommerce native method
        if (count($orders) == 0 && function_exists('wc_get_orders')) {
            error_log('Orders Jet: Close table - Trying WooCommerce native method...');
            
            $wc_orders = wc_get_orders(array(
                'status' => array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'),
                'meta_key' => '_oj_table_number',
                'meta_value' => $table_number,
                'limit' => -1
            ));
            
            error_log('Orders Jet: Close table - WooCommerce native method found ' . count($wc_orders) . ' orders');
            
            if (count($wc_orders) > 0) {
                // Convert WC_Order objects to post objects for consistency
                $orders = array();
                foreach ($wc_orders as $wc_order) {
                    $post = get_post($wc_order->get_id());
                    if ($post) {
                        $orders[] = $post;
                    }
                }
                error_log('Orders Jet: Close table - Converted ' . count($orders) . ' WooCommerce orders to post objects');
            }
        }
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order) {
                $status = $order->get_status();
                $total = $order->get_total();
                $table_meta = $order->get_meta('_oj_table_number');
                error_log('Orders Jet: Close table - Order #' . $order->get_id() . ' - Status: "' . $status . '" - Total: ' . $total . ' - Table Meta: "' . $table_meta . '"');
                
                // Include orders that are processing or pending (not completed yet)
                if (in_array($status, array('processing', 'pending', 'on-hold'))) {
                    $total_amount += $total;
                    error_log('Orders Jet: Close table - Added order #' . $order->get_id() . ' to total. New total: ' . $total_amount);
                } else {
                    error_log('Orders Jet: Close table - Skipped order #' . $order->get_id() . ' because status "' . $status . '" is not pending/processing/on-hold');
                }
            } else {
                error_log('Orders Jet: Close table - Failed to get order object for post ID: ' . $order_post->ID);
            }
        }
        
        error_log('Orders Jet: Close table - Final total amount for pending orders: ' . $total_amount);
        
        if ($total_amount == 0) {
            wp_send_json_error(array('message' => __('No pending orders found for this table', 'orders-jet')));
        }
        
        // Update table status to available
        update_post_meta($table_id, '_oj_table_status', 'available');
        
        // Mark all pending/processing orders as completed
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order && in_array($order->get_status(), array('processing', 'pending', 'on-hold'))) {
                $order->set_status('completed');
                $order->update_meta_data('_oj_payment_method', $payment_method);
                $order->update_meta_data('_oj_table_closed', current_time('mysql'));
                $order->save();
                error_log('Orders Jet: Marked order #' . $order->get_id() . ' as completed');
            }
        }
        
        // Generate a session ID for this table closure
        $session_id = 'session_' . $table_number . '_' . time();
        
        // Update all orders with session ID, payment method, and table closed timestamp
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order && in_array($order->get_status(), array('processing', 'pending', 'on-hold'))) {
                $order->update_meta_data('_oj_session_id', $session_id);
                $order->update_meta_data('_oj_payment_method', $payment_method);
                $order->update_meta_data('_oj_table_closed', current_time('mysql'));
                $order->save();
            }
        }
        
        // Generate invoice URL using direct file access with session ID
        $invoice_url = add_query_arg(array(
            'table' => $table_number,
            'payment_method' => $payment_method,
            'session' => $session_id
        ), ORDERS_JET_PLUGIN_URL . 'table-invoice.php');
        
        wp_send_json_success(array(
            'message' => __('Table closed successfully', 'orders-jet'),
            'total' => $total_amount,
            'payment_method' => $payment_method,
            'invoice_url' => $invoice_url
        ));
    }
    
    /**
     * Check if this is a new session for the table
     */
    private function is_new_table_session($table_number) {
        // Check if there are any recent pending/processing orders for this table
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending', 'wc-on-hold'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '2 hours ago',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        return empty($recent_orders);
    }
    
    /**
     * Get or create a session ID for a table
     */
    private function get_or_create_table_session($table_number) {
        // Check if there's an active session for this table (last 2 hours)
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending', 'wc-on-hold'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '2 hours ago',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($recent_orders)) {
            $existing_order = wc_get_order($recent_orders[0]->ID);
            if ($existing_order) {
                $existing_session = $existing_order->get_meta('_oj_session_id');
                if (!empty($existing_session)) {
                    return $existing_session;
                }
            }
        }
        
        // Create new session ID
        return 'session_' . $table_number . '_' . time();
    }
    
}
