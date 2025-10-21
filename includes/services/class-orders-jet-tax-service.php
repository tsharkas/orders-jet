<?php
declare(strict_types=1);
/**
 * Orders Jet - Tax Service Class
 * Handles tax calculations for individual orders and table invoices
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Tax_Service {
    
    /**
     * Calculate taxes for individual orders (per order basis)
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return void
     */
    public function calculate_individual_order_taxes($order) {
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
     * 
     * @param float $subtotal The combined subtotal of all orders
     * @param array $order_ids Array of order IDs for this table
     * @return array Tax calculation results
     */
    public function calculate_table_invoice_taxes($subtotal, $order_ids) {
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
        
        // Get all applicable tax rates for the store location
        $tax_rates = WC_Tax::find_rates(array(
            'country' => $store_country,
            'state' => $store_state,
            'city' => '',
            'postcode' => '',
            'tax_class' => ''  // Standard tax class
        ));
        
        if (empty($tax_rates)) {
            error_log('Orders Jet: No tax rates found for location - returning zero tax amounts');
            return array(
                'service_tax' => 0,
                'vat_tax' => 0,
                'total_tax' => 0,
                'grand_total' => $subtotal
            );
        }
        
        // Calculate taxes on the combined subtotal
        $calculated_taxes = WC_Tax::calc_tax($subtotal, $tax_rates, false);
        
        // Separate service tax and VAT if multiple rates exist
        $service_tax = 0;
        $vat_tax = 0;
        $total_tax = 0;
        
        foreach ($calculated_taxes as $rate_id => $tax_amount) {
            $rate = WC_Tax::_get_tax_rate($rate_id);
            $rate_label = strtolower($rate['tax_rate_name'] ?? '');
            
            if (strpos($rate_label, 'service') !== false) {
                $service_tax += $tax_amount;
            } elseif (strpos($rate_label, 'vat') !== false || strpos($rate_label, 'value') !== false) {
                $vat_tax += $tax_amount;
            } else {
                // Default to VAT if we can't determine the type
                $vat_tax += $tax_amount;
            }
            
            $total_tax += $tax_amount;
        }
        
        $grand_total = $subtotal + $total_tax;
        
        error_log('Orders Jet: Table invoice tax calculation - Subtotal: ' . $subtotal . ', Service Tax: ' . $service_tax . ', VAT: ' . $vat_tax . ', Total Tax: ' . $total_tax . ', Grand Total: ' . $grand_total);
        
        return array(
            'subtotal' => $subtotal,
            'service_tax' => $service_tax,
            'vat_tax' => $vat_tax,
            'total_tax' => $total_tax,
            'grand_total' => $grand_total,
            'tax_rates' => $tax_rates,
            'order_ids' => $order_ids
        );
    }
    
    /**
     * Validate tax isolation for orders
     * 
     * @param WC_Order $order The order to validate
     * @param string $expected_tax_behavior Expected behavior: 'deferred', 'calculated', or 'consolidated'
     * @return bool True if validation passes
     */
    public function validate_tax_isolation($order, $expected_tax_behavior) {
        $order_tax = $order->get_total_tax();
        $table_number = $order->get_meta('_oj_table_number');
        $is_table_order = !empty($table_number);
        
        switch ($expected_tax_behavior) {
            case 'deferred':
                // Table orders should have zero tax (deferred to consolidated invoice)
                if ($is_table_order && $order_tax > 0.01) {
                    error_log('Orders Jet: TAX ISOLATION VIOLATION - Table order #' . $order->get_id() . ' has individual tax: ' . $order_tax . ' (should be 0)');
                    return false;
                }
                break;
                
            case 'calculated':
                // Pickup orders should have calculated tax
                if (!$is_table_order && $order_tax <= 0) {
                    error_log('Orders Jet: TAX ISOLATION VIOLATION - Pickup order #' . $order->get_id() . ' has no tax: ' . $order_tax . ' (should be > 0)');
                    return false;
                }
                break;
                
            case 'consolidated':
                // Consolidated orders should have proper tax calculation
                $session_id = $order->get_meta('_oj_session_id');
                if (empty($session_id)) {
                    error_log('Orders Jet: TAX ISOLATION WARNING - Consolidated order #' . $order->get_id() . ' missing session ID');
                    return false;
                }
                break;
        }
        
        error_log('Orders Jet: Tax isolation validation PASSED for order #' . $order->get_id() . ' - Expected: ' . $expected_tax_behavior . ', Tax: ' . $order_tax);
        return true;
    }
    
    /**
     * Get tax summary for display purposes
     * 
     * @param WC_Order $order The order to get tax summary for
     * @return array Tax summary information
     */
    public function get_tax_summary($order) {
        $tax_data = array(
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'total' => $order->get_total(),
            'tax_enabled' => wc_tax_enabled(),
            'is_table_order' => !empty($order->get_meta('_oj_table_number')),
            'tax_method' => $order->get_meta('_oj_tax_method'),
            'tax_deferred' => $order->get_meta('_oj_tax_deferred') === 'yes'
        );
        
        // Get individual tax line items
        $tax_data['tax_lines'] = array();
        foreach ($order->get_tax_totals() as $code => $tax) {
            $tax_data['tax_lines'][] = array(
                'code' => $code,
                'label' => $tax->label,
                'amount' => $tax->amount,
                'formatted_amount' => $tax->formatted_amount
            );
        }
        
        return $tax_data;
    }
}
