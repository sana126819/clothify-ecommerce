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
$_SESSION['cart'] ??= [];
$_SESSION['wishlist'] ??= [];

// ----------------------------
// HEADER COUNTS
// ----------------------------
if ($user_id) {
    // Wishlist count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($wishlist_count);
    $stmt->fetch();
    $stmt->close();

    // Cart count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($cart_count);
    $stmt->fetch();
    $stmt->close();

    // Fetch wishlist product IDs for quick use
    $stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userWishlist = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'product_id');
    $stmt->close();

} else {
    $wishlist_count = count($_SESSION['wishlist']);
    $cart_count = count($_SESSION['cart']);
    $userWishlist = $_SESSION['wishlist'];
}

// ----------------------------
// FETCH CART ITEMS
// ----------------------------
$cart = [];
$total = 0;

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT p.product_id, p.product_name, p.price, p.image_url, p.stock_quantity, c.quantity, m.name AS main_category_name
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        LEFT JOIN main_categories m ON p.main_category_id = m.id
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $guest_ids = array_keys($_SESSION['cart']);
    if (!empty($guest_ids)) {
        $ids = implode(',', array_map('intval', $guest_ids));
        $res = $conn->query("
            SELECT p.product_id, p.product_name, p.price, p.image_url, p.stock_quantity, m.name AS main_category_name
            FROM products p
            LEFT JOIN main_categories m ON p.main_category_id = m.id
            WHERE p.product_id IN ($ids)
        ");
        $products = $res->fetch_all(MYSQLI_ASSOC);
        foreach ($products as $p) {
            $p['quantity'] = $_SESSION['cart'][$p['product_id']];
            $cart[] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clothify - Cart</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/home.css">
<link rel="stylesheet" href="../css/cart.css">
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
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= $wishlist_count ?></span>
            </div>
            <div class="icon-wrapper active" onclick="location.href='cart.php'" title="Cart">
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

<main class="main-content" style="max-width:1000px; margin:50px auto;">
    <h1>Shopping Cart</h1>

    <?php if(empty($cart)): ?>
        <p style="text-align:center; margin-top:20px;">Your cart is empty.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Price (Rs)</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cart as $item): 
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                <tr data-id="<?= $item['product_id'] ?>">
                    <td><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>" style="width:70px;height:70px;object-fit:cover;border-radius:6px;"></td>
                    <td><?= e($item['product_name']) ?></td>
                    <td><?= number_format($item['price'],2) ?></td>
                    <td>
                        <div class="qty-controls">
                            <form action="update_cart.php" method="post" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit">−</button>
                            </form>
                            <span><?= $item['quantity'] ?></span>
                            <form action="update_cart.php" method="post" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" <?= $item['quantity'] >= $item['stock_quantity'] ? 'disabled' : '' ?>>+</button>
                            </form>
                        </div>
                    </td>
                    <td><?= number_format($subtotal,2) ?></td>
                    <td>
                        <a href="remove_cart.php?product_id=<?= $item['product_id'] ?>" onclick="return confirm('Remove this item?');" class="remove-btn">Remove</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;"><strong>Total:</strong></td>
                    <td colspan="2"><strong>Rs <?= number_format($total,2) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="buttons-container" style="margin-top:20px;">
            <?php if($user_id): ?>
                <form action="../payment/checkout1.php" method="post" style="display:inline-block;">
                    <button type="submit" class="buy-btn">Place Order</button>
                </form>
            <?php else: ?>
                <a href="login.php" class="buy-btn">Login to Checkout</a>
            <?php endif; ?>
            <a href="index.php" class="buy-btn">Continue Shopping</a>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
<script src="../js/main.js"></script>
</body>
</html>
