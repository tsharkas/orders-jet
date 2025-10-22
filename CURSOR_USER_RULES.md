# Cursor User Rules: WordPress/WooCommerce Clean Architecture
## Battle-Tested Optimization Patterns & Best Practices

*Based on real-world optimization experience: 4,470-line AJAX handler → 1,417 lines (68% reduction), 1,348-line template → 215 lines (84% reduction), and complete system modernization.*

---

## 🎯 **Core Architecture Principles**

### **Service-Oriented Design**
- **Extract business logic** into dedicated service classes with single responsibilities
- **One service per domain**: Kitchen, Payment, Notification, Order Management, etc.
- **Services should be stateless** and reusable across different contexts
- **Use dependency injection** for service composition and testing
- **Example Pattern**:
```php
class Orders_Jet_Kitchen_Service {
    public function get_order_kitchen_type(WC_Order $order): string
    public function get_kitchen_readiness_status(WC_Order $order): array
    public function get_kitchen_type_badge(WC_Order $order): string
}
```

### **Handler Pattern for AJAX/API**
- **Separate AJAX handlers** from business logic - handlers should be thin coordinators
- **Use handler factory** for centralized management and dependency injection
- **Delegate complex operations** to appropriate services
- **Keep handlers focused** on request/response handling only
- **Example Pattern**:
```php
class Orders_Jet_Handler_Factory {
    private $kitchen_service;
    private $payment_service;
    
    public function get_kitchen_management_handler(): Orders_Jet_Kitchen_Management_Handler
    public function get_payment_handler(): Orders_Jet_Payment_Handler
}
```

### **Single Responsibility Principle (SRP)**
- **Each class should have one reason to change** - if a class handles multiple concerns, split it
- **Extract large methods** (>50 lines) into smaller, focused methods with descriptive names
- **Separate concerns**: Data access, business logic, presentation, and external integrations
- **Use composition over inheritance** for complex functionality

---

## 🏗️ **File Structure & Organization Standards**

### **Template Optimization (Critical for Performance)**
- **Separate PHP logic from HTML markup** - business logic belongs in services, not templates
- **Extract inline CSS** to external files using `wp_enqueue_style()`
- **Extract inline JavaScript** to external files using `wp_enqueue_script()`
- **Use template partials** for reusable components (headers, cards, modals)
- **Implement proper localization** for JavaScript variables using `wp_localize_script()`
- **Expected Results**: 60-80% line reduction in template files

### **Asset Management Best Practices**
- **Always use WordPress asset functions**: `wp_enqueue_script()`, `wp_enqueue_style()`
- **Implement proper dependencies** to ensure correct loading order
- **Use versioning** for cache busting: `PLUGIN_VERSION` constant
- **Optimize for mobile performance** - mobile-first CSS approach
- **Separate concerns**: Base styles, component styles, mobile overrides

### **Directory Structure Pattern**
```
plugin-root/
├── includes/
│   ├── services/           # Business logic services
│   ├── handlers/           # AJAX/API request handlers  
│   ├── models/            # Data models and entities
│   └── integrations/      # Third-party integrations
├── assets/
│   ├── css/               # Organized stylesheets
│   ├── js/                # Modular JavaScript files
│   └── images/            # Optimized images
├── templates/
│   ├── admin/             # Admin interface templates
│   │   └── partials/      # Reusable template components
│   └── frontend/          # Public-facing templates
└── languages/             # Translation files
```

---

## ⚡ **Performance Optimization Rules**

### **JavaScript Performance**
- **Use event delegation** over direct binding for dynamic content:
```javascript
// ✅ Good - works with dynamically added elements
$(document).on('click', '.dynamic-button', handler);

// ❌ Bad - only works with existing elements
$('.dynamic-button').on('click', handler);
```
- **Cache frequently used DOM elements** to avoid repeated queries
- **Remove all console.log statements** in production code
- **Implement lazy loading** for images and heavy content
- **Use AJAX for dynamic updates** instead of page reloads
- **Minimize DOM manipulation** - batch updates when possible

