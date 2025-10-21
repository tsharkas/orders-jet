<?php
declare(strict_types=1);
/**
 * Orders Jet - Product Details Handler Class
 * Handles complex product details retrieval including add-ons, variations, and food plugin integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Product_Details_Handler {
    
    /**
     * Get detailed product information including add-ons and variations
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Product details response data
     * @throws Exception On processing errors
     */
    public function get_details($post_data) {
        $product_id = intval($post_data['product_id']);
        
        if (!$product_id) {
            throw new Exception(__('Invalid product ID', 'orders-jet'));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Product not found', 'orders-jet'));
        }
        
        // Build response data structure
        $response_data = array(
            'food_info' => array(),
            'addons' => array(),
            'variations' => array(),
            'price_info' => $this->get_price_info($product),
            'debug' => $this->get_debug_info($product_id, $product)
        );
        
        // Get food information from various plugins
        $response_data['food_info'] = $this->get_food_info($product_id);
        
        // Get add-ons from various sources
        $response_data['addons'] = $this->get_product_addons($product_id, $product);
        
        // Get variations for variable products
        if ($product->is_type('variable')) {
            $response_data['variations'] = $this->get_product_variations($product);
        }
        
        return $response_data;
    }
    
    /**
     * Get price information for the product
     */
    private function get_price_info($product) {
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
        
        return $price_info;
    }
    
    /**
     * Get debug information for troubleshooting
     */
    private function get_debug_info($product_id, $product) {
        // Get ALL meta fields for debugging
        $all_meta = get_post_meta($product_id);
        $debug_info = array();
        
        foreach ($all_meta as $key => $value) {
            // Only show non-empty values and limit to reasonable size
            if (!empty($value) && (!is_array($value) || (is_array($value) && count($value) < 50))) {
                $debug_info[$key] = $value;
            }
        }
        
        // Additional debug: Check active food plugins
        $active_plugins = get_option('active_plugins');
        $food_plugins = array();
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'food') !== false || strpos($plugin, 'restaurant') !== false) {
                $food_plugins[] = $plugin;
            }
        }
        
        $debug_info['active_food_plugins'] = $food_plugins;
        $debug_info['product_type'] = $product->get_type();
        $debug_info['product_attributes'] = $product->get_attributes();
        
        return $debug_info;
    }
    
    /**
     * Get food information from WooCommerce Food plugin meta fields
     */
    private function get_food_info($product_id) {
        $food_info = array();
        
        $food_meta_fields = array(
            '_food_info', '_food_nutrition', '_food_allergens', '_food_calories',
            '_food_prep_time', '_food_cooking_time', '_food_serving_size'
        );
        
        foreach ($food_meta_fields as $field) {
            $value = get_post_meta($product_id, $field, true);
            if ($value) {
                $field_name = ucwords(str_replace(array('_food_', '_'), array('', ' '), $field));
                $food_info[$field_name] = $value;
            }
        }
        
        return $food_info;
    }
    
    /**
     * Get product add-ons from various plugin sources
     */
    private function get_product_addons($product_id, $product) {
        $addons = array();
        
        // Try multiple sources for add-ons
        $addons = array_merge($addons, $this->get_exfood_addons($product_id));
        $addons = array_merge($addons, $this->get_woocommerce_food_addons($product_id));
        $addons = array_merge($addons, $this->get_alternative_plugin_addons($product_id));
        $addons = array_merge($addons, $this->get_woocommerce_product_addons($product_id));
        $addons = array_merge($addons, $this->get_custom_food_plugin_addons($product_id));
        
        return $addons;
    }
    
    /**
     * Get add-ons from Exfood plugin
     */
    private function get_exfood_addons($product_id) {
        $addons = array();
        
        // Check for Exfood plugin exwo_options field (serialized data)
        $exwo_options = get_post_meta($product_id, 'exwo_options', true);
        if ($exwo_options) {
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
                        
                        $addons[] = $addon;
                    }
                }
            }
        }
        
        // Check for other Exfood plugin fields
        $exfood_fields = array(
            'exwf_addons', 'exwf_options', 'exwf_extra_items', 'exwf_product_addons',
            '_exwf_addons', '_exwf_options', '_exwf_extra_items', '_exwf_product_addons',
            'exwf_product_data', '_exwf_product_data'
        );
        
        foreach ($exfood_fields as $field) {
            $addon_data = get_post_meta($product_id, $field, true);
            if ($addon_data && is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    $addons[] = array(
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
        
        return $addons;
    }
    
    /**
     * Get add-ons from WooCommerce Food plugin
     */
    private function get_woocommerce_food_addons($product_id) {
        $addons = array();
        
        // Check for WooCommerce Food plugin add-ons
        $food_addons = get_post_meta($product_id, '_food_addons', true);
        if ($food_addons && is_array($food_addons)) {
            foreach ($food_addons as $addon) {
                $addons[] = array(
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
                $addons[] = array(
                    'id' => $option_group['id'] ?? uniqid(),
                    'name' => $option_group['title'] ?? $option_group['name'] ?? '',
                    'price' => '',
                    'required' => $option_group['required'] ?? false,
                    'max_selections' => $option_group['max_selections'] ?? null,
                    'options' => $option_group['options'] ?? array()
                );
            }
        }
        
        return $addons;
    }
    
    /**
     * Get add-ons from alternative food plugins
     */
    private function get_alternative_plugin_addons($product_id) {
        $addons = array();
        
        $alternative_fields = array(
            '_woo_food_addons', '_exfood_addons', '_food_extra_options',
            '_product_addons', '_additional_options', '_food_customization'
        );
        
        foreach ($alternative_fields as $field) {
            $addon_data = get_post_meta($product_id, $field, true);
            if ($addon_data && is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    $addons[] = array(
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
        
        return $addons;
    }
    
    /**
     * Get add-ons from WooCommerce Product Add-ons plugin
     */
    private function get_woocommerce_product_addons($product_id) {
        $addons = array();
        
        if (function_exists('WC_Product_Addons')) {
            $addon_data = WC_Product_Addons()->get_product_addons($product_id);
            if ($addon_data) {
                foreach ($addon_data as $addon) {
                    foreach ($addon['options'] as $option) {
                        $addons[] = array(
                            'id' => $addon['id'] . '_' . $option['label'],
                            'name' => $option['label'],
                            'price' => wc_price($option['price']),
                            'required' => $addon['required']
                        );
                    }
                }
            }
        }
        
        return $addons;
    }
    
    /**
     * Get add-ons from custom food plugin structures
     */
    private function get_custom_food_plugin_addons($product_id) {
        $addons = array();
        
        // Check for custom food plugin patterns
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
                        $addons[] = array(
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
            $plugin_addons = exfood_get_product_addons($product_id);
            if ($plugin_addons) {
                foreach ($plugin_addons as $addon) {
                    $addons[] = array(
                        'id' => $addon['id'] ?? uniqid(),
                        'name' => $addon['name'] ?? '',
                        'price' => isset($addon['price']) ? wc_price($addon['price']) : '',
                        'required' => $addon['required'] ?? false
                    );
                }
            }
        }
        
        return $addons;
    }
    
    /**
     * Get product variations for variable products
     */
    private function get_product_variations($product) {
        $variations = array();
        
        try {
            error_log('Orders Jet: Processing variable product with ID: ' . $product->get_id());
            
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
                
                $variations[$attribute_label] = $variation_options;
            }
        } catch (Exception $e) {
            error_log('Orders Jet: Error processing variations: ' . $e->getMessage());
            $variations = array();
        }
        
        return $variations;
    }
}
