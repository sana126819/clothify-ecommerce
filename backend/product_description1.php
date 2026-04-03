<?php
session_start();
require 'db.php';
 // TF-IDF recommendations

$product_id = intval($_GET['product_id'] ?? $_GET['id'] ?? 0);
if ($product_id <= 0) die("Invalid product ID.");

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

// Initialize session arrays
$_SESSION['cart'] ??= [];
$_SESSION['wishlist'] ??= [];

// Fetch product and category
$stmt = $conn->prepare("
    SELECT p.*, m.name AS main_category_name 
    FROM products p 
    LEFT JOIN main_categories m ON p.main_category_id = m.id
    WHERE p.product_id=? LIMIT 1
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) die("Product not found.");

// Log user activity
if ($user_id) {
    $log = $conn->prepare("INSERT INTO user_activity (user_id, product_id, action) VALUES (?, ?, 'view')");
    $log->bind_param("ii", $user_id, $product_id);
    $log->execute();
    $log->close();
}

// Sizes & default
$available_sizes = !empty($product['sizes']) ? array_map('trim', explode(',', $product['sizes'])) : ['XS','S','M','L','XL'];
$default_size = $product['default_size'] ?? $available_sizes[0];
$product_size = $default_size;

// Color mapping
function colorToCss($name){
    $map = ['Black'=>'#000','White'=>'#fff','Red'=>'#f00','Blue'=>'#00f','Green'=>'#008000','Yellow'=>'#ff0','Pink'=>'#ffc0cb','Purple'=>'#800080','Orange'=>'#ffa500','Brown'=>'#8b4513','Gray'=>'#808080','Beige'=>'#f5e9d4'];
    return $map[$name] ?? '#f5e9d4';
}
$cssColor = colorToCss($product['color'] ?? 'Beige');