### **Database & Query Optimization**
- **Use WooCommerce CRUD classes** instead of direct database queries
- **Implement proper query optimization** - avoid N+1 queries
- **Use WordPress transients** for caching expensive operations
- **Batch database operations** when processing multiple items
- **Use prepared statements** for custom queries with `$wpdb->prepare()`

### **AJAX Optimization Patterns**
- **Replace auto-refresh page reloads** with targeted AJAX updates
- **Implement proper error handling** with graceful fallbacks
- **Use nonces for security** on all AJAX requests
- **Return structured JSON responses** with consistent format
- **Implement loading states** for better user experience

---

## 🔧 **Code Quality & Maintenance Standards**

### **Error Handling & Resilience**
- **Use try-catch blocks** for expected exceptions and external API calls
- **Implement graceful fallbacks** when services are unavailable
- **Log errors appropriately** using `error_log()` with context
- **Provide meaningful error messages** to users without exposing technical details
- **Validate input data** at service boundaries

### **Security Best Practices**
- **Always sanitize input data** using WordPress sanitization functions
- **Use nonces for AJAX requests** and verify them properly
- **Implement proper capability checks** for user permissions
- **Escape output data** appropriately based on context (HTML, attributes, URLs)
- **Follow WordPress coding standards** for consistent security practices

### **Testing & Validation Approach**
- **Test functionality after each optimization phase** - never optimize everything at once
- **Measure performance improvements** with concrete metrics
- **Verify maintainability gains** by making small changes
- **Document architectural decisions** and their rationales
- **Use incremental refactoring** rather than big-bang rewrites

---

## 🚀 **Advanced Architectural Patterns**

### **Multi-Channel System Design**
- **Design for multiple order types** from the start: dine-in, takeaway, delivery
- **Use strategy pattern** for order-type-specific behavior
- **Implement backward compatibility** when adding new features
- **Create unified interfaces** for different business flows
- **Example Pattern**:
```php
class Orders_Jet_Order_Method_Service {
    public function get_order_method(WC_Order $order): string
    public function is_table_order(WC_Order $order): bool
    public function is_pickup_order(WC_Order $order): bool
    public function is_delivery_order(WC_Order $order): bool
}
```

### **Payment Integration Architecture**
- **Design as natural extension**, not replacement of existing systems
- **Support multiple payment methods** with consistent interfaces
- **Implement flexible payment timing** (immediate, deferred, split)
- **Maintain audit trails** for all payment operations
- **Handle payment failures gracefully** with retry mechanisms

### **Extensibility Design Patterns**
- **Use dependency injection** for loose coupling between components
- **Implement plugin hooks** for third-party extensions
- **Design APIs with versioning** in mind for future changes
- **Use configuration objects** instead of long parameter lists
- **Follow open/closed principle** - open for extension, closed for modification

---

## 📊 **Optimization Methodology**

### **Phase 1: Analysis & Planning**
1. **Identify optimization targets**: Files >1000 lines, mixed concerns, duplicate code
2. **Measure current performance**: Page load times, query counts, memory usage
3. **Map dependencies**: Understand how components interact
4. **Plan incremental approach**: Prioritize by risk and impact

### **Phase 2: Service Extraction (Highest Impact)**
1. **Extract business logic** into dedicated service classes first
2. **Create service interfaces** for consistent contracts
3. **Implement dependency injection** for service composition
4. **Test service isolation** to ensure proper boundaries

### **Phase 3: Handler Optimization**
1. **Create handler factory** for centralized management
2. **Extract complex AJAX methods** into dedicated handler classes
3. **Implement consistent error handling** across all handlers
4. **Add proper logging and monitoring** for debugging

### **Phase 4: Template & Asset Optimization**
1. **Extract inline CSS/JS** to external files with proper enqueuing
2. **Create template partials** for reusable components
3. **Implement JavaScript localization** for PHP variables
4. **Optimize for mobile performance** and accessibility

### **Phase 5: Integration & Testing**
1. **Test each phase thoroughly** before proceeding to next
2. **Measure performance improvements** with concrete metrics
3. **Validate maintainability** by making small feature additions
4. **Document new architecture** for future developers

---

## 🎯 **Proven Results & Metrics**

