// ================================
// MAIN JS FOR CLOTHIFY - SINGLE SOURCE OF TRUTH
// Handles wishlist, cart, notifications, dropdowns
// Backend is the single source of truth
// ================================

document.addEventListener('DOMContentLoaded', () => {

    /* =========================
       NOTIFICATION SYSTEM
    ========================= */
    function showNotification(message, type = 'info') {
        document.querySelectorAll('.notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Make showNotification globally available
    window.showNotification = showNotification;

    /* =========================
       WISHLIST TOGGLE (with Mutual Exclusivity)
    ========================= */
    document.body.addEventListener('click', async e => {
        const btn = e.target.closest('.wishlist-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const productId = btn.dataset.id;
        if (!productId) return;

        const isActive = btn.classList.contains('active');
        const action = isActive ? 'remove_from_wishlist' : 'add_to_wishlist';

        try {
            const res = await fetch('wishlist_toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&product_id=${productId}`
            });

            const data = await res.json();
            
            if (!data.success) {
                if (data.requires_login) {
                    window.location.href = 'login.php';
                    return;
                }
                showNotification(data.message || 'Action failed', 'error');
                return;
            }

            // Update wishlist button
            btn.classList.toggle('active', data.in_wishlist);
            btn.title = data.in_wishlist ? 'Remove from wishlist' : 'Add to wishlist';

            // Update cart button if it exists (mutual exclusivity)
            const cartBtn = document.querySelector(`.cart-btn[data-id="${productId}"]`);
            if (cartBtn && data.in_wishlist) {
                // If added to wishlist, ensure cart is inactive
                cartBtn.classList.remove('active');
                cartBtn.title = 'Add to cart';
            }

            // Update counts
            if (data.wishlist_count !== undefined) {
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount) {
                    wishlistCount.textContent = data.wishlist_count;
                }
            }

            if (data.cart_count !== undefined) {
                const cartCount = document.getElementById('cart-count');
                if (cartCount) {
                    cartCount.textContent = data.cart_count;
                }
            }

            showNotification(data.message, 'success');

        } catch (err) {
            console.error('Wishlist error:', err);
            showNotification('Network error. Please try again.', 'error');
        }
    });

    /* =========================
       CART TOGGLE (with Mutual Exclusivity)
    ========================= */
    document.body.addEventListener('click', async e => {
        const btn = e.target.closest('.cart-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const productId = btn.dataset.id;
        if (!productId) return;

        const isActive = btn.classList.contains('active');
        const action = isActive ? 'remove_from_cart' : 'add_to_cart';

        try {
            const res = await fetch('cart_toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&product_id=${productId}`
            });

            const data = await res.json();

            if (!data.success) {
                if (data.requires_login) {
                    window.location.href = 'login.php';
                    return;
                }
                showNotification(data.message || 'Action failed', 'error');
                return;
            }

            // Update cart button
            btn.classList.toggle('active', data.in_cart);
            btn.title = data.in_cart ? 'Remove from cart' : 'Add to cart';

            // Update wishlist button if it exists (mutual exclusivity)
            const wishlistBtn = document.querySelector(`.wishlist-btn[data-id="${productId}"]`);
            if (wishlistBtn && data.in_cart) {
                // If added to cart, ensure wishlist is inactive
                wishlistBtn.classList.remove('active');
                wishlistBtn.title = 'Add to wishlist';
            }

            // Update counts
            if (data.cart_count !== undefined) {
                const cartCount = document.getElementById('cart-count');
                if (cartCount) {
                    cartCount.textContent = data.cart_count;
                }
            }

            if (data.wishlist_count !== undefined) {
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount) {
                    wishlistCount.textContent = data.wishlist_count;
                }
            }

            showNotification(data.message, 'success');

        } catch (err) {
            console.error('Cart error:', err);
            showNotification('Network error. Please try again.', 'error');
        }
    });

    /* =========================
       QUICK VIEW
    ========================= */
    document.body.addEventListener('click', e => {
        const btn = e.target.closest('.quick-view');
        if (!btn) return;
        e.preventDefault();

        const productId = btn.dataset.id;
        if (productId) {
            window.location.href = `product_description.php?id=${productId}`;
        }
    });

    /* =========================
       PRODUCT CARD CLICK (redirect to product page)
    ========================= */
    document.body.addEventListener('click', e => {
        const card = e.target.closest('.product-card');
        if (!card) return;
        
        // Don't redirect if clicking on buttons
        if (e.target.closest('.action-btn') || 
            e.target.closest('.quick-view') || 
            e.target.closest('.btn-buy')) {
            return;
        }

        const productId = card.dataset.id;
        if (productId) {
            window.location.href = `product_description.php?id=${productId}`;
        }
    });

    /* =========================
       USER DROPDOWN
    ========================= */
    const userDropdown = document.querySelector('.user-dropdown');
    if (userDropdown) {
        const toggle = userDropdown.querySelector('.user-info');
        const menu = userDropdown.querySelector('.dropdown-menu');

        toggle.addEventListener('click', e => {
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        document.addEventListener('click', () => menu.classList.remove('show'));
    }

    /* =========================
       NEWSLETTER
    ========================= */
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailInput = newsletterForm.querySelector('input[type="email"]');
            const email = emailInput.value.trim();
            if (!email) return;

            // Simulate API call
            await new Promise(r => setTimeout(r, 800));
            showNotification(`Subscribed successfully! Check ${email} for offers.`, 'success');
            emailInput.value = '';
        });
    }

    /* =========================
       LOAD INITIAL STATE (BOTH cart AND wishlist)
    ========================= */
    async function loadInitialState() {
        try {
            const res = await fetch('get_user_state.php');
            const data = await res.json();
            
            if (data.success) {
                // Update counts
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount) {
                    wishlistCount.textContent = data.wishlist_count;
                }

                const cartCount = document.getElementById('cart-count');
                if (cartCount) {
                    cartCount.textContent = data.cart_count;
                }

                // Update cart button states
                if (data.cart_items) {
                    data.cart_items.forEach(productId => {
                        const btn = document.querySelector(`.cart-btn[data-id="${productId}"]`);
                        if (btn) {
                            btn.classList.add('active');
                            btn.title = 'Remove from cart';
                        }
                    });
                }

                // Update wishlist button states
                if (data.wishlist_items) {
                    data.wishlist_items.forEach(productId => {
                        const btn = document.querySelector(`.wishlist-btn[data-id="${productId}"]`);
                        if (btn) {
                            btn.classList.add('active');
                            btn.title = 'Remove from wishlist';
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error loading initial state:', error);
        }
    }

    // Load initial state
    loadInitialState();

    // Add notification styles if not already in CSS
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 0.75rem;
                z-index: 1000;
                transform: translateX(150%);
                transition: transform 0.3s ease;
                max-width: 350px;
            }
            
            .notification.show {
                transform: translateX(0);
            }
            
            .notification.success {
                border-left: 4px solid #27ae60;
            }
            
            .notification.error {
                border-left: 4px solid #e74c3c;
            }
            
            .notification.info {
                border-left: 4px solid #3498db;
            }
            
            .notification i {
                font-size: 1.2rem;
            }
            
            .notification.success i {
                color: #27ae60;
            }
            
            .notification.error i {
                color: #e74c3c;
            }
            
            .notification.info i {
                color: #3498db;
            }
        `;
        document.head.appendChild(style);
    }
/* =========================
   PRODUCT DESCRIPTION PAGE SPECIFIC
   (Star ratings, size selection)
========================= */
if (document.querySelector('.star-rating')) {
    const stars = document.querySelectorAll('.star-rating i');
    const ratingInput = document.getElementById('ratingValue');
    
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const rating = this.dataset.rating;
            stars.forEach((s, index) => {
                if (index < rating) s.classList.add('hover');
            });
        });
        
        star.addEventListener('mouseout', () => {
            stars.forEach(s => s.classList.remove('hover'));
        });
        
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            if (ratingInput) ratingInput.value = rating;
            stars.forEach((s, index) => {
                s.classList.remove('active', 'far');
                s.classList.add('fas');
                if (index < rating) s.classList.add('active');
            });
        });
    });
}

// Size selection
document.querySelectorAll('.size-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.size-option').forEach(opt => 
            opt.classList.remove('selected'));
        this.classList.add('selected');
    });
});
});