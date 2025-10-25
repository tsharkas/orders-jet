/**
 * QR Menu JavaScript - Extracted from Original Working Template
 * Clean, proven functions from the original qr-menu.php
 */

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
        
        // Event delegation for variation changes (since variations are loaded dynamically)
        document.addEventListener('change', function(e) {
            if (e.target.matches('input[name^="variation-"]')) {
                console.log('Variation changed via event delegation:', e.target);
                updateVariationPrice();
            }
        });
        
        // Cart events
        elements.clearCart.addEventListener('click', clearCart);
        elements.placeOrder.addEventListener('click', placeOrder);
        
        // Floating cart button event (new structure)
        const floatingCartBtn = document.getElementById('floating-cart-btn');
        if (floatingCartBtn) {
            floatingCartBtn.addEventListener('click', function() {
                switchTab('cart');
            });
        }
        
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
        fetch(window.OrdersJetQRMenu.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_get_product_details',
                product_id: productId,
                nonce: window.OrdersJetQRMenu.nonces.product_details
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
            <div class="popup-loading-text">Loading product details...</div>
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
        console.log('=== DISPLAY PRODUCT DETAILS ===');
        console.log('Product details received:', data);
        
        // Handle price information for variable products
        if (data.price_info) {
            console.log('Price info found:', data.price_info);
            handleProductPricing(data.price_info);
        } else {
            console.log('No price info received');
        }
        
        // Display variations first (most important)
        if (data.variations && Object.keys(data.variations).length > 0) {
            console.log('Variations found:', data.variations);
            displayVariations(data.variations);
        } else {
            console.log('No variations found');
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
    
    // Handle product pricing based on type
    function handleProductPricing(priceInfo) {
        if (priceInfo.is_variable) {
            // For variable products, show price range and disable add to cart
            elements.popupPrice.innerHTML = priceInfo.price_range;
            elements.popupAddToCart.disabled = true;
            elements.popupAddToCart.textContent = 'Select Options';
            elements.popupAddToCart.classList.add('disabled');
            
            // Store price info for later use
            currentProduct.priceInfo = priceInfo;
            currentProduct.isVariable = true;
        } else {
            // For simple products, show normal price and enable add to cart
            elements.popupPrice.innerHTML = priceInfo.price_range;
            elements.popupAddToCart.disabled = false;
            elements.popupAddToCart.textContent = 'Add to Cart';
            elements.popupAddToCart.classList.remove('disabled');
            
            currentProduct.isVariable = false;
        }
    }
    
    // Display variations
    function displayVariations(variations) {
        console.log('=== DISPLAY VARIATIONS ===');
        const variationsSection = document.getElementById('variations-section');
        const variationsContent = document.getElementById('variations-content');
        
        // Mark product as variable when variations are displayed
        if (currentProduct) {
            currentProduct.isVariable = true;
            console.log('Product marked as variable');
            
            // Calculate price range from variations if not available
            if (!currentProduct.priceInfo) {
                const priceRange = calculatePriceRangeFromVariations(variations);
                if (priceRange) {
                    elements.popupPrice.innerHTML = priceRange;
                    console.log('Set price range from variations:', priceRange);
                }
            }
            
            // Disable add to cart button for variable products
            elements.popupAddToCart.disabled = true;
            elements.popupAddToCart.textContent = 'Select Options';
            elements.popupAddToCart.classList.add('disabled');
            console.log('Add to cart button disabled');
        }
        
        let variationsHTML = '';
        for (const [attributeName, options] of Object.entries(variations)) {
            console.log(`Processing attribute: ${attributeName} with ${options.length} options`);
            variationsHTML += `
                <div class="exrow-group ex-radio">
                    <span class="exfood-label">
                        <span class="exwo-otitle">${attributeName}</span>
                    </span>
                    <div class="exwo-container">
                        ${options.map(option => {
                            console.log(`Option: ${option.label}, Price: ${option.price}, ID: ${option.variation_id}`);
                            return `
                            <div class="addon-option-item">
                                <div class="addon-option-content">
                                    <input type="radio" class="ex-options variation-option" name="variation-${attributeName}" 
                                           id="variation-${option.value}" value="${option.value}" 
                                           data-price="${option.price || 0}" 
                                           data-variation-id="${option.variation_id || 0}">
                                    <label class="addon-option-label" for="variation-${option.value}">
                                        <span class="exwo-op-name">${option.label}</span>
                                        <!-- Price removed for native WooCommerce behavior -->
                                    </label>
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        variationsContent.innerHTML = variationsHTML;
        variationsSection.style.display = 'block';
        
        console.log('Variations HTML generated and displayed');
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
    
    // Update price when variation is selected (Native WooCommerce behavior)
    function updateVariationPrice() {
        console.log('=== UPDATE VARIATION PRICE ===');
        console.log('Current product:', currentProduct);
        
        if (!currentProduct) {
            console.log('No current product');
                return;
            }
            
        console.log('Product isVariable:', currentProduct.isVariable);
        
        const selectedVariation = document.querySelector('input[name^="variation-"]:checked');
        console.log('Selected variation element:', selectedVariation);
        
        if (selectedVariation) {
            const variationPrice = parseFloat(selectedVariation.dataset.price) || 0;
            const variationId = selectedVariation.dataset.variationId;
            console.log('Variation price:', variationPrice);
            console.log('Variation ID:', variationId);
            
            if (variationPrice > 0) {
                const formattedPrice = formatPrice(variationPrice);
                console.log('Formatted price:', formattedPrice);
                
                // Update price display
                elements.popupPrice.innerHTML = formattedPrice;
                
                // Enable add to cart button
                elements.popupAddToCart.disabled = false;
                elements.popupAddToCart.textContent = 'Add to Cart';
                elements.popupAddToCart.classList.remove('disabled');
                
                console.log('Price updated and button enabled');
            } else {
                console.log('Invalid variation price:', variationPrice);
            }
        } else {
            console.log('No variation selected');
            
            // No variation selected, show price range and disable button
            let priceRange = null;
            if (currentProduct.priceInfo && currentProduct.priceInfo.price_range) {
                priceRange = currentProduct.priceInfo.price_range;
            } else {
                // Fallback: calculate from current variations
                const variationInputs = document.querySelectorAll('input[name^="variation-"]');
                if (variationInputs.length > 0) {
                    const variations = {};
                    variationInputs.forEach(input => {
                        const attributeName = input.name.replace('variation-', '');
                        if (!variations[attributeName]) variations[attributeName] = [];
                        variations[attributeName].push({
                            price: parseFloat(input.dataset.price) || 0,
                            label: input.value
                        });
                    });
                    priceRange = calculatePriceRangeFromVariations(variations);
                }
            }
            
            if (priceRange) {
                elements.popupPrice.innerHTML = priceRange;
                console.log('Price range restored:', priceRange);
            }
            
            elements.popupAddToCart.disabled = true;
            elements.popupAddToCart.textContent = 'Select Options';
            elements.popupAddToCart.classList.add('disabled');
            
            console.log('Button disabled, waiting for selection');
        }
    }
    
    // Format price helper function
    function formatPrice(price) {
        const currencySymbol = window.OrdersJetQRMenu.currencySymbol || 'EGP';
        return price.toFixed(2) + ' ' + currencySymbol;
    }
    
    // Calculate price range from variations data
    function calculatePriceRangeFromVariations(variations) {
        console.log('Calculating price range from variations:', variations);
        let minPrice = Infinity;
        let maxPrice = 0;
        
        Object.values(variations).forEach(options => {
            options.forEach(option => {
                const price = parseFloat(option.price) || 0;
                if (price > 0) {
                    minPrice = Math.min(minPrice, price);
                    maxPrice = Math.max(maxPrice, price);
                }
            });
        });
        
        if (minPrice === Infinity) {
            console.log('No valid prices found in variations');
            return null;
        }
        
        console.log(`Price range: ${minPrice} - ${maxPrice}`);
        
        if (minPrice === maxPrice) {
            return formatPrice(minPrice);
        } else {
            return formatPrice(minPrice) + ' - ' + formatPrice(maxPrice);
        }
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
        
        // Optimized display update - only update what's necessary for clearing
        if (elements.cartItems) {
            elements.cartItems.innerHTML = '<div class="cart-empty"><p>Your cart is empty</p></div>';
        }
        
        if (elements.cartTotal) {
            elements.cartTotal.textContent = '0.00 EGP';
        }
        
        // Update floating cart efficiently
        if (elements.floatingCart) {
            elements.floatingCart.classList.remove('show');
        }
        
        // Update floating cart details
        const floatingCartTotal = document.getElementById('floating-cart-total');
        const floatingCartItems = document.getElementById('floating-cart-items');
        
        if (floatingCartTotal) {
            floatingCartTotal.textContent = '0.00 EGP';
        }
        
        if (floatingCartItems) {
            floatingCartItems.textContent = '0 items';
        }
        
        // Update cart badge
        const cartBadge = document.querySelector('.oj-cart-badge');
        if (cartBadge) {
            cartBadge.textContent = '0';
            cartBadge.style.display = 'none';
        }
        
        showAppNotification('Cart cleared successfully', 'success');
    }
    
    function updateCartDisplay() {
        // Update cart items
        if (cart.length === 0) {
            elements.cartItems.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">Your cart is empty</p>';
        } else {
            let html = '';
            cart.forEach((item, index) => {
                let addonDetails = '';
                
                // Show add-ons with detailed breakdown
                if (item.add_ons && item.add_ons.length > 0) {
                    addonDetails += '<div class="cart-addon-details">';
                    item.add_ons.forEach(addon => {
                        const addonQty = addon.quantity || 1;
                        const addonPricePerUnit = addon.price || 0;
                        const addonTotalForAllItems = addonPricePerUnit * addonQty * item.quantity;
                        
                        if (addonQty > 1) {
                            addonDetails += `<div class="cart-addon-item">+ ${addon.name} + ${addonPricePerUnit.toFixed(2)} EGP × ${addonQty} × ${item.quantity} (+${addonTotalForAllItems.toFixed(2)} EGP)</div>`;
                        } else if (addon.value) {
                            addonDetails += `<div class="cart-addon-item">+ ${addon.name}: ${addon.value}</div>`;
                        } else {
                            addonDetails += `<div class="cart-addon-item">+ ${addon.name} + ${addonPricePerUnit.toFixed(2)} EGP × ${item.quantity} (+${addonTotalForAllItems.toFixed(2)} EGP)</div>`;
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
                
                // Calculate base price breakdown
                const basePrice = item.base_price || 0;
                const basePriceTotal = basePrice * item.quantity;
                
                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.display_name || item.name}</div>
                            <div class="cart-item-price">${basePrice.toFixed(2)} EGP × ${item.quantity} (${basePriceTotal.toFixed(2)} EGP)</div>
                            ${addonDetails}
                            ${item.notes ? `<div class="cart-item-notes">${item.notes}</div>` : ''}
                            <div class="cart-item-total"><strong>Total: ${itemTotal.toFixed(2)} EGP</strong></div>
                        </div>
                        <button class="cart-item-remove" onclick="removeFromCart(${index})">Remove</button>
                    </div>
                `;
            });
            elements.cartItems.innerHTML = html;
        }
        
        // Update totals - calculate proper cart total
        const total = cart.reduce((sum, item) => {
            const itemPrice = item.display_price || item.base_price || 0;
            const itemTotal = itemPrice * item.quantity;
            return sum + itemTotal;
        }, 0);
        
        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        const currencySymbol = window.OrdersJetQRMenu.currencySymbol || 'EGP';
        
        // Update cart tab total
        elements.cartTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
        
        // Update floating cart bar
        const floatingCartTotal = document.getElementById('floating-cart-total');
        const floatingCartItems = document.getElementById('floating-cart-items');
        
        if (floatingCartTotal) {
            floatingCartTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
        }
        
        if (floatingCartItems) {
            const itemText = itemCount === 1 ? 'item' : 'items';
            floatingCartItems.textContent = `${itemCount} ${itemText}`;
        }
        
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
        localStorage.setItem(`oj_cart_${window.OrdersJetQRMenu.tableNumber}`, JSON.stringify(cart));
    }
    
    function loadCart() {
        const saved = localStorage.getItem(`oj_cart_${window.OrdersJetQRMenu.tableNumber}`);
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
            `Place order for table ${window.OrdersJetQRMenu.tableNumber}?`,
            () => {
                // User confirmed - place the order
                console.log('Order confirmed, placing order...');
                console.log('Cart items:', cart);
                
                // Show loading notification
                showAppNotification('Placing order...', 'info');
                
                // Prepare order data (WooFood-style simplified)
                const orderData = {
                    table_number: window.OrdersJetQRMenu.tableNumber,
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
                fetch(window.OrdersJetQRMenu.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'oj_submit_table_order',
                        order_data: JSON.stringify(orderData),
                        nonce: window.OrdersJetQRMenu.nonces.table_order
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
        console.log(`Loading order history for table ${window.OrdersJetQRMenu.tableNumber}`);
        
        // Show skeleton loading for better UX
        elements.orderHistory.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-order">
                    <div class="skeleton-header"></div>
                    <div class="skeleton-items">
                        <div class="skeleton-item"></div>
                        <div class="skeleton-item"></div>
                    </div>
                    <div class="skeleton-total"></div>
                </div>
                <div class="skeleton-order">
                    <div class="skeleton-header"></div>
                    <div class="skeleton-items">
                        <div class="skeleton-item"></div>
                    </div>
                    <div class="skeleton-total"></div>
                </div>
            </div>
        `;
        
        // Fetch orders for this table
        fetch(window.OrdersJetQRMenu.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_get_table_orders',
                table_number: window.OrdersJetQRMenu.tableNumber,
                nonce: window.OrdersJetQRMenu.nonces.table_order
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
                    elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No orders found for this table</p>';
                }
            } else {
                console.log('No orders found or error:', data);
                console.log('Error message:', data.data?.message);
                elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No orders found</p>';
            }
        })
        .catch(error => {
            console.error('Error loading order history:', error);
            elements.orderHistory.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading orders</p>';
        });
    }
    
    // Display order history
    function displayOrderHistory(orders) {
            if (orders.length === 0) {
            elements.orderHistory.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No orders found</p>';
            
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
                    <h3 style="color: #155724; margin: 0 0 10px 0;">✅ Table Closed</h3>
                    <p style="color: #155724; margin: 0;">All orders have been completed and payment processed.</p>
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
        const currencySymbol = window.OrdersJetQRMenu.currencySymbol || 'EGP';
        elements.tableTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
        
        // Also update invoice total
        const invoiceTotal = document.getElementById('invoice-total');
        if (invoiceTotal) {
            invoiceTotal.textContent = `${total.toFixed(2)} ${currencySymbol}`;
        }
    }
    
    // Request Invoice functionality (simplified)
    function payNow() {
        // Show simple confirmation dialog
        showInvoiceRequestDialog();
    }
    
    // Show simple invoice request confirmation
    function showInvoiceRequestDialog() {
        const dialog = document.createElement('div');
        dialog.className = 'app-confirm-dialog';
        
        const content = document.createElement('div');
        content.className = 'app-dialog-content';
        content.style.maxWidth = '400px';
        
        content.innerHTML = `
            <div class="app-dialog-message">
                <h3>Request Invoice</h3>
                <p style="margin: 20px 0; color: #666;">
                    Our staff will bring your invoice and assist you with payment.
                </p>
            </div>
            <div class="app-dialog-actions">
                <button class="app-dialog-btn app-dialog-cancel">Cancel</button>
                <button class="app-dialog-btn app-dialog-confirm">Request Invoice</button>
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
            sendInvoiceRequest();
            dialog.remove();
        };
    }
    
    // Send simple invoice request to staff
    function sendInvoiceRequest() {
        console.log('Sending invoice request to staff');
        
        // Show loading
        showAppNotification('Notifying staff...', 'info');
        
        // Send simple request to mark table as needing invoice
        fetch(window.OrdersJetQRMenu.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oj_request_table_invoice',
                table_number: window.OrdersJetQRMenu.tableNumber,
                nonce: window.OrdersJetQRMenu.nonces.table_order
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showInvoiceRequestSuccess();
            } else {
                showAppNotification('Request failed: ' + (data.data?.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Invoice request error:', error);
            showAppNotification('Request error: ' + error.message, 'error');
        });
    }
    
    // Show simple success message
    function showInvoiceRequestSuccess() {
        const paySection = document.querySelector('.pay-section');
        if (!paySection) return;
        
        const successHtml = `
            <div class="invoice-request-success">
                <div class="success-icon">🔔</div>
                <h3>Staff Notified!</h3>
                <p style="color: #28a745; margin: 20px 0;">
                    Your server will bring your invoice shortly and assist you with payment.
                </p>
                
                <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #155724; margin-bottom: 10px;">What happens next:</h4>
                    <ul style="color: #155724; margin: 0; padding-left: 20px;">
                        <li>Staff will prepare your invoice</li>
                        <li>Your server will bring it to your table</li>
                        <li>You can pay with cash or card</li>
                    </ul>
                </div>
                
                <div class="thank-you-message">
                    <h4>Thank you for dining with us!</h4>
                    <p>Please remain seated. We'll be with you shortly.</p>
                </div>
            </div>
        `;
        
        // Replace the pay section with success message
        paySection.innerHTML = successHtml;
        
        // Show success notification
        showAppNotification('Staff has been notified!', 'success');
    }
    
    // Removed old invoice functions - guests now simply request staff assistance
    
    // Removed viewHTMLInvoice - guests now simply request staff assistance
    
    // Removed getCompletedOrdersForPDF - guests now simply request staff assistance
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