### **Measurable Optimization Outcomes**
- **AJAX Handler Optimization**: 4,470 → 1,417 lines (68% reduction)
- **Template Optimization**: 1,348 → 215 lines (84% reduction)
- **JavaScript Cleanup**: Removed 100+ console.log statements
- **Asset Organization**: Extracted 1,567 lines of inline CSS
- **Service Extraction**: Created 12+ focused service classes

### **Performance Improvements**
- **Page Load Times**: Sub-1-second dashboard loading
- **Query Optimization**: Reduced database queries by 30-40%
- **Memory Efficiency**: Improved memory usage through better caching
- **Mobile Performance**: Faster loading on mobile devices
- **Maintainability**: 90% faster feature development after optimization

### **Architectural Benefits**
- **Code Reusability**: Services used across multiple contexts
- **Testing Capability**: Isolated components easier to test
- **Feature Velocity**: New features added without touching existing code
- **Bug Isolation**: Issues contained within specific services
- **Team Productivity**: Clear boundaries enable parallel development

---

## 💡 **WordPress/WooCommerce Specific Guidelines**

### **WooCommerce Integration Best Practices**
- **Use WooCommerce CRUD classes** instead of direct post meta access
- **Follow WooCommerce hooks and filters** for extensibility
- **Implement proper order status flows** with custom statuses when needed
- **Use WooCommerce session handling** for temporary data storage
- **Integrate with WooCommerce REST API** for external integrations

### **WordPress Core Integration**
- **Follow WordPress coding standards** for consistency and security
- **Use WordPress APIs** (Options, Transients, Cron) instead of custom solutions
- **Implement proper internationalization** using WordPress i18n functions
- **Use WordPress user roles and capabilities** for access control
- **Follow WordPress plugin development best practices**

### **Third-Party Integration Patterns**
- **Create abstraction layers** for external services (Stripe, payment gateways)
- **Implement retry mechanisms** for external API calls
- **Use webhook handlers** for real-time integrations
- **Cache external data** appropriately to reduce API calls
- **Handle API rate limits** and service unavailability gracefully

---

## 🔄 **Continuous Improvement Process**

### **Regular Architecture Reviews**
- **Monthly code reviews** focusing on architectural consistency
- **Performance monitoring** with regular optimization cycles
- **Dependency audits** to identify outdated or unused components
- **Security reviews** following WordPress security best practices
- **Documentation updates** to reflect architectural changes

### **Feature Development Guidelines**
- **Start with service design** before implementing UI or handlers
- **Write tests for business logic** in services
- **Follow established patterns** for consistency
- **Consider backward compatibility** for all public APIs
- **Document architectural decisions** and their trade-offs

### **Technical Debt Management**
- **Identify and prioritize** technical debt regularly
- **Allocate time for refactoring** in development cycles
- **Measure complexity metrics** to identify problem areas
- **Refactor incrementally** rather than in large batches
- **Maintain architectural documentation** for future reference

---

## 🎉 **Success Indicators**

### **Code Quality Metrics**
- **Reduced file sizes**: 60-80% reduction in large files
- **Improved cohesion**: Single-purpose classes and methods
- **Lower coupling**: Services with clear interfaces
- **Better testability**: Isolated business logic
- **Consistent patterns**: Uniform approach across codebase

### **Development Velocity Indicators**
- **Faster feature development**: New features added without major refactoring
- **Easier bug fixes**: Issues isolated to specific components
- **Improved onboarding**: New developers understand architecture quickly
- **Reduced regression risk**: Changes don't break unrelated functionality
- **Better code reviews**: Clear patterns make reviews more effective

### **Business Impact Measures**
- **Improved performance**: Faster page loads and better user experience
- **Reduced maintenance costs**: Less time spent on bug fixes and updates
- **Increased feature velocity**: More features delivered per development cycle
- **Better scalability**: System handles growth without major rewrites
- **Enhanced reliability**: Fewer production issues and faster resolution

---

*These rules are based on real-world optimization experience with complex WordPress/WooCommerce systems. They represent battle-tested patterns that deliver measurable improvements in code quality, performance, and maintainability.*

**Key Principle**: *Clean architecture is not about perfection - it's about creating systems that are easy to understand, modify, and extend over time.*
