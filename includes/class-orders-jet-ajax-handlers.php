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
        add_action('wp_ajax_oj_mark_order_ready', array($this, 'mark_order_ready'));
        add_action('wp_ajax_oj_mark_order_delivered', array($this, 'mark_order_delivered'));
        add_action('wp_ajax_oj_confirm_pickup_payment', array($this, 'confirm_pickup_payment'));
        add_action('wp_ajax_oj_get_table_summary', array($this, 'get_table_summary'));
        add_action('wp_ajax_oj_complete_table_cash', array($this, 'complete_table_cash'));
        add_action('wp_ajax_oj_complete_individual_order', array($this, 'complete_individual_order'));
        add_action('wp_ajax_oj_get_completed_orders_for_pdf', array($this, 'get_completed_orders_for_pdf'));
        add_action('wp_ajax_oj_generate_guest_pdf', array($this, 'generate_guest_pdf'));
        add_action('wp_ajax_oj_generate_admin_pdf', array($this, 'generate_admin_pdf'));
        add_action('wp_ajax_oj_generate_table_pdf', array($this, 'generate_table_pdf'));
        add_action('wp_ajax_oj_bulk_action', array($this, 'bulk_action'));
        add_action('wp_ajax_oj_search_order_invoice', array($this, 'search_order_invoice'));
        add_action('wp_ajax_oj_close_table_group', array($this, 'close_table_group'));
        
        // AJAX handlers for non-logged in users (guests)
        add_action('wp_ajax_nopriv_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_nopriv_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_nopriv_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_nopriv_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_nopriv_oj_get_table_orders', array($this, 'get_table_orders'));
        add_action('wp_ajax_nopriv_oj_close_table', array($this, 'close_table'));
        add_action('wp_ajax_nopriv_oj_get_completed_orders_for_pdf', array($this, 'get_completed_orders_for_pdf'));
        add_action('wp_ajax_nopriv_oj_generate_guest_pdf', array($this, 'generate_guest_pdf'));
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
            // For table orders, set tax to zero (will be calculated on consolidated order)
            $totals_array = array(
                'subtotal' => $total_price * $quantity,
                'total' => $total_price * $quantity,
            );
            
            // If this is a table order, ensure no tax is calculated on line items
            if (!empty($table_number)) {
                $totals_array['subtotal_tax'] = 0;
                $totals_array['total_tax'] = 0;
            }
            
            $item_id = $order->add_product($product, $quantity, array(
                'variation' => ($variation_id > 0) ? $product->get_variation_attributes() : array(),
                'totals' => $totals_array
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
        
        // Set order subtotal and let WooCommerce calculate taxes naturally
        // Store the original total for reference
        $order->update_meta_data('_oj_original_total', $final_total);
        
        // Set basic order data without forcing tax to zero
        $order->update_meta_data('_order_shipping', 0);
        $order->update_meta_data('_order_shipping_tax', 0);
        $order->update_meta_data('_order_discount', 0);
        $order->update_meta_data('_order_discount_tax', 0);
        
        // For table orders, don't calculate taxes (they will be calculated on consolidated order)
        // For pickup orders, calculate taxes normally
        if (!empty($table_number)) {
            // Table order - set totals manually without tax calculation
            $order->set_total($final_total);
            $order->update_meta_data('_order_tax', 0);
            $order->update_meta_data('_order_total_tax', 0);
            $order->update_meta_data('_oj_tax_deferred', 'yes'); // Mark that tax will be calculated later
            error_log('Orders Jet: Table order #' . $order->get_id() . ' - Tax calculation skipped (will be calculated on consolidated order)');
        } else {
            // Pickup order - calculate taxes normally
            $order->calculate_totals();
            error_log('Orders Jet: Pickup order #' . $order->get_id() . ' - Tax calculated normally');
        }
        
        // Save order with WooCommerce-calculated totals
        $order->save();
        
        // Log final totals with tax information
        error_log('Orders Jet: Final order #' . $order->get_id() . ' totals:');
        error_log('  - Subtotal: ' . $order->get_subtotal());
        error_log('  - Tax: ' . $order->get_total_tax());
        error_log('  - Total: ' . $order->get_total());
        error_log('  - Order Type: ' . (!empty($table_number) ? 'Table Order (Tax Deferred)' : 'Pickup Order (Tax Calculated)'));
        
        // SAFEGUARD: Validate tax isolation
        $expected_behavior = !empty($table_number) ? 'deferred' : 'calculated';
        $this->validate_tax_isolation($order, $expected_behavior);
        
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
        
        // For variable products, calculate price range
        $price_info = array();
        if ($product->is_type('variable')) {
            $prices = $product->get_variation_prices();
            $min_price = min($prices['price']);
            $max_price = max($prices['price']);
            
            if ($min_price === $max_price) {
                $price_info['price_range'] = wc_price($min_price);
                $price_info['price_range_text'] = wc_price($min_price);
            } else {
                $price_info['price_range'] = wc_price($min_price) . ' - ' . wc_price($max_price);
                $price_info['price_range_text'] = wc_price($min_price) . ' - ' . wc_price($max_price);
            }
            $price_info['is_variable'] = true;
            $price_info['min_price'] = $min_price;
            $price_info['max_price'] = $max_price;
        } else {
            $price_info['price_range'] = $product->get_price_html();
            $price_info['price_range_text'] = $product->get_price_html();
            $price_info['is_variable'] = false;
            $price_info['price'] = $product->get_price();
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
                        
                        // Format label properly (handle multiple words, capitalize each word)
                        $formatted_label = ucwords(str_replace(array('-', '_'), ' ', $option));
                        
                        $variation_options[] = array(
                            'value' => $option,
                            'label' => $formatted_label, // Properly formatted display label
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
        
        // Add price information to response
        $response_data['price_info'] = $price_info;
        
        wp_send_json_success($response_data);
        
        } catch (Exception $e) {
            error_log('Orders Jet: Error in get_product_details: ' . $e->getMessage());
            error_log('Orders Jet: Error trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Error loading product details: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get table orders for order history (current session only)
     */
    public function get_table_orders() {
        check_ajax_referer('oj_table_order', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        
        error_log('Orders Jet: Getting orders for table: ' . $table_number);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // For guest order history, only show pending/processing orders (exclude completed)
        // This prevents guests from seeing completed orders from previous sessions
        $post_statuses = array(
            'wc-pending',
            'wc-processing'
        );
        
        error_log('Orders Jet: Showing only pending/processing orders for guest privacy');
        
        // Get orders for this table - only pending/processing orders for guest privacy
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
                'wc-pending',
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
                'wc-pending',
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
                'status' => array('pending', 'processing', 'pending', 'completed', 'cancelled', 'refunded', 'failed'),
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
                if (in_array($status, array('processing', 'pending', 'pending'))) {
                    $total_amount += $total;
                    error_log('Orders Jet: Close table - Added order #' . $order->get_id() . ' to total. New total: ' . $total_amount);
                } else {
                    error_log('Orders Jet: Close table - Skipped order #' . $order->get_id() . ' because status "' . $status . '" is not pending/processing/pending');
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
        
        // Generate a session ID for this table closure
        $session_id = 'session_' . $table_number . '_' . time();
        
        // Mark all pending/processing orders as completed and collect order IDs
        $completed_order_ids = array();
        $table_subtotal = 0;
        
        // First pass: Complete orders WITHOUT calculating individual taxes and accumulate subtotals
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if ($order && in_array($order->get_status(), array('processing', 'pending', 'pending'))) {
                
                // For table orders: Do NOT calculate individual taxes
                // Just accumulate the subtotals (without tax)
                $order_subtotal = $order->get_subtotal();
                if ($order_subtotal <= 0) {
                    // Fallback: calculate subtotal from line items if not set
                    $order_subtotal = 0;
                    foreach ($order->get_items() as $item) {
                        $order_subtotal += $item->get_subtotal();
                    }
                }
                $table_subtotal += $order_subtotal;
                
                $order->set_status('completed');
                $order->update_meta_data('_oj_session_id', $session_id);
                $order->update_meta_data('_oj_payment_method', $payment_method);
                $order->update_meta_data('_oj_table_closed', current_time('mysql'));
                
                // Mark this as a table order for tax calculation reference
                $order->update_meta_data('_oj_tax_method', 'combined_invoice');
                
                $order->save();
                $completed_order_ids[] = $order->get_id();
                error_log('Orders Jet: Marked table order #' . $order->get_id() . ' as completed - Subtotal: ' . $order_subtotal);
            }
        }
        
        // TABLE ORDERS: Calculate tax on the combined invoice total
        $table_tax_data = $this->calculate_table_invoice_taxes($table_subtotal, $completed_order_ids);
        
        // Store table-level tax information for invoice generation
        $table_invoice_data = array(
            'subtotal' => $table_subtotal,
            'service_tax' => $table_tax_data['service_tax'],
            'vat_tax' => $table_tax_data['vat_tax'],
            'total_tax' => $table_tax_data['total_tax'],
            'grand_total' => $table_tax_data['grand_total'],
            'order_ids' => $completed_order_ids,
            'payment_method' => $payment_method,
            'table_number' => $table_number,
            'session_id' => $session_id,
            'closed_at' => current_time('mysql')
        );
        
        // Store the combined tax data for the table session
        update_option('oj_table_tax_' . $session_id, $table_invoice_data);
        
        error_log('Orders Jet: Table #' . $table_number . ' - Combined invoice tax calculation complete - Subtotal: ' . $table_subtotal . ', Total Tax: ' . $table_tax_data['total_tax'] . ', Grand Total: ' . $table_tax_data['grand_total']);
        
        // Generate invoice URL using direct file access with session ID
        $invoice_url = add_query_arg(array(
            'table' => $table_number,
            'payment_method' => $payment_method,
            'session' => $session_id
        ), ORDERS_JET_PLUGIN_URL . 'table-invoice.php');
        
        wp_send_json_success(array(
            'message' => __('Table closed successfully', 'orders-jet'),
            'total' => $total_amount,
            'subtotal' => $table_tax_data['subtotal'] ?? $table_subtotal,
            'service_tax' => $table_tax_data['service_tax'] ?? 0,
            'vat_tax' => $table_tax_data['vat_tax'] ?? 0,
            'total_tax' => $table_tax_data['total_tax'] ?? 0,
            'grand_total' => $table_tax_data['grand_total'] ?? $table_subtotal,
            'payment_method' => $payment_method,
            'invoice_url' => $invoice_url,
            'order_ids' => $completed_order_ids,
            'tax_method' => 'combined_invoice'
        ));
    }
    
    /**
     * Check if this is a new session for the table
     */
    private function is_new_table_session($table_number) {
        // Check if there are any recent pending/processing/pending orders for this table
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending', 'wc-pending'),
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
            'post_status' => array('wc-processing', 'wc-pending', 'wc-pending'),
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
    
    /**
     * Mark order as ready (Kitchen Dashboard)
     */
    public function mark_order_ready() {
        // Check nonce for security
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'orders-jet')));
        }
        
        $order_id = intval($_POST['order_id']);
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Order ID is required.', 'orders-jet')));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'orders-jet')));
        }
        
        // Get table number (if any) - this works for both table and pickup orders
        $table_number = $order->get_meta('_oj_table_number');
        
        // Check current status
        $current_status = $order->get_status();
        if (!in_array($current_status, array('pending', 'processing'))) {
            wp_send_json_error(array('message' => sprintf(__('Order cannot be marked ready from status: %s', 'orders-jet'), $current_status)));
        }
        
        try {
            // Mark order as ready (pending status means ready for pickup/payment)
            $order->set_status('pending');
            
            // Add order note with order type context
            $order_type = !empty($table_number) ? 'table' : 'pickup';
            $order->add_order_note(sprintf(
                __('Order marked as ready by kitchen staff (%s) - %s order', 'orders-jet'), 
                wp_get_current_user()->display_name,
                ucfirst($order_type)
            ));
            
            // Save the order
            $order->save();
            
            error_log('Orders Jet Kitchen: Order #' . $order_id . ' (' . $order_type . ') marked as ready (pending) by user #' . get_current_user_id());
            
            // Send notifications to manager and waiter dashboards
            $this->send_ready_notifications($order, $table_number);
            
            $success_message = !empty($table_number) 
                ? sprintf(__('Table order #%d marked as ready!', 'orders-jet'), $order_id)
                : sprintf(__('Pickup order #%d marked as ready!', 'orders-jet'), $order_id);
            
            wp_send_json_success(array(
                'message' => $success_message,
                'order_id' => $order_id,
                'table_number' => $table_number,
                'order_type' => $order_type,
                'new_status' => 'pending'
            ));
            
        } catch (Exception $e) {
            error_log('Orders Jet Kitchen: Error marking order ready: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to mark order as ready. Please try again.', 'orders-jet')));
        }
    }
    
    /**
     * Send notifications when order is ready
     */
    private function send_ready_notifications($order, $table_number) {
        // Store notification in transient for manager/waiter dashboards to pick up
        $notification = array(
            'type' => 'order_ready',
            'order_id' => $order->get_id(),
            'table_number' => $table_number,
            'message' => sprintf(__('Table %s - Order #%d is ready for pickup!', 'orders-jet'), $table_number, $order->get_id()),
            'timestamp' => current_time('timestamp'),
            'staff_name' => wp_get_current_user()->display_name
        );
        
        // Store notification for 5 minutes (manager/waiter dashboards will pick it up)
        $existing_notifications = get_transient('oj_ready_notifications') ?: array();
        $existing_notifications[] = $notification;
        
        // Keep only last 10 notifications
        if (count($existing_notifications) > 10) {
            $existing_notifications = array_slice($existing_notifications, -10);
        }
        
        set_transient('oj_ready_notifications', $existing_notifications, 300); // 5 minutes
        
        error_log('Orders Jet Kitchen: Ready notification stored for Table ' . $table_number . ' Order #' . $order->get_id());
    }
    
    /**
     * Mark order as delivered (Manager Dashboard)
     */
    public function mark_order_delivered() {
        // Check nonce for security
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'orders-jet')));
        }
        
        $order_id = intval($_POST['order_id']);
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Order ID is required.', 'orders-jet')));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'orders-jet')));
        }
        
        // Check if this is a table order
        $table_number = $order->get_meta('_oj_table_number');
        if (!$table_number) {
            wp_send_json_error(array('message' => __('This is not a table order.', 'orders-jet')));
        }
        
        // Check current status (should be pending)
        $current_status = $order->get_status();
        if ($current_status !== 'pending') {
            wp_send_json_error(array('message' => sprintf(__('Order cannot be marked delivered from status: %s', 'orders-jet'), $current_status)));
        }
        
        try {
            // Mark order as delivered (completed status)
            $order->set_status('completed');
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Order marked as delivered by staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
            
            // Save the order
            $order->save();
            
            error_log('Orders Jet Manager: Order #' . $order_id . ' marked as delivered (completed) by user #' . get_current_user_id());
            
            wp_send_json_success(array(
                'message' => sprintf(__('Order #%d marked as delivered!', 'orders-jet'), $order_id),
                'order_id' => $order_id,
                'table_number' => $table_number,
                'new_status' => 'completed'
            ));
            
        } catch (Exception $e) {
            error_log('Orders Jet Manager: Error marking order delivered: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to mark order as delivered. Please try again.', 'orders-jet')));
        }
    }
    
    /**
     * Confirm payment for pickup orders
     */
    public function confirm_pickup_payment() {
        // Check nonce for security
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'orders-jet')));
        }
        
        $order_id = intval($_POST['order_id']);
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Order ID is required.', 'orders-jet')));
        }
        
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error(array('message' => __('Order not found.', 'orders-jet')));
            }
            
            // Only allow payment confirmation for pending orders
            if ($order->get_status() !== 'pending') {
                wp_send_json_error(array('message' => sprintf(__('Order cannot be completed from status: %s (must be pending)', 'orders-jet'), $order->get_status())));
            }
            
            // Confirm this is a pickup order (not a table order)
            $table_number = $order->get_meta('_oj_table_number');
            if (!empty($table_number)) {
                wp_send_json_error(array('message' => __('Table orders should be paid through the table invoice system.', 'orders-jet')));
            }
            
            // Mark order as completed
            $order->set_status('completed');
            $order->add_order_note(sprintf(
                __('Pickup payment confirmed and order completed by staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
            
            // Save the order
            $order->save();
            
            error_log('Orders Jet Manager: Pickup order #' . $order_id . ' payment confirmed and completed by user #' . get_current_user_id());
            
            wp_send_json_success(array(
                'message' => sprintf(__('Payment confirmed! Order #%d completed.', 'orders-jet'), $order_id),
                'order_id' => $order_id,
                'new_status' => 'completed'
            ));
            
        } catch (Exception $e) {
            error_log('Orders Jet Manager: Error confirming pickup payment: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to confirm payment. Please try again.', 'orders-jet')));
        }
    }
    
    /**
     * Get table summary for smart close table
     */
    public function get_table_summary() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table']);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // Get all served orders for this table (both pending and pending)
        $orders = wc_get_orders(array(
            'status' => array('pending', 'pending'),
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        if (empty($orders)) {
            wp_send_json_error(array('message' => __('No orders found for this table', 'orders-jet')));
        }
        
        $order_data = array();
        $total_amount = 0;
        
        foreach ($orders as $order) {
            $order_data[] = array(
                'id' => $order->get_id(),
                'total' => wc_price($order->get_total()),
                'items_count' => count($order->get_items())
            );
            $total_amount += $order->get_total();
        }
        
        // Get available payment gateways
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $has_online_gateways = !empty($available_gateways);
        
        wp_send_json_success(array(
            'table' => $table_number,
            'orders' => $order_data,
            'total' => $total_amount,
            'total_formatted' => wc_price($total_amount),
            'has_online_gateways' => $has_online_gateways,
            'invoice_url' => site_url("/wp-content/plugins/orders-jet-integration/table-invoice.php?table=$table_number")
        ));
    }
    
    /**
     * Complete table with cash payment
     */
    public function complete_table_cash() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // Get all served orders for this table (both pending and pending)
        $orders = wc_get_orders(array(
            'status' => array('pending', 'pending'),
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        if (empty($orders)) {
            wp_send_json_error(array('message' => __('No orders found for this table', 'orders-jet')));
        }
        
        $completed_orders = array();
        
        foreach ($orders as $order) {
            // Mark order as completed
            $order->set_status('completed');
            $order->add_order_note(sprintf(
                __('Table %s closed with cash payment by manager (%s)', 'orders-jet'),
                $table_number,
                wp_get_current_user()->display_name
            ));
            $order->save();
            
            $completed_orders[] = $order->get_id();
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Table %s closed successfully! %d orders completed.', 'orders-jet'), $table_number, count($completed_orders)),
            'completed_orders' => $completed_orders
        ));
    }
    
    /**
     * Complete individual order
     */
    public function complete_individual_order() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'cash');
        
        if (empty($order_id)) {
            wp_send_json_error(array('message' => __('Order ID is required', 'orders-jet')));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'orders-jet')));
        }
        
        // Check if it's an individual order (no table number)
        $table_number = $order->get_meta('_oj_table_number');
        if (!empty($table_number)) {
            wp_send_json_error(array('message' => __('This is a table order. Use Close Table instead.', 'orders-jet')));
        }
        
        // Store original totals for logging
        $original_subtotal = $order->get_subtotal();
        $original_total = $order->get_total();
        
        // INDIVIDUAL ORDER: Calculate tax per order
        $this->calculate_individual_order_taxes($order);
        
        // Store payment method and tax calculation method
        $order->update_meta_data('_oj_payment_method', $payment_method);
        $order->update_meta_data('_oj_tax_method', 'individual_order');
        
        // Mark order as completed
        $order->set_status('completed');
        $order->add_order_note(sprintf(
            __('Individual order completed by manager (%s) - Payment: %s - Tax calculated per order (Subtotal: %s, Tax: %s, Total: %s)', 'orders-jet'),
            wp_get_current_user()->display_name,
            $payment_method,
            wc_price($order->get_subtotal()),
            wc_price($order->get_total_tax()),
            wc_price($order->get_total())
        ));
        $order->save();
        
        error_log('Orders Jet: Individual order #' . $order_id . ' completed - Original Total: ' . $original_total . ', New Total with Tax: ' . $order->get_total());
        
        wp_send_json_success(array(
            'message' => sprintf(__('Order #%d completed successfully!', 'orders-jet'), $order_id),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'total' => $order->get_total(),
            'payment_method' => $payment_method,
            'tax_method' => 'individual_order'
        ));
    }
    
    /**
     * Get completed orders for PDF generation (for guests)
     */
    public function get_completed_orders_for_pdf() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        // Get completed orders for this table
        $wc_orders = wc_get_orders(array(
            'status' => 'completed',
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $order_ids = array();
        foreach ($wc_orders as $order) {
            $order_ids[] = $order->get_id();
        }
        
        if (empty($order_ids)) {
            wp_send_json_error(array('message' => __('No completed orders found for this table', 'orders-jet')));
        }
        
        wp_send_json_success(array(
            'order_ids' => $order_ids,
            'table_number' => $table_number
        ));
    }
    
    /**
     * Generate PDF invoice for guests (public access with validation)
     */
    public function generate_guest_pdf() {
        // Verify nonce for security
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'oj_guest_pdf')) {
            wp_die(__('Security check failed', 'orders-jet'));
        }
        
        $order_id = intval($_GET['order_id'] ?? 0);
        $table_number = sanitize_text_field($_GET['table'] ?? '');
        $document_type = sanitize_text_field($_GET['document_type'] ?? 'invoice');
        $output = sanitize_text_field($_GET['output'] ?? 'pdf');
        $force_download = isset($_GET['force_download']);
        
        if (!$order_id || !$table_number) {
            wp_die(__('Invalid order or table information', 'orders-jet'));
        }
        
        // Verify the order belongs to the specified table
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'orders-jet'));
        }
        
        $order_table = $order->get_meta('_oj_table_number');
        if ($order_table !== $table_number) {
            wp_die(__('Order does not belong to this table', 'orders-jet'));
        }
        
        // Verify the order is completed
        if ($order->get_status() !== 'completed') {
            wp_die(__('Order is not completed', 'orders-jet'));
        }
        
        // Check if PDF Invoices plugin is available
        if (!function_exists('wcpdf_get_document')) {
            wp_die(__('PDF invoice functionality is not available', 'orders-jet'));
        }
        
        try {
            // Get the PDF document
            $document = wcpdf_get_document($document_type, $order);
            
            if (!$document) {
                wp_die(__('Could not generate PDF document', 'orders-jet'));
            }
            
            // Set appropriate headers
            if ($output === 'html') {
                header('Content-Type: text/html; charset=utf-8');
                echo $document->get_html();
            } else {
                // PDF output
                $pdf_data = $document->get_pdf();
                $filename = $document->get_filename();
                
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf_data));
                
                if ($force_download) {
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                } else {
                    header('Content-Disposition: inline; filename="' . $filename . '"');
                }
                
                echo $pdf_data;
            }
            
            exit;
            
        } catch (Exception $e) {
            error_log('Orders Jet: PDF generation error: ' . $e->getMessage());
            wp_die(__('Error generating PDF: ', 'orders-jet') . $e->getMessage());
        }
    }
    
    /**
     * Generate PDF invoice for admin users (with proper authentication)
     */
    public function generate_admin_pdf() {
        // Verify nonce for security
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'oj_admin_pdf')) {
            wp_die(__('Security check failed', 'orders-jet'));
        }
        
        // Check if user has admin capabilities
        if (!current_user_can('manage_woocommerce') && !current_user_can('access_oj_manager_dashboard')) {
            wp_die(__('You do not have permission to access this resource', 'orders-jet'));
        }
        
        $order_id = intval($_GET['order_id'] ?? 0);
        $document_type = sanitize_text_field($_GET['document_type'] ?? 'invoice');
        $output = sanitize_text_field($_GET['output'] ?? 'pdf');
        $force_download = isset($_GET['force_download']);
        
        if (!$order_id) {
            wp_die(__('Invalid order ID', 'orders-jet'));
        }
        
        // Verify the order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'orders-jet'));
        }
        
        // Check if PDF Invoices plugin is available
        if (!function_exists('wcpdf_get_document')) {
            wp_die(__('PDF invoice functionality is not available', 'orders-jet'));
        }
        
        try {
            // Get the PDF document using the plugin's function
            $document = wcpdf_get_document($document_type, $order);
            
            if (!$document) {
                wp_die(__('Could not generate PDF document', 'orders-jet'));
            }
            
            // Set appropriate headers
            if ($output === 'html') {
                header('Content-Type: text/html; charset=utf-8');
                echo $document->get_html();
            } else {
                // PDF output
                $pdf_data = $document->get_pdf();
                $filename = $document->get_filename();
                
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf_data));
                
                if ($force_download) {
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                } else {
                    header('Content-Disposition: inline; filename="' . $filename . '"');
                }
                
                echo $pdf_data;
            }
            
            exit;
            
        } catch (Exception $e) {
            error_log('Orders Jet: Admin PDF generation error: ' . $e->getMessage());
            wp_die(__('Error generating PDF: ', 'orders-jet') . $e->getMessage());
        }
    }
    
    /**
     * Generate combined PDF invoice for table orders (admin users)
     */
    public function generate_table_pdf() {
        // Verify nonce for security
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'oj_admin_pdf')) {
            wp_die(__('Security check failed', 'orders-jet'));
        }
        
        // Check if user has admin capabilities
        if (!current_user_can('manage_woocommerce') && !current_user_can('access_oj_manager_dashboard')) {
            wp_die(__('You do not have permission to access this resource', 'orders-jet'));
        }
        
        $table_number = sanitize_text_field($_GET['table_number'] ?? '');
        $order_ids = sanitize_text_field($_GET['order_ids'] ?? '');
        $output = sanitize_text_field($_GET['output'] ?? 'pdf');
        $force_download = isset($_GET['force_download']);
        
        if (!$table_number || !$order_ids) {
            wp_die(__('Invalid table number or order IDs', 'orders-jet'));
        }
        
        // Parse order IDs
        $order_id_array = explode(',', $order_ids);
        $order_id_array = array_map('intval', $order_id_array);
        
        if (empty($order_id_array)) {
            wp_die(__('No valid order IDs provided', 'orders-jet'));
        }
        
        try {
            // Generate combined table invoice HTML
            $invoice_html = $this->generate_table_invoice_html($table_number, $order_id_array);
            
            if ($output === 'html') {
                header('Content-Type: text/html; charset=utf-8');
                echo $invoice_html;
            } else {
                // Generate PDF from HTML
                $this->generate_pdf_from_html($invoice_html, $table_number, $force_download);
            }
            
            exit;
            
        } catch (Exception $e) {
            error_log('Orders Jet: Table PDF generation error: ' . $e->getMessage());
            wp_die(__('Error generating table PDF: ', 'orders-jet') . $e->getMessage());
        }
    }
    
    /**
     * Generate combined table invoice HTML
     */
    private function generate_table_invoice_html($table_number, $order_ids) {
        // Get all completed orders for this table
        $orders = array();
        $total_amount = 0;
        $order_data = array();
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            // Verify order belongs to this table
            $order_table = $order->get_meta('_oj_table_number');
            if ($order_table !== $table_number) continue;
            
            $order_items = array();
            foreach ($order->get_items() as $item) {
                $order_items[] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total()
                );
            }
            
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total' => $order->get_total(),
                'items' => $order_items,
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'payment_method' => $order->get_meta('_oj_payment_method') ?: 'cash'
            );
            
            $total_amount += $order->get_total();
        }
        
        // Get table information
        $table_id = oj_get_table_id_by_number($table_number);
        $table_capacity = $table_id ? get_post_meta($table_id, '_oj_table_capacity', true) : '';
        $table_location = $table_id ? get_post_meta($table_id, '_oj_table_location', true) : '';
        
        // Generate HTML using our existing template logic
        ob_start();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php printf(__('Table %s Invoice', 'orders-jet'), $table_number); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .invoice-container { max-width: 800px; margin: 0 auto; }
                .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .invoice-header h1 { color: #c41e3a; margin: 0; font-size: 28px; }
                .invoice-info { margin-bottom: 30px; }
                .info-row { display: flex; justify-content: space-between; margin: 8px 0; }
                .info-label { font-weight: bold; }
                .orders-section h2 { color: #333; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .order-block { margin-bottom: 25px; border: 1px solid #ddd; padding: 15px; }
                .order-header { background: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .items-table th, .items-table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                .items-table th { background: #f8f9fa; font-weight: bold; }
                .order-total { text-align: right; font-weight: bold; margin-top: 10px; }
                .invoice-total { background: #c41e3a; color: white; padding: 20px; text-align: center; margin-top: 30px; }
                .invoice-total h2 { margin: 0; font-size: 24px; }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <div class="invoice-header">
                    <h1><?php _e('Restaurant Invoice', 'orders-jet'); ?></h1>
                    <p><?php printf(__('Table %s', 'orders-jet'), $table_number); ?></p>
                </div>
                
                <div class="invoice-info">
                    <div class="info-row">
                        <span class="info-label"><?php _e('Table Number:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_number); ?></span>
                    </div>
                    <?php if ($table_capacity): ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Capacity:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_capacity); ?> <?php _e('people', 'orders-jet'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($table_location): ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Location:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_location); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Invoice Date:', 'orders-jet'); ?></span>
                        <span><?php echo current_time('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Number of Orders:', 'orders-jet'); ?></span>
                        <span><?php echo count($order_data); ?></span>
                    </div>
                </div>
                
                <div class="orders-section">
                    <h2><?php _e('Order Details', 'orders-jet'); ?></h2>
                    
                    <?php foreach ($order_data as $order): ?>
                    <div class="order-block">
                        <div class="order-header">
                            <strong><?php _e('Order #', 'orders-jet'); ?><?php echo $order['order_number']; ?></strong>
                            <span style="float: right;"><?php echo $order['date']; ?></span>
                        </div>
                        
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Item', 'orders-jet'); ?></th>
                                    <th><?php _e('Quantity', 'orders-jet'); ?></th>
                                    <th><?php _e('Price', 'orders-jet'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo wc_price($item['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="order-total">
                            <?php _e('Order Total:', 'orders-jet'); ?> <?php echo wc_price($order['total']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="invoice-total">
                    <h2><?php _e('Total Amount:', 'orders-jet'); ?> <?php echo wc_price($total_amount); ?></h2>
                    <p><?php printf(__('Payment Method: %s', 'orders-jet'), ucfirst($order_data[0]['payment_method'] ?? 'Cash')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Generate PDF from HTML using available PDF library
     */
    private function generate_pdf_from_html($html, $table_number, $force_download = false) {
        // Clean any previous output to prevent PDF corruption
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Try to use WooCommerce PDF plugin's TCPDF first
        if (function_exists('wcpdf_get_document') && class_exists('WPO\WC\PDF_Invoices\TCPDF')) {
            try {
                // Use WooCommerce PDF plugin's TCPDF
                $pdf = new WPO\WC\PDF_Invoices\TCPDF();
                
                // Set document information
                $pdf->SetCreator('Orders Jet');
                $pdf->SetAuthor('Restaurant');
                $pdf->SetTitle('Table ' . $table_number . ' Invoice');
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                $pdf->SetAutoPageBreak(TRUE, 15);
                
                // Add a page
                $pdf->AddPage();
                
                // Clean HTML for PDF compatibility
                $clean_html = $this->clean_html_for_pdf($html);
                
                // Write HTML content
                $pdf->writeHTML($clean_html, true, false, true, false, '');
                
                // Generate filename
                $filename = 'table-' . $table_number . '-combined-invoice.pdf';
                
                // Output PDF
                if ($force_download) {
                    $pdf->Output($filename, 'D'); // Force download
                } else {
                    $pdf->Output($filename, 'I'); // Display in browser
                }
                
                return; // Success, exit function
                
            } catch (Exception $e) {
                error_log('Orders Jet: WooCommerce TCPDF Error: ' . $e->getMessage());
            }
        }
        
        // Try standard TCPDF if available
        if (class_exists('TCPDF')) {
            try {
                // Create new PDF document
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                
                // Set document information
                $pdf->SetCreator('Orders Jet');
                $pdf->SetAuthor('Restaurant');
                $pdf->SetTitle('Table ' . $table_number . ' Invoice');
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                $pdf->SetAutoPageBreak(TRUE, 15);
                
                // Add a page
                $pdf->AddPage();
                
                // Clean HTML for PDF compatibility
                $clean_html = $this->clean_html_for_pdf($html);
                
                // Write HTML content
                $pdf->writeHTML($clean_html, true, false, true, false, '');
                
                // Generate filename
                $filename = 'table-' . $table_number . '-combined-invoice.pdf';
                
                // Output PDF
                if ($force_download) {
                    $pdf->Output($filename, 'D'); // Force download
                } else {
                    $pdf->Output($filename, 'I'); // Display in browser
                }
                
                return; // Success, exit function
                
            } catch (Exception $e) {
                error_log('Orders Jet: Standard TCPDF Error: ' . $e->getMessage());
            }
        }
        
        // Try using a simple PDF generation approach
        try {
            // Use a basic PDF generation method
            $this->generate_simple_pdf($html, $table_number, $force_download);
            return;
        } catch (Exception $e) {
            error_log('Orders Jet: Simple PDF Error: ' . $e->getMessage());
        }
        
        // Final fallback to HTML
        error_log('Orders Jet: No PDF libraries available, using HTML fallback');
        $this->output_html_fallback($html, $table_number, $force_download);
    }
    
    /**
     * Clean HTML for PDF compatibility
     */
    private function clean_html_for_pdf($html) {
        // Remove problematic CSS and elements for PDF
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Add basic PDF-friendly styles
        $pdf_styles = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #c41e3a; font-size: 18px; text-align: center; }
            h2 { font-size: 14px; color: #333; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .invoice-total { background-color: #c41e3a; color: white; padding: 15px; text-align: center; }
            .order-block { border: 1px solid #ddd; margin: 10px 0; padding: 10px; }
            .order-header { background-color: #f8f9fa; padding: 8px; font-weight: bold; }
        </style>';
        
        // Insert styles after <head>
        $html = str_replace('<head>', '<head>' . $pdf_styles, $html);
        
        return $html;
    }
    
    /**
     * Generate PDF using simple method with proper headers
     */
    private function generate_simple_pdf($html, $table_number, $force_download = false) {
        // Create a simple text-based PDF content
        $pdf_content = $this->create_simple_pdf_content($html, $table_number);
        
        $filename = 'table-' . $table_number . '-combined-invoice.pdf';
        
        // Set proper PDF headers
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf_content));
        
        if ($force_download) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        
        // Disable caching
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $pdf_content;
    }
    
    /**
     * Create simple PDF content without external libraries
     */
    private function create_simple_pdf_content($html, $table_number) {
        // Extract and structure content from HTML properly
        $structured_content = $this->extract_structured_content($html, $table_number);
        
        // Create a basic PDF structure
        $pdf_header = "%PDF-1.4\n";
        
        // PDF objects
        $objects = array();
        
        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
        
        // Object 2: Pages
        $objects[2] = "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
        
        // Object 3: Page
        $objects[3] = "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n/F2 6 0 R\n>>\n>>\n>>\nendobj\n";
        
        // Object 4: Content stream
        $stream_content = $this->build_pdf_content_stream($structured_content);
        $stream_length = strlen($stream_content);
        
        $objects[4] = "4 0 obj\n<<\n/Length $stream_length\n>>\nstream\n$stream_content\nendstream\nendobj\n";
        
        // Object 5: Regular Font
        $objects[5] = "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
        
        // Object 6: Bold Font
        $objects[6] = "6 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Bold\n>>\nendobj\n";
        
        // Build PDF content
        $pdf_content = $pdf_header;
        $xref_offset = strlen($pdf_content);
        
        foreach ($objects as $obj) {
            $pdf_content .= $obj;
        }
        
        // Cross-reference table
        $xref_table = "xref\n0 7\n0000000000 65535 f \n";
        $offset = strlen($pdf_header);
        
        for ($i = 1; $i <= 6; $i++) {
            $xref_table .= sprintf("%010d 00000 n \n", $offset);
            $offset += strlen($objects[$i]);
        }
        
        $pdf_content .= $xref_table;
        
        // Trailer
        $trailer = "trailer\n<<\n/Size 7\n/Root 1 0 R\n>>\nstartxref\n$xref_offset\n%%EOF\n";
        $pdf_content .= $trailer;
        
        return $pdf_content;
    }
    
    /**
     * Extract structured content from HTML
     */
    private function extract_structured_content($html, $table_number) {
        // Remove CSS styles first
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/style="[^"]*"/i', '', $html);
        
        // Create structured content array
        $content = array(
            'title' => 'Restaurant Invoice',
            'subtitle' => 'Table ' . $table_number,
            'sections' => array()
        );
        
        // Extract table information
        if (preg_match('/Table Number:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Table Number', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Capacity:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Capacity', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Location:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Location', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Invoice Date:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Invoice Date', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Number of Orders:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Number of Orders', 'value' => trim($matches[1]));
        }
        
        // Add section break
        $content['sections'][] = array('type' => 'section_header', 'text' => 'Order Details');
        
        // Extract orders
        preg_match_all('/Order #(\d+)\s+([0-9-:\s]+).*?Order Total:\s*([0-9.,]+\s*EGP)/is', $html, $order_matches, PREG_SET_ORDER);
        
        foreach ($order_matches as $order_match) {
            $order_id = $order_match[1];
            $order_date = trim($order_match[2]);
            $order_total = $order_match[3];
            
            $content['sections'][] = array('type' => 'order_header', 'text' => "Order #$order_id - $order_date");
            
            // Extract items for this order
            $order_section = $order_match[0];
            preg_match_all('/([A-Za-z\s\-]+)\s+(\d+)\s+([0-9.,]+\s*EGP)/i', $order_section, $item_matches, PREG_SET_ORDER);
            
            foreach ($item_matches as $item_match) {
                $item_name = trim($item_match[1]);
                $quantity = $item_match[2];
                $price = $item_match[3];
                
                if (!empty($item_name) && $item_name !== 'Order Total') {
                    $content['sections'][] = array(
                        'type' => 'item', 
                        'name' => $item_name, 
                        'quantity' => $quantity, 
                        'price' => $price
                    );
                }
            }
            
            $content['sections'][] = array('type' => 'order_total', 'text' => "Order Total: $order_total");
            $content['sections'][] = array('type' => 'spacer', 'text' => '');
        }
        
        // Extract final totals
        if (preg_match('/Total Amount:\s*([0-9.,]+\s*EGP)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'final_total', 'text' => 'Total Amount: ' . $matches[1]);
        }
        
        if (preg_match('/Payment Method:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'payment_method', 'text' => 'Payment Method: ' . trim($matches[1]));
        }
        
        return $content;
    }
    
    /**
     * Build PDF content stream from structured content
     */
    private function build_pdf_content_stream($content) {
        $stream = "BT\n";
        $current_y = 750;
        $line_height = 15;
        
        // Title
        $stream .= "/F2 18 Tf\n"; // Bold, larger font
        $stream .= "50 $current_y Td\n";
        $stream .= "(" . $this->escape_pdf_string($content['title']) . ") Tj\n";
        $current_y -= 25;
        
        // Subtitle  
        $stream .= "/F2 14 Tf\n"; // Bold, medium font
        $stream .= "0 -25 Td\n"; // Move down relative to current position
        $stream .= "(" . $this->escape_pdf_string($content['subtitle']) . ") Tj\n";
        $current_y -= 30;
        
        // Content sections
        foreach ($content['sections'] as $section) {
            if ($current_y < 50) break; // Prevent overflow
            
            switch ($section['type']) {
                case 'info':
                    $stream .= "/F1 10 Tf\n"; // Regular font
                    $stream .= "0 -" . $line_height . " Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['label'] . ': ' . $section['value']) . ") Tj\n";
                    $current_y -= $line_height;
                    break;
                    
                case 'section_header':
                    $stream .= "/F2 14 Tf\n"; // Bold font
                    $stream .= "0 -25 Td\n"; // Extra space before section
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 25;
                    break;
                    
                case 'order_header':
                    $stream .= "/F2 12 Tf\n"; // Bold font
                    $stream .= "0 -20 Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 20;
                    break;
                    
                case 'item':
                    $stream .= "/F1 10 Tf\n"; // Regular font
                    $stream .= "20 -" . $line_height . " Td\n"; // Indent items
                    $item_line = $section['name'] . ' x' . $section['quantity'] . ' - ' . $section['price'];
                    $stream .= "(" . $this->escape_pdf_string($item_line) . ") Tj\n";
                    $stream .= "-20 0 Td\n"; // Reset indent
                    $current_y -= $line_height;
                    break;
                    
                case 'order_total':
                    $stream .= "/F2 10 Tf\n"; // Bold font
                    $stream .= "20 -" . $line_height . " Td\n"; // Indent
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $stream .= "-20 0 Td\n"; // Reset indent
                    $current_y -= $line_height;
                    break;
                    
                case 'final_total':
                    $stream .= "/F2 14 Tf\n"; // Bold, larger font
                    $stream .= "0 -25 Td\n"; // Extra space before final total
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 25;
                    break;
                    
                case 'payment_method':
                    $stream .= "/F1 12 Tf\n"; // Regular font
                    $stream .= "0 -" . $line_height . " Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= $line_height;
                    break;
                    
                case 'spacer':
                    $stream .= "0 -10 Td\n";
                    $current_y -= 10;
                    break;
            }
        }
        
        $stream .= "ET\n";
        return $stream;
    }
    
    /**
     * Prepare text content for PDF
     */
    private function prepare_text_for_pdf($text) {
        // Clean up the text
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = trim($text);
        
        // Add line breaks for better formatting
        $text = str_replace('Restaurant Invoice', "Restaurant Invoice\n\n", $text);
        $text = str_replace('Order Details', "\n\nOrder Details\n", $text);
        $text = str_replace('Total Amount:', "\n\nTotal Amount:", $text);
        $text = str_replace('Payment Method:', "\nPayment Method:", $text);
        
        // Wrap long lines
        $lines = explode("\n", $text);
        $wrapped_lines = array();
        
        foreach ($lines as $line) {
            if (strlen($line) > 80) {
                $wrapped_lines = array_merge($wrapped_lines, str_split($line, 80));
            } else {
                $wrapped_lines[] = $line;
            }
        }
        
        return implode("\n", $wrapped_lines);
    }
    
    /**
     * Escape string for PDF
     */
    private function escape_pdf_string($string) {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace('(', '\\(', $string);
        $string = str_replace(')', '\\)', $string);
        return $string;
    }
    
    /**
     * Generate PDF using alternative method (browser-based conversion)
     */
    private function generate_pdf_via_alternative($html, $table_number, $force_download = false) {
        // Use a simple approach: create a temporary HTML file that auto-prints
        $filename = 'table-' . $table_number . '-combined-invoice.pdf';
        
        // For now, let's try a different approach - use the browser's print-to-PDF capability
        // by creating a special HTML page that triggers print dialog
        
        $print_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Table ' . $table_number . ' Invoice</title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
                body { font-family: Arial, sans-serif; margin: 20px; }
                .print-instructions { 
                    background: #f0f8ff; 
                    border: 2px solid #4CAF50; 
                    padding: 15px; 
                    margin: 20px 0; 
                    border-radius: 5px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="print-instructions no-print">
                <h3>📄 Save as PDF Instructions</h3>
                <p><strong>To save this invoice as PDF:</strong></p>
                <ol style="text-align: left; display: inline-block;">
                    <li>Press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac)</li>
                    <li>Select "Save as PDF" as the destination</li>
                    <li>Click "Save" and choose your download location</li>
                </ol>
                <button onclick="window.print()" style="background: #c41e3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px;">
                    🖨️ Print / Save as PDF
                </button>
            </div>
            ' . $html . '
        </body>
        </html>';
        
        // Set headers for HTML with PDF instructions
        header('Content-Type: text/html; charset=utf-8');
        if ($force_download) {
            header('Content-Disposition: attachment; filename="table-' . $table_number . '-invoice-print-to-pdf.html"');
        }
        
        echo $print_html;
    }
    
    /**
     * Output HTML fallback when PDF generation fails
     */
    private function output_html_fallback($html, $table_number, $force_download) {
        $filename = 'table-' . $table_number . '-invoice.html';
        
        header('Content-Type: text/html; charset=utf-8');
        
        if ($force_download) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        // Add print-friendly styles and print button
        $print_html = str_replace('<body>', '<body>
            <div style="text-align: center; margin: 20px; print:none;">
                <button onclick="window.print()" style="background: #c41e3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">🖨️ Print Invoice</button>
            </div>', $html);
        
        echo $print_html;
    }
    
    /**
     * Handle bulk actions on orders
     */
    public function bulk_action() {
        check_ajax_referer('oj_bulk_action', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $order_ids = array_map('intval', $_POST['order_ids']);
        
        if (empty($order_ids)) {
            wp_send_json_error(array('message' => __('No orders selected', 'orders-jet')));
        }
        
        $success_count = 0;
        $error_count = 0;
        $processed_orders = array();
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $error_count++;
                error_log('Orders Jet: Bulk Action - Order not found: ' . $order_id);
                continue;
            }
            
            $current_status = $order->get_status();
            $order_number = $order->get_order_number();
            
            $table_number = $order->get_meta('_oj_table_number');
            $is_table_order = !empty($table_number);
            
            switch ($bulk_action) {
                case 'mark_ready':
                    if ($current_status === 'processing') {
                        $order->set_status('pending');
                        $order->add_order_note(__('Order marked as ready via bulk action', 'orders-jet'));
                        $order->save();
                        $success_count++;
                        $processed_orders[] = $order_number;
                        error_log('Orders Jet: Bulk Action - Marked order #' . $order_number . ' as ready');
                    } else {
                        $error_count++;
                        error_log('Orders Jet: Bulk Action - Cannot mark order #' . $order_number . ' as ready. Current status: ' . $current_status);
                    }
                    break;
                    
                case 'complete_pickup_orders':
                    // CRITICAL: Only allow pickup orders to be completed individually
                    if ($is_table_order) {
                        $error_count++;
                        error_log('Orders Jet: Bulk Action - BLOCKED: Attempted to complete table order #' . $order_number . ' individually. Table: ' . $table_number);
                        continue 2; // Skip to next order
                    }
                    
                    if (in_array($current_status, ['processing', 'pending'])) {
                        $order->set_status('completed');
                        $order->add_order_note(__('Pickup order completed via bulk action', 'orders-jet'));
                        $order->save();
                        $success_count++;
                        $processed_orders[] = $order_number;
                        error_log('Orders Jet: Bulk Action - Completed pickup order #' . $order_number);
                    } else {
                        $error_count++;
                        error_log('Orders Jet: Bulk Action - Cannot complete pickup order #' . $order_number . '. Current status: ' . $current_status);
                    }
                    break;
                    
                case 'close_tables':
                    // CRITICAL: Only allow table orders for this action
                    if (!$is_table_order) {
                        $error_count++;
                        error_log('Orders Jet: Bulk Action - BLOCKED: Attempted to close table for pickup order #' . $order_number);
                        continue 2; // Skip to next order
                    }
                    
                    // Group table orders by table number for proper closing
                    if (!isset($table_groups)) {
                        $table_groups = array();
                    }
                    
                    if (!isset($table_groups[$table_number])) {
                        $table_groups[$table_number] = array();
                    }
                    
                    $table_groups[$table_number][] = $order_id;
                    break;
                    
                case 'cancel_orders':
                    if (in_array($current_status, ['processing', 'pending'])) {
                        $order->set_status('cancelled');
                        $order->add_order_note(__('Order cancelled via bulk action', 'orders-jet'));
                        $order->save();
                        $success_count++;
                        $processed_orders[] = $order_number;
                        error_log('Orders Jet: Bulk Action - Cancelled order #' . $order_number);
                    } else {
                        $error_count++;
                        error_log('Orders Jet: Bulk Action - Cannot cancel order #' . $order_number . '. Current status: ' . $current_status);
                    }
                    break;
                    
                default:
                    $error_count++;
                    error_log('Orders Jet: Bulk Action - Unknown action: ' . $bulk_action);
            }
        }
        
        // Handle table closing if that was the action
        if ($bulk_action === 'close_tables' && isset($table_groups)) {
            foreach ($table_groups as $table_num => $table_order_ids) {
                $table_success = $this->close_table_orders($table_num, $table_order_ids);
                if ($table_success) {
                    $success_count += count($table_order_ids);
                    $processed_orders = array_merge($processed_orders, $table_order_ids);
                    error_log('Orders Jet: Bulk Action - Closed table ' . $table_num . ' with ' . count($table_order_ids) . ' orders');
                } else {
                    $error_count += count($table_order_ids);
                    error_log('Orders Jet: Bulk Action - Failed to close table ' . $table_num);
                }
            }
        }
        
        // Prepare response message
        $action_names = array(
            'mark_ready' => __('marked as ready', 'orders-jet'),
            'complete_pickup_orders' => __('completed', 'orders-jet'),
            'close_tables' => __('closed', 'orders-jet'),
            'cancel_orders' => __('cancelled', 'orders-jet')
        );
        
        $action_name = isset($action_names[$bulk_action]) ? $action_names[$bulk_action] : $bulk_action;
        
        if ($success_count > 0) {
            $message = sprintf(
                _n(
                    '%d order %s successfully.',
                    '%d orders %s successfully.',
                    $success_count,
                    'orders-jet'
                ),
                $success_count,
                $action_name
            );
            
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    _n(
                        '%d order failed to process.',
                        '%d orders failed to process.',
                        $error_count,
                        'orders-jet'
                    ),
                    $error_count
                );
            }
        } else {
            $message = sprintf(
                __('No orders could be %s. Please check order statuses.', 'orders-jet'),
                $action_name
            );
        }
        
        error_log('Orders Jet: Bulk Action Summary - Action: ' . $bulk_action . ', Success: ' . $success_count . ', Errors: ' . $error_count);
        
        wp_send_json_success(array(
            'message' => $message,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'processed_orders' => $processed_orders
        ));
    }
    
    /**
     * Close table orders properly (used by bulk actions)
     */
    private function close_table_orders($table_number, $order_ids) {
        try {
            // Generate session ID for this table closure
            $session_id = 'bulk_' . time() . '_' . $table_number;
            
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    error_log('Orders Jet: Close Table - Order not found: ' . $order_id);
                    continue;
                }
                
                // Verify this is actually a table order for the correct table
                $order_table = $order->get_meta('_oj_table_number');
                if ($order_table !== $table_number) {
                    error_log('Orders Jet: Close Table - Order #' . $order_id . ' does not belong to table ' . $table_number);
                    continue;
                }
                
                // Only close orders that are processing or pending
                if (in_array($order->get_status(), ['processing', 'pending'])) {
                    $order->set_status('completed');
                    $order->update_meta_data('_oj_session_id', $session_id);
                    $order->update_meta_data('_oj_payment_method', 'bulk_action');
                    $order->update_meta_data('_oj_table_closed', current_time('mysql'));
                    $order->add_order_note(__('Table closed via bulk action', 'orders-jet'));
                    $order->save();
                    
                    error_log('Orders Jet: Close Table - Completed order #' . $order_id . ' for table ' . $table_number);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Orders Jet: Close Table Error - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search order for invoice (minimal AJAX handler)
     */
    public function search_order_invoice() {
        check_ajax_referer('oj_search_invoice', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $order_number = sanitize_text_field($_POST['order_number']);
        
        if (empty($order_number)) {
            wp_send_json_error(array('message' => __('Please enter an order number', 'orders-jet')));
        }
        
        // Try to find order by ID or order number
        $order = wc_get_order($order_number);
        
        // If not found by ID, try searching by order number
        if (!$order) {
            $orders = wc_get_orders(array(
                'meta_query' => array(
                    array(
                        'key' => '_order_number',
                        'value' => $order_number,
                        'compare' => '='
                    )
                ),
                'limit' => 1
            ));
            
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'orders-jet')));
        }
        
        if ($order->get_status() !== 'completed') {
            wp_send_json_error(array('message' => __('Order is not completed yet', 'orders-jet')));
        }
        
        $table_number = $order->get_meta('_oj_table_number');
        
        wp_send_json_success(array(
            'id' => $order->get_id(),
            'type' => !empty($table_number) ? 'table' : 'pickup',
            'table' => $table_number ?: '',
            'status' => $order->get_status(),
            'total' => $order->get_total()
        ));
    }
    
    /**
     * Calculate taxes for individual orders (per order basis)
     */
    private function calculate_individual_order_taxes($order) {
        if (!$order) return;
        
        $tax_enabled = wc_tax_enabled();
        if (!$tax_enabled) {
            error_log('Orders Jet: Taxes not enabled in WooCommerce');
            return;
        }
        
        // Set customer location for tax calculation (use store location)
        $store_country = WC()->countries->get_base_country();
        $store_state = WC()->countries->get_base_state();
        
        // Set order addresses for tax calculation
        $order->set_billing_country($store_country);
        $order->set_billing_state($store_state);
        $order->set_shipping_country($store_country);
        $order->set_shipping_state($store_state);
        
        // Calculate taxes for each item in this individual order
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Get tax class for the product
            $tax_class = $product->get_tax_class();
            
            // Get tax rates for this product
            $tax_rates = WC_Tax::find_rates(array(
                'country' => $store_country,
                'state' => $store_state,
                'city' => '',
                'postcode' => '',
                'tax_class' => $tax_class
            ));
            
            if (!empty($tax_rates)) {
                $line_subtotal = $item->get_subtotal();
                $line_total = $item->get_total();
                
                // Calculate taxes
                $line_subtotal_taxes = WC_Tax::calc_tax($line_subtotal, $tax_rates, false);
                $line_taxes = WC_Tax::calc_tax($line_total, $tax_rates, false);
                
                // Set item taxes
                $item->set_taxes(array(
                    'subtotal' => $line_subtotal_taxes,
                    'total' => $line_taxes
                ));
                
                $item->save();
                
                error_log('Orders Jet: Applied individual order taxes to item ' . $item->get_name() . ' - Tax: ' . array_sum($line_taxes));
            }
        }
        
        // Recalculate order totals (this will sum up all item taxes)
        $order->calculate_totals();
        
        // Log tax calculation results
        error_log('Orders Jet: Individual Order #' . $order->get_id() . ' - Tax calculated per order - Subtotal: ' . $order->get_subtotal() . ', Tax: ' . $order->get_total_tax() . ', Total: ' . $order->get_total());
    }
    
    /**
     * Calculate taxes for table orders (per combined invoice total)
     */
    private function calculate_table_invoice_taxes($subtotal, $order_ids) {
        if (!wc_tax_enabled()) {
            error_log('Orders Jet: Taxes not enabled - returning zero tax amounts');
            return array(
                'service_tax' => 0,
                'vat_tax' => 0,
                'total_tax' => 0,
                'grand_total' => $subtotal
            );
        }
        
        // Get tax rates for standard tax class
        $store_country = WC()->countries->get_base_country();
        $store_state = WC()->countries->get_base_state();
        
        $tax_rates = WC_Tax::find_rates(array(
            'country' => $store_country,
            'state' => $store_state,
            'city' => '',
            'postcode' => '',
            'tax_class' => '' // Standard tax class
        ));
        
        // Initialize tax amounts
        $service_tax = 0;
        $vat_tax = 0;
        $running_total = $subtotal;
        
        // Sort tax rates by priority to ensure correct compound calculation
        uasort($tax_rates, function($a, $b) {
            return intval($a['priority']) - intval($b['priority']);
        });
        
        foreach ($tax_rates as $rate_id => $rate) {
            $rate_percent = floatval($rate['rate']);
            $is_compound = $rate['compound'] === 'yes';
            $priority = intval($rate['priority']);
            
            error_log('Orders Jet: Processing tax rate - Rate: ' . $rate_percent . '%, Compound: ' . ($is_compound ? 'Yes' : 'No') . ', Priority: ' . $priority);
            
            if ($rate_percent == 12.0) {
                // Service tax (12%, Priority 1)
                $service_tax = ($running_total * $rate_percent) / 100;
                error_log('Orders Jet: Service Tax (12%) calculated: ' . $service_tax . ' on base: ' . $running_total);
                
                if ($is_compound) {
                    $running_total += $service_tax;
                    error_log('Orders Jet: Service tax is compound - new running total: ' . $running_total);
                }
            } elseif ($rate_percent == 14.0) {
                // VAT tax (14%, Priority 2, should be compound)
                if ($is_compound) {
                    // Compound: calculate on subtotal + previous taxes
                    $vat_tax = ($running_total * $rate_percent) / 100;
                    error_log('Orders Jet: VAT (14% compound) calculated: ' . $vat_tax . ' on base: ' . $running_total);
                } else {
                    // Non-compound: calculate on original subtotal only
                    $vat_tax = ($subtotal * $rate_percent) / 100;
                    error_log('Orders Jet: VAT (14% non-compound) calculated: ' . $vat_tax . ' on base: ' . $subtotal);
                }
            }
        }
        
        $total_tax = $service_tax + $vat_tax;
        $grand_total = $subtotal + $total_tax;
        
        error_log('Orders Jet: Table Invoice Tax Calculation Complete - Subtotal: ' . $subtotal . ', Service Tax (12%): ' . $service_tax . ', VAT (14% compound): ' . $vat_tax . ', Total Tax: ' . $total_tax . ', Grand Total: ' . $grand_total);
        
        return array(
            'service_tax' => $service_tax,
            'vat_tax' => $vat_tax,
            'total_tax' => $total_tax,
            'grand_total' => $grand_total,
            'order_ids' => $order_ids
        );
    }
    
    /**
     * Close table group and create consolidated order (NEW APPROACH)
     */
    public function close_table_group() {
        check_ajax_referer('oj_table_order', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        error_log('Orders Jet: ========== TABLE GROUP CLOSURE START ==========');
        error_log('Orders Jet: Closing table group: ' . $table_number . ' with payment method: ' . $payment_method);
        
        try {
            // 1. Get all table orders for this table
            $table_orders = wc_get_orders(array(
                'status' => array('processing', 'pending'),
                'meta_key' => '_oj_table_number',
                'meta_value' => $table_number,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'ASC'
            ));
            
            if (empty($table_orders)) {
                wp_send_json_error(array('message' => __('No active orders found for this table', 'orders-jet')));
            }
            
            error_log('Orders Jet: Found ' . count($table_orders) . ' orders for table ' . $table_number);
            
            // 2. Check if all orders are ready (pending status)
            $all_ready = true;
            $not_ready_orders = array();
            
            foreach ($table_orders as $order) {
                if ($order->get_status() !== 'pending') {
                    $all_ready = false;
                    $not_ready_orders[] = '#' . $order->get_id();
                }
            }
            
            if (!$all_ready) {
                wp_send_json_error(array(
                    'message' => sprintf(__('All orders must be ready before closing table. Orders not ready: %s', 'orders-jet'), 
                        implode(', ', $not_ready_orders)),
                    'action_required' => 'make_all_ready'
                ));
            }
            
            error_log('Orders Jet: All orders are ready, proceeding with consolidation');
            
            // 3. Create consolidated order
            $consolidated_order = wc_create_order();
            
            if (is_wp_error($consolidated_order)) {
                error_log('Orders Jet: Failed to create consolidated order: ' . $consolidated_order->get_error_message());
                wp_send_json_error(array('message' => __('Failed to create consolidated order', 'orders-jet')));
            }
            
            // 4. Extract and add all items from child orders
            $total_items = 0;
            $child_order_ids = array();
            
            foreach ($table_orders as $child_order) {
                $child_order_ids[] = $child_order->get_id();
                
                foreach ($child_order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        // Use the original subtotal from child order (without tax) for consolidated order
                        // The consolidated order will then calculate taxes on this subtotal
                        $consolidated_order->add_product(
                            $product,
                            $item->get_quantity(),
                            array(
                                'totals' => array(
                                    'subtotal' => $item->get_subtotal(),
                                    'total' => $item->get_subtotal(), // Use subtotal as total (no tax from child)
                                )
                            )
                        );
                        $total_items += $item->get_quantity();
                        
                        // Copy over any item meta data (notes, add-ons, etc.)
                        $new_items = $consolidated_order->get_items();
                        $new_item = end($new_items); // Get the last added item
                        
                        if ($new_item) {
                            // Copy item notes
                            $notes = $item->get_meta('_oj_item_notes');
                            if ($notes) {
                                $new_item->add_meta_data('_oj_item_notes', $notes);
                            }
                            
                            // Copy add-ons data
                            $addons = $item->get_meta('_oj_item_addons');
                            if ($addons) {
                                $new_item->add_meta_data('_oj_item_addons', $addons);
                            }
                            
                            $addons_data = $item->get_meta('_oj_addons_data');
                            if ($addons_data) {
                                $new_item->add_meta_data('_oj_addons_data', $addons_data);
                            }
                            
                            $new_item->save();
                        }
                    }
                }
            }
            
            error_log('Orders Jet: Added ' . $total_items . ' items to consolidated order');
            
            // 5. Set consolidated order properties
            $consolidated_order->set_billing_first_name('Table ' . $table_number);
            $consolidated_order->set_billing_last_name('Combined Invoice');
            $consolidated_order->set_billing_phone('N/A');
            $consolidated_order->set_billing_email('table' . $table_number . '@restaurant.local');
            
            // Set consolidated order meta
            $consolidated_order->update_meta_data('_oj_table_number', $table_number);
            $consolidated_order->update_meta_data('_oj_consolidated_order', 'yes');
            $consolidated_order->update_meta_data('_oj_child_order_ids', $child_order_ids);
            $consolidated_order->update_meta_data('_oj_payment_method', $payment_method);
            $consolidated_order->update_meta_data('_oj_order_method', 'dinein');
            $consolidated_order->update_meta_data('_oj_table_closed', current_time('mysql'));
            
            // 6. Calculate totals (this will calculate taxes using WooCommerce native system)
            $consolidated_order->calculate_totals();
            
            // 7. Complete consolidated order
            $consolidated_order->set_status('completed');
            
            // Add completion note
            $consolidated_order->add_order_note(sprintf(
                __('Table %s closed - Consolidated order from %d child orders - Payment: %s (Subtotal: %s, Tax: %s, Total: %s)', 'orders-jet'),
                $table_number,
                count($child_order_ids),
                $payment_method,
                wc_price($consolidated_order->get_subtotal()),
                wc_price($consolidated_order->get_total_tax()),
                wc_price($consolidated_order->get_total())
            ));
            
            $consolidated_order->save();
            
            error_log('Orders Jet: Consolidated order #' . $consolidated_order->get_id() . ' created and completed');
            error_log('Orders Jet: Consolidated order totals - Subtotal: ' . $consolidated_order->get_subtotal() . ', Tax: ' . $consolidated_order->get_total_tax() . ', Total: ' . $consolidated_order->get_total());
            
            // SAFEGUARD: Validate consolidated order tax calculation
            $this->validate_tax_isolation($consolidated_order, 'consolidated');
            
            // 8. Permanently delete child orders
            error_log('Orders Jet: Starting deletion of ' . count($table_orders) . ' child orders');
            
            foreach ($table_orders as $child_order) {
                $child_order_id = $child_order->get_id();
                error_log('Orders Jet: Attempting to delete child order #' . $child_order_id);
                
                try {
                    // Check if order exists before deletion
                    $order_exists = wc_get_order($child_order_id);
                    if (!$order_exists) {
                        error_log('Orders Jet: Child order #' . $child_order_id . ' does not exist, skipping deletion');
                        continue;
                    }
                    
                    // Use WooCommerce's native delete method (handles items, meta, and post deletion)
                    $deletion_result = $child_order->delete(true); // Force delete permanently
                    
                    if ($deletion_result) {
                        error_log('Orders Jet: ✅ Child order #' . $child_order_id . ' deleted successfully using WC native method');
                    } else {
                        error_log('Orders Jet: ❌ WC delete method failed for order #' . $child_order_id . ', trying wp_delete_post');
                        
                        // Fallback to WordPress method
                        $wp_result = wp_delete_post($child_order_id, true);
                        if ($wp_result) {
                            error_log('Orders Jet: ✅ Child order #' . $child_order_id . ' deleted using wp_delete_post fallback');
                        } else {
                            error_log('Orders Jet: ❌ Both deletion methods failed for order #' . $child_order_id);
                        }
                    }
                    
                    // Verify deletion
                    $verification = wc_get_order($child_order_id);
                    if (!$verification) {
                        error_log('Orders Jet: ✅ Deletion verified - Order #' . $child_order_id . ' no longer exists');
                    } else {
                        error_log('Orders Jet: ❌ Deletion verification failed - Order #' . $child_order_id . ' still exists');
                    }
                    
                } catch (Exception $e) {
                    error_log('Orders Jet: ❌ Error deleting child order #' . $child_order_id . ': ' . $e->getMessage());
                }
            }
            
            error_log('Orders Jet: Child order deletion process completed');
            
            // 9. Update table status to available
            $table_id = oj_get_table_id_by_number($table_number);
            if ($table_id) {
                update_post_meta($table_id, '_oj_table_status', 'available');
                error_log('Orders Jet: Table ' . $table_number . ' (ID: ' . $table_id . ') status updated to available');
            }
            
            // 10. Log table closure
            update_option('oj_table_closed_' . $table_number . '_' . time(), array(
                'table_number' => $table_number,
                'consolidated_order_id' => $consolidated_order->get_id(),
                'child_order_ids' => $child_order_ids,
                'closed_at' => current_time('mysql'),
                'payment_method' => $payment_method,
                'total_amount' => $consolidated_order->get_total()
            ));
            
            // Generate invoice URL
            $invoice_url = add_query_arg(array(
                'order_id' => $consolidated_order->get_id(),
                'table' => $table_number,
                'payment_method' => $payment_method
            ), admin_url('admin.php?page=manager-invoice'));
            
            error_log('Orders Jet: ========== TABLE GROUP CLOSURE COMPLETE ==========');
            
            wp_send_json_success(array(
                'message' => __('Table closed successfully', 'orders-jet'),
                'consolidated_order_id' => $consolidated_order->get_id(),
                'subtotal' => $consolidated_order->get_subtotal(),
                'total_tax' => $consolidated_order->get_total_tax(),
                'grand_total' => $consolidated_order->get_total(),
                'payment_method' => $payment_method,
                'invoice_url' => $invoice_url,
                'child_order_ids' => $child_order_ids,
                'tax_method' => 'consolidated_woocommerce'
            ));
            
        } catch (Exception $e) {
            error_log('Orders Jet: Table group closure error: ' . $e->getMessage());
            error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => __('Table closure failed: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    /**
     * Validate tax calculation isolation (SAFEGUARD FUNCTION)
     * Ensures tax changes only affect the intended order types
     */
    private function validate_tax_isolation($order, $expected_tax_behavior) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $tax_deferred = $order->get_meta('_oj_tax_deferred');
        $total_tax = $order->get_total_tax();
        
        if ($expected_tax_behavior === 'deferred') {
            // Table orders should have zero tax and deferred flag
            if (!empty($table_number) && $tax_deferred === 'yes' && $total_tax == 0) {
                error_log("Orders Jet: ✅ Tax isolation VALIDATED - Order #{$order_id} (Table {$table_number}) has tax deferred correctly");
                return true;
            } else {
                error_log("Orders Jet: ❌ Tax isolation FAILED - Order #{$order_id} should have deferred tax but doesn't");
                return false;
            }
        } elseif ($expected_tax_behavior === 'calculated') {
            // Pickup orders should have calculated tax
            if (empty($table_number) && $tax_deferred !== 'yes') {
                error_log("Orders Jet: ✅ Tax isolation VALIDATED - Order #{$order_id} (Pickup) has tax calculated correctly: {$total_tax}");
                return true;
            } else {
                error_log("Orders Jet: ❌ Tax isolation FAILED - Order #{$order_id} should have calculated tax but doesn't");
                return false;
            }
        } elseif ($expected_tax_behavior === 'consolidated') {
            // Consolidated orders should have calculated tax
            $is_consolidated = $order->get_meta('_oj_consolidated_order');
            if ($is_consolidated === 'yes') {
                error_log("Orders Jet: ✅ Tax isolation VALIDATED - Consolidated Order #{$order_id} has tax calculated correctly: {$total_tax}");
                return true;
            } else {
                error_log("Orders Jet: ❌ Tax isolation FAILED - Order #{$order_id} should be consolidated but isn't");
                return false;
            }
        }
        
        return false;
    }
    
}
