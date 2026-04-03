<?php
session_start();
require 'db.php';

// Escape output
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

// Initialize session arrays
$_SESSION['wishlist'] ??= [];
$_SESSION['cart'] ??= [];

// ----------------------------
// HEADER COUNTS
// ----------------------------
if ($user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($wishlist_count);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($cart_count);
    $stmt->fetch();
    $stmt->close();
} else {
    $wishlist_count = count($_SESSION['wishlist']);
    $cart_count = count($_SESSION['cart']);
}

// ----------------------------
// FETCH WISHLIST ITEMS
// ----------------------------
$wishlist = [];

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT p.product_id, p.product_name, p.price, p.image_url, p.stock_quantity
        FROM wishlist w
        JOIN products p ON w.product_id = p.product_id
        WHERE w.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wishlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $guest_ids = array_keys($_SESSION['wishlist']);
    if (!empty($guest_ids)) {
        $ids = implode(',', array_map('intval', $guest_ids));
        $res = $conn->query("
            SELECT product_id, product_name, price, image_url, stock_quantity
            FROM products
            WHERE product_id IN ($ids)
        ");
        $wishlist = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Wishlist</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/home.css">
<style>
/* Quick table styling */
table { width:100%; border-collapse:separate; border-spacing:0 10px; }
th, td { padding:10px; text-align:left; }
button { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; }
.move-to-cart-btn { background:#e60023; color:#fff; }
.remove-wishlist-btn { background:#004c97; color:#fff; }
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo"><h1><i class="fas fa-tshirt"></i> Clothify</h1></div>
    <nav class="main-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
            <li><a href="about_us.php"><i class="fas fa-phone"></i>About Us</a></li>
        </ul>
    </nav>
    <div class="header-right">
        <div class="header-icons">
            <div class="icon-wrapper active" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= $wishlist_count ?></span>
            </div>
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="cart-count"><?= $cart_count ?></span>
            </div>

            <?php if($user_id): ?>
            <div class="user-dropdown">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span class="user-name"><?= e($user_name) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="user_orders.php"><i class="fas fa-box"></i> Orders</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <?php else: ?>
            <div class="auth-buttons">
                <a href="login.php" class="btn-login">Login</a>
                <a href="register.php" class="btn-signup">Sign Up</a>
            </div>
            <?php endif; ?>
        </div>
        <form class="search-bar" action="search.php" method="GET">
            <input type="text" name="q" placeholder="Search products..." required>
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="main-content" style="max-width:1000px; margin:50px auto;">
    <h1 style="text-align:center; margin-bottom:30px;">My Wishlist</h1>

    <?php if(empty($wishlist)): ?>
        <p style="text-align:center;">Your wishlist is empty.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Image</th>
                <th>Product</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
            <?php foreach($wishlist as $item): ?>
            <tr>
                <td><img src="<?= e($item['image_url']) ?>" style="width:70px;height:70px;object-fit:cover;border-radius:6px;"></td>
                <td><?= e($item['product_name']) ?></td>
                <td><?= number_format($item['price'], 2) ?></td>
                <td>
                    <button class="move-to-cart-btn" data-id="<?= $item['product_id'] ?>">Move to Cart</button>
                    <button class="remove-wishlist-btn" data-id="<?= $item['product_id'] ?>">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script>
document.querySelectorAll('.move-to-cart-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const pid = btn.dataset.id;
        
        // Step 1: Remove from wishlist first
        fetch('remove_wishlist.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'product_id='+pid
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                // Step 2: Add to cart
                return fetch('add_to_cart.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'product_id='+pid
                });
            } else {
                throw new Error('Failed to remove from wishlist');
            }
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                btn.textContent = 'Added to Cart';
                btn.disabled = true;
                btn.closest('tr').remove();
                
                // Update cart count (handle empty string)
                const cartEl = document.getElementById('cart-count');
                let cartCount = cartEl.textContent === '' ? 0 : parseInt(cartEl.textContent);
                cartEl.textContent = cartCount + 1;
                
                // Update wishlist count (handle empty string)
                const wishlistEl = document.getElementById('wishlist-count');
                let wishlistCount = wishlistEl.textContent === '' ? 0 : parseInt(wishlistEl.textContent);
                let newWishlistCount = wishlistCount - 1;
                wishlistEl.textContent = newWishlistCount > 0 ? newWishlistCount : '';
                
                // Show success message
                showNotification('Moved to cart successfully!', 'success');
                
                // Check if wishlist is empty
                if(document.querySelectorAll('table tbody tr').length === 0) {
                    location.reload(); // Show empty wishlist message
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error moving to cart');
        });
    });
});

document.querySelectorAll('.remove-wishlist-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const pid = btn.dataset.id;
        fetch('remove_wishlist.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'product_id='+pid
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                btn.closest('tr').remove();
                
                // Update wishlist count (handle empty string)
                const wishlistEl = document.getElementById('wishlist-count');
                let currentCount = wishlistEl.textContent === '' ? 0 : parseInt(wishlistEl.textContent);
                let newCount = currentCount - 1;
                wishlistEl.textContent = newCount > 0 ? newCount : '';
                
                showNotification('Removed from wishlist', 'success');
                
                // Check if wishlist is empty
                if(document.querySelectorAll('table tbody tr').length === 0) {
                    location.reload();
                }
            }
        })
        .catch(() => alert('Error removing from wishlist'));
    });
});

// Add notification function (same as other pages)
function showNotification(message, type = 'success') {
    // Check if notification already exists
    if (document.querySelector('.notification')) return;
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add notification styles if not present
if (!document.querySelector('#wishlist-styles')) {
    const style = document.createElement('style');
    style.id = 'wishlist-styles';
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
        .notification.show { transform: translateX(0); }
        .notification.success { border-left: 4px solid #27ae60; }
        .notification i { font-size: 1.2rem; }
        .notification.success i { color: #27ae60; }
    `;
    document.head.appendChild(style);
}
</script>
<script src="../js/main.js"></script>
</body>
</html>
