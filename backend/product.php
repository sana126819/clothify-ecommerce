<?php
session_start();
require 'db.php';

// User session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

// Initialize guest session arrays
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// Fetch main categories
$main_categories = $conn->query("SELECT id, name FROM main_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Selected category/sub-category
$main_category_id = isset($_GET['main_category']) ? intval($_GET['main_category']) : null;
$sub_category_id = isset($_GET['sub_category']) ? intval($_GET['sub_category']) : null;

// Get category name for heading
$current_category_name = "All Products";
$current_sub_category_name = "";

if ($main_category_id) {
    $cat_result = $conn->query("SELECT name FROM main_categories WHERE id = $main_category_id");
    if ($cat_result->num_rows > 0) {
        $current_category_name = $cat_result->fetch_assoc()['name'];
    }
}

if ($sub_category_id) {
    $sub_result = $conn->query("SELECT name FROM sub_categories WHERE id = $sub_category_id");
    if ($sub_result->num_rows > 0) {
        $current_sub_category_name = $sub_result->fetch_assoc()['name'];
    }
}

// Fetch sub-categories for sidebar
$sidebar_subs = [];
if ($main_category_id) {
    $subRes = $conn->prepare("SELECT id, name FROM sub_categories WHERE main_category_id=? ORDER BY name");
    $subRes->bind_param("i", $main_category_id);
    $subRes->execute();
    $sidebar_subs = $subRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $subRes->close();
}

// Build SQL query for products
$where_clauses = [];
$params = [];
$types = "";

if ($sub_category_id) {
    $where_clauses[] = "sub_category_id = ?";
    $params[] = $sub_category_id;
    $types .= "i";
} elseif ($main_category_id) {
    $where_clauses[] = "main_category_id = ?";
    $params[] = $main_category_id;
    $types .= "i";
}

$where_clauses[] = "stock_quantity > 0";

$sql = "SELECT p.*, m.name as main_category_name FROM products p 
        LEFT JOIN main_categories m ON p.main_category_id = m.id";
        
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY p.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user cart and wishlist (same as index.php)
$userCart = $user_id 
    ? array_column($conn->query("SELECT product_id FROM cart WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id') 
    : array_keys($_SESSION['cart']);

$userWishlist = $user_id
    ? array_column($conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC), 'product_id')
    : array_keys($_SESSION['wishlist']);

function e($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// Product card rendering function (SAME AS INDEX.PHP)
function render_product_card($product, $userCart, $userWishlist) {
    $inCart = in_array($product['product_id'], $userCart);
    $inWishlist = in_array($product['product_id'], $userWishlist);
    ?>
    <div class="product-card" data-id="<?= $product['product_id'] ?>">
        <div class="product-image">
            <a href="product_description.php?id=<?= $product['product_id'] ?>">
                <img src="<?= e($product['image_url']) ?>" 
                     alt="<?= e($product['product_name']) ?>" 
                     loading="lazy">
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
                <button class="action-btn cart-btn <?= $inCart ? 'active' : '' ?>" 
                        data-id="<?= $product['product_id'] ?>"
                        title="<?= $inCart ? 'Remove from cart' : 'Add to cart' ?>">
                    <i class="fas fa-shopping-cart"></i>
                </button>
                <button class="btn-buy" onclick="location.href='product_description.php?id=<?= $product['product_id'] ?>'">
                    View Details
                </button>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($current_category_name . ($current_sub_category_name ? " - " . $current_sub_category_name : "")) ?> | Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        /* Category Header */
        .category-header {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: var(--text-color);
            font-weight: 500;
        }
        
        .page-title h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .page-title .subtitle {
            color: var(--secondary-color);
            font-weight: 400;
        }
        
        .page-title p {
            color: var(--text-light);
            font-size: 1rem;
        }
        
        /* Category Navigation */
        .category-navigation {
            background: white;
            padding: 1.5rem 2rem;
            margin: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .tab {
            background: var(--light-color);
            border-radius: 25px;
            transition: var(--transition);
        }
        
        .tab a {
            display: block;
            padding: 0.8rem 1.8rem;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .tab:hover {
            background: rgba(52, 152, 219, 0.1);
        }
        
        .tab.active {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980b9 100%);
        }
        
        .tab.active a {
            color: white;
        }
        
        /* Shop Container */
        .shop-container {
            display: flex;
            gap: 2.5rem;
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }
        
        /* Sidebar */
        .sidebar {
            flex: 0 0 260px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .sidebar-section {
            margin-bottom: 2.5rem;
        }
        
        .sidebar-section:last-child {
            margin-bottom: 0;
        }
        
        .sidebar-section h3 {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .sub-category-list {
            list-style: none;
            padding: 0;
        }
        
        .sub-category-list li {
            margin-bottom: 0.8rem;
            transition: var(--transition);
        }
        
        .sub-category-list li a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--text-color);
            text-decoration: none;
            padding: 0.6rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .sub-category-list li a:hover {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            padding-left: 1.2rem;
        }
        
        .sub-category-list li.active a {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(41, 128, 185, 0.1) 100%);
            color: var(--secondary-color);
            font-weight: 600;
            border-left: 4px solid var(--secondary-color);
        }
        
        .sub-category-list li i {
            font-size: 0.9rem;
            width: 20px;
        }
        
        /* Products Section */
        .products-section {
            flex: 1;
        }
        
        .no-products {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .no-products i {
            font-size: 4rem;
            color: var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .no-products h3 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .no-products p {
            color: var(--text-light);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .page-btn:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980b9 100%);
            color: white;
            border-color: var(--secondary-color);
        }
        
        .page-btn.next {
            width: auto;
            padding: 0 1.5rem;
            gap: 0.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .shop-container {
                flex-direction: column;
                gap: 2rem;
            }
            
            .sidebar {
                position: static;
                width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .category-header,
            .shop-container {
                padding: 0 1.5rem;
            }
            
            .page-title h1 {
                font-size: 2rem;
            }
            
            .category-tabs {
                gap: 0.3rem;
            }
            
            .tab a {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .category-header {
                padding: 0 1rem;
                margin: 1.5rem auto;
            }
            
            .shop-container {
                padding: 0 1rem;
                margin: 2rem auto;
            }
            
            .category-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 0.5rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            @media (max-width: 400px) {
                .products-grid {
                    grid-template-columns: 1fr;
                }
            }
        }
    </style>
</head>
<body>

<!-- HEADER (Same as Home Page) -->
<header>
    <div class="logo">
        <h1><i class="fas fa-tshirt"></i> Clothify</h1>
    </div>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php" class="active"><i class="fas fa-shopping-bag"></i> Shop</a></li>
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

<!-- Category Header -->
<section class="category-header">
    <div class="breadcrumb">
        <a href="index.php">Home</a> &gt;
        <a href="product.php">Shop</a>
        <?php if($main_category_id): ?>
            &gt; <a href="product.php?main_category=<?= $main_category_id ?>"><?= e($current_category_name) ?></a>
        <?php endif; ?>
        <?php if($sub_category_id): ?>
            &gt; <span><?= e($current_sub_category_name) ?></span>
        <?php endif; ?>
    </div>
    
    <div class="page-title">
        <h1><?= e($current_category_name) ?>
            <?php if($current_sub_category_name): ?>
                <span class="subtitle"> / <?= e($current_sub_category_name) ?></span>
            <?php endif; ?>
        </h1>
        <p><?= count($products) ?> products found</p>
    </div>
</section>

<!-- Category Navigation -->
<section class="category-navigation">
    <div class="category-tabs">
        <div class="tab <?= !$main_category_id ? 'active' : '' ?>">
            <a href="product.php">All Products</a>
        </div>
        <?php foreach($main_categories as $category): ?>
            <div class="tab <?= $main_category_id == $category['id'] ? 'active' : '' ?>">
                <a href="product.php?main_category=<?= $category['id'] ?>">
                    <?= e($category['name']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Main Content -->
<div class="shop-container">
    <!-- Sidebar -->
    <?php if($main_category_id && !empty($sidebar_subs)): ?>
    <aside class="sidebar">
        <div class="sidebar-section">
            <h3><i class="fas fa-list"></i> Sub-Categories</h3>
            <ul class="sub-category-list">
                <?php foreach($sidebar_subs as $sub): ?>
                    <li class="<?= $sub_category_id == $sub['id'] ? 'active' : '' ?>">
                        <a href="product.php?main_category=<?= $main_category_id ?>&sub_category=<?= $sub['id'] ?>">
                            <i class="fas fa-chevron-right"></i> <?= e($sub['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a href="product.php?main_category=<?= $main_category_id ?>">
                        <i class="fas fa-bars"></i> View All
                    </a>
                </li>
            </ul>
        </div>
    </aside>
    <?php endif; ?>

    <!-- Products Grid -->
    <main class="products-section">
        <?php if(empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-search"></i>
                <h3>No Products Found</h3>
                <p>Try browsing other categories or check back later for new arrivals.</p>
                <a href="product.php" class="btn-primary">Browse All Products</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach($products as $product): ?>
                    <?php render_product_card($product, $userCart, $userWishlist); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Pagination (if needed) -->
        <?php if(count($products) > 12): ?>
            <div class="pagination">
                <a href="#" class="page-btn active">1</a>
                <a href="#" class="page-btn">2</a>
                <a href="#" class="page-btn">3</a>
                <a href="#" class="page-btn next">Next <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'footer.php'; ?>

<script src="../js/main.js"></script>
</body>
</html>