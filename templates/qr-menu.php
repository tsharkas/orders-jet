<?php
/**
 * Table Menu Template - Clean Implementation
 * 
 * This template provides a clean, working implementation of the table menu
 * without conflicts with the main plugin scripts.
 */

// Get table data
$table_number = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';
$table_id = function_exists('oj_get_table_id_by_number') ? oj_get_table_id_by_number($table_number) : null;

if (!$table_number || !$table_id) {
    wp_die(__('Table not found', 'orders-jet'));
}

// Get table meta
$table_capacity = get_post_meta($table_id, '_oj_table_capacity', true);
$table_location = get_post_meta($table_id, '_oj_table_location', true);
$table_status = get_post_meta($table_id, '_oj_table_status', true);

// Get menu categories
$categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Get products
$products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish',
    'orderby' => 'menu_order',
    'order' => 'ASC'
));
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#c41e3a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo sprintf(__('Table %s Menu', 'orders-jet'), $table_number); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    
    <style>
        /* Clean, Modern CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .header h1 {
            color: #c41e3a;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        /* Tabs */
        .tabs {
            background: white;
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6c757d;
            text-align: center;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tab-btn:hover {
            border-color: #c41e3a;
            color: #c41e3a;
            background: #f8f9fa;
        }
        
        .tab-btn.active {
            background: #c41e3a;
            color: white;
            border-color: #c41e3a;
            box-shadow: 0 2px 8px rgba(196, 30, 58, 0.3);
        }
        
        .tab-btn.active:hover {
            background: #a0172f;
            border-color: #a0172f;
            color: white;
        }
        
        /* Category Filters */
        .category-filters {
            background: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .category-filters::-webkit-scrollbar {
            display: none;
        }
        
        .category-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef !important;
            background: white !important;
            border-radius: 25px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            white-space: nowrap !important;
            color: #333 !important;
            text-decoration: none !important;
            display: inline-block !important;
            min-width: auto !important;
            box-shadow: none !important;
        }
        
        .category-btn:hover {
            border-color: #c41e3a !important;
            background: #f8f9fa !important;
            color: #c41e3a !important;
        }
        
        .category-btn.active {
            background: #c41e3a !important;
            color: white !important;
            border-color: #c41e3a !important;
        }
        
        .category-btn.active:hover {
            background: #a0172f !important;
            color: white !important;
        }
        
        /* Menu Items */
        .menu-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 100px;
        }
        
        /* Category Sections */
        .category-section {
            margin-bottom: 30px;
        }
        
        .category-section-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding: 0 15px;
            position: sticky;
            top: 80px;
            background: #f8f9fa;
            z-index: 90;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Lazy Loading */
        .lazy-image {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .lazy-image.loaded {
            opacity: 1;
        }
        
        .lazy-placeholder {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
        }
        
        .menu-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 15px;
            gap: 15px;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .menu-item-image {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        
        .menu-item-content {
            flex: 1;
            padding: 0;
        }
        
        .menu-item-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            line-height: 1.3;
        }
        
        .menu-item-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .menu-item-price {
            font-size: 15px;
            font-weight: bold;
            color: #c41e3a;
            text-align: right;
            margin: 0;
        }
        
        .menu-item-hint {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
            font-style: italic;
            text-align: center;
        }
        
        /* Popup */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .popup-overlay.show {
            display: flex;
        }
        
        .popup-content {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .popup-header {
            padding: 20px 20px 0 20px;
            display: flex;
            justify-content: flex-end;
        }
        
        .popup-close {
            width: 40px;
            height: 40px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 50%;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #666;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .popup-close:hover {
            background: #f8f9fa;
            border-color: #c41e3a;
            color: #c41e3a;
            transform: scale(1.1);
        }
        
        .popup-body {
            padding: 20px;
        }
        
        .popup-image {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            overflow: hidden;
        }
        
        .popup-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .popup-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .popup-description {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .popup-price-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .popup-price {
            font-size: 20px;
            font-weight: bold;
            color: #c41e3a;
            margin: 0;
            line-height: 1.4;
        }
        
        .popup-price del {
            color: #999;
            font-size: 16px;
            margin-right: 8px;
        }
        
        .popup-price ins {
            text-decoration: none;
            color: #c41e3a;
            font-weight: bold;
        }
        
        .popup-section {
            margin-bottom: 25px;
        }
        
        .popup-section label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #c41e3a;
            background: white;
            color: #c41e3a;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background: #c41e3a;
            color: white;
        }
        
        .quantity-input {
            width: 60px;
            height: 35px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        
        .notes-textarea {
            width: 100%;
            min-height: 100px;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            resize: vertical;
            font-family: inherit;
        }
        
        /* Exfood Add-on Styling */
        .exrow-group {
            display: block;
            width: 100%;
            box-sizing: border-box;
            margin: 0 0 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px solid #e9ecef;
        }
        
        .exrow-group * {
            box-sizing: border-box;
        }
        
        .exrow-group .exfood-label {
            display: block;
            font-weight: 600;
            margin: 0 0 15px 0;
            cursor: pointer;
            font-size: 16px;
            color: #333;
            text-transform: none;
        }
        
        .exrow-group .exwo-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        /* Beautiful Add-on Option Items */
        .addon-option-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .addon-option-item:hover {
            border-color: #c41e3a;
            box-shadow: 0 2px 8px rgba(196, 30, 58, 0.1);
        }
        
        .addon-option-content {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .addon-option-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: #333;
            flex: 1;
        }
        
        .ex-options {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
            accent-color: #c41e3a;
        }
        
        .ex-options[type="radio"] {
            border-radius: 50%;
        }
        
        .ex-options[type="checkbox"] {
            border-radius: 4px;
        }
        
        .exwo-op-name {
            font-size: 15px;
            font-weight: 500;
            color: #333;
        }
        
        .exwo-op-img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            margin-right: 8px;
        }
        
        .exwo-op-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Variation Option Styling - Same as Add-ons */
        .variation-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 8px;
        }
        
        .variation-option:hover {
            border-color: #c41e3a;
            box-shadow: 0 2px 8px rgba(196, 30, 58, 0.1);
        }
        
        .variation-option .variation-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: #333;
            flex: 1;
        }
        
        .variation-option .variation-price {
            color: #c41e3a;
            font-weight: 600;
            font-size: 14px;
        }
        
        .variation-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0 8px 0 0;
            cursor: pointer;
            accent-color: #c41e3a;
        }
        
        .addon-option-qty {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .ex-qty-op {
            width: 50px;
            height: 32px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }
        
        .ex-qty-op:focus {
            border-color: #c41e3a;
            outline: none;
        }
        
        /* Selected state styling */
        .addon-option-item:has(.ex-options:checked) {
            border-color: #c41e3a;
            background: #fff5f5;
            box-shadow: 0 2px 12px rgba(196, 30, 58, 0.15);
        }
        
        .addon-option-item:has(.ex-options:checked) .exwo-op-name {
            color: #c41e3a;
            font-weight: 600;
        }
        
        /* Required field styling */
        .exrow-group.ex-required .exfood-label::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Disabled state */
        .addon-option-item:has(.ex-options:disabled) {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .addon-option-item:has(.ex-options:disabled):hover {
            border-color: #e9ecef;
            box-shadow: none;
        }
        
        .ex-red-message,
        .ex-required-min-message,
        .ex-required-max-message,
        .ex-required-message {
            color: red;
            padding: 0;
            margin: 3px 0;
            display: none;
            font-size: 12px;
        }
        
        .ex-required span.exfood-label .exwo-otitle:after {
            content: " * ";
            color: red;
        }
        
        .exrow-group span.exfood-label span {
            margin: 0;
            padding: 0;
        }
        
        .exrow-group .exwo-container {
            margin-top: 8px;
        }
        
        .exrow-group input.ex-options[type="number"],
        .exrow-group input.ex-options[type="text"],
        .exrow-group textarea.ex-options {
            width: 100%;
            border: 1px solid #ddd;
            background: #fafafa;
            padding: 8px;
            border-radius: 4px;
        }
        
        .exrow-group .exwo-container.exwo-qty-option > span {
            width: 100%;
            position: relative;
            padding: 10px 85px 10px 0;
        }
        
        .exrow-group .exwo-container.exwo-qty-option input + label + .exqty-op {
            pointer-events: none;
            opacity: .3;
            position: absolute;
            right: 0;
            top: 50%;
            transform: translate(0, -50%);
            width: 85px;
            padding: 0 0 0 5px;
        }
        
        .exrow-group .exwo-container.exwo-qty-option input:checked + label + .exqty-op {
            pointer-events: auto;
            opacity: 1;
        }
        
        .exrow-group .exwo-container.exwo-qty-option .exqty-op input {
            height: 37px;
            margin: 0;
            padding: 8px 10px;
            border: 1px solid #ddd;
            width: 100%;
        }
        
        /* Food Information Styling */
        .food-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .food-info-item:last-child {
            border-bottom: none;
        }
        
        .food-info-label {
            font-weight: 600;
            color: #333;
        }
        
        .food-info-value {
            color: #666;
        }
        
        .popup-actions {
            display: flex;
            gap: 12px;
            padding: 20px;
        }
        
        .popup-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .popup-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .popup-btn-secondary:hover {
            background: #5a6268;
        }
        
        .popup-btn-primary {
            background: #c41e3a;
            color: white;
        }
        
        .popup-btn-primary:hover {
            background: #a0172f;
        }
        
        /* Floating Cart */
        .floating-cart {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: #c41e3a;
            color: white;
            padding: 15px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(196, 30, 58, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .floating-cart.show {
            display: flex;
        }
        
        .floating-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(196, 30, 58, 0.4);
        }
        
        /* Cart Tab */
        .cart-content {
            display: none;
        }
        
        .cart-content.active {
            display: block;
        }
        
        /* Hide menu content when cart is active */
        body.cart-active #menu-tab {
            display: none;
        }
        
        body.cart-active .category-filters {
            display: none;
        }
        
        /* Hide menu content when history is active */
        body.history-active #menu-tab {
            display: none;
        }
        
        body.history-active .category-filters {
            display: none;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            color: #c41e3a;
            font-weight: 600;
            line-height: 1.4;
        }
        
        .cart-item-price del {
            color: #999;
            font-size: 14px;
            margin-right: 6px;
        }
        
        .cart-item-price ins {
            text-decoration: none;
            color: #c41e3a;
            font-weight: bold;
        }
        
        .cart-addon-details {
            margin: 8px 0;
            padding-left: 15px;
        }
        
        .cart-addon-item {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
            padding: 2px 0;
        }
        
        .cart-item-notes {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        .cart-item-total {
            font-size: 14px;
            font-weight: 600;
            color: #c41e3a;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }
        
        /* App Notification System */
        .app-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 350px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        .app-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .app-notification-content {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        
        .app-notification-success .app-notification-content {
            border-left: 4px solid #28a745;
        }
        
        .app-notification-error .app-notification-content {
            border-left: 4px solid #dc3545;
        }
        
        .app-notification-info .app-notification-content {
            border-left: 4px solid #17a2b8;
        }
        
        .app-notification-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        
        .app-notification-close {
            background: none;
            border: none;
            font-size: 18px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .app-notification-close:hover {
            color: #333;
        }
        
        /* App Confirm Dialog */
        .app-confirm-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10001;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .app-confirm-dialog.show {
            opacity: 1;
        }
        
        .app-dialog-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .app-dialog-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 25px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .app-dialog-message {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 25px;
            line-height: 1.4;
        }
        
        .app-dialog-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .app-dialog-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 80px;
        }
        
        .app-dialog-cancel {
            background: #6c757d;
            color: white;
        }
        
        .app-dialog-cancel:hover {
            background: #5a6268;
        }
        
        .app-dialog-confirm {
            background: #c41e3a;
            color: white;
        }
        
        .app-dialog-confirm:hover {
            background: #a0172f;
        }
        
        .cart-item-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .cart-total {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #c41e3a;
        }
        
        .cart-actions {
            display: flex;
            gap: 15px;
        }
        
        /* Order History Tab Styles */
        .history-content {
            display: none;
        }
        
        .history-content.active {
            display: block;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .history-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .table-total {
            font-weight: bold;
            color: #dc3545;
            font-size: 16px;
        }
        
        .order-history-item {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background: white;
        }
        
        .order-history-item:last-child {
            border-bottom: none;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .order-number {
            font-weight: bold;
            color: #007cba;
        }
        
        .order-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .order-status.processing {
            background: #d4edda;
            color: #155724;
        }
        
        .order-status.completed {
            background: #cce5ff;
            color: #004085;
        }
        
        .order-total {
            font-weight: bold;
            color: #dc3545;
        }
        
        .order-items {
            margin-top: 10px;
        }
        
        .order-item {
            padding: 12px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .order-item-name {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .order-item-quantity {
            color: #666;
            font-size: 14px;
            margin: 0 10px;
        }
        
        .order-item-price {
            font-weight: 600;
            color: #c41e3a;
        }
        
        .order-item-variations {
            margin-bottom: 6px;
        }
        
        .order-item-variation {
            display: inline-block;
            background: #f8f9fa;
            color: #666;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 6px;
            margin-bottom: 4px;
        }
        
        .order-item-addons {
            margin-bottom: 6px;
        }
        
        .order-item-addon {
            display: inline-block;
            background: #e8f5e8;
            color: #2d5a2d;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 6px;
            margin-bottom: 4px;
        }
        
        .order-item-notes {
            margin-bottom: 6px;
        }
        
        .order-item-note {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-style: italic;
        }
        
        /* Order History - Cart-like styling */
        .order-item-name {
            font-weight: bold;
            color: #333;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .order-item-price {
            color: #c41e3a;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .order-item-addons {
            margin: 4px 0;
        }
        
        .order-item-addon-detail {
            color: #666;
            font-size: 14px;
            margin: 2px 0;
            padding-left: 10px;
        }
        
        .order-item-notes {
            margin: 4px 0;
        }
        
        .order-item-total {
            color: #c41e3a;
            font-weight: bold;
            font-size: 14px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        
        .history-actions {
            padding: 20px 15px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .pay-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .invoice-total {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            min-width: 200px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .invoice-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .invoice-amount {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
        }
        
        .cart-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .cart-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .cart-btn-primary {
            background: #28a745;
            color: white;
        }
        
        .cart-btn-primary:disabled {
            background: #6c757d;
            color: #fff;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .payment-success {
            text-align: center;
            padding: 30px;
            background: #f8fff8;
            border: 2px solid #28a745;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .payment-success h3 {
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .payment-success p {
            margin: 10px 0;
            font-size: 16px;
        }
        
        .thank-you-message {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .thank-you-message h4 {
            color: #c41e3a;
            margin-bottom: 10px;
        }
        
        /* Product Popup Loading State */
        .popup-loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 15px;
        }
        
        .popup-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #c41e3a;
            border-radius: 50%;
            animation: popup-spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes popup-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .popup-loading-text {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }
        
        .popup-content.loading {
            position: relative;
        }
        
        .popup-content.loading .popup-body {
            opacity: 0.3;
            pointer-events: none;
        }
        
        .popup-content.loading .popup-actions {
            opacity: 0.3;
            pointer-events: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .category-filters {
                padding: 10px;
            }
            
            .popup-overlay {
                padding: 10px;
            }
            
            /* Mobile add-on styling */
            .addon-option-item {
                padding: 10px 12px;
            }
            
            .addon-option-content {
                gap: 10px;
            }
            
            .exwo-op-name {
                font-size: 14px;
            }
            
            .ex-qty-op {
                width: 45px;
                height: 28px;
                font-size: 13px;
            }
            
            .exrow-group {
                padding: 12px;
            }
            
            .exrow-group .exfood-label {
                font-size: 15px;
                margin-bottom: 12px;
            }
            
            /* Mobile image styling */
            .exwo-op-img {
                width: 35px;
                height: 35px;
                border-radius: 6px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo sprintf(__('Table %s', 'orders-jet'), $table_number); ?></h1>
            <p><?php echo sprintf(__('Capacity: %d people', 'orders-jet'), $table_capacity); ?></p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="menu"><?php _e('Menu', 'orders-jet'); ?></button>
            <button class="tab-btn" data-tab="cart"><?php _e('Cart', 'orders-jet'); ?></button>
            <button class="tab-btn" data-tab="history"><?php _e('Order History', 'orders-jet'); ?></button>
        </div>
        
        <!-- Menu Tab -->
        <div id="menu-tab" class="tab-content">
            <!-- Category Filters -->
            <div class="category-filters">
                <button class="category-btn active" data-category="all"><?php _e('All', 'orders-jet'); ?></button>
                <?php foreach ($categories as $category): ?>
                    <button class="category-btn" data-category="<?php echo esc_attr($category->slug); ?>">
                        <?php echo esc_html($category->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Menu Grid -->
            <div class="menu-grid" id="menu-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-section" id="category-<?php echo esc_attr($category->slug); ?>">
                        <h3 class="category-section-title"><?php echo esc_html($category->name); ?></h3>
                        <?php 
                        $category_products = get_posts(array(
                            'post_type' => 'product',
                            'posts_per_page' => -1,
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'product_cat',
                                    'field' => 'slug',
                                    'terms' => $category->slug,
                                ),
                            ),
                        ));
                        
                        foreach ($category_products as $post):
                            $product = wc_get_product($post->ID);
                        ?>
                            <div class="menu-item" data-product-id="<?php echo $product->get_id(); ?>" data-categories="<?php echo implode(',', wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'))); ?>">
                                <div class="menu-item-image">
                                    <?php if ($product->get_image_id()): ?>
                                        <img class="lazy-image" 
                                             data-src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'medium'); ?>" 
                                             alt="<?php echo esc_attr($product->get_name()); ?>"
                                             loading="lazy">
                                        <div class="lazy-placeholder"><?php _e('Loading...', 'orders-jet'); ?></div>
                                    <?php else: ?>
                                        <span><?php _e('No Image', 'orders-jet'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="menu-item-content">
                                    <h3 class="menu-item-title"><?php echo esc_html($product->get_name()); ?></h3>
                                    <p class="menu-item-description"><?php echo esc_html($product->get_short_description()); ?></p>
                                    <div class="menu-item-price"><?php echo $product->get_price_html(); ?></div>
                                    <div class="menu-item-hint"><?php _e('Tap to view details', 'orders-jet'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Cart Tab -->
        <div id="cart-tab" class="tab-content cart-content">
            <div class="cart-total">
                <?php _e('Total:', 'orders-jet'); ?> <span id="cart-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
            </div>
            
            <div id="cart-items">
                <!-- Cart items will be populated by JavaScript -->
            </div>
            
            <div class="cart-actions">
                <button class="cart-btn cart-btn-secondary" id="clear-cart"><?php _e('Clear Cart', 'orders-jet'); ?></button>
                <button class="cart-btn cart-btn-primary" id="place-order"><?php _e('Place Order', 'orders-jet'); ?></button>
            </div>
        </div>
        
        <!-- Order History Tab -->
        <div id="history-tab" class="tab-content history-content">
            <div class="history-header">
                <h3><?php _e('Your Orders', 'orders-jet'); ?></h3>
                <div class="table-total">
                    <?php _e('Table Total:', 'orders-jet'); ?> <span id="table-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
                </div>
            </div>
            
            <div id="order-history">
                <!-- Order history will be populated by JavaScript -->
            </div>
            
                    <div class="history-actions">
                        <div class="pay-section">
                            <div class="invoice-total">
                                <span class="invoice-label"><?php _e('Invoice Total:', 'orders-jet'); ?></span>
                                <span class="invoice-amount" id="invoice-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
                            </div>
                            <button class="cart-btn cart-btn-primary" id="pay-now"><?php _e('Ask for Invoice', 'orders-jet'); ?></button>
                        </div>
                    </div>
        </div>
    </div>
    
    <!-- Popup -->
    <div class="popup-overlay" id="product-popup">
        <div class="popup-content">
            <div class="popup-header">
                <button class="popup-close" id="popup-close">&times;</button>
            </div>
            <div class="popup-body">
                <div class="popup-image" id="popup-image">
                    <img src="" alt="" style="display: none;">
                    <span><?php _e('No Image', 'orders-jet'); ?></span>
                </div>
                <h2 class="popup-title" id="popup-title"></h2>
                <p class="popup-description" id="popup-description"></p>
                
                <div class="popup-price-section">
                    <div class="popup-price" id="popup-price"></div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" id="quantity-minus">-</button>
                        <input type="number" class="quantity-input" id="quantity-input" value="1" min="1">
                        <button class="quantity-btn" id="quantity-plus">+</button>
                    </div>
                </div>
                
                <!-- Food Information Section -->
                <div class="popup-section" id="food-info-section" style="display: none;">
                    <label><?php _e('Food Information', 'orders-jet'); ?></label>
                    <div id="food-info-content"></div>
                </div>
                
                <!-- Variations Section -->
                <div class="popup-section" id="variations-section" style="display: none;">
                    <label><?php _e('Options', 'orders-jet'); ?></label>
                    <div id="variations-content"></div>
                </div>
                
                <!-- Add-ons Section -->
                <div class="popup-section" id="addons-section" style="display: none;">
                    <label><?php _e('Add-ons', 'orders-jet'); ?></label>
                    <div id="addons-content"></div>
                </div>
                
                <div class="popup-section">
                    <textarea class="notes-textarea" id="popup-notes" placeholder="<?php _e('Any special requests or notes...', 'orders-jet'); ?>"></textarea>
                </div>
                
                <div class="popup-actions">
                    <button class="popup-btn popup-btn-secondary" id="popup-back"><?php _e('Back to Menu', 'orders-jet'); ?></button>
                    <button class="popup-btn popup-btn-primary" id="popup-add-to-cart"><?php _e('Add to Cart', 'orders-jet'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Cart -->
    <div class="floating-cart" id="floating-cart">
        <span>🛒</span>
        <span id="floating-cart-total">0.00 <?php echo get_woocommerce_currency_symbol(); ?></span>
    </div>
    
    <script>
    // Clean, Simple JavaScript Implementation
    (function() {
        'use strict';
        
        console.log('QR Menu script starting...');
        
        // State
        let cart = [];
        let currentProduct = null;
        
        // Elements
        const elements = {
            tabs: document.querySelectorAll('.tab-btn'),
            tabContents: document.querySelectorAll('.tab-content'),
            categoryBtns: document.querySelectorAll('.category-btn'),
            menuItems: document.querySelectorAll('.menu-item'),
            menuGrid: document.getElementById('menu-grid'),
            popup: document.getElementById('product-popup'),
            popupClose: document.getElementById('popup-close'),
            popupBack: document.getElementById('popup-back'),
            popupImage: document.getElementById('popup-image'),
            popupTitle: document.getElementById('popup-title'),
            popupDescription: document.getElementById('popup-description'),
            popupPrice: document.getElementById('popup-price'),
            popupNotes: document.getElementById('popup-notes'),
            quantityInput: document.getElementById('quantity-input'),
            quantityMinus: document.getElementById('quantity-minus'),
            quantityPlus: document.getElementById('quantity-plus'),
            popupAddToCart: document.getElementById('popup-add-to-cart'),
            floatingCart: document.getElementById('floating-cart'),
            floatingCartTotal: document.getElementById('floating-cart-total'),
            cartItems: document.getElementById('cart-items'),
            cartTotal: document.getElementById('cart-total'),
            clearCart: document.getElementById('clear-cart'),
            placeOrder: document.getElementById('place-order'),
            historyTab: document.getElementById('history-tab'),
            orderHistory: document.getElementById('order-history'),
            tableTotal: document.getElementById('table-total'),
            payNow: document.getElementById('pay-now')
        };
        
        // Initialize
        function init() {
            console.log('Orders Jet: Initializing clean implementation');
            bindEvents();
            loadCart();
            updateCartDisplay();
            initLazyLoading();
        }
        
        // Bind Events
        function bindEvents() {
            // Tab switching
            elements.tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.dataset.tab;
                    switchTab(tabName);
                });
            });
            
            // Category scrolling
            elements.categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const category = this.dataset.category;
                    scrollToCategory(category);
                    updateActiveCategory(this);
                });
            });
            
            // Menu item clicks
            elements.menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    showProductPopup(productId);
                });
            });
            
            // Popup events
            elements.popupClose.addEventListener('click', closePopup);
            elements.popupBack.addEventListener('click', closePopup);
            elements.popup.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePopup();
                }
            });
            
            // Quantity controls
            elements.quantityMinus.addEventListener('click', function() {
                const current = parseInt(elements.quantityInput.value);
                if (current > 1) {
                    elements.quantityInput.value = current - 1;
                }
            });
            
            elements.quantityPlus.addEventListener('click', function() {
                const current = parseInt(elements.quantityInput.value);
                elements.quantityInput.value = current + 1;
            });
            
            // Add to cart
            elements.popupAddToCart.addEventListener('click', addToCart);
            
            // Cart events
            elements.clearCart.addEventListener('click', clearCart);
            elements.placeOrder.addEventListener('click', placeOrder);
            elements.floatingCart.addEventListener('click', function() {
                switchTab('cart');
            });
            
            // Pay Now event
            elements.payNow.addEventListener('click', payNow);
            
            // Debug buttons removed - functionality is working
        }
        
        // Tab switching
        function switchTab(tabName) {
            elements.tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabName);
            });
            
            elements.tabContents.forEach(content => {
                content.classList.toggle('active', content.id === tabName + '-tab');
            });
            
            // Add/remove active classes to body
            if (tabName === 'cart') {
                document.body.classList.add('cart-active');
                document.body.classList.remove('history-active');
            } else if (tabName === 'history') {
                document.body.classList.add('history-active');
                document.body.classList.remove('cart-active');
            } else {
                document.body.classList.remove('cart-active');
                document.body.classList.remove('history-active');
            }
            
            // Load order history when switching to history tab
            if (tabName === 'history') {
                loadOrderHistory();
            }
        }
        
        // Category scrolling
        function scrollToCategory(category) {
            if (category === 'all') {
                // For "All", scroll to top of menu
                const menuGrid = document.getElementById('menu-grid');
                if (menuGrid) {
                    menuGrid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            } else {
                // For specific categories, scroll to that section
                const sectionId = `category-${category}`;
                const section = document.getElementById(sectionId);
                
                if (section) {
                    // Calculate offset for sticky header
                    const stickyHeader = document.querySelector('.category-filters');
                    const headerHeight = stickyHeader ? stickyHeader.offsetHeight + 20 : 80;
                    
                    // Get section position
                    const sectionRect = section.getBoundingClientRect();
                    const absoluteElementTop = sectionRect.top + window.pageYOffset;
                    
                    // Scroll to section with offset
                    window.scrollTo({
                        top: absoluteElementTop - headerHeight,
                        behavior: 'smooth'
                    });
                }
            }
        }
        
        function updateActiveCategory(activeBtn) {
            elements.categoryBtns.forEach(btn => {
                btn.classList.toggle('active', btn === activeBtn);
            });
        }
        
        // Lazy loading
        function initLazyLoading() {
            const lazyImages = document.querySelectorAll('.lazy-image');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const placeholder = img.nextElementSibling;
                            
                            img.src = img.dataset.src;
                            img.onload = function() {
                                img.classList.add('loaded');
                                if (placeholder) {
                                    placeholder.style.display = 'none';
                                }
                            };
                            observer.unobserve(img);
                        }
                    });
                });
                
                lazyImages.forEach(img => {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                lazyImages.forEach(img => {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                });
            }
        }
        
        // Product popup - Clean implementation
        function showProductPopup(productId) {
            // Find product data from menu item
            const menuItem = document.querySelector(`[data-product-id="${productId}"]`);
            if (!menuItem) {
                console.error('Menu item not found for product ID:', productId);
                return;
            }
            
            // Set current product data
            const priceElement = menuItem.querySelector('.menu-item-price');
            currentProduct = {
                id: productId,
                name: menuItem.querySelector('.menu-item-title').textContent,
                description: menuItem.querySelector('.menu-item-description').textContent,
                price: priceElement.textContent,
                priceHTML: priceElement.innerHTML,
                image: menuItem.querySelector('img')?.src || ''
            };
            
            // Populate popup with basic product data
            elements.popupTitle.textContent = currentProduct.name;
            elements.popupDescription.textContent = currentProduct.description;
            elements.popupPrice.innerHTML = currentProduct.priceHTML;
            elements.popupNotes.value = '';
            elements.quantityInput.value = 1;
            
            // Handle product image
            const img = elements.popupImage.querySelector('img');
            const placeholder = elements.popupImage.querySelector('span');
            if (currentProduct.image) {
                img.src = currentProduct.image;
                img.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'block';
            }
            
            // Show popup with loading state
            elements.popup.classList.add('show');
            showPopupLoading();
            
            // Load additional product data (variations, add-ons)
            loadProductDetails(productId);
        }
        
        // Load product details - Clean implementation
        function loadProductDetails(productId) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'oj_get_product_details',
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce('oj_product_details'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProductDetails(data.data);
                } else {
                    console.error('Error loading product details:', data.data?.message || 'Unknown error');
                }
                hidePopupLoading();
            })
            .catch(error => {
                console.error('Error loading product details:', error);
                hidePopupLoading();
            });
        }
        
        // Show popup loading state
        function showPopupLoading() {
            const popupContent = document.querySelector('.popup-content');
            if (!popupContent) return;
            
            // Add loading class to popup content
            popupContent.classList.add('loading');
            
            // Create and show loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'popup-loading';
            loadingOverlay.id = 'popup-loading-overlay';
            loadingOverlay.innerHTML = `
                <div class="popup-loading-spinner"></div>
                <div class="popup-loading-text"><?php _e('Loading product details...', 'orders-jet'); ?></div>
            `;
            popupContent.appendChild(loadingOverlay);
        }
        
        // Hide popup loading state
        function hidePopupLoading() {
            const popupContent = document.querySelector('.popup-content');
            const loadingOverlay = document.getElementById('popup-loading-overlay');
            
            if (popupContent) {
                popupContent.classList.remove('loading');
            }
            
            if (loadingOverlay) {
                loadingOverlay.remove();
            }
        }
        
        // Display product details - Clean implementation
        function displayProductDetails(data) {
            // Display variations first (most important)
            if (data.variations && Object.keys(data.variations).length > 0) {
                displayVariations(data.variations);
            }
            
            // Display add-ons
            if (data.addons && data.addons.length > 0) {
                displayAddons(data.addons);
            }
            
            // Display food information (if any)
            if (data.food_info && Object.keys(data.food_info).length > 0) {
                displayFoodInfo(data.food_info);
            }
        }
        
        // Display variations
        function displayVariations(variations) {
            const variationsSection = document.getElementById('variations-section');
            const variationsContent = document.getElementById('variations-content');
            
            let variationsHTML = '';
            for (const [attributeName, options] of Object.entries(variations)) {
                variationsHTML += `
                    <div class="exrow-group ex-radio">
                        <span class="exfood-label">
                            <span class="exwo-otitle">${attributeName}</span>
                        </span>
                        <div class="exwo-container">
                            ${options.map(option => `
                                <div class="addon-option-item">
                                    <div class="addon-option-content">
                                        <input type="radio" class="ex-options" name="variation-${attributeName}" 
                                               id="variation-${option.value}" value="${option.value}" 
                                               data-price="${option.price || 0}" 
                                               data-variation-id="${option.variation_id || 0}">
                                        <label class="addon-option-label" for="variation-${option.value}">
                                            <span class="exwo-op-name">${option.label}</span>
                                            ${option.price_display ? `<span class="variation-price">${option.price_display}</span>` : ''}
                                        </label>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            variationsContent.innerHTML = variationsHTML;
            variationsSection.style.display = 'block';
        }
        
        // Display add-ons
        function displayAddons(addons) {
            const addonsSection = document.getElementById('addons-section');
            const addonsContent = document.getElementById('addons-content');
            
            let addonsHTML = '';
            addons.forEach((addon, index) => {
                const inputType = addon.type || 'checkbox';
                const inputName = `ex_options_${index}[]`;
                const requiredClass = addon.required ? 'ex-required' : '';
                const minClass = addon.min_selections > 0 ? 'ex-required-min' : '';
                const maxClass = addon.max_selections > 0 ? 'ex-required-max' : '';
                
                addonsHTML += `
                    <div class="exrow-group ex-${inputType} ${requiredClass} ${minClass} ${maxClass}" 
                         id="addon-group-${index}" 
                         data-minsl="${addon.min_selections}" 
                         data-maxsl="${addon.max_selections}">
                        <span class="exfood-label">
                            <span class="exwo-otitle">${addon.name}</span>
                            ${addon.price > 0 ? `<span> + ${addon.price} EGP</span>` : ''}
                        </span>
                        <div class="exwo-container">
                `;
                
                if (addon.options && addon.options.length > 0) {
                    addon.options.forEach(option => {
                        const optionId = `addon-${addon.id}-${option.id}`;
                        const optionValue = option.price > 0 ? `${option.name} + ${option.price} EGP` : option.name;
                        
                        addonsHTML += `
                            <div class="addon-option-item">
                                <div class="addon-option-content">
                                    <input class="ex-options" type="${inputType}" name="${inputName}" 
                                           id="${optionId}" value="${option.id}" 
                                           data-price="${option.price}" 
                                           ${option.dis === 'yes' ? 'disabled' : ''}>
                                    <label for="${optionId}" class="addon-option-label">
                                        <span class="exwo-op-name">${optionValue}</span>
                                    </label>
                                </div>
                            </div>
                        `;
                    });
                } else if (inputType === 'text' || inputType === 'textarea') {
                    addonsHTML += `
                        <input class="ex-options" type="${inputType}" name="ex_options_${index}" 
                               data-price="${addon.price}">
                    `;
                }
                
                // Add validation messages
                if (addon.required) {
                    addonsHTML += `<p class="ex-required-message" id="required-msg-${index}">This option is required</p>`;
                }
                if (addon.min_selections > 0) {
                    addonsHTML += `<p class="ex-required-min-message" id="min-msg-${index}">Please choose at least ${addon.min_selections} options.</p>`;
                }
                if (addon.max_selections > 0) {
                    addonsHTML += `<p class="ex-required-max-message" id="max-msg-${index}">You only can select max ${addon.max_selections} options.</p>`;
                }
                
                addonsHTML += `
                        </div>
                    </div>
                `;
            });
            
            addonsContent.innerHTML = addonsHTML;
            addonsSection.style.display = 'block';
            bindAddonEventListeners();
        }
        
        // Display food information
        function displayFoodInfo(foodInfo) {
            const foodInfoSection = document.getElementById('food-info-section');
            const foodInfoContent = document.getElementById('food-info-content');
            
            let foodInfoHTML = '';
            for (const [key, value] of Object.entries(foodInfo)) {
                if (value) {
                    foodInfoHTML += `
                        <div class="food-info-item">
                            <span class="food-info-label">${key}:</span>
                            <span class="food-info-value">${value}</span>
                        </div>
                    `;
                }
            }
            
            if (foodInfoHTML) {
                foodInfoContent.innerHTML = foodInfoHTML;
                foodInfoSection.style.display = 'block';
            }
        }
        
        function closePopup() {
            elements.popup.classList.remove('show');
            currentProduct = null;
            
            // Clear sections
            document.getElementById('food-info-section').style.display = 'none';
            document.getElementById('addons-section').style.display = 'none';
            document.getElementById('variations-section').style.display = 'none';
            document.getElementById('food-info-content').innerHTML = '';
            document.getElementById('addons-content').innerHTML = '';
            document.getElementById('variations-content').innerHTML = '';
        }
        
        // Bind event listeners for add-on validation
        function bindAddonEventListeners() {
            // Real-time validation for maximum selections
            document.querySelectorAll('.ex-checkbox.ex-required-max .ex-options').forEach(input => {
                input.addEventListener('change', function() {
                    const group = this.closest('.ex-checkbox.ex-required-max');
                    if (group.classList.contains('exwf-offrq')) return;
                    
                    const groupId = group.id;
                    const index = groupId.replace('addon-group-', '');
                    const maxSelections = parseInt(group.dataset.maxsl) || 0;
                    const selectedCount = group.querySelectorAll('.ex-options:checked').length;
                    
                    if (selectedCount > maxSelections) {
                        const msg = document.getElementById(`max-msg-${index}`);
                        if (msg) msg.style.display = 'block';
                        this.checked = false; // Uncheck the last selected item
                    } else {
                        const msg = document.getElementById(`max-msg-${index}`);
                        if (msg) msg.style.display = 'none';
                    }
                });
            });
            
            // Real-time validation for minimum quantity
            document.querySelectorAll('.ex-checkbox.ex-required-min-opqty .ex-options').forEach(input => {
                input.addEventListener('change', function() {
                    const group = this.closest('.ex-checkbox.ex-required-min-opqty');
                    if (group.classList.contains('exwf-offrq')) return;
                    
                    const groupId = group.id;
                    const index = groupId.replace('addon-group-', '');
                    const minQty = parseInt(group.dataset.minopqty) || 0;
                    let totalQty = 0;
                    
                    group.querySelectorAll('.ex-options:checked').forEach(checkbox => {
                        const qtyInput = checkbox.closest('span')?.querySelector('.ex-qty-op');
                        if (qtyInput) {
                            totalQty += parseInt(qtyInput.value) || 1;
                        } else {
                            totalQty += 1;
                        }
                    });
                    
                    if (totalQty > 0 && totalQty < minQty) {
                        const msg = document.getElementById(`minqty-msg-${index}`);
                        if (msg) msg.style.display = 'block';
                    } else {
                        const msg = document.getElementById(`minqty-msg-${index}`);
                        if (msg) msg.style.display = 'none';
                    }
                });
            });
            
            // Real-time validation for maximum quantity
            document.querySelectorAll('.ex-checkbox.ex-required-max-opqty .ex-options').forEach(input => {
                input.addEventListener('change', function() {
                    const group = this.closest('.ex-checkbox.ex-required-max-opqty');
                    if (group.classList.contains('exwf-offrq')) return;
                    
                    const groupId = group.id;
                    const index = groupId.replace('addon-group-', '');
                    const maxQty = parseInt(group.dataset.maxopqty) || 0;
                    let totalQty = 0;
                    
                    group.querySelectorAll('.ex-options:checked').forEach(checkbox => {
                        const qtyInput = checkbox.closest('span')?.querySelector('.ex-qty-op');
                        if (qtyInput) {
                            totalQty += parseInt(qtyInput.value) || 1;
                        } else {
                            totalQty += 1;
                        }
                    });
                    
                    if (totalQty > maxQty) {
                        const msg = document.getElementById(`maxqty-msg-${index}`);
                        if (msg) msg.style.display = 'block';
                        this.checked = false; // Uncheck the last selected item
                    } else {
                        const msg = document.getElementById(`maxqty-msg-${index}`);
                        if (msg) msg.style.display = 'none';
                    }
                });
            });
            
            // Quantity input validation
            document.querySelectorAll('.ex-qty-op').forEach(input => {
                input.addEventListener('input', function() {
                    const min = parseInt(this.getAttribute('min')) || 1;
                    const max = parseInt(this.getAttribute('max')) || 999;
                    let value = parseInt(this.value) || 0;
                    
                    if (value < min) {
                        this.value = min;
                    } else if (value > max) {
                        this.value = max;
                    }
                    
                    // Trigger change event for quantity validation
                    const checkbox = this.closest('span')?.querySelector('.ex-options');
                    if (checkbox) {
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });
        }
        
        // Exfood validation function
        function validateRequiredAddons() {
            let isValid = true;
            
            console.log('Starting validation...');
            
            // Hide all previous validation messages
            document.querySelectorAll('.ex-required-message, .ex-required-min-message, .ex-required-max-message, .ex-required-minqty-message, .ex-required-maxqty-message').forEach(msg => {
                msg.style.display = 'none';
            });
            
            // Get all add-on groups and check each one individually
            const allGroups = document.querySelectorAll('.exrow-group');
            console.log('Found add-on groups:', allGroups.length);
            
            allGroups.forEach((group, i) => {
                // Check if this group is required (has ex-required class)
                const isRequired = group.classList.contains('ex-required');
                
                if (!isRequired) {
                    return; // Skip non-required groups
                }
                
                if (group.classList.contains('exwf-offrq')) {
                    console.log('Skipping hidden group:', group.id);
                    return; // Skip hidden groups
                }
                
                const groupId = group.id;
                const index = groupId.replace('addon-group-', '');
                console.log('Group ID:', groupId, 'Index:', index);
                
                const isRadio = group.classList.contains('ex-radio');
                const isCheckbox = group.classList.contains('ex-checkbox');
                const isSelect = group.classList.contains('ex-select');
                
                console.log('Input type - Radio:', isRadio, 'Checkbox:', isCheckbox, 'Select:', isSelect);
                
                if (isRadio || isCheckbox || isSelect) {
                    // Check if any option is selected
                    const hasSelection = group.querySelector('.ex-options:checked');
                    if (!hasSelection) {
                        const msg = document.getElementById(`required-msg-${index}`);
                        if (msg) {
                            msg.style.display = 'block';
                            isValid = false;
                        }
                    }
                } else {
                    // Check text/textarea/quantity inputs
                    const input = group.querySelector('.ex-options');
                    if (input && input.value.trim() === '') {
                        const msg = document.getElementById(`required-msg-${index}`);
                        if (msg) {
                            msg.style.display = 'block';
                            isValid = false;
                        }
                    }
                }
            });
            
            // Check minimum selection requirements
            document.querySelectorAll('.exrow-group.ex-checkbox.ex-required-min').forEach(group => {
                if (group.classList.contains('exwf-offrq')) {
                    return; // Skip hidden groups
                }
                
                const groupId = group.id;
                const index = groupId.replace('addon-group-', '');
                const minSelections = parseInt(group.dataset.minsl) || 0;
                const selectedCount = group.querySelectorAll('.ex-options:checked').length;
                
                if (selectedCount < minSelections) {
                    const msg = document.getElementById(`min-msg-${index}`);
                    if (msg) msg.style.display = 'block';
                    isValid = false;
                }
            });
            
            // Check maximum selection requirements
            document.querySelectorAll('.exrow-group.ex-checkbox.ex-required-max').forEach(group => {
                if (group.classList.contains('exwf-offrq')) {
                    return; // Skip hidden groups
                }
                
                const groupId = group.id;
                const index = groupId.replace('addon-group-', '');
                const maxSelections = parseInt(group.dataset.maxsl) || 0;
                const selectedCount = group.querySelectorAll('.ex-options:checked').length;
                
                if (selectedCount > maxSelections) {
                    const msg = document.getElementById(`max-msg-${index}`);
                    if (msg) msg.style.display = 'block';
                    isValid = false;
                }
            });
            
            // Check minimum quantity requirements
            document.querySelectorAll('.exrow-group.ex-checkbox.ex-required-min-opqty').forEach(group => {
                if (group.classList.contains('exwf-offrq')) {
                    return; // Skip hidden groups
                }
                
                const groupId = group.id;
                const index = groupId.replace('addon-group-', '');
                const minQty = parseInt(group.dataset.minopqty) || 0;
                let totalQty = 0;
                
                group.querySelectorAll('.ex-options:checked').forEach(checkbox => {
                    const qtyInput = checkbox.closest('span')?.querySelector('.ex-qty-op');
                    if (qtyInput) {
                        totalQty += parseInt(qtyInput.value) || 1;
                    } else {
                        totalQty += 1;
                    }
                });
                
                if (totalQty > 0 && totalQty < minQty) {
                    const msg = document.getElementById(`minqty-msg-${index}`);
                    if (msg) msg.style.display = 'block';
                    isValid = false;
                }
            });
            
            // Check maximum quantity requirements
            document.querySelectorAll('.exrow-group.ex-checkbox.ex-required-max-opqty').forEach(group => {
                if (group.classList.contains('exwf-offrq')) {
                    return; // Skip hidden groups
                }
                
                const groupId = group.id;
                const index = groupId.replace('addon-group-', '');
                const maxQty = parseInt(group.dataset.maxopqty) || 0;
                let totalQty = 0;
                
                group.querySelectorAll('.ex-options:checked').forEach(checkbox => {
                    const qtyInput = checkbox.closest('span')?.querySelector('.ex-qty-op');
                    if (qtyInput) {
                        totalQty += parseInt(qtyInput.value) || 1;
                    } else {
                        totalQty += 1;
                    }
                });
                
                if (totalQty > maxQty) {
                    const msg = document.getElementById(`maxqty-msg-${index}`);
                    if (msg) msg.style.display = 'block';
                    isValid = false;
                }
            });
            
            console.log('Validation result:', isValid);
            
            if (!isValid) {
                // Show a general error message
                alert('Please check all required fields and try again.');
                
                // Scroll to the first error
                const firstError = document.querySelector('.ex-required-message[style*="block"], .ex-required-min-message[style*="block"], .ex-required-max-message[style*="block"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            console.log('=== VALIDATION COMPLETE ===');
            console.log('Validation result:', isValid);
            if (!isValid) {
                console.log('Validation failed - showing error messages');
            } else {
                console.log('Validation passed - all required fields completed');
            }
            return isValid;
        }
        
        // Cart management - WooCommerce native with proper validation
        function addToCart() {
            if (!currentProduct) return;
            
            // Validate required variations
            if (!validateRequiredVariations()) return;
            
            // Validate required add-ons
            if (!validateRequiredAddons()) return;
            
            const quantity = parseInt(elements.quantityInput.value);
            const notes = elements.popupNotes.value.trim();
            
            // Get selected variation ID and calculate price
            const variationId = getSelectedVariationId();
            const variationPrice = getSelectedVariationPrice();
            
            // Collect selected add-ons (simplified)
            const selectedAddons = collectSelectedAddons();
            
            // Calculate total price for display purposes (per item, not total quantity)
            const basePrice = variationPrice || parseProductPrice(currentProduct.price);
            const addonTotalPerItem = selectedAddons.reduce((sum, addon) => sum + (addon.price * (addon.quantity || 1)), 0);
            const pricePerItem = basePrice + addonTotalPerItem;
            
            console.log('Price calculation debug:');
            console.log('- Variation price:', variationPrice);
            console.log('- Base price (final):', basePrice);
            console.log('- Selected add-ons:', selectedAddons);
            console.log('- Add-on total per item:', addonTotalPerItem);
            console.log('- Final price per item:', pricePerItem);
            console.log('- Quantity:', quantity);
            
            // Create simple cart item (WooFood style) with calculated price for display
            const cartItem = {
                product_id: currentProduct.id,
                variation_id: variationId,
                name: currentProduct.name,
                quantity: quantity,
                notes: notes,
                add_ons: selectedAddons,
                display_name: createDisplayName(variationId, selectedAddons),
                display_price: pricePerItem,        // Price per single item (base + add-ons)
                base_price: basePrice,              // Base price per item
                addon_total: addonTotalPerItem      // Add-on total per item
            };
            
            // Add to cart
            cart.push(cartItem);
            saveCart();
            updateCartDisplay();
            closePopup();
            
            showAppNotification(`Added to cart: ${cartItem.display_name} (Qty: ${quantity})`, 'success');
        }
        
        // Validate required variations
        function validateRequiredVariations() {
            const variationInputs = document.querySelectorAll('input[name^="variation-"]');
            if (variationInputs.length === 0) {
                return true; // No variations required
            }
            
            const selectedVariation = document.querySelector('input[name^="variation-"]:checked');
            if (!selectedVariation) {
                showAppNotification('Please select a variation', 'error');
                return false;
            }
            
            return true;
        }
        
        // Get selected variation ID (simplified)
        function getSelectedVariationId() {
            const checkedRadio = document.querySelector('input[name^="variation-"]:checked');
            return checkedRadio ? parseInt(checkedRadio.dataset.variationId || 0) : 0;
        }
        
        // Get selected variation price
        function getSelectedVariationPrice() {
            const checkedRadio = document.querySelector('input[name^="variation-"]:checked');
            if (!checkedRadio) return 0;
            
            const price = parseFloat(checkedRadio.dataset.price || 0);
            console.log('Selected variation price:', price, 'from element:', checkedRadio);
            return price;
        }
        
        // Parse product price from text
        function parseProductPrice(priceText) {
            if (!priceText) return 0;
            
            if (priceText.includes('Current price is:')) {
                const match = priceText.match(/Current price is:\s*([\d,]+)/);
                return match ? parseFloat(match[1].replace(',', '.')) : 0;
            }
            
            const match = priceText.match(/[\d,]+\.?\d*/);
            return match ? parseFloat(match[0].replace(',', '.')) : 0;
        }
        
        // Collect selected add-ons (simplified)
        function collectSelectedAddons() {
            const addons = [];
            
            // Collect checked add-ons (not variations)
            document.querySelectorAll('#addons-section .ex-options:checked').forEach(checkbox => {
                const label = getAddonLabel(checkbox);
                addons.push({
                    id: checkbox.value,
                    name: label,
                    price: parseFloat(checkbox.dataset.price || 0)
                });
            });
            
            // Collect quantity-based add-ons
            document.querySelectorAll('#addons-section .ex-options[type="number"]').forEach(input => {
                const quantity = parseInt(input.value || 0);
                if (quantity > 0) {
                    const label = getAddonLabel(input);
                    addons.push({
                        id: input.name,
                        name: label,
                        price: parseFloat(input.dataset.price || 0),
                        quantity: quantity
                    });
                }
            });
            
            // Collect text inputs
            document.querySelectorAll('#addons-section .ex-options[type="text"], #addons-section .ex-options[type="textarea"]').forEach(input => {
                if (input.value.trim() !== '') {
                    addons.push({
                        id: input.name,
                        name: input.placeholder || 'Custom option',
                        value: input.value.trim(),
                        price: parseFloat(input.dataset.price || 0)
                    });
                }
            });
            
            return addons;
        }
        
        // Helper function to get add-on label
        function getAddonLabel(input) {
            // Try multiple ways to find the label
            let label = input.closest('.addon-option-label');
            if (!label) {
                label = document.querySelector(`label[for="${input.id}"]`);
            }
            if (!label) {
                label = input.nextElementSibling;
                if (label && !label.classList.contains('addon-option-label')) {
                    label = null;
                }
            }
            
            if (label) {
                const nameElement = label.querySelector('.exwo-op-name, .addon-name');
                return nameElement ? nameElement.textContent.trim() : label.textContent.trim();
            }
            
            return input.dataset.name || 'Option';
        }
        
        // Create display name (simplified)
        function createDisplayName(variationId, addons) {
            let displayName = currentProduct.name;
            
            // Add variation info if selected
            if (variationId > 0) {
                const variationRadio = document.querySelector(`input[data-variation-id="${variationId}"]`);
                if (variationRadio) {
                    const variationLabel = getAddonLabel(variationRadio);
                    displayName += ` (${variationLabel})`;
                }
            }
            
            return displayName;
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            saveCart();
            updateCartDisplay();
        }
        
        function clearCart() {
            cart = [];
            saveCart();
            updateCartDisplay();
            showAppNotification('Cart cleared successfully', 'success');
        }
        
        function updateCartDisplay() {
            // Update cart items
            if (cart.length === 0) {
                elements.cartItems.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('Your cart is empty', 'orders-jet'); ?></p>';
            } else {
                let html = '';
                cart.forEach((item, index) => {
                    let addonDetails = '';
                    
                    // Show add-ons if any (with proper quantity calculation)
                    if (item.add_ons && item.add_ons.length > 0) {
                        addonDetails += '<div class="cart-addon-details">';
                        item.add_ons.forEach(addon => {
                            const addonQty = addon.quantity || 1;
                            const addonPricePerUnit = addon.price || 0;
                            const addonTotalForAllItems = addonPricePerUnit * addonQty * item.quantity;
                            
                            if (addonQty > 1) {
                                addonDetails += `<div class="cart-addon-item">+ ${addon.name} × ${addonQty} × ${item.quantity} (+${addonTotalForAllItems.toFixed(2)} EGP)</div>`;
                            } else if (addon.value) {
                                addonDetails += `<div class="cart-addon-item">+ ${addon.name}: ${addon.value}</div>`;
                            } else {
                                addonDetails += `<div class="cart-addon-item">+ ${addon.name} × ${item.quantity} (+${addonTotalForAllItems.toFixed(2)} EGP)</div>`;
                            }
                        });
                        addonDetails += '</div>';
                    }
                    
                    // Support old cart format for backward compatibility
                    if (!addonDetails && item.addons && item.addons.length > 0) {
                        addonDetails += '<div class="cart-addon-details">';
                        item.addons.forEach(addon => {
                            const addonTotalForAllItems = (addon.price || 0) * item.quantity;
                            addonDetails += `<div class="cart-addon-item">+ ${addon.name} × ${item.quantity} (+${addonTotalForAllItems.toFixed(2)} EGP)</div>`;
                        });
                        addonDetails += '</div>';
                    }
                    
                    // Calculate item total for display (base price + add-ons) × quantity
                    const itemPricePerUnit = item.display_price || item.base_price || 0;
                    const itemTotal = itemPricePerUnit * item.quantity;
                    
                    html += `
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.display_name || item.name}</div>
                                <div class="cart-item-price">${itemPricePerUnit.toFixed(2)} EGP × ${item.quantity}</div>
                                ${addonDetails}
                                ${item.notes ? `<div class="cart-item-notes">${item.notes}</div>` : ''}
                                <div class="cart-item-total"><strong>Total: ${itemTotal.toFixed(2)} EGP</strong></div>
                            </div>
                            <button class="cart-item-remove" onclick="removeFromCart(${index})"><?php _e('Remove', 'orders-jet'); ?></button>
                        </div>
                    `;
                });
                elements.cartItems.innerHTML = html;
            }
            
            // Update totals - calculate proper cart total
            const total = cart.reduce((sum, item) => {
                const itemPrice = item.display_price || item.base_price || 0;
                return sum + (itemPrice * item.quantity);
            }, 0);
            
            const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            const currencySymbol = '<?php echo get_woocommerce_currency_symbol(); ?>';
            
            elements.cartTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
            elements.floatingCartTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
            
            // Show/hide floating cart
            if (cart.length > 0) {
                elements.floatingCart.classList.add('show');
            } else {
                elements.floatingCart.classList.remove('show');
            }
            
            // Update cart badge
            const cartBadge = document.querySelector('.oj-cart-badge');
            if (cartBadge) {
                cartBadge.textContent = itemCount;
                cartBadge.style.display = itemCount > 0 ? 'block' : 'none';
            }
        }
        
        // Local storage
        function saveCart() {
            localStorage.setItem('oj_cart_<?php echo esc_js($table_number); ?>', JSON.stringify(cart));
        }
        
        function loadCart() {
            const saved = localStorage.getItem('oj_cart_<?php echo esc_js($table_number); ?>');
            if (saved) {
                cart = JSON.parse(saved);
                
                // Migrate old cart format to new simplified format
                cart = cart.map(item => {
                    // If already in new format, return as is
                    if (item.product_id && item.add_ons !== undefined && item.display_price !== undefined) {
                        return item;
                    }
                    
                    // Convert old format to new format
                    const newItem = {
                        product_id: item.id || item.product_id,
                        variation_id: item.variation_id || 0,
                        name: item.name,
                        quantity: item.quantity,
                        notes: item.notes || '',
                        add_ons: [],
                        display_name: item.display_name || item.name,
                        display_price: 0,
                        base_price: 0,
                        addon_total: 0
                    };
                    
                    // Convert old addons format
                    if (item.addons && Array.isArray(item.addons)) {
                        newItem.add_ons = item.addons.map(addon => ({
                            id: addon.id || 'addon_' + Math.random(),
                            name: addon.name || 'Add-on',
                            price: addon.price || 0,
                            quantity: addon.quantity || 1
                        }));
                        newItem.addon_total = newItem.add_ons.reduce((sum, addon) => sum + (addon.price * (addon.quantity || 1)), 0);
                    }
                    
                    // Handle old variations format (extract variation_id)
                    if (item.variations && typeof item.variations === 'object' && !newItem.variation_id) {
                        const firstVariation = Object.values(item.variations)[0];
                        if (firstVariation && firstVariation.variation_id) {
                            newItem.variation_id = firstVariation.variation_id;
                            newItem.base_price = firstVariation.price || 0;
                        }
                    }
                    
                    // Calculate display price from old numericPrice or parse from price text
                    if (item.numericPrice) {
                        // Old numericPrice was total for all quantity, convert to per-item price
                        const totalOldPrice = item.numericPrice;
                        const oldAddonTotal = newItem.addon_total || 0;
                        newItem.display_price = totalOldPrice / item.quantity;  // Per item price
                        newItem.base_price = newItem.display_price - oldAddonTotal;
                    } else if (item.price) {
                        // Parse price from text (this was usually per-item)
                        let priceText = item.price;
                        if (priceText.includes('Current price is:')) {
                            const match = priceText.match(/Current price is:\s*([\d,]+)/);
                            newItem.display_price = match ? parseFloat(match[1].replace(',', '.')) : 0;
                        } else {
                            const match = priceText.match(/[\d,]+\.?\d*/);
                            newItem.display_price = match ? parseFloat(match[0].replace(',', '.')) : 0;
                        }
                        newItem.base_price = newItem.display_price - (newItem.addon_total || 0);
                    }
                    
                    return newItem;
                });
                
                // Save migrated cart
                saveCart();
            }
        }
        
        // Place order
        function placeOrder() {
            if (cart.length === 0) {
                showAppNotification('Your cart is empty', 'error');
                return;
            }
            
            // Show confirmation dialog
            showAppConfirmDialog(
                `Place order for table <?php echo esc_js($table_number); ?>?`,
                () => {
                    // User confirmed - place the order
                    console.log('Order confirmed, placing order...');
                    console.log('Cart items:', cart);
                    
                    // Show loading notification
                    showAppNotification('Placing order...', 'info');
                    
                    // Prepare order data (WooFood-style simplified)
                    const orderData = {
                        table_number: '<?php echo esc_js($table_number); ?>',
                        items: cart.map(item => ({
                            product_id: item.product_id || item.id,  // Support both new and old format
                            variation_id: item.variation_id || 0,
                            name: item.display_name || item.name,
                            quantity: item.quantity,
                            notes: item.notes || '',
                            add_ons: item.add_ons || item.addons || []  // Support both formats
                        }))
                    };
                    
                    console.log('Order data being sent:', orderData);
                    console.log('Cart items count:', cart.length);
                    console.log('Total items:', orderData.items.reduce((sum, item) => sum + item.quantity, 0));
                    
                    // Send order to server
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'oj_submit_table_order',
                            order_data: JSON.stringify(orderData),
                            nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                        })
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        
                        // Check if response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            // Response is not JSON, get text to see what we got
                            return response.text().then(text => {
                                console.error('Non-JSON response received:', text);
                                throw new Error('Server returned HTML instead of JSON. Check console for details.');
                            });
                        }
                        
                        return response.json();
                    })
                    .then(data => {
                        console.log('Order response data:', data);
                        if (data.success) {
                            showAppNotification('Order placed successfully!', 'success');
                            clearCart();
                            switchTab('menu');
                        } else {
                            showAppNotification('Failed to place order: ' + (data.data?.message || 'Unknown error'), 'error');
                            console.error('Order placement failed:', data);
                        }
                    })
                    .catch(error => {
                        console.error('Order placement error:', error);
                        showAppNotification('Error placing order: ' + error.message, 'error');
                    });
                }
            );
        }
        
        // App Notification System
        function showAppNotification(message, type = 'info') {
            // Remove any existing notification
            const existingNotification = document.querySelector('.app-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `app-notification app-notification-${type}`;
            notification.innerHTML = `
                <div class="app-notification-content">
                    <span class="app-notification-message">${message}</span>
                    <button class="app-notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto hide after 4 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
        
        function showAppConfirmDialog(message, onConfirm, onCancel = null) {
            // Remove any existing dialog
            const existingDialog = document.querySelector('.app-confirm-dialog');
            if (existingDialog) {
                existingDialog.remove();
            }
            
            // Create dialog element
            const dialog = document.createElement('div');
            dialog.className = 'app-confirm-dialog';
            
            // Create confirm button with proper event handling
            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'app-dialog-btn app-dialog-confirm';
            confirmBtn.textContent = 'OK';
            confirmBtn.onclick = () => {
                dialog.remove();
                if (onConfirm) onConfirm();
            };
            
            // Create cancel button
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'app-dialog-btn app-dialog-cancel';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = () => {
                dialog.remove();
                if (onCancel) onCancel();
            };
            
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'app-dialog-overlay';
            overlay.onclick = () => dialog.remove();
            
            // Create message element
            const messageEl = document.createElement('div');
            messageEl.className = 'app-dialog-message';
            messageEl.textContent = message;
            
            // Create actions container
            const actions = document.createElement('div');
            actions.className = 'app-dialog-actions';
            actions.appendChild(cancelBtn);
            actions.appendChild(confirmBtn);
            
            // Create content container
            const content = document.createElement('div');
            content.className = 'app-dialog-content';
            content.appendChild(messageEl);
            content.appendChild(actions);
            
            // Assemble dialog
            dialog.appendChild(overlay);
            dialog.appendChild(content);
            
            // Add to body
            document.body.appendChild(dialog);
            
            // Show dialog
            setTimeout(() => dialog.classList.add('show'), 100);
        }
        
        // Global function for remove button
        window.removeFromCart = removeFromCart;
        
        // Load order history for the table
        function loadOrderHistory() {
            console.log('Loading order history for table <?php echo esc_js($table_number); ?>');
            
            // Show loading state
            elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('Loading orders...', 'orders-jet'); ?></p>';
            
            // Fetch orders for this table
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'oj_get_table_orders',
                    table_number: '<?php echo esc_js($table_number); ?>',
                    nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                })
            })
            .then(response => {
                console.log('Order history response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Order history response data:', data);
                console.log('Response success:', data.success);
                console.log('Response data:', data.data);
                
                if (data.success) {
                    console.log('Orders found:', data.data.orders);
                    console.log('Total amount:', data.data.total);
                    console.log('Debug info:', data.data.debug);
                    
                    displayOrderHistory(data.data.orders);
                    updateTableTotal(data.data.total);
                    
                    // Show simple message if no orders found
                    if (data.data.orders.length === 0) {
                        elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('No orders found for this table', 'orders-jet'); ?></p>';
                    }
                } else {
                    console.log('No orders found or error:', data);
                    console.log('Error message:', data.data?.message);
                    elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('No orders found', 'orders-jet'); ?></p>';
                }
            })
            .catch(error => {
                console.error('Error loading order history:', error);
                elements.orderHistory.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 40px;"><?php _e('Error loading orders', 'orders-jet'); ?></p>';
            });
        }
        
        // Display order history
        function displayOrderHistory(orders) {
            if (orders.length === 0) {
                elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('No orders found', 'orders-jet'); ?></p>';
                
                // Disable and hide Pay Now button when no orders
                const payNowBtn = document.getElementById('pay-now');
                const invoiceTotal = document.getElementById('invoice-total');
                if (payNowBtn) {
                    payNowBtn.style.display = 'none';
                    payNowBtn.disabled = true;
                }
                if (invoiceTotal) invoiceTotal.style.display = 'none';
                return;
            }
            
            let html = '';
            let hasPendingOrders = false;
            
            orders.forEach(order => {
                // Check if order is still pending (not completed)
                if (order.status === 'processing' || order.status === 'pending' || order.status === 'on-hold') {
                    hasPendingOrders = true;
                }
                
                html += `
                    <div class="order-history-item">
                        <div class="order-header">
                            <span class="order-number">#${order.order_number}</span>
                            <span class="order-status ${order.status}">${order.status}</span>
                        </div>
                        <div class="order-items">
                            ${order.items.map(item => {
                                // Calculate base price and add-on details like in cart
                                let basePrice = 0;
                                let addonDetails = '';
                                let extraTotal = 0;
                                
                                // Use the base_price provided by the backend
                                console.log('Order history item data:', {
                                    name: item.name,
                                    base_price: item.base_price,
                                    unit_price: item.unit_price,
                                    variations: item.variations,
                                    addons: item.addons
                                });
                                
                                if (item.base_price !== undefined) {
                                    basePrice = parseFloat(item.base_price);
                                    console.log('Using base_price:', basePrice);
                                } else {
                                    // Fallback: use unit_price for backward compatibility
                                    basePrice = parseFloat(item.unit_price.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                                    console.log('Using unit_price fallback:', basePrice);
                                }
                                
                                // Format add-ons with calculations
                                if (item.addons && item.addons.length > 0) {
                                    item.addons.forEach(addon => {
                                        // Parse add-on price from string like "+100.00 EGP" or "Extra (+100.00 EGP)"
                                        // First try to find price in parentheses
                                        let addonPrice = 0;
                                        let addonName = addon;
                                        
                                        const parenthesesMatch = addon.match(/\(([^)]+)\)/);
                                        if (parenthesesMatch) {
                                            // Extract name (before parentheses)
                                            addonName = addon.replace(/\s*\([^)]+\)\s*.*$/, '').replace(/^\+?\s*/, '');
                                            
                                            // Extract price from parentheses
                                            const priceInParentheses = parenthesesMatch[1];
                                            const priceMatch = priceInParentheses.match(/\+?([\d,]+\.?\d*)/);
                                            if (priceMatch) {
                                                addonPrice = parseFloat(priceMatch[1].replace(',', '.'));
                                            }
                                        } else {
                                            // Fallback: try to parse price from the end of the string
                                            const priceMatch = addon.match(/\+?([\d,]+\.?\d*)\s*EGP/i);
                                            if (priceMatch) {
                                                addonPrice = parseFloat(priceMatch[1].replace(',', '.'));
                                                addonName = addon.replace(/\s*\+?[\d,]+\.?\d*\s*EGP.*$/i, '').replace(/^\+?\s*/, '');
                                            }
                                        }
                                        
                                        const addonTotal = (addonPrice * item.quantity).toFixed(2);
                                        extraTotal += addonPrice * item.quantity;
                                        
                                        addonDetails += `
                                            <div class="order-item-addon-detail">
                                                + ${addonName}: +${addonPrice.toFixed(2)} EGP × ${item.quantity} = +${addonTotal} EGP
                                            </div>
                                        `;
                                    });
                                }
                                
                                // Calculate final total
                                const itemTotal = (basePrice * item.quantity) + extraTotal;
                                
                                return `
                                    <div class="order-item">
                                        <div class="order-item-name">${item.name}</div>
                                        <div class="order-item-price">${basePrice.toFixed(2)} EGP × ${item.quantity}</div>
                                        ${addonDetails ? `<div class="order-item-addons">${addonDetails}</div>` : ''}
                                        ${item.notes ? `<div class="order-item-notes">Note: ${item.notes}</div>` : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                        <div class="order-total">Total: ${order.total.replace(',', '.')}</div>
                    </div>
                `;
            });
            
            elements.orderHistory.innerHTML = html;
            
            // Show/hide Pay Now button based on pending orders
            const payNowBtn = document.getElementById('pay-now');
            const invoiceTotal = document.getElementById('invoice-total');
            
            if (hasPendingOrders) {
                // Enable and show Pay Now button for pending orders
                if (payNowBtn) {
                    payNowBtn.style.display = 'block';
                    payNowBtn.disabled = false;
                }
                if (invoiceTotal) invoiceTotal.style.display = 'block';
                
                // Remove table closed message if it exists
                const existingMsg = document.querySelector('.table-closed-message');
                if (existingMsg) {
                    existingMsg.remove();
                }
            } else {
                // Disable and hide Pay Now button and show table closed message
                if (payNowBtn) {
                    payNowBtn.style.display = 'none';
                    payNowBtn.disabled = true;
                }
                if (invoiceTotal) {
                    invoiceTotal.style.display = 'none';
                }
                
                // Add table closed message
                const tableClosedMsg = document.createElement('div');
                tableClosedMsg.className = 'table-closed-message';
                tableClosedMsg.innerHTML = `
                    <div style="text-align: center; padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; margin: 20px;">
                        <h3 style="color: #155724; margin: 0 0 10px 0;">✅ <?php _e('Table Closed', 'orders-jet'); ?></h3>
                        <p style="color: #155724; margin: 0;"><?php _e('All orders have been completed and payment processed.', 'orders-jet'); ?></p>
                    </div>
                `;
                
                // Remove existing table closed message if any
                const existingMsg = document.querySelector('.table-closed-message');
                if (existingMsg) {
                    existingMsg.remove();
                }
                
                // Insert table closed message after order history
                elements.orderHistory.parentNode.insertBefore(tableClosedMsg, elements.orderHistory.nextSibling);
            }
        }
        
        // Update table total
        function updateTableTotal(total) {
            elements.tableTotal.textContent = `${total.toFixed(2)} <?php echo get_woocommerce_currency_symbol(); ?>`;
            
            // Also update invoice total
            const invoiceTotal = document.getElementById('invoice-total');
            if (invoiceTotal) {
                invoiceTotal.textContent = `${total.toFixed(2)} <?php echo get_woocommerce_currency_symbol(); ?>`;
            }
        }
        
        // Pay Now functionality
        function payNow() {
            // Show payment method selection dialog
            showPaymentDialog();
        }
        
        // Show payment method selection
        function showPaymentDialog() {
            const dialog = document.createElement('div');
            dialog.className = 'app-confirm-dialog';
            
            const content = document.createElement('div');
            content.className = 'app-dialog-content';
            content.style.maxWidth = '400px';
            
            content.innerHTML = `
                <div class="app-dialog-message">
                    <h3><?php _e('Select Payment Method', 'orders-jet'); ?></h3>
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin: 10px 0; cursor: pointer;">
                            <input type="radio" name="payment_method" value="cash" checked style="margin-right: 10px;">
                            <?php _e('Cash', 'orders-jet'); ?>
                        </label>
                        <label style="display: block; margin: 10px 0; cursor: pointer;">
                            <input type="radio" name="payment_method" value="card" style="margin-right: 10px;">
                            <?php _e('Card', 'orders-jet'); ?>
                        </label>
                    </div>
                </div>
                <div class="app-dialog-actions">
                    <button class="app-dialog-btn app-dialog-cancel"><?php _e('Cancel', 'orders-jet'); ?></button>
                    <button class="app-dialog-btn app-dialog-confirm"><?php _e('Request Invoice', 'orders-jet'); ?></button>
                </div>
            `;
            
            const overlay = document.createElement('div');
            overlay.className = 'app-dialog-overlay';
            overlay.onclick = () => dialog.remove();
            
            dialog.appendChild(overlay);
            dialog.appendChild(content);
            document.body.appendChild(dialog);
            
            // Show dialog
            setTimeout(() => dialog.classList.add('show'), 100);
            
            // Handle buttons
            const cancelBtn = content.querySelector('.app-dialog-cancel');
            const confirmBtn = content.querySelector('.app-dialog-confirm');
            
            cancelBtn.onclick = () => dialog.remove();
            confirmBtn.onclick = () => {
                const paymentMethod = content.querySelector('input[name="payment_method"]:checked').value;
                processPayment(paymentMethod);
                dialog.remove();
            };
        }
        
        // Process payment and close table
        function processPayment(paymentMethod) {
            console.log('Processing payment with method:', paymentMethod);
            
            // Show loading
            showAppNotification('<?php _e('Processing payment...', 'orders-jet'); ?>', 'info');
            
            // Send payment request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'oj_close_table',
                    table_number: '<?php echo esc_js($table_number); ?>',
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('oj_table_order'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show payment success message instead of invoice
                    showPaymentSuccess(data.data.total, paymentMethod);
                    
                    // Reload order history to show updated statuses
                    setTimeout(() => {
                        loadOrderHistory();
                    }, 1000);
                } else {
                    showAppNotification('<?php _e('Payment failed: ', 'orders-jet'); ?>' + (data.data?.message || '<?php _e('Unknown error', 'orders-jet'); ?>'), 'error');
                }
            })
            .catch(error => {
                console.error('Payment error:', error);
                showAppNotification('<?php _e('Payment error: ', 'orders-jet'); ?>' + error.message, 'error');
            });
        }
        
        // Show payment success message
        function showPaymentSuccess(total, paymentMethod) {
            const paySection = document.querySelector('.pay-section');
            if (!paySection) return;
            
            const successHtml = `
                <div class="payment-success">
                    <div class="success-icon">✓</div>
                    <h3><?php _e('Payment Successful!', 'orders-jet'); ?></h3>
                    <p><?php _e('Amount:', 'orders-jet'); ?> <strong>${total} EGP</strong></p>
                    <p><?php _e('Method:', 'orders-jet'); ?> <strong>${paymentMethod.toUpperCase()}</strong></p>
                    <div class="thank-you-message">
                        <h4><?php _e('Thank you for dining with us!', 'orders-jet'); ?></h4>
                        <p><?php _e('We hope you enjoyed your meal. Please come again!', 'orders-jet'); ?></p>
                    </div>
                </div>
            `;
            
            // Replace the pay section with success message
            paySection.innerHTML = successHtml;
            
            // Show success notification
            showAppNotification('<?php _e('Payment completed successfully!', 'orders-jet'); ?>', 'success');
        }
        
        
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        
    })();
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>