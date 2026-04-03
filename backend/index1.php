<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// Get featured products with main category names
$featured = $conn->query("
    SELECT p.*, m.name as main_category_name 
    FROM products p 
    LEFT JOIN main_categories m ON p.main_category_id = m.id 
    ORDER BY p.created_at DESC 
    LIMIT 8
");

// Get newest arrivals
$products = $conn->query("
    SELECT p.*, m.name as main_category_name 
    FROM products p 
    LEFT JOIN main_categories m ON p.main_category_id = m.id 
    ORDER BY p.created_at DESC 
    LIMIT 6
");

// Get user cart and wishlist
$userCart = $user_id 
    ? array_column($conn->query("SELECT product_id FROM cart WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC),'product_id') 
    : array_keys($_SESSION['cart']);
    
// Initialize session wishlist for guests
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

$userWishlist = $user_id
    ? array_column($conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC),'product_id')
    : array_keys($_SESSION['wishlist']);

// Collaborative Filtering Recommendations
$collaborative_recommended = [];
if ($user_id) {
    // Get user's interacted products (from cart, wishlist)
    $interacted_products = [];
    
    // Get from cart
    $cart_result = $conn->query("SELECT product_id FROM cart WHERE user_id=$user_id");
    while($row = $cart_result->fetch_assoc()) {
        $interacted_products[] = $row['product_id'];
    }
    
    // Get from wishlist
    $wishlist_result = $conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id");
    while($row = $wishlist_result->fetch_assoc()) {
        $interacted_products[] = $row['product_id'];
    }
    
    // Remove duplicates
    $interacted_products = array_unique($interacted_products);
    
    if (!empty($interacted_products)) {
        $product_ids_str = implode(',', $interacted_products);
        
        $rec_query = "
           SELECT r.recommended_product_id, r.score, p.*, m.name as main_category_name
FROM recommendations r
JOIN products p ON r.recommended_product_id = p.product_id
LEFT JOIN main_categories m ON p.main_category_id = m.id
WHERE r.product_id IN ($product_ids_str)
AND r.model = 'collaborative'
ORDER BY r.score DESC
LIMIT 6

        ";
        
        $rec_result = $conn->query($rec_query);
        if ($rec_result) {
            while($row = $rec_result->fetch_assoc()) {
                $collaborative_recommended[] = $row;
            }
        }
        
        // If no collaborative recommendations, show popular products
        if (empty($collaborative_recommended)) {
            $popular_query = "
                SELECT p.*, m.name as main_category_name
                FROM products p
                LEFT JOIN main_categories m ON p.main_category_id = m.id
                WHERE p.product_id NOT IN ($product_ids_str)
                ORDER BY RAND()
                LIMIT 6
            ";
            
            $popular_result = $conn->query($popular_query);
            while($row = $popular_result->fetch_assoc()) {
                $collaborative_recommended[] = $row;
            }
        }
    }
}

function e($x){ return htmlspecialchars($x,ENT_QUOTES,'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo">
        <h1><i class="fas fa-tshirt"></i> Clothify</h1>
    </div>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
            <li><a href="about_us.php"><i class="fas fa-phone"></i>About Us</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= count($userWishlist) ?></span>
            </div>
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="cart-count"><?= count($userCart) ?></span>
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

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-content">
        <h1>Discover Your Style</h1>
        <p>Premium fashion for every occasion. Quality meets comfort in our latest collection.</p>
        <div class="hero-buttons">
            <button class="btn-primary" onclick="location.href='product.php'">Shop Now</button>
            <button class="btn-secondary" onclick="location.href='#new-arrivals'">New Arrivals</button>
        </div>
    </div>
    <div class="hero-stats">
        <div class="stat-item">
            <h3>100+</h3>
            <p>Happy Customers</p>
        </div>
        <div class="stat-item">
            <h3>50+</h3>
            <p>Products</p>
        </div>
        <div class="stat-item">
            <h3>24/7</h3>
            <p>Support</p>
        </div>
    </div>
</section>

<!-- CATEGORY SECTION -->
<section class="category-section">
    <h2 class="section-title">Shop By Category</h2>
    <div class="category-grid">
        <div class="category-card" onclick="location.href='product.php?main_category=1'">
            <div class="category-icon">
                <i class="fas fa-male"></i>
            </div>
            <h3>Men</h3>
            <p>T-Shirts, Shirts, Jeans & More</p>
        </div>
        <div class="category-card" onclick="location.href='product.php?main_category=2'">
            <div class="category-icon">
                <i class="fas fa-female"></i>
            </div>
            <h3>Women</h3>
            <p>Dresses, Tops, Skirts & More</p>
        </div>
        <div class="category-card" onclick="location.href='product.php?main_category=3'">
            <div class="category-icon">
                <i class="fas fa-child"></i>
            </div>
            <h3>Kids</h3>
            <p>Comfortable & Stylish Wear</p>
        </div>
        <div class="category-card" onclick="location.href='product.php?main_category=4'">
            <div class="category-icon">
                <i class="fas fa-tshirt"></i>
            </div>
            <h3>Accessories</h3>
            <p>Hats, Scarves & More</p>
        </div>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section class="featured-section">
    <div class="section-header">
        <h2>Featured Products</h2>
        <a href="product.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="products-grid">
        <?php if($featured && $featured->num_rows > 0): ?>
            <?php while($product = $featured->fetch_assoc()): ?>
                <?php render_product_card($product, $userCart, $userWishlist); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-products">No featured products available.</p>
        <?php endif; ?>
    </div>
</section>

<!-- PROMO BANNER -->
<section class="promo-banner">
    <div class="promo-content">
        <h2>Shop Now</h2>
        <p>Click to See All Products!</p>
        <button class="btn-promo" onclick="location.href='product.php?filter=sale'">
            Shop Now <i class="fas fa-arrow-right"></i>
        </button>
    </div>
</section>

<!-- NEW ARRIVALS -->
<section class="new-arrivals" id="new-arrivals">
    <div class="section-header">
        <h2>New Arrivals</h2>
        <a href="product.php?sort=newest" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="products-grid">
        <?php if($products && $products->num_rows > 0): ?>
            <?php while($product = $products->fetch_assoc()): ?>
                <?php render_product_card($product, $userCart, $userWishlist); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-products">No new arrivals available.</p>
        <?php endif; ?>
    </div>
</section>

<!-- PERSONALIZED RECOMMENDATIONS -->
<?php if($user_id && !empty($collaborative_recommended)): ?>
<section class="recommended-section">
    <div class="section-header">
        <h2>Recommended For You</h2>
        <p>Based on your preferences</p>
    </div>
    <div class="products-grid">
        <?php foreach($collaborative_recommended as $product): ?>
            <div class="product-card" data-id="<?= $product['product_id'] ?>">
                <div class="product-image">
                    <a href="product_description1.php?id=<?= $product['product_id'] ?>">
                        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['product_name']) ?>" loading="lazy">
                        <?php if($product['main_category_name']): ?>
                            <span class="product-category"><?= e($product['main_category_name']) ?></span>
                        <?php endif; ?>
                    </a>
                    <button class="quick-view" data-id="<?= $product['product_id'] ?>">
                        <i class="fas fa-eye"></i> Quick View
                    </button>
                </div>
                <div class="product-info">
                    <h3 class="product-title"><?= e($product['product_name']) ?></h3>
                    <p class="product-price">Rs <?= number_format($product['price'], 2) ?></p>
                    <div class="product-meta">
                        <span class="product-color"><?= e($product['color']) ?></span>
                        <span class="product-size">Size: <?= e($product['size']) ?></span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn wishlist-btn <?= in_array($product['product_id'], $userWishlist) ? 'active' : '' ?>" 
                                data-id="<?= $product['product_id'] ?>"
                                title="<?= in_array($product['product_id'], $userWishlist) ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                        <button class="action-btn cart-btn <?= in_array($product['product_id'], $userCart) ? 'in-cart' : '' ?>" 
                                data-id="<?= $product['product_id'] ?>"
                                title="<?= in_array($product['product_id'], $userCart) ? 'View in cart' : 'Add to cart' ?>">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                        <button class="btn-buy" onclick="location.href='product_description1.php?id=<?= $product['product_id'] ?>'">
                            View Details
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- FEATURES -->
<section class="features">
    <div class="feature">
        <i class="fas fa-shipping-fast"></i>
        <h3>Free Shipping</h3>
        <p>On orders over Rs 3000</p>
    </div>
    <div class="feature">
        <i class="fas fa-exchange-alt"></i>
        <h3>Easy Returns</h3>
        <p>30-day return policy</p>
    </div>
    <div class="feature">
        <i class="fas fa-shield-alt"></i>
        <h3>Secure Payment</h3>
        <p>100% secure checkout</p>
    </div>
    <div class="feature">
        <i class="fas fa-headset"></i>
        <h3>24/7 Support</h3>
        <p>Customer support</p>
    </div>
</section>

<!-- NEWSLETTER -->
<section class="newsletter">
    <div class="newsletter-content">
        <h2>Stay Updated</h2>
        <p>Subscribe to get 10% off your first order and updates on new arrivals</p>
        <form class="newsletter-form" id="newsletterForm">
            <div class="input-group">
                <input type="email" placeholder="Your email address" required>
                <button type="submit" class="btn-subscribe">
                    <i class="fas fa-paper-plane"></i> Subscribe
                </button>
            </div>
            <p class="privacy-note">By subscribing, you agree to our Privacy Policy</p>
        </form>
    </div>
</section>

<?php include 'footer.php'; ?>

<!-- Product Card Rendering Function -->
<?php
function render_product_card($product, $userCart, $userWishlist) {
    $inCart = in_array($product['product_id'], $userCart);
    $inWishlist = in_array($product['product_id'], $userWishlist);
    ?>
    <div class="product-card" data-id="<?= $product['product_id'] ?>">
        <div class="product-image">
            <a href="product_description1.php?id=<?= $product['product_id'] ?>">
                <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['product_name']) ?>" loading="lazy">
                <?php if($product['main_category_name']): ?>
                    <span class="product-category"><?= e($product['main_category_name']) ?></span>
                <?php endif; ?>
            </a>
            <button class="quick-view" data-id="<?= $product['product_id'] ?>">
                <i class="fas fa-eye"></i> Quick View
            </button>
        </div>
        <div class="product-info">
            <h3 class="product-title"><?= e($product['product_name']) ?></h3>
            <p class="product-price">Rs <?= number_format($product['price'], 2) ?></p>
            <div class="product-meta">
                <span class="product-color"><?= e($product['color']) ?></span>
                <span class="product-size">Size: <?= e($product['size']) ?></span>
            </div>
            <div class="product-actions">
                <button class="action-btn wishlist-btn <?= $inWishlist ? 'active' : '' ?>" 
                        data-id="<?= $product['product_id'] ?>"
                        title="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                    <i class="fas fa-heart"></i>
                </button>
                <button class="action-btn cart-btn <?= $inCart ? 'in-cart' : '' ?>" 
                        data-id="<?= $product['product_id'] ?>"
                        title="<?= $inCart ? 'View in cart' : 'Add to cart' ?>">
                    <i class="fas fa-shopping-cart"></i>
                </button>
                <button class="btn-buy" onclick="location.href='product_description1.php?id=<?= $product['product_id'] ?>'">
                    View Details
                </button>
            </div>
        </div>
    </div>
    <?php
}

?>
<script src="../js/main.js"></script>


</body>
</html>