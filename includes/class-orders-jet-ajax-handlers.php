<?php
declare(strict_types=1);
/**
 * Orders Jet - AJAX Handlers Class
 * Handles AJAX requests for table ordering system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_AJAX_Handlers {
    
    /**
     * Service instances (Phase 2 refactoring)
     */
    private $tax_service;
    private $kitchen_service;
    private $notification_service;
    
    /**
     * Handler factory instance (Phase 3 refactoring)
     */
    private $handler_factory;
    
    public function __construct() {
        // Initialize service classes (Phase 2 refactoring)
        $this->tax_service = new Orders_Jet_Tax_Service();
        $this->kitchen_service = new Orders_Jet_Kitchen_Service();
        $this->notification_service = new Orders_Jet_Notification_Service();
        
        // Initialize handler factory (Phase 3-8 refactoring - FINAL PHASE COMPLETE!)
        // File size reduced from 4,470 → 1,417 lines (68.3% reduction)
        $this->handler_factory = new Orders_Jet_Handler_Factory(
            $this->tax_service,
            $this->kitchen_service,
            $this->notification_service
        );
        
        // AJAX handlers for logged in users
        add_action('wp_ajax_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_oj_get_table_orders', array($this, 'get_table_orders'));
        // CORE FUNCTIONALITY - Currently Used
        add_action('wp_ajax_oj_mark_order_ready', array($this, 'mark_order_ready'));
        add_action('wp_ajax_oj_complete_individual_order', array($this, 'complete_individual_order'));
        add_action('wp_ajax_oj_close_table_group', array($this, 'close_table_group'));
        add_action('wp_ajax_oj_get_order_invoice', array($this, 'get_order_invoice'));
        add_action('wp_ajax_oj_get_filter_counts', array($this, 'get_filter_counts'));
        add_action('wp_ajax_oj_confirm_payment_received', array($this, 'confirm_payment_received'));
        add_action('wp_ajax_oj_refresh_dashboard', array($this, 'refresh_dashboard_ajax'));
        
        // NOTE: Phase 1, 2, 3, 4 & 5 refactoring complete:
        // - Phase 1: Removed obsolete functions (4,470 → 3,483 lines)
        // - Phase 2: Extracted service classes (3,483 → 3,121 lines)  
        // - Phase 3: Extracted complex handlers (3,121 → 2,126 lines)
        // - Phase 4: Extracted product details handler (2,126 → 1,773 lines)
        // - Phase 5: Extracted dashboard analytics handler (1,773 → 1,678 lines)
        // - Total reduction: 2,792 lines (62% smaller, much better organized)
        
        // AJAX handlers for non-logged in users (guests)
        add_action('wp_ajax_nopriv_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_nopriv_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_nopriv_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_nopriv_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_nopriv_oj_get_table_orders', array($this, 'get_table_orders'));
        // Guest handlers kept minimal for security
    }
    
    /**
     * Submit table order (contactless)
     */
    public function submit_table_order() {
        try {
            check_ajax_referer('oj_table_order', 'nonce');
            
            $handler = $this->handler_factory->get_order_submission_handler();
            $result = $handler->process_submission($_POST);
            
            wp_send_json_success($result);
        
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
    
    /**
     * Get product details including add-ons and food information
     */
    public function get_product_details() {
        try {
            check_ajax_referer('oj_product_details', 'nonce');
            
            $handler = $this->handler_factory->get_product_details_handler();
            $result = $handler->get_details($_POST);
            
            wp_send_json_success($result);
        
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
        try {
        check_ajax_referer('oj_table_order', 'nonce');
        
            $handler = $this->handler_factory->get_table_query_handler();
            $result = $handler->get_orders($_POST);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('Orders Jet: Table query error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => __('Failed to get table orders: ' . $e->getMessage(), 'orders-jet')
            ));
        }
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
        try {
        // Check nonce for security
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
            $handler = $this->handler_factory->get_kitchen_management_handler();
            $result = $handler->mark_order_ready($_POST);

            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('Orders Jet Kitchen: Error marking order ready: ' . $e->getMessage());
            error_log('Orders Jet Kitchen: Stack trace: ' . $e->getTraceAsString());

            wp_send_json_error(array(
                'message' => __('Failed to mark order as ready: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    /**
     * Send notifications when order is ready
     */
    
    
    
    
    
    /**
     * Complete individual order
     */
    public function complete_individual_order() {
        try {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
            $handler = $this->handler_factory->get_individual_order_completion_handler();
            $result = $handler->complete_order($_POST);

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('Orders Jet: Individual order completion error: ' . $e->getMessage());
            error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());

            wp_send_json_error(array(
                'message' => __('Order completion failed: ' . $e->getMessage(), 'orders-jet')
            ));
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
     * Close table group and create consolidated order (NEW APPROACH)
     */
    public function close_table_group() {
        try {
        check_ajax_referer('oj_table_order', 'nonce');
        
            $handler = $this->handler_factory->get_table_closure_handler();
            $result = $handler->process_closure($_POST);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            // Check if this is a confirmation request (special case)
            $message = $e->getMessage();
            if (strpos($message, 'processing orders') !== false && strpos($message, 'Are you sure') !== false) {
                // This is a confirmation request, not a real error
                $processing_order_numbers = array(); // Would need to extract from message or modify handler
                wp_send_json_error(array(
                    'message' => $message,
                        'action_required' => 'confirm_force_close',
                        'show_confirmation' => true
                    ));
            } elseif (strpos($message, 'mixed orders are not fully ready') !== false) {
                // Kitchen blocking error
                wp_send_json_error(array(
                    'message' => $message,
                    'kitchen_blocking' => true
                ));
            } else {
                // Regular error
            error_log('Orders Jet: Table group closure error: ' . $e->getMessage());
            error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => __('Table closure failed: ' . $e->getMessage(), 'orders-jet')
            ));
            }
        }
    }
    
    /**
     * Validate tax calculation isolation (SAFEGUARD FUNCTION)
     * Ensures tax changes only affect the intended order types
     */
    
    
    /**
     * Get order invoice (for view/print)
     */
    public function get_order_invoice() {
        try {
            $handler = $this->handler_factory->get_invoice_generation_handler();
            $handler->generate_invoice($_GET);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Orders Jet: Error generating invoice: ' . $e->getMessage());
                error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());
            }
            wp_die(__('Error generating invoice: ', 'orders-jet') . $e->getMessage());
        }
    }
    
    
    /**
     * Generate HTML for single order invoice (thermal printer optimized)
     */
    private function generate_single_order_invoice_html($order, $print_mode = false) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $order_type = !empty($table_number) ? 'Table' : 'Pickup';
        
        // Get order items for thermal format
        $items_html = '';
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $notes = $item->get_meta('_oj_item_notes');
            $addons_text = '';
            
            // Get addons if available
            if (function_exists('wc_pb_get_bundled_order_items')) {
                $addon_names = array();
                $addons = $item->get_meta('_wc_pao_addon_value');
                if (!empty($addons)) {
                    foreach ($addons as $addon) {
                        if (!empty($addon['name'])) {
                            $addon_names[] = $addon['name'] . ': ' . $addon['value'];
                        }
                    }
                    $addons_text = implode(', ', $addon_names);
                }
            }
            
            $name = $item->get_name();
            // Truncate long names for thermal width (max 25 chars)
            if (strlen($name) > 25) {
                $name = substr($name, 0, 22) . '...';
            }
            
            $items_html .= '<tr>';
            $items_html .= '<td>' . $name;
            if ($notes) {
                $items_html .= '<br><span class="thermal-note">Note: ' . esc_html($notes) . '</span>';
            }
            if ($addons_text) {
                $items_html .= '<br><span class="thermal-note">+ ' . esc_html($addons_text) . '</span>';
            }
            $items_html .= '</td>';
            $items_html .= '<td class="thermal-center">' . $item->get_quantity() . '</td>';
            $items_html .= '<td class="thermal-right">' . number_format(floatval($item->get_total()), 2) . '</td>';
            $items_html .= '</tr>';
        }
        
        $print_button = $print_mode ? '
            <div class="thermal-print-button">
                <button onclick="window.print()">🖨️ Print Invoice</button>
            </div>' : '';
        
        // Get currency symbol
        $currency = get_woocommerce_currency();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . $order->get_order_number() . '</title>
    <style>
        /* Screen styles */
        body { 
            font-family: "Courier New", monospace; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            font-size: 14px;
            line-height: 1.3;
        }
        
        .invoice-container { 
            max-width: 400px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        .thermal-header { 
            text-align: center; 
            border-bottom: 1px dashed #000; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        
        .thermal-header h1 { 
            margin: 0 0 5px 0; 
            font-size: 18px; 
            font-weight: bold; 
        }
        
        .thermal-header p { 
            margin: 2px 0; 
            font-size: 12px; 
        }
        
        .thermal-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        
        .thermal-table td { 
            padding: 3px 2px; 
            border: none; 
            vertical-align: top; 
            font-size: 12px;
        }
        
        .thermal-table th {
            padding: 5px 2px;
            border: none;
            font-weight: bold;
            font-size: 12px;
        }
        
        .thermal-center { text-align: center; }
        .thermal-right { text-align: right; }
        
        .thermal-separator { 
            border-top: 1px dashed #000; 
            margin: 8px 0; 
        }
        
        .thermal-total { 
            font-weight: bold; 
            font-size: 14px; 
        }
        
        .thermal-note {
            font-size: 10px;
            color: #666;
        }
        
        .thermal-print-button {
            text-align: center;
            margin: 20px 0;
            print: none;
        }
        
        .thermal-print-button button {
            background: #c41e3a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .thermal-print-button button:hover {
            background: #a01729;
        }
        
        .thermal-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        
        /* Thermal printer optimizations */
        @media print {
            body {
                font-family: "Courier New", monospace !important;
                font-size: 12px !important;
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 5px !important;
                width: 80mm !important;
                background: white !important;
            }
            
            .invoice-container {
                width: 100% !important;
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .thermal-print-button {
                display: none !important;
            }
            
            .thermal-header h1 {
                font-size: 16px !important;
            }
            
            .thermal-table td, .thermal-table th {
                font-size: 10px !important;
                padding: 1px !important;
            }
            
            .thermal-total {
                font-size: 12px !important;
            }
            
            .thermal-note {
                font-size: 8px !important;
            }
            
            .thermal-footer {
                font-size: 8px !important;
            }
        }
    </style>
</head>
<body>
    ' . $print_button . '
    <div class="invoice-container">
        <div class="thermal-header">
            <h1>' . strtoupper(get_bloginfo('name')) . '</h1>
            <p>INVOICE</p>
            <p>Order #' . $order->get_order_number() . ' - ' . $order_type . '</p>
        </div>
        
        <table class="thermal-table">
            <tr><td>Order ID:</td><td class="thermal-right">#' . $order->get_id() . '</td></tr>
            <tr><td>Type:</td><td class="thermal-right">' . $order_type . '</td></tr>
            ' . (!empty($table_number) ? '<tr><td>Table:</td><td class="thermal-right">' . $table_number . '</td></tr>' : '') . '
            <tr><td>Date:</td><td class="thermal-right">' . $order->get_date_created()->format('Y-m-d H:i') . '</td></tr>
            <tr><td>Status:</td><td class="thermal-right">' . ucfirst($order->get_status()) . '</td></tr>
        </table>
        
        <div class="thermal-separator"></div>
        
        <table class="thermal-table">
            <tr>
                <th>Item</th>
                <th class="thermal-center">Qty</th>
                <th class="thermal-right">Total</th>
            </tr>
            <tr><td colspan="3" class="thermal-separator"></td></tr>
            ' . $items_html . '
        </table>
        
        <div class="thermal-separator"></div>
        
        <table class="thermal-table">
            <tr><td>Subtotal:</td><td class="thermal-right">' . number_format(floatval($order->get_subtotal()), 2) . ' ' . $currency . '</td></tr>
            ' . ($order->get_total_tax() > 0 ? '<tr><td>Tax:</td><td class="thermal-right">' . number_format(floatval($order->get_total_tax()), 2) . ' ' . $currency . '</td></tr>' : '') . '
            <tr class="thermal-total">
                <td>TOTAL:</td>
                <td class="thermal-right">' . number_format(floatval($order->get_total()), 2) . ' ' . $currency . '</td>
            </tr>
        </table>
        
        <div class="thermal-footer">
            <div>Thank you for your visit!</div>
            <div>Generated: ' . current_time('Y-m-d H:i:s') . '</div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get filter counts for dashboard
     */
    public function get_filter_counts() {
        try {
            // Check nonce for security
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            // Check permissions
            if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_woocommerce')) {
                wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
            }
            
            $handler = $this->handler_factory->get_dashboard_analytics_handler();
            $counts = $handler->get_filter_counts();
            
            wp_send_json_success($counts);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Orders Jet: Error getting filter counts: ' . $e->getMessage());
            }
            wp_send_json_error(array('message' => __('Error getting filter counts', 'orders-jet')));
        }
    }
    
    /**
     * Confirm payment received for individual order
     */
    public function confirm_payment_received() {
        try {
            // Check nonce for security
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            $handler = $this->handler_factory->get_kitchen_management_handler();
            $result = $handler->confirm_payment_received($_POST);

            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Orders Jet: Error confirming payment: ' . $e->getMessage());
                error_log('Orders Jet: Stack trace: ' . $e->getTraceAsString());
            }
            wp_send_json_error(array('message' => __('Error confirming payment: ' . $e->getMessage(), 'orders-jet')));
        }
    }
    
    // ========================================================================
    // DUAL KITCHEN SYSTEM FUNCTIONS
    // ========================================================================
    
    /**
     * Determine the kitchen type for an order based on its items
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string 'food', 'beverages', or 'mixed'
     */
    
    /**
     * AJAX Dashboard Refresh Handler (JavaScript Optimization)
     * Provides AJAX-based dashboard refresh instead of full page reload
     */
    public function refresh_dashboard_ajax() {
        // Verify nonce
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        try {
            // Get current page to determine what to refresh
            $page = sanitize_text_field($_POST['page'] ?? '');
            
            if ($page === 'orders-jet-express') {
                // Refresh express dashboard
                $this->refresh_express_dashboard();
            } else {
                // Refresh regular dashboard (fallback)
                $this->refresh_regular_dashboard();
            }
            
        } catch (Exception $e) {
            error_log('Orders Jet: Dashboard refresh error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to refresh dashboard', 'orders-jet')
            ));
        }
    }
    
    /**
     * Refresh Express Dashboard via AJAX
     */
    private function refresh_express_dashboard() {
        // Initialize services (same as template)
        $kitchen_service = new Orders_Jet_Kitchen_Service();
        $order_method_service = new Orders_Jet_Order_Method_Service();
        
        // Get active orders (same query as template)
        $active_orders = wc_get_orders(array(
            'status' => array('wc-pending', 'wc-processing'),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'objects'
        ));
        
        // Prepare orders data (same as template)
        $orders_data = array();
        $filter_counts = array(
            'active' => 0,
            'processing' => 0,
            'pending' => 0,
            'dinein' => 0,
            'takeaway' => 0,
            'delivery' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0
        );
        
        foreach ($active_orders as $order) {
            $order_data = oj_express_prepare_order_data($order, $kitchen_service, $order_method_service);
            $orders_data[] = $order_data;
            oj_express_update_filter_counts($filter_counts, $order_data);
        }
        
        // Generate orders HTML
        ob_start();
        if (empty($orders_data)) {
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/empty-state.php';
        } else {
            foreach ($orders_data as $order_data) {
                include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
            }
        }
        $orders_html = ob_get_clean();
        
        wp_send_json_success(array(
            'orders_html' => $orders_html,
            'filter_counts' => $filter_counts,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Refresh Regular Dashboard via AJAX (fallback)
     */
    private function refresh_regular_dashboard() {
        // For now, just return success - can be expanded later
        wp_send_json_success(array(
            'message' => __('Dashboard refreshed', 'orders-jet'),
            'timestamp' => current_time('mysql')
        ));
    }
}
