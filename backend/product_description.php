<?php
session_start();
require 'db.php';

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

// Fetch reviews for this product
$reviews = [];
$review_stmt = $conn->prepare("
    SELECT r.*, u.username 
    FROM reviews r 
    LEFT JOIN users u ON r.user_id = u.user_id 
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$review_stmt->bind_param("i", $product_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();
while ($review = $review_result->fetch_assoc()) {
    $reviews[] = $review;
}
$review_stmt->close();

// Calculate average rating
$avg_rating_stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating 
    FROM reviews 
    WHERE product_id = ? AND status = 'approved'
");
$avg_rating_stmt->bind_param("i", $product_id);
$avg_rating_stmt->execute();
$avg_rating_result = $avg_rating_stmt->get_result()->fetch_assoc();
$average_rating = $avg_rating_result['avg_rating'] ?? 0;
$avg_rating_stmt->close();

// Check if user has already reviewed this product
$user_review = null;
if ($user_id) {
    $user_review_stmt = $conn->prepare("SELECT * FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
    $user_review_stmt->bind_param("ii", $product_id, $user_id);
    $user_review_stmt->execute();
    $user_review_result = $user_review_stmt->get_result();
    $user_review = $user_review_result->fetch_assoc();
    $user_review_stmt->close();
}

// Log user activity
if ($user_id) {
    $log = $conn->prepare("INSERT INTO user_activity (user_id, product_id, action) VALUES (?, ?, 'view')");
    $log->bind_param("ii", $user_id, $product_id);
    $log->execute();
    $log->close();
}

// Cart & Wishlist
if ($user_id) {
    $userCart = array_column($conn->query("SELECT product_id FROM cart WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id');
    $userWishlist = array_column($conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id');
} else {
    $userCart = array_keys($_SESSION['cart']);
    $userWishlist = $_SESSION['wishlist'] ?? [];
}

// Get similar products
// Get TF-IDF recommendations (NEW - uses your algorithm)
$similar_products = [];
$similar_stmt = $conn->prepare("
    SELECT p.*, m.name as main_category_name 
    FROM recommendations r
    JOIN products p ON p.product_id = r.recommended_product_id
    LEFT JOIN main_categories m ON p.main_category_id = m.id
    WHERE r.product_id = ?
      AND r.model = 'content'
    ORDER BY r.score DESC
    LIMIT 4
");
$similar_stmt->bind_param("i", $product_id);
$similar_stmt->execute();
$similar_result = $similar_stmt->get_result();
while ($row = $similar_result->fetch_assoc()) {
    $similar_products[] = $row;
}
$similar_stmt->close();

function e($str) { 
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); 
}

function renderStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star"></i>';
    }
    return $stars;
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
    <style>
        /* PRODUCT DESCRIPTION PAGE CSS */
        :root {
            --border-radius: 8px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .breadcrumb {
            padding: 1rem 2rem;
            background: #f8f9fa;
            font-size: 0.9rem;
            color: #666;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            margin: 0 5px;
        }
        
        .product-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        
        @media (max-width: 768px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
        
        /* Product Images */
        .product-images {
            position: sticky;
            top: 2rem;
        }
        
        .main-image {
            width: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .main-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        /* Product Info */
        .product-info h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 1.8rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 1.5rem;
        }
        
        .product-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            color: #666;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stars {
            color: #ffd700;
        }
        
        .rating-count {
            color: #666;
            font-size: 0.9rem;
        }
        
        .product-description {
            margin: 2rem 0;
            padding: 1rem 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Size & Color */
        .size-color {
            margin: 1.5rem 0;
        }
        
        .options {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .size-option, .color-option {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .size-option:hover, .color-option:hover {
            border-color: #3498db;
        }
        
        .size-option.selected {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .btn-add-cart, .btn-wishlist {
            flex: 1;
            padding: 0.8rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-add-cart {
            background: #3498db;
            color: white;
        }
        
        .btn-add-cart:hover {
            background: #2980b9;
        }
        
        .btn-wishlist {
            background: #f8f9fa;
            color: #2c3e50;
            border: 2px solid #ddd;
        }
        
        .btn-wishlist:hover, .btn-wishlist.active {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }
        
        /* Reviews Section */
        .reviews-section {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 1rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
        }
        
        /* Add Review Form */
        .review-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        
        .review-form h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .rating-input {
            margin: 1rem 0;
        }
        
        .star-rating {
            display: flex;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }
        
        .star-rating i {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star-rating i:hover,
        .star-rating i.active {
            color: #ffd700;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-submit-review {
            background: #27ae60;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-submit-review:hover {
            background: #229954;
        }
        
        /* Reviews List */
        .reviews-list {
            margin-top: 2rem;
        }
        
        .review-item {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border-left: 4px solid #3498db;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .review-author {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .review-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .review-rating {
            color: #ffd700;
            margin: 0.5rem 0;
        }
        
        .review-content {
            color: #333;
            line-height: 1.6;
        }
        
        .no-reviews {
            text-align: center;
            padding: 2rem;
            color: #666;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }
        
        /* Similar Products */
        .similar-products {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 1rem;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-card-content {
            padding: 1rem;
        }
        
        .product-card-title {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .product-card-price {
            font-weight: bold;
            color: #e74c3c;
        }
        
        /* Badge for already reviewed */
        .already-reviewed {
            background: #27ae60;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .review-header {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .product-container {
                padding: 0 0.5rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-info h1 {
                font-size: 1.5rem;
            }
        }
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
            <li><a href="about_us.php"><i class="fas fa-phone"></i> About Us</a></li>
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
                        <span><?= e($user_name) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
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

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="index.php">Home</a> <span>&gt;</span>
    <a href="product.php">Shop</a> <span>&gt;</span>
    <span><?= e($product['product_name']) ?></span>
</div>

<!-- PRODUCT DETAIL -->
<div class="product-container">
    <div class="product-images">
        <div class="main-image">
            <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['product_name']) ?>">
        </div>
    </div>
    
    <div class="product-info">
        <h1><?= e($product['product_name']) ?></h1>
        
        <div class="rating">
            <div class="stars">
                <?= renderStars($average_rating) ?>
            </div>
            <span class="rating-count">(<?= count($reviews) ?> reviews)</span>
            <span class="product-price">Rs <?= number_format($product['price'], 2) ?></span>
        </div>
        
        <div class="product-meta">
            <div><i class="fas fa-tag"></i> <?= e($product['main_category_name'] ?? 'Category') ?></div>
            <div><i class="fas fa-palette"></i> Color: <?= e($product['color']) ?></div>
            <div><i class="fas fa-box"></i> In Stock</div>
        </div>
        
        <div class="product-description">
            <p><?= nl2br(e($product['description'] ?? 'No description available.')) ?></p>
        </div>
        
        <div class="size-color">
            <div class="size-section">
                <strong>Size:</strong>
                <div class="options">
                    <?php 
                    $sizes = !empty($product['sizes']) ? explode(',', $product['sizes']) : ['S', 'M', 'L', 'XL'];
                    foreach ($sizes as $size): 
                    ?>
                        <div class="size-option <?= ($size === ($product['default_size'] ?? 'M')) ? 'selected' : '' ?>">
                            <?= trim($size) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!-- Action Buttons - FIXED -->
<div class="action-buttons">
    <button class="btn-add-cart cart-btn <?= in_array($product_id, $userCart) ? 'active' : '' ?>" 
            data-id="<?= $product_id ?>"
            title="<?= in_array($product_id, $userCart) ? 'Remove from cart' : 'Add to cart' ?>">
        <i class="fas fa-shopping-cart"></i>
        <?= in_array($product_id, $userCart) ? 'Remove from Cart' : 'Add to Cart' ?>
    </button>
    
    <button class="btn-wishlist wishlist-btn <?= in_array($product_id, $userWishlist) ? 'active' : '' ?>" 
            data-id="<?= $product_id ?>"
            title="<?= in_array($product_id, $userWishlist) ? 'Remove from wishlist' : 'Add to wishlist' ?>">
        <i class="fas fa-heart"></i>
        <?= in_array($product_id, $userWishlist) ? 'In Wishlist' : 'Add to Wishlist' ?>
    </button>
</div>
       
    </div>
</div>

<!-- REVIEWS SECTION -->
<div class="reviews-section">
    <h2 class="section-title">Customer Reviews</h2>
    
    <!-- Add Review Form (Only for logged-in users who haven't reviewed) -->
    <?php if($user_id && !$user_review): ?>
    <div class="review-form" id="reviewForm">
        <h3>Write a Review</h3>
        <form method="POST" action="submit_review.php">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            
            <div class="rating-input">
                <label>Rating:</label>
                <div class="star-rating">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star" data-rating="<?= $i ?>"></i>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingValue" required>
            </div>
            
            <div class="form-group">
                <label for="reviewText">Your Review:</label>
                <textarea id="reviewText" name="review" class="form-control" 
                          placeholder="Share your experience with this product..." 
                          maxlength="500" required></textarea>
                <small style="color: #666;">Max 500 characters</small>
            </div>
            
            <button type="submit" class="btn-submit-review">Submit Review</button>
        </form>
    </div>
    <?php elseif($user_id && $user_review): ?>
        <p style="color: #27ae60; padding: 1rem; background: #f8f9fa; border-radius: var(--border-radius);">
            <i class="fas fa-check-circle"></i> You have already reviewed this product.
        </p>
    <?php else: ?>
        <p style="color: #666; padding: 1rem; background: #f8f9fa; border-radius: var(--border-radius);">
            <i class="fas fa-info-circle"></i> Please <a href="login.php">login</a> to write a review.
        </p>
    <?php endif; ?>
    
    <!-- Reviews List -->
    <div class="reviews-list">
        <?php if(!empty($reviews)): ?>
            <?php foreach($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <span class="review-author"><?= e($review['username'] ?? 'Anonymous') ?></span>
                        <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                    </div>
                    <div class="review-rating">
                        <?= renderStars($review['rating']) ?>
                    </div>
                    <div class="review-content">
                        <?= nl2br(e($review['review'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-reviews">
                <i class="far fa-comment-alt" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <p>No reviews yet. Be the first to review this product!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIMILAR PRODUCTS -->
<?php if(!empty($similar_products)): ?>
<div class="similar-products">
    <h2 class="section-title">Similar Products</h2>
    <div class="products-grid">
        <?php foreach($similar_products as $similar): ?>
            <div class="product-card" onclick="location.href='product_description.php?product_id=<?= $similar['product_id'] ?>'">
                <img src="<?= e($similar['image_url']) ?>" alt="<?= e($similar['product_name']) ?>">
                <div class="product-card-content">
                    <div class="product-card-title"><?= e($similar['product_name']) ?></div>
                    <div class="product-card-price">Rs <?= number_format($similar['price'], 2) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- FOOTER -->
<?php include 'footer.php'; ?>
<script src="../js/main.js"></script>
</body>
</html>