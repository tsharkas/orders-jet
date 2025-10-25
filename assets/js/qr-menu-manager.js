/**
 * QR Menu Manager
 * Main JavaScript module for the refactored QR menu
 */

(function() {
    'use strict';
    
    // Check if window.ojQrMenu is available
    if (typeof window.ojQrMenu === 'undefined') {
        console.error('QR Menu: Configuration not found');
        return;
    }
    
    const config = window.ojQrMenu;
    
    // State Management
    const state = {
        currentTab: 'menu',
        cart: [],
        orderHistory: [],
        currentProduct: null,
        isLoading: false
    };
    
    // DOM Elements Cache
    const elements = {
        // Navigation
        navTabs: document.querySelectorAll('.nav-tab'),
        tabContents: document.querySelectorAll('.tab-content'),
        cartBadge: document.getElementById('cart-badge'),
        
        // Menu
        categoryBtns: document.querySelectorAll('.category-btn'),
        menuItems: document.querySelectorAll('.menu-item'),
        
        // Product Popup
        productPopup: document.getElementById('product-popup'),
        popupClose: document.getElementById('popup-close'),
        popupImage: document.getElementById('popup-image'),
        popupTitle: document.getElementById('popup-title'),
        popupDescription: document.getElementById('popup-description'),
        popupPrice: document.getElementById('popup-price'),
        popupNotesInput: document.getElementById('popup-notes-input'),
        quantityInput: document.getElementById('quantity-input'),
        quantityMinus: document.getElementById('quantity-minus'),
        quantityPlus: document.getElementById('quantity-plus'),
        popupAddToCart: document.getElementById('popup-add-to-cart'),
        
        // Cart
        cartItems: document.getElementById('cart-items'),
        cartSummary: document.getElementById('cart-summary'),
        cartSubtotal: document.getElementById('cart-subtotal'),
        cartTax: document.getElementById('cart-tax'),
        cartTotal: document.getElementById('cart-total'),
        cartActions: document.getElementById('cart-actions'),
        clearCart: document.getElementById('clear-cart'),
        placeOrder: document.getElementById('place-order'),
        emptyCartMessage: document.getElementById('empty-cart-message'),
        
        // History
        orderHistory: document.getElementById('order-history'),
        refreshHistory: document.getElementById('refresh-history'),
        tableTotalSection: document.getElementById('table-total-section'),
        tableSubtotal: document.getElementById('table-subtotal'),
        tableTotal: document.getElementById('table-total'),
        payNow: document.getElementById('pay-now'),
        emptyHistoryMessage: document.getElementById('empty-history-message'),
        
        // Floating Cart
        floatingCart: document.getElementById('floating-cart'),
        floatingCartTotal: document.getElementById('floating-cart-total'),
        
        // Modals
        orderConfirmationModal: document.getElementById('order-confirmation-modal'),
        paymentModal: document.getElementById('payment-modal'),
        paymentSuccessModal: document.getElementById('payment-success-modal')
    };
    
    // Initialize
    function init() {
        console.log('QR Menu: Initializing...');
        
        bindEvents();
        loadCart();
        loadOrderHistory();
        updateCartDisplay();
        
        console.log('QR Menu: Initialized successfully');
    }
    
    // Event Binding
    function bindEvents() {
        // Navigation
        elements.navTabs.forEach(tab => {
            tab.addEventListener('click', handleTabSwitch);
        });
        
        // Category filtering
        elements.categoryBtns.forEach(btn => {
            btn.addEventListener('click', handleCategoryFilter);
        });
        
        // Menu items
        elements.menuItems.forEach(item => {
            item.addEventListener('click', handleMenuItemClick);
        });
        
        // Product popup
        if (elements.popupClose) {
            elements.popupClose.addEventListener('click', closeProductPopup);
        }
        
        if (elements.productPopup) {
            elements.productPopup.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProductPopup();
                }
            });
        }
        
        // Quantity controls
        if (elements.quantityMinus) {
            elements.quantityMinus.addEventListener('click', () => adjustQuantity(-1));
        }
        
        if (elements.quantityPlus) {
            elements.quantityPlus.addEventListener('click', () => adjustQuantity(1));
        }
        
        // Add to cart
        if (elements.popupAddToCart) {
            elements.popupAddToCart.addEventListener('click', handleAddToCart);
        }
        
        // Cart actions
        if (elements.clearCart) {
            elements.clearCart.addEventListener('click', handleClearCart);
        }
        
        if (elements.placeOrder) {
            elements.placeOrder.addEventListener('click', handlePlaceOrder);
        }
        
        // History actions
        if (elements.refreshHistory) {
            elements.refreshHistory.addEventListener('click', loadOrderHistory);
        }
        
        if (elements.payNow) {
            elements.payNow.addEventListener('click', handlePayNow);
        }
        
        // Floating cart
        if (elements.floatingCart) {
            elements.floatingCart.addEventListener('click', () => switchTab('cart'));
        }
        
        // Back to menu buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('back-to-menu-btn')) {
                switchTab('menu');
            }
        });
        
        // Modal close buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-close')) {
                closeAllModals();
            }
        });
    }
    
    // Tab Management
    function handleTabSwitch(e) {
        const tabName = e.currentTarget.dataset.tab;
        switchTab(tabName);
    }
    
    function switchTab(tabName) {
        // Update navigation
        elements.navTabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });
        
        // Update content
        elements.tabContents.forEach(content => {
            content.classList.toggle('active', content.id === `${tabName}-tab`);
        });
        
        state.currentTab = tabName;
        
        // Load data for specific tabs
        if (tabName === 'history') {
            loadOrderHistory();
        }
        
        // Hide floating cart on cart tab
        if (elements.floatingCart) {
            elements.floatingCart.style.display = tabName === 'cart' ? 'none' : 'block';
        }
    }
    
    // Category Filtering
    function handleCategoryFilter(e) {
        const category = e.currentTarget.dataset.category;
        
        // Update active button
        elements.categoryBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.category === category);
        });
        
        // Filter menu items
        elements.menuItems.forEach(item => {
            const itemCategory = item.dataset.category;
            const shouldShow = category === 'all' || itemCategory === category;
            item.style.display = shouldShow ? 'block' : 'none';
        });
        
        // Scroll to category if not "all"
        if (category !== 'all') {
            const categorySection = document.getElementById(`category-${category}`);
            if (categorySection) {
                categorySection.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }
    
    // Menu Item Interaction
    function handleMenuItemClick(e) {
        const productId = e.currentTarget.dataset.productId;
        showProductPopup(productId);
    }
    
    function showProductPopup(productId) {
        if (!productId) return;
        
        showLoading(true);
        
        // Fetch product details
        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_get_product_details',
                product_id: productId,
                nonce: config.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                displayProductPopup(data.data);
            } else {
                showNotification(data.data?.message || config.strings.error, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error fetching product details:', error);
            showNotification(config.strings.error, 'error');
        });
    }
    
    function displayProductPopup(productData) {
        state.currentProduct = productData;
        
        // Update popup content
        if (elements.popupImage && productData.image_url) {
            elements.popupImage.src = productData.image_url;
            elements.popupImage.alt = productData.name;
        }
        
        if (elements.popupTitle) {
            elements.popupTitle.textContent = productData.name;
        }
        
        if (elements.popupDescription) {
            elements.popupDescription.textContent = productData.description || '';
        }
        
        if (elements.popupPrice) {
            elements.popupPrice.textContent = productData.formatted_price;
        }
        
        // Reset form
        if (elements.popupNotesInput) {
            elements.popupNotesInput.value = '';
        }
        
        if (elements.quantityInput) {
            elements.quantityInput.value = 1;
        }
        
        // Show popup
        if (elements.productPopup) {
            elements.productPopup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeProductPopup() {
        if (elements.productPopup) {
            elements.productPopup.style.display = 'none';
            document.body.style.overflow = '';
        }
        state.currentProduct = null;
    }
    
    // Quantity Management
    function adjustQuantity(delta) {
        if (!elements.quantityInput) return;
        
        const currentValue = parseInt(elements.quantityInput.value) || 1;
        const newValue = Math.max(1, currentValue + delta);
        elements.quantityInput.value = newValue;
    }
    
    // Cart Management
    function handleAddToCart() {
        if (!state.currentProduct) return;
        
        const quantity = parseInt(elements.quantityInput?.value) || 1;
        const notes = elements.popupNotesInput?.value || '';
        
        const cartItem = {
            product_id: state.currentProduct.id,
            name: state.currentProduct.name,
            price: state.currentProduct.price,
            formatted_price: state.currentProduct.formatted_price,
            quantity: quantity,
            notes: notes,
            image_url: state.currentProduct.image_url
        };
        
        addToCart(cartItem);
        closeProductPopup();
        showNotification(`${cartItem.name} added to cart`, 'success');
    }
    
    function addToCart(item) {
        // Check if item already exists
        const existingIndex = state.cart.findIndex(cartItem => 
            cartItem.product_id === item.product_id && cartItem.notes === item.notes
        );
        
        if (existingIndex !== -1) {
            // Update quantity
            state.cart[existingIndex].quantity += item.quantity;
        } else {
            // Add new item
            state.cart.push(item);
        }
        
        saveCart();
        updateCartDisplay();
    }
    
    function removeFromCart(index) {
        if (index >= 0 && index < state.cart.length) {
            state.cart.splice(index, 1);
            saveCart();
            updateCartDisplay();
        }
    }
    
    function updateCartQuantity(index, quantity) {
        if (index >= 0 && index < state.cart.length) {
            if (quantity <= 0) {
                removeFromCart(index);
            } else {
                state.cart[index].quantity = quantity;
                saveCart();
                updateCartDisplay();
            }
        }
    }
    
    function handleClearCart() {
        if (confirm('Are you sure you want to clear your cart?')) {
            state.cart = [];
            saveCart();
            updateCartDisplay();
            showNotification('Cart cleared', 'success');
        }
    }
    
    function updateCartDisplay() {
        const cartCount = state.cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartTotal = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        // Update cart badge
        if (elements.cartBadge) {
            elements.cartBadge.textContent = cartCount;
            elements.cartBadge.style.display = cartCount > 0 ? 'flex' : 'none';
        }
        
        // Update floating cart
        if (elements.floatingCartTotal) {
            elements.floatingCartTotal.textContent = formatPrice(cartTotal);
        }
        
        if (elements.floatingCart) {
            elements.floatingCart.style.display = cartCount > 0 && state.currentTab !== 'cart' ? 'block' : 'none';
        }
        
        // Update cart content
        updateCartContent();
    }
    
    function updateCartContent() {
        if (!elements.cartItems) return;
        
        if (state.cart.length === 0) {
            // Show empty state
            if (elements.emptyCartMessage) {
                elements.emptyCartMessage.style.display = 'block';
            }
            if (elements.cartSummary) {
                elements.cartSummary.style.display = 'none';
            }
            if (elements.cartActions) {
                elements.cartActions.style.display = 'none';
            }
            return;
        }
        
        // Hide empty state
        if (elements.emptyCartMessage) {
            elements.emptyCartMessage.style.display = 'none';
        }
        
        // Generate cart items HTML
        let cartHTML = '';
        let subtotal = 0;
        
        state.cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            
            cartHTML += `
                <div class="cart-item" data-index="${index}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${escapeHtml(item.name)}</div>
                        <div class="cart-item-price">${item.formatted_price} × ${item.quantity}</div>
                        ${item.notes ? `<div class="cart-item-notes">${escapeHtml(item.notes)}</div>` : ''}
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-controls">
                            <button type="button" onclick="window.ojQrMenuManager.updateCartQuantity(${index}, ${item.quantity - 1})">−</button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button type="button" onclick="window.ojQrMenuManager.updateCartQuantity(${index}, ${item.quantity + 1})">+</button>
                        </div>
                        <button type="button" class="remove-item" onclick="window.ojQrMenuManager.removeFromCart(${index})">🗑️</button>
                    </div>
                    <div class="cart-item-total">${formatPrice(itemTotal)}</div>
                </div>
            `;
        });
        
        elements.cartItems.innerHTML = cartHTML;
        
        // Update summary
        const tax = 0; // Calculate tax if needed
        const total = subtotal + tax;
        
        if (elements.cartSubtotal) {
            elements.cartSubtotal.textContent = formatPrice(subtotal);
        }
        if (elements.cartTax) {
            elements.cartTax.textContent = formatPrice(tax);
        }
        if (elements.cartTotal) {
            elements.cartTotal.textContent = formatPrice(total);
        }
        
        // Show summary and actions
        if (elements.cartSummary) {
            elements.cartSummary.style.display = 'block';
        }
        if (elements.cartActions) {
            elements.cartActions.style.display = 'flex';
        }
    }
    
    // Order Management
    function handlePlaceOrder() {
        if (state.cart.length === 0) {
            showNotification('Your cart is empty', 'error');
            return;
        }
        
        showLoading(true);
        
        // Prepare order data
        const orderData = {
            table_number: config.tableNumber,
            items: state.cart.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                notes: item.notes,
                price: item.price
            })),
            total: state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
        };
        
        // Submit order
        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_submit_table_order',
                order_data: JSON.stringify(orderData),
                nonce: config.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                // Clear cart
                state.cart = [];
                saveCart();
                updateCartDisplay();
                
                // Switch to history tab
                switchTab('history');
                loadOrderHistory();
                
                showNotification('Order placed successfully!', 'success');
            } else {
                showNotification(data.data?.message || 'Failed to place order', 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error placing order:', error);
            showNotification('Failed to place order', 'error');
        });
    }
    
    // Order History
    function loadOrderHistory() {
        if (!elements.orderHistory) return;
        
        showLoading(true);
        
        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_get_table_orders',
                table_number: config.tableNumber,
                nonce: config.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                displayOrderHistory(data.data);
            } else {
                showNotification(data.data?.message || 'Failed to load order history', 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error loading order history:', error);
            showNotification('Failed to load order history', 'error');
        });
    }
    
    function displayOrderHistory(historyData) {
        if (!elements.orderHistory) return;
        
        state.orderHistory = historyData.orders || [];
        
        if (state.orderHistory.length === 0) {
            // Show empty state
            if (elements.emptyHistoryMessage) {
                elements.emptyHistoryMessage.style.display = 'block';
            }
            if (elements.tableTotalSection) {
                elements.tableTotalSection.style.display = 'none';
            }
            elements.orderHistory.innerHTML = '';
            return;
        }
        
        // Hide empty state
        if (elements.emptyHistoryMessage) {
            elements.emptyHistoryMessage.style.display = 'none';
        }
        
        // Generate history HTML
        let historyHTML = '';
        
        state.orderHistory.forEach(order => {
            historyHTML += `
                <div class="order-history-item">
                    <div class="order-header">
                        <span class="order-number">#${order.order_number}</span>
                        <span class="order-status ${order.status}">${order.status}</span>
                    </div>
                    <div class="order-items">
                        ${order.items.map(item => `
                            <div class="order-item">
                                <span class="item-name">${escapeHtml(item.name)}</span>
                                <span class="item-quantity">×${item.quantity}</span>
                                <span class="item-total">${item.formatted_total}</span>
                            </div>
                        `).join('')}
                    </div>
                    <div class="order-total">
                        <strong>${order.formatted_total}</strong>
                    </div>
                </div>
            `;
        });
        
        elements.orderHistory.innerHTML = historyHTML;
        
        // Update table total
        if (elements.tableTotalSection && historyData.total_amount > 0) {
            elements.tableTotalSection.style.display = 'block';
            
            if (elements.tableSubtotal) {
                elements.tableSubtotal.textContent = formatPrice(historyData.total_amount);
            }
            if (elements.tableTotal) {
                elements.tableTotal.textContent = historyData.formatted_total;
            }
        }
    }
    
    // Payment
    function handlePayNow() {
        if (state.orderHistory.length === 0) {
            showNotification('No orders to pay for', 'error');
            return;
        }
        
        showPaymentModal();
    }
    
    function showPaymentModal() {
        if (elements.paymentModal) {
            elements.paymentModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
    
    // Utility Functions
    function saveCart() {
        try {
            localStorage.setItem(`oj_cart_${config.tableNumber}`, JSON.stringify(state.cart));
        } catch (error) {
            console.error('Error saving cart:', error);
        }
    }
    
    function loadCart() {
        try {
            const savedCart = localStorage.getItem(`oj_cart_${config.tableNumber}`);
            if (savedCart) {
                state.cart = JSON.parse(savedCart);
            }
        } catch (error) {
            console.error('Error loading cart:', error);
            state.cart = [];
        }
    }
    
    function formatPrice(amount) {
        return new Intl.NumberFormat('en-EG', {
            style: 'currency',
            currency: 'EGP',
            minimumFractionDigits: 2
        }).format(amount);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showLoading(show) {
        state.isLoading = show;
        // Implement loading UI if needed
    }
    
    function showNotification(message, type = 'info') {
        // Simple notification - can be enhanced
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // You can implement a toast notification system here
        alert(message);
    }
    
    function closeAllModals() {
        const modals = [
            elements.productPopup,
            elements.orderConfirmationModal,
            elements.paymentModal,
            elements.paymentSuccessModal
        ];
        
        modals.forEach(modal => {
            if (modal) {
                modal.style.display = 'none';
            }
        });
        
        document.body.style.overflow = '';
    }
    
    // Public API
    window.ojQrMenuManager = {
        updateCartQuantity,
        removeFromCart,
        switchTab,
        loadOrderHistory
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
