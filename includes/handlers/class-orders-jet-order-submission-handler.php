<?php
declare(strict_types=1);
/**
 * Orders Jet - Order Submission Handler Class
 * Handles complex order submission logic extracted from AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Order_Submission_Handler {
    
    private $tax_service;
    private $notification_service;
    
    public function __construct($tax_service, $notification_service) {
        $this->tax_service = $tax_service;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Process table order submission
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function process_submission($post_data) {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // Don't display errors, log them instead
        
        // Log the incoming request (debug mode only)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: ========== ORDER SUBMISSION START ==========');
            error_log('Orders Jet: Order submission request received');
            error_log('Orders Jet: POST data: ' . print_r($post_data, true));
            error_log('Orders Jet: User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
            error_log('Orders Jet: Current user ID: ' . get_current_user_id());
        }
        
        // Parse and validate input data
        $order_data = $this->parse_order_data($post_data);
        
        // Create WooCommerce order
        $order = $this->create_woocommerce_order($order_data);
        
        // Add items to order
        $this->add_items_to_order($order, $order_data['items']);
        
        // Set order metadata and customer info
        $this->set_order_metadata($order, $order_data);
        
        // Handle tax calculation
        $final_total = $this->handle_tax_calculation($order, $order_data);
        
        // Update table status
        $this->update_table_status($order_data['table_number'], $order_data['table_id']);
        
        // Send notifications
        $this->send_notifications($order);
        
        // Verify order was saved correctly
        $this->verify_order_saved($order);
        
        error_log('Orders Jet: ========== ORDER SUBMISSION COMPLETE ==========');
        
        return array(
            'message' => __('Order placed successfully', 'orders-jet'),
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total' => $final_total
        );
    }
    
    /**
     * Parse and validate order data from POST request
     */
    private function parse_order_data($post_data) {
        $table_id = 0; // Default value
        
        if (isset($post_data['order_data'])) {
            // New format - JSON data
            $order_data = json_decode(stripslashes($post_data['order_data']), true);
            $table_number = sanitize_text_field($order_data['table_number']);
            $items = $order_data['items'];
            $total = floatval($order_data['total']);
            
            error_log('Orders Jet: Received order data - Total from frontend: ' . $total);
            error_log('Orders Jet: Number of items: ' . count($items));
        } else {
            // Old format - individual fields (backward compatibility)
            $table_number = sanitize_text_field($post_data['table_number']);
            $table_id = intval($post_data['table_id'] ?? 0);
            $special_requests = sanitize_textarea_field($post_data['special_requests'] ?? '');
            $cart_items = $post_data['cart_items'] ?? array();
            
            // Convert old format to new format with backward compatibility
            $items = array();
            foreach ($cart_items as $item) {
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
            
            $total = 0; // Will be calculated from items
        }
        
        // Get table ID from table number if needed
        if (empty($table_id) || $table_id == 0) {
            $table_id = oj_get_table_id_by_number($table_number);
            error_log('Orders Jet: Retrieved table ID from table number: ' . $table_id);
        }
        
        // Validate required fields
        if (empty($table_number) || empty($items)) {
            throw new Exception(__('Table number and cart items are required', 'orders-jet'));
        }
        
        return array(
            'table_number' => $table_number,
            'table_id' => $table_id,
            'items' => $items,
            'total' => $total
        );
    }
    
    /**
     * Create WooCommerce order
     */
    private function create_woocommerce_order($order_data) {
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            error_log('Orders Jet: Failed to create WooCommerce order: ' . $order->get_error_message());
            throw new Exception(__('Failed to create order: ' . $order->get_error_message(), 'orders-jet'));
        }
        
        if (!$order) {
            error_log('Orders Jet: Order creation returned null');
            throw new Exception(__('Failed to create order: Unknown error', 'orders-jet'));
        }
        
        return $order;
    }
    
    /**
     * Add items to the WooCommerce order
     */
    private function add_items_to_order($order, $items) {
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
            $totals_array = array(
                'subtotal' => $total_price * $quantity,
                'total' => $total_price * $quantity,
                'subtotal_tax' => 0,
                'total_tax' => 0
            );
            
            $item_id = $order->add_product($product, $quantity, array(
                'variation' => ($variation_id > 0) ? $product->get_variation_attributes() : array(),
                'totals' => $totals_array
            ));
            
            if ($item_id) {
                $this->add_item_metadata($order->get_item($item_id), $notes, $add_ons, $base_price);
            } else {
                error_log('Orders Jet: Failed to add product to order: ' . $product_id);
            }
        }
    }
    
    /**
     * Add metadata to order item
     */
    private function add_item_metadata($order_item, $notes, $add_ons, $base_price) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Orders Jet: Added item using WooCommerce native method - Product: ' . $order_item->get_name());
        }
        
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
    }
    
    /**
     * Set order metadata and customer information
     */
    private function set_order_metadata($order, $order_data) {
        $table_number = $order_data['table_number'];
        $table_id = $order_data['table_id'];
        
        // Set order meta data (contactless - no customer details)
        $order->set_billing_first_name('Table ' . $table_number);
        $order->set_billing_last_name('Guest');
        $order->set_billing_phone('N/A');
        $order->set_billing_email('table' . $table_number . '@restaurant.local');
        
        // Check if this is the first order for this table in this session
        $is_new_session = $this->is_new_table_session($table_number);
        $session_id = $this->get_or_create_table_session($table_number);
        
        $order->update_meta_data('_oj_table_number', $table_number);
        $order->update_meta_data('_oj_table_id', $table_id ?? 0);
        $order->update_meta_data('_oj_order_method', 'dinein');
        $order->update_meta_data('_oj_contactless_order', 'yes');
        $order->update_meta_data('_oj_order_total', $order_data['total']);
        $order->update_meta_data('_oj_order_timestamp', current_time('mysql'));
        $order->update_meta_data('_oj_session_id', $session_id);
        $order->update_meta_data('_oj_session_start', $is_new_session ? 'yes' : 'no');
        
        // Set WooFood compatible order method meta
        $order->update_meta_data('exwf_odmethod', 'dinein');
        $order->update_meta_data('_oj_order_type', 'dine_in');
        
        // Set order status
        $order->set_status('processing');
        
        // Save order first to ensure all items are saved
        $order_id = $order->save();
        error_log('Orders Jet: Order saved with ID: ' . $order_id);
        
        // Trigger WooFood integration for dine-in order
        if (class_exists('Orders_Jet_WooFood_Integration')) {
            do_action('exwf_order_created', $order_id, 'dine_in');
        }
    }
    
    /**
     * Handle tax calculation for the order
     */
    private function handle_tax_calculation($order, $order_data) {
        $table_number = $order_data['table_number'];
        $total = $order_data['total'];
        
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
        $order->update_meta_data('_oj_original_total', $final_total);
        
        // Set basic order data without forcing tax to zero
        $order->update_meta_data('_order_shipping', 0);
        $order->update_meta_data('_order_shipping_tax', 0);
        $order->update_meta_data('_order_discount', 0);
        $order->update_meta_data('_order_discount_tax', 0);
        
        // For table orders, don't calculate taxes (they will be calculated on consolidated order)
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
        $this->tax_service->validate_tax_isolation($order, $expected_behavior);
        
        return $final_total;
    }
    
    /**
     * Update table status to occupied
     */
    private function update_table_status($table_number, $table_id) {
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
    }
    
    /**
     * Send notifications to staff
     */
    private function send_notifications($order) {
        $this->notification_service->send_order_notification($order);
        
        // Clear any WooCommerce cache for this order
        wp_cache_delete($order->get_id(), 'posts');
        wp_cache_delete($order->get_id(), 'post_meta');
    }
    
    /**
     * Verify order was saved correctly
     */
    private function verify_order_saved($order) {
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
            throw new Exception(__('Order verification failed - order not found in database', 'orders-jet'));
        }
    }
    
    /**
     * Check if this is a new session for the table
     * Note: This method needs to be implemented or moved from the main AJAX class
     */
    private function is_new_table_session($table_number) {
        // Check if there are any recent pending/processing orders for this table
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending'),
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
            'posts_per_page' => 1
        ));
        
        return empty($recent_orders);
    }
    
    /**
     * Get or create table session ID
     * Note: This method needs to be implemented or moved from the main AJAX class
     */
    private function get_or_create_table_session($table_number) {
        // Check for existing session
        $existing_session = get_transient('oj_table_session_' . $table_number);
        
        if ($existing_session) {
            return $existing_session;
        }
        
        // Create new session
        $session_id = 'session_' . $table_number . '_' . time();
        set_transient('oj_table_session_' . $table_number, $session_id, 4 * HOUR_IN_SECONDS);
        
        return $session_id;
    }
}