// Cart & Wishlist
if ($user_id) {
    $userCart = array_column($conn->query("SELECT product_id FROM cart WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id');
    $userWishlist = array_column($conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id');
} else {
    $userCart = array_keys($_SESSION['cart']);
    $userWishlist = $_SESSION['wishlist'] ?? [];
}



function e($str){ return htmlspecialchars($str, ENT_QUOTES,'UTF-8'); }
?>
<?php
$recommendedProducts = [];

if (isset($product_id)) {

    $stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.price,
            p.image_url,
            r.score
        FROM recommendations r
        JOIN products p 
            ON p.product_id = r.recommended_product_id
        WHERE r.product_id = ?
          AND r.model = 'content'
        ORDER BY r.score DESC
        LIMIT 5
    ");

    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recommendedProducts[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($product['product_name']) ?> | Clothify</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/home.css">
<link rel="stylesheet" href="../css/product_description.css">
<style>
/* --- Simplified CSS --- */
.header-icons .count { background:red;color:#fff;font-size:12px;padding:2px 6px;border-radius:50%;position:absolute;top:-8px;right:-8px; }
.product-detail-container { display:flex; gap:30px; padding:20px; flex-wrap:wrap; }
.product-image-section img { max-width:400px; border-radius:10px; }
.product-info-section { max-width:600px; }
.size-options { display:flex; gap:10px; margin-top:5px; }
.size-option { padding:5px 10px; border:1px solid #ccc; cursor:pointer; border-radius:5px; }
.size-option.selected { background:#000; color:#fff; }
.color-option { display:inline-block; width:20px; height:20px; border-radius:50%; margin-right:5px; border:1px solid #ccc; vertical-align:middle; }
.similar-products-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:20px; }
.similar-product-card img { width:100%; border-radius:8px; cursor:pointer; }
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
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i><span class="count" id="wishlist-count"><?= count($userWishlist) ?></span>
            </div>
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i><span class="count" id="cart-count"><?= count($userCart) ?></span>
            </div>
            <?php if($user_id): ?>
            <div class="user-dropdown">
                <div class="user-info"><i class="fas fa-user-circle"></i> <?= e($user_name) ?> <i class="fas fa-chevron-down"></i></div>
                <div class="dropdown-menu">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="orders.php"><i class="fas fa-box"></i> Orders</a>
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

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="home_user.php">Home</a> &gt;
    <a href="product.php">Shop</a> &gt;
    <a href="product.php?main_category=<?= $product['main_category_id'] ?>"><?= e($product['main_category_name'] ?? 'Category') ?></a> &gt;
    <span><?= e($product['product_name']) ?></span>
</div>

<!-- PRODUCT DETAIL -->
<div class="product-detail-container">
    <div class="product-image-section">
        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['product_name']) ?>" class="product-main-image">
    </div>
    <div class="product-info-section">
        <h1><?= e($product['product_name']) ?></h1>
        <div class="product-price"><?= number_format($product['price'],2) ?></div>
        <p><?= nl2br(e($product['description'])) ?></p>
        <div class="product-details-grid">
            <div><i class="fas fa-palette"></i> Color: <?= e($product['color']) ?></div>
            <div><i class="fas fa-ruler-combined"></i> Size: <?= e($product_size) ?></div>
            <div><i class="fas fa-layer-group"></i> Material: Premium Cotton Blend</div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="product-actions">
    <button id="addToCartBtn" class="action-btn buy-now-btn <?= in_array($product_id, $userCart) ? 'added' : '' ?>" <?= in_array($product_id, $userCart) ? 'disabled' : '' ?>>
        <i class="fas fa-shopping-bag"></i> <?= in_array($product_id, $userCart) ? 'Already in Cart' : 'Add To Cart' ?>
    </button>

    <button id="wishlistBtn" class="action-btn wishlist-btn <?= in_array($product_id, $userWishlist) ? 'added' : '' ?>">
        <i class="fas fa-heart"></i> <?= in_array($product_id, $userWishlist) ? 'Added to Wishlist' : 'Add to Wishlist' ?>
    </button>
</div>

    </div>
</div>

<!-- Similar Products -->
<div class="similar-products-section">
    <h2>You Might Also Like</h2>
    <div class="similar-products-grid">
        <?php foreach($recommendedProducts as $item): ?>
            <div class="similar-product-card" onclick="location.href='product_description.php?product_id=<?= $item['product_id'] ?>'">
                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>">
                <div><?= e($item['product_name']) ?> - <?= number_format($item['price'],2) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- FOOTER -->
<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const productId = <?= $product_id ?>;
    const cartBtn = document.getElementById('addToCartBtn');
    const wishlistBtn = document.getElementById('wishlistBtn');

    /* =========================
       ADD TO CART (ADD ONLY)
    ========================= */
    if (cartBtn && !cartBtn.disabled) {
        cartBtn.addEventListener('click', async () =>H {
            try {
                const res = await fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${productId}`
                });

                const data = await res.json();
                if (!data.success) throw new Error();

                // UI
                cartBtn.textContent = 'Already in Cart';
                cartBtn.disabled = true;
                cartBtn.classList.add('added');

                // Counts (backend truth)
                if (document.getElementById('cart-count')) {
                    document.getElementById('cart-count').textContent = data.cart_count;
                }
                if (document.getElementById('wishlist-count')) {
                    document.getElementById('wishlist-count').textContent = data.wishlist_count;
                }

                // Remove wishlist button if exists
                wishlistBtn?.remove();

            } catch {
                alert('Failed to add to cart');
            }
        });
    }

    /* =========================
       WISHLIST (TOGGLE)
    ========================= */
    if (wishlistBtn) {
        wishlistBtn.addEventListener('click', async () => {
            try {
                const res = await fetch('wishlist_toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${productId}`
                });

                const data = await res.json();
                if (!data.success) throw new Error();

                wishlistBtn.classList.toggle('added', data.active);
                wishlistBtn.innerHTML = `
                    <i class="fas fa-heart"></i>
                    ${data.active ? 'Added to Wishlist' : 'Add to Wishlist'}
                `;

                if (document.getElementById('wishlist-count')) {
                    document.getElementById('wishlist-count').textContent = data.total_items;
                }

            } catch {
                alert('Wishlist action failed');
            }
        });
    }

});
</script>
<script src="..js/main.js"></script>
</body>
</html>
