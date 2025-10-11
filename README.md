# Orders Jet Integration Plugin

A WordPress plugin that extends WooCommerce Food plugin with advanced table management, QR code menus, and contactless ordering system for restaurants.

## Features

### Table Management
- Create and manage restaurant tables
- Table zones and locations
- Real-time table status tracking
- QR code generation for each table

### Contactless Ordering
- QR code menu access
- Mobile-optimized interface
- No personal information required
- Real-time cart updates
- Floating cart bar

### Order Management
- WooCommerce integration
- Staff notifications
- Kitchen display system
- Order status tracking

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- WooCommerce Food Plugin
- PHP 7.4+

## Installation

1. Upload the plugin files to `/wp-content/plugins/orders-jet-integration/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce and WooCommerce Food plugins are active

## Usage

### Setting Up Tables

1. Go to **Products > Table Management** in WordPress admin
2. Click **Add New Table**
3. Fill in table details:
   - Table Number (e.g., T01, A12)
   - Capacity (number of people)
   - Status (Available, Occupied, Reserved, Maintenance)
   - Location (e.g., Terrace, Corner, Window)
4. Save the table to generate QR code

### QR Code Menu

1. Each table automatically gets a QR code
2. Customers scan QR code to access menu
3. Menu URL format: `yoursite.com/table-menu/?table=T01`
4. Use shortcode `[orders_jet_qr_menu]` on any page

### Staff Interface

- **Table List**: `[orders_jet_table_list]` - View all tables and their status
- **Kitchen Display**: `[orders_jet_kitchen_display]` - Show orders for kitchen staff

## Shortcodes

### `[orders_jet_qr_menu]`
Displays the QR code menu interface for customers.

**Attributes:**
- `table` - Table number (optional, can be set via URL parameter)

### `[orders_jet_table_list]`
Displays table management interface for staff.

**Attributes:**
- `zone` - Filter by table zone (optional)

### `[orders_jet_kitchen_display]`
Displays kitchen order management interface.

**Attributes:**
- `status` - Order status to display (default: processing)

## Customization

### Styling
- CSS files: `/assets/css/`
- Main styles: `qr-menu.css`
- Admin styles: `admin.css`

### JavaScript
- JS files: `/assets/js/`
- Main functionality: `qr-menu.js`
- Admin functionality: `admin.js`

### Templates
- Template files: `/templates/`
- QR menu template: `qr-menu.php`

## API

### AJAX Actions

#### `oj_submit_table_order`
Submit a contactless table order.

**Parameters:**
- `table_number` - Table number
- `table_id` - Table post ID
- `special_requests` - Special instructions
- `cart_items` - Array of cart items
- `nonce` - Security nonce

#### `oj_get_table_status`
Get current table status.

**Parameters:**
- `table_number` - Table number
- `nonce` - Security nonce

#### `oj_regenerate_qr_code`
Regenerate QR code for a table.

**Parameters:**
- `table_id` - Table post ID
- `nonce` - Security nonce

### Helper Functions

#### `oj_get_table_id_by_number($table_number)`
Get table post ID by table number.

#### `oj_get_current_table_order($table_number)`
Get current active order for a table.

#### `oj_send_order_notification($order)`
Send order notification to staff.

## Database

### Custom Post Types
- `oj_table` - Restaurant tables

### Custom Taxonomies
- `oj_table_zone` - Table zones/locations

### Meta Fields
- `_oj_table_number` - Table number
- `_oj_table_capacity` - Table capacity
- `_oj_table_status` - Table status
- `_oj_table_location` - Table location
- `_oj_table_qr_code` - QR code URL

## Security

- All AJAX requests use WordPress nonces
- Input sanitization and validation
- Capability checks for admin functions
- XSS protection in templates

## Support

For support and customization requests, contact Orders Jet team.

## Changelog

### 1.0.0
- Initial release
- Table management system
- QR code menu functionality
- Contactless ordering
- Staff interfaces
- WooCommerce integration

## License

GPL v2 or later

