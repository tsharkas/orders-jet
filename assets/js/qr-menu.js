/**
 * Orders Jet - QR Code Menu JavaScript
 * Handles mobile menu interface and table ordering
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // QR Menu Object
    var OrdersJetQRMenu = {
        
        config: {
            tableNumber: '',
            tableId: null,
            currentOrder: null,
            cart: [],
            ajaxUrl: '',
            nonce: ''
        },
        
        init: function(options) {
            this.config = $.extend(this.config, options);
            this.bindEvents();
            this.loadCart();
            this.loadOrders();
            this.startOrderUpdates();
        },
        
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.oj-tab-button', this.switchTab);
            
            // Category filtering
            $(document).on('click', '.oj-category-filter', this.filterByCategory);
            
            // Quantity controls
            $(document).on('click', '.oj-quantity-btn', this.updateQuantity);
            $(document).on('change', '.oj-quantity-input', this.updateQuantityInput);
            
            // Add to cart
            $(document).on('click', '.oj-add-to-cart-btn', this.addToCart);
            
            // Cart management
            $(document).on('click', '.oj-clear-cart-btn', this.clearCart);
            $(document).on('click', '.oj-remove-cart-item', this.removeCartItem);
            $(document).on('click', '.oj-update-cart-item', this.updateCartItem);
            
            // Checkout
            $(document).on('click', '.oj-checkout-btn', this.checkout);
            
            // Service call
            $(document).on('click', '.oj-service-call-btn', this.callService);
            
            // Variation selection
            $(document).on('change', '.oj-variation-select', this.selectVariation);
        },
        
        switchTab: function(e) {
            e.preventDefault();
            var $button = $(this);
            var tabName = $button.data('tab');
            
            // Update active tab button
            $('.oj-tab-button').removeClass('active');
            $button.addClass('active');
            
            // Update active tab content
            $('.oj-tab-content').removeClass('active');
            $('#' + tabName + '-tab').addClass('active');
            
            // Load content if needed
            if (tabName === 'cart') {
                OrdersJetQRMenu.renderCart();
            } else if (tabName === 'orders') {
                OrdersJetQRMenu.loadOrders();
            }
        },
        
        filterByCategory: function(e) {
            e.preventDefault();
            var $button = $(this);
            var category = $button.data('category');
            
            // Update active filter
            $('.oj-category-filter').removeClass('active');
            $button.addClass('active');
            
            // Filter menu items
            if (category === 'all') {
                $('.oj-menu-item').show();
            } else {
                $('.oj-menu-item').hide();
                $('.oj-menu-item[data-categories*="' + category + '"]').show();
            }
        },
        
        updateQuantity: function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $button = $(this);
            var $input = $button.siblings('.oj-quantity-input');
            var currentValue = parseInt($input.val()) || 1;
            var newValue = currentValue;
            
            if ($button.hasClass('oj-quantity-plus')) {
                newValue = currentValue + 1; // Remove artificial limit
            } else if ($button.hasClass('oj-quantity-minus')) {
                newValue = Math.max(currentValue - 1, 1);
            }
            
            $input.val(newValue);
        },
        
        updateQuantityInput: function(e) {
            var $input = $(this);
            var value = parseInt($input.val());
            
            if (isNaN(value) || value < 1) {
                $input.val(1);
            }
            // Remove artificial upper limit
        },
        
        addToCart: function(e) {
            e.preventDefault();
            var $button = $(this);
            var productId = $button.data('product-id');
            var $menuItem = $button.closest('.oj-menu-item');
            var quantity = parseInt($menuItem.find('.oj-quantity-input').val());
            var variationId = $menuItem.find('.oj-variation-select').val();
            
            // Get product details
            var productName = $menuItem.find('.oj-menu-item-title').text();
            var productPrice = $menuItem.find('.oj-menu-item-price').text();
            var productImage = $menuItem.find('.oj-menu-item-image img').attr('src');
            
            // Create cart item
            var cartItem = {
                product_id: productId,
                variation_id: variationId || null,
                quantity: quantity,
                name: productName,
                price: productPrice,
                image: productImage,
                table: OrdersJetQRMenu.config.tableNumber
            };
            
            // Add to cart
            OrdersJetQRMenu.addToCartItem(cartItem);
            
            // Show success message
            OrdersJetQRMenu.showNotification('Item added to cart!', 'success');
            
            // Switch to cart tab
            $('.oj-tab-button[data-tab="cart"]').click();
        },
        
        addToCartItem: function(cartItem) {
            // Check if item already exists in cart
            var existingItem = OrdersJetQRMenu.cart.find(function(item) {
                return item.product_id === cartItem.product_id && 
                       item.variation_id === cartItem.variation_id;
            });
            
            if (existingItem) {
                existingItem.quantity += cartItem.quantity;
            } else {
                OrdersJetQRMenu.cart.push(cartItem);
            }
            
            // Save to localStorage
            OrdersJetQRMenu.saveCart();
            
            // Update cart display
            OrdersJetQRMenu.renderCart();
        },
        
        renderCart: function() {
            var $cartItems = $('.oj-cart-items');
            var $cartTotal = $('.oj-cart-total-amount');
            
            if (OrdersJetQRMenu.cart.length === 0) {
                $cartItems.html('<p class="oj-empty-cart">' + 'Your cart is empty' + '</p>');
                $cartTotal.text('$0.00');
                return;
            }
            
            var html = '';
            var total = 0;
            
            OrdersJetQRMenu.cart.forEach(function(item, index) {
                // Extract numeric value from price (handle comma as decimal separator)
                var priceString = item.price.replace(/[^\d,.]/g, ''); // Keep digits, commas, and dots
                var priceValue;
                
                // Handle different price formats
                if (priceString.includes(',') && priceString.includes('.')) {
                    // Format like "1,200.00" - comma is thousands separator
                    priceValue = parseFloat(priceString.replace(/,/g, ''));
                } else if (priceString.includes(',')) {
                    // Format like "120,00" - comma is decimal separator
                    priceValue = parseFloat(priceString.replace(',', '.'));
                } else {
                    // Format like "120.00" or "120"
                    priceValue = parseFloat(priceString);
                }
                
                var itemTotal = priceValue * item.quantity;
                total += itemTotal;
                
                html += '<div class="oj-cart-item" data-index="' + index + '">';
                html += '<div class="oj-cart-item-image">';
                if (item.image) {
                    html += '<img src="' + item.image + '" alt="' + item.name + '">';
                }
                html += '</div>';
                html += '<div class="oj-cart-item-details">';
                html += '<h4>' + item.name + '</h4>';
                html += '<p class="oj-cart-item-price">' + item.price + '</p>';
                html += '<div class="oj-cart-item-quantity">';
                html += '<button class="oj-quantity-btn oj-quantity-minus" data-index="' + index + '">-</button>';
                html += '<input type="number" value="' + item.quantity + '" min="1" max="10" data-index="' + index + '">';
                html += '<button class="oj-quantity-btn oj-quantity-plus" data-index="' + index + '">+</button>';
                html += '</div>';
                html += '</div>';
                html += '<div class="oj-cart-item-actions">';
                html += '<button class="oj-remove-cart-item" data-index="' + index + '">Remove</button>';
                html += '</div>';
                html += '</div>';
            });
            
            $cartItems.html(html);
            $cartTotal.text(total.toFixed(2) + ' EGP');
        },
        
        removeCartItem: function(e) {
            e.preventDefault();
            var index = $(this).data('index');
            OrdersJetQRMenu.cart.splice(index, 1);
            OrdersJetQRMenu.saveCart();
            OrdersJetQRMenu.renderCart();
        },
        
        updateCartItem: function(e) {
            e.preventDefault();
            var $input = $(this);
            var index = $input.data('index');
            var newQuantity = parseInt($input.val());
            
            if (newQuantity < 1) {
                newQuantity = 1;
            } else if (newQuantity > 10) {
                newQuantity = 10;
            }
            
            OrdersJetQRMenu.cart[index].quantity = newQuantity;
            OrdersJetQRMenu.saveCart();
            OrdersJetQRMenu.renderCart();
        },
        
        clearCart: function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to clear your cart?')) {
                OrdersJetQRMenu.cart = [];
                OrdersJetQRMenu.saveCart();
                OrdersJetQRMenu.renderCart();
            }
        },
        
        checkout: function(e) {
            e.preventDefault();
            
            if (OrdersJetQRMenu.cart.length === 0) {
                OrdersJetQRMenu.showNotification('Your cart is empty!', 'warning');
                return;
            }
            
            // Show checkout form
            OrdersJetQRMenu.showCheckoutForm();
        },
        
        showCheckoutForm: function() {
            var modalHtml = '<div id="oj-checkout-modal" class="oj-modal">';
            modalHtml += '<div class="oj-modal-content">';
            modalHtml += '<div class="oj-modal-header">';
            modalHtml += '<h3>Place Order - Table ' + OrdersJetQRMenu.config.tableNumber + '</h3>';
            modalHtml += '<span class="oj-modal-close">&times;</span>';
            modalHtml += '</div>';
            modalHtml += '<div class="oj-modal-body">';
            modalHtml += '<div class="oj-contactless-checkout">';
            modalHtml += '<div class="oj-table-info-checkout">';
            modalHtml += '<h4>Table Information</h4>';
            modalHtml += '<p><strong>Table Number:</strong> ' + OrdersJetQRMenu.config.tableNumber + '</p>';
            modalHtml += '<p class="oj-checkout-note">Your order will be prepared and delivered to your table. No personal information required!</p>';
            modalHtml += '</div>';
            modalHtml += '<div class="oj-form-group">';
            modalHtml += '<label for="oj-special-requests">Special Requests (Optional):</label>';
            modalHtml += '<textarea id="oj-special-requests" name="special_requests" rows="3" placeholder="Any special instructions for your order..."></textarea>';
            modalHtml += '</div>';
            modalHtml += '<div class="oj-order-summary">';
            modalHtml += '<h4>Order Summary</h4>';
            modalHtml += '<div class="oj-order-items"></div>';
            modalHtml += '<div class="oj-order-total">';
            modalHtml += '<strong>Total: <span class="oj-total-amount">$0.00</span></strong>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '<div class="oj-modal-footer">';
            modalHtml += '<button type="button" class="button oj-cancel-checkout">Cancel</button>';
            modalHtml += '<button type="button" class="button button-primary oj-confirm-order">Place Order</button>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            
            $('body').append(modalHtml);
            
            // Populate order summary
            OrdersJetQRMenu.populateOrderSummary();
            
            // Bind modal events
            OrdersJetQRMenu.bindCheckoutEvents();
        },
        
        populateOrderSummary: function() {
            var $orderItems = $('.oj-order-items');
            var $totalAmount = $('.oj-total-amount');
            var html = '';
            var total = 0;
            
            OrdersJetQRMenu.cart.forEach(function(item) {
                var itemTotal = parseFloat(item.price.replace(/[^0-9.-]+/g, '')) * item.quantity;
                total += itemTotal;
                
                html += '<div class="oj-order-item">';
                html += '<span class="oj-item-name">' + item.name + ' x' + item.quantity + '</span>';
                html += '<span class="oj-item-total">$' + itemTotal.toFixed(2) + '</span>';
                html += '</div>';
            });
            
            $orderItems.html(html);
            $totalAmount.text('$' + total.toFixed(2));
        },
        
        bindCheckoutEvents: function() {
            var $modal = $('#oj-checkout-modal');
            
            // Use event delegation for modal events
            $(document).on('click', '.oj-checkout-modal .oj-modal-close, .oj-checkout-modal .oj-cancel-checkout', function() {
                $(this).closest('.oj-checkout-modal').remove();
            });
            
            $(document).on('click', '.oj-checkout-modal .oj-confirm-order', function() {
                OrdersJetQRMenu.submitOrder();
            });
        },
        
        submitOrder: function() {
            var specialRequests = $('#oj-special-requests').val() || '';
            var formData = {
                action: 'oj_submit_table_order',
                table_number: OrdersJetQRMenu.config.tableNumber,
                table_id: OrdersJetQRMenu.config.tableId,
                special_requests: specialRequests,
                cart_items: OrdersJetQRMenu.cart,
                nonce: OrdersJetQRMenu.config.nonce
            };
            
            // Show loading
            $('.oj-confirm-order').prop('disabled', true).text('Placing Order...');
            
            $.ajax({
                url: OrdersJetQRMenu.config.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        OrdersJetQRMenu.showNotification('Order placed successfully! Staff will prepare your order.', 'success');
                        OrdersJetQRMenu.cart = [];
                        OrdersJetQRMenu.saveCart();
                        OrdersJetQRMenu.renderCart();
                        $('#oj-checkout-modal').remove();
                        
                        // Switch to orders tab
                        $('.oj-tab-button[data-tab="orders"]').click();
                    } else {
                        OrdersJetQRMenu.showNotification('Failed to place order. Please try again.', 'error');
                    }
                },
                error: function() {
                    OrdersJetQRMenu.showNotification('Error placing order. Please try again.', 'error');
                },
                complete: function() {
                    $('.oj-confirm-order').prop('disabled', false).text('Place Order');
                }
            });
        },
        
        loadOrders: function() {
            $.ajax({
                url: OrdersJetQRMenu.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_get_table_orders',
                    table_number: OrdersJetQRMenu.config.tableNumber,
                    nonce: OrdersJetQRMenu.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        OrdersJetQRMenu.renderOrders(response.data);
                    }
                }
            });
        },
        
        renderOrders: function(orders) {
            var $ordersList = $('.oj-orders-list');
            
            if (orders.length === 0) {
                $ordersList.html('<p class="oj-no-orders">No orders found for this table.</p>');
                return;
            }
            
            var html = '';
            orders.forEach(function(order) {
                html += '<div class="oj-order-card">';
                html += '<div class="oj-order-header">';
                html += '<h4>Order #' + order.number + '</h4>';
                html += '<span class="oj-order-status status-' + order.status + '">' + order.status + '</span>';
                html += '</div>';
                html += '<div class="oj-order-details">';
                html += '<p><strong>Total:</strong> ' + order.total + '</p>';
                html += '<p><strong>Items:</strong> ' + order.item_count + '</p>';
                html += '<p><strong>Date:</strong> ' + order.date + '</p>';
                html += '</div>';
                html += '<div class="oj-order-actions">';
                html += '<button class="button oj-view-order-details" data-order-id="' + order.id + '">View Details</button>';
                html += '</div>';
                html += '</div>';
            });
            
            $ordersList.html(html);
        },
        
        callService: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: OrdersJetQRMenu.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_call_service',
                    table_number: OrdersJetQRMenu.config.tableNumber,
                    nonce: OrdersJetQRMenu.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        OrdersJetQRMenu.showNotification('Service call sent! Staff will be with you shortly.', 'success');
                    } else {
                        OrdersJetQRMenu.showNotification('Failed to send service call. Please try again.', 'error');
                    }
                },
                error: function() {
                    OrdersJetQRMenu.showNotification('Error sending service call. Please try again.', 'error');
                }
            });
        },
        
        startOrderUpdates: function() {
            // Update order status every 30 seconds
            setInterval(function() {
                OrdersJetQRMenu.loadOrders();
            }, 30000);
        },
        
        saveCart: function() {
            localStorage.setItem('oj_cart_' + OrdersJetQRMenu.config.tableNumber, JSON.stringify(OrdersJetQRMenu.cart));
        },
        
        loadCart: function() {
            var savedCart = localStorage.getItem('oj_cart_' + OrdersJetQRMenu.config.tableNumber);
            if (savedCart) {
                OrdersJetQRMenu.cart = JSON.parse(savedCart);
            }
        },
        
        showNotification: function(message, type) {
            var $notification = $('<div class="oj-notification oj-notification-' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Expose to global scope
    window.OrdersJetQRMenu = OrdersJetQRMenu;
});
