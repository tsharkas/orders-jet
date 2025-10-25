# Orders Jet - Troubleshooting Reference Guide

## 🚨 **Quick Problem Resolution**

This guide provides rapid solutions for common Orders Jet plugin issues based on real debugging experience.

---

## 🔧 **JavaScript Issues**

### **Problem: JavaScript Files Not Loading**

**Symptoms:**
- AJAX functionality broken on dashboard pages
- Console errors about undefined objects (OrdersJetAdmin, ojExpressData)
- Auto-refresh not working
- Filter buttons not responding

**Quick Fix Checklist:**
1. ✅ Check `includes/class-orders-jet-admin-dashboard.php`
2. ✅ Verify `$manager_pages` array includes all dashboard pages:
   ```php
   $manager_pages = array(
       'toplevel_page_manager-overview',
       'manager-overview_page_manager-orders',
       'manager-overview_page_manager-orders-express', // ← Must be included
       'manager-overview_page_manager-tables',
       // ... other pages
   );
   ```
3. ✅ Confirm JavaScript files exist in `assets/js/`
4. ✅ Check WordPress admin hook names match menu structure

**Root Cause:** Missing WordPress admin hook names in asset enqueuing method.

---

## 💳 **Payment Modal Issues**

### **Problem: Payment Modal Buttons Not Working**

**Symptoms:**
- Payment method modal appears but buttons are unclickable
- Close button (✕) doesn't work
- Order processing stuck at payment selection
- No response when clicking Cash/Card/Other buttons

**Quick Fix Checklist:**
1. ✅ Check CSS class consistency in `assets/js/dashboard-express.js`
2. ✅ Verify modal HTML classes match event handler selectors:
   ```javascript
   // Modal HTML should use:
   <div class="oj-success-modal-overlay">
   
   // Event handlers should target:
   modal.find('.oj-payment-btn').on('click', function(e) {
   ```
3. ✅ Ensure event handlers use direct binding for modal elements
4. ✅ Add preventDefault() and stopPropagation() to button clicks
5. ✅ Validate callback functions before execution

**Root Cause:** CSS class mismatch between modal HTML and JavaScript event handlers.

---

## 🍽️ **Table Management Issues**

### **Problem: Table Orders Not Completing**

**Symptoms:**
- Orders stuck in processing state
- Table closure fails
- Combined invoices not generating
- AJAX errors in browser console

**Quick Fix Checklist:**
1. ✅ Check AJAX handler registration in `includes/class-orders-jet-ajax-handlers.php`
2. ✅ Verify nonce validation:
   ```php
   check_ajax_referer('oj_table_order', 'nonce');
   ```
3. ✅ Confirm table closure handler exists and is properly initialized
4. ✅ Check order status flow: `processing` → `pending-payment` → `completed`
5. ✅ Validate table number meta data on orders

**Root Cause:** Usually AJAX handler issues or nonce validation failures.

---

## 🎯 **Asset Loading Issues**

### **Problem: CSS/JS Files Not Loading**

**Symptoms:**
- Dashboard pages look unstyled
- JavaScript functionality completely broken
- Console errors about missing files
- WordPress admin looks broken

**Quick Fix Checklist:**
1. ✅ Check file paths in asset enqueuing:
   ```php
   wp_enqueue_script(
       'orders-jet-admin',
       ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js', // ← Verify path
       array('jquery'),
       ORDERS_JET_VERSION,
       true
   );
   ```
2. ✅ Confirm files exist in correct directories
3. ✅ Check WordPress hook names for enqueuing
4. ✅ Verify plugin constants are defined correctly

**Root Cause:** Incorrect file paths or missing WordPress hooks.

---

## 🔍 **Debugging Methodology**

### **Step 1: Identify the Scope**
- Is it affecting all pages or specific ones?
- Is it JavaScript, PHP, or CSS related?
- Does it work locally but not live (or vice versa)?

### **Step 2: Check the Basics**
- File paths and existence
- WordPress hook names
- CSS class consistency
- AJAX handler registration

### **Step 3: Use Browser Developer Tools**
- Check Console for JavaScript errors
- Verify Network tab for failed asset loading
- Inspect Elements for CSS class names

### **Step 4: Check WordPress Debug Logs**
- Enable WP_DEBUG in wp-config.php
- Check error logs for PHP issues
- Look for AJAX handler errors

### **Step 5: Test Systematically**
- Isolate the problem to specific components
- Test one fix at a time
- Verify in both local and live environments

---

## 🛠️ **Common Fixes**

### **WordPress Asset Enqueuing**
```php
// Always include all dashboard pages in asset enqueuing
$manager_pages = array(
    'toplevel_page_manager-overview',
    'manager-overview_page_manager-orders',
    'manager-overview_page_manager-orders-express',
    'manager-overview_page_manager-tables',
    'manager-overview_page_manager-staff',
    'manager-overview_page_manager-reports',
    'manager-overview_page_manager-settings'
);
```

### **JavaScript Modal Event Handling**
```javascript
// Use direct binding for modal elements
modal.find('.oj-payment-btn').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const method = $(this).data('method');
    modal.remove();
    if (typeof callback === 'function') {
        callback(method);
    }
});
```

### **AJAX Handler Registration**
```php
// Ensure all AJAX actions are registered
add_action('wp_ajax_oj_close_table_group', array($this, 'close_table_group'));
add_action('wp_ajax_oj_complete_individual_order', array($this, 'complete_individual_order'));
add_action('wp_ajax_oj_mark_order_ready', array($this, 'mark_order_ready'));
```

---

## 📋 **Prevention Checklist**

### **Before Making Changes:**
- ✅ Understand the current architecture
- ✅ Check existing patterns and follow them
- ✅ Test in local environment first
- ✅ Verify all related components

### **After Making Changes:**
- ✅ Test all affected functionality
- ✅ Check browser console for errors
- ✅ Verify in both local and live environments
- ✅ Document any new patterns or fixes

### **Code Quality Standards:**
- ✅ Follow WordPress coding standards
- ✅ Use consistent naming conventions
- ✅ Implement proper error handling
- ✅ Add comments for complex logic

---

## 🚀 **Emergency Recovery**

### **If Everything Breaks:**
1. **Revert to Last Working Commit:**
   ```bash
   git log --oneline
   git reset --hard [commit-hash]
   git push --force-with-lease origin main
   ```

2. **Check Plugin Activation:**
   - Deactivate and reactivate the plugin
   - Check for PHP fatal errors
   - Verify database tables exist

3. **Clear All Caches:**
   - WordPress object cache
   - Browser cache
   - CDN cache (if applicable)

4. **Enable Debug Mode:**
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

---

## 📞 **Getting Help**

### **Information to Gather:**
- Exact error messages from console/logs
- Steps to reproduce the issue
- Environment details (local/live)
- Recent changes made
- Browser and WordPress versions

### **Useful Debug Commands:**
```bash
# Check git history
git log --oneline -10

# See current changes
git status
git diff

# Check file permissions (if needed)
ls -la assets/js/
```

---

## 📚 **Related Documentation**

- **[Debugging Case Study](DEBUGGING_CASE_STUDY.md)** - Detailed analysis of recent fixes
- **[System Architecture Map](SYSTEM_ARCHITECTURE_MAP.md)** - Overall system design
- **[Cursor User Rules](CURSOR_USER_RULES.md)** - Development best practices
- **[Workspace Setup](workspace-setup.md)** - Environment configuration

---

**Remember: Most issues are caused by simple mismatches in naming, paths, or WordPress hooks. Check the basics first!** 🎯
