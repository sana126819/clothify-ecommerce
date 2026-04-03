<?php
session_start();
include 'db.php';

// --------------------
// 1. User info
// --------------------
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

// Initialize guest cart/wishlist
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// --------------------
// 2. Handle AJAX cart/wishlist requests
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }
    
    switch ($action) {
        case 'add_to_cart':
        case 'remove_from_cart':
        case 'toggle_cart':
            $response = handleCartAction($user_id, $product_id, $action);
            break;
            
        case 'add_to_wishlist':
        case 'remove_from_wishlist':
        case 'toggle_wishlist':
            $response = handleWishlistAction($user_id, $product_id, $action);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    echo json_encode($response);
    exit;
}

function handleCartAction($user_id, $product_id, $action) {
    global $conn;
    
    if ($user_id) {
        // Check if product is in cart
        $check_cart = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
        $check_cart->bind_param("ii", $user_id, $product_id);
        $check_cart->execute();
        $in_cart = $check_cart->get_result()->num_rows > 0;
        
        // Check if product is in wishlist
        $check_wish = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
        $check_wish->bind_param("ii", $user_id, $product_id);
        $check_wish->execute();
        $in_wishlist = $check_wish->get_result()->num_rows > 0;
        
        if ($action === 'add_to_cart' || ($action === 'toggle_cart' && !$in_cart)) {
            if (!$in_cart) {
                // Add to cart
                $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                $insert->bind_param("ii", $user_id, $product_id);
                $insert->execute();
                
                // If it was in wishlist, remove it from wishlist
                if ($in_wishlist) {
                    $delete_wish = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                    $delete_wish->bind_param("ii", $user_id, $product_id);
                    $delete_wish->execute();
                }
                
                return [
                    'success' => true, 
                    'message' => 'Added to cart', 
                    'in_cart' => true,
                    'in_wishlist' => false,
                    'cart_count' => getCartCount($user_id),
                    'wishlist_count' => getWishlistCount($user_id)
                ];
            }
        } else {
            // Remove from cart
            $delete = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $delete->bind_param("ii", $user_id, $product_id);
            $delete->execute();
            
            return [
                'success' => true, 
                'message' => 'Removed from cart', 
                'in_cart' => false,
                'in_wishlist' => $in_wishlist,
                'cart_count' => getCartCount($user_id),
                'wishlist_count' => getWishlistCount($user_id)
            ];
        }
    } else {
        // Guest user
        if ($action === 'add_to_cart' || ($action === 'toggle_cart' && !isset($_SESSION['cart'][$product_id]))) {
            $_SESSION['cart'][$product_id] = 1;
            
            // If it was in wishlist, remove from wishlist
            if (isset($_SESSION['wishlist'][$product_id])) {
                unset($_SESSION['wishlist'][$product_id]);
                $in_wishlist = false;
            }
            
            return [
                'success' => true, 
                'message' => 'Added to cart', 
                'in_cart' => true,
                'in_wishlist' => false,
                'cart_count' => count($_SESSION['cart']),
                'wishlist_count' => count($_SESSION['wishlist'])
            ];
        } else {
            unset($_SESSION['cart'][$product_id]);
            return [
                'success' => true, 
                'message' => 'Removed from cart', 
                'in_cart' => false,
                'in_wishlist' => isset($_SESSION['wishlist'][$product_id]),
                'cart_count' => count($_SESSION['cart']),
                'wishlist_count' => count($_SESSION['wishlist'])
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Action failed'];
}

function handleWishlistAction($user_id, $product_id, $action) {
    global $conn;
    
    if ($user_id) {
        // Check if product is in wishlist
        $check_wish = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
        $check_wish->bind_param("ii", $user_id, $product_id);
        $check_wish->execute();
        $in_wishlist = $check_wish->get_result()->num_rows > 0;
        
        // Check if product is in cart
        $check_cart = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
        $check_cart->bind_param("ii", $user_id, $product_id);
        $check_cart->execute();
        $in_cart = $check_cart->get_result()->num_rows > 0;
        
        if ($action === 'add_to_wishlist' || ($action === 'toggle_wishlist' && !$in_wishlist)) {
            if (!$in_wishlist) {
                // Add to wishlist
                $insert = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $insert->bind_param("ii", $user_id, $product_id);
                $insert->execute();
                
                // If it was in cart, remove it from cart
                if ($in_cart) {
                    $delete_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $delete_cart->bind_param("ii", $user_id, $product_id);
                    $delete_cart->execute();
                }
                
                return [
                    'success' => true, 
                    'message' => 'Added to wishlist', 
                    'in_wishlist' => true,
                    'in_cart' => false,
                    'cart_count' => getCartCount($user_id),
                    'wishlist_count' => getWishlistCount($user_id)
                ];
            }
        } else {
            // Remove from wishlist
            $delete = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $delete->bind_param("ii", $user_id, $product_id);
            $delete->execute();
            
            return [
                'success' => true, 
                'message' => 'Removed from wishlist', 
                'in_wishlist' => false,
                'in_cart' => $in_cart,
                'cart_count' => getCartCount($user_id),
                'wishlist_count' => getWishlistCount($user_id)
            ];
        }
    } else {
        // Guest user
        if ($action === 'add_to_wishlist' || ($action === 'toggle_wishlist' && !isset($_SESSION['wishlist'][$product_id]))) {
            $_SESSION['wishlist'][$product_id] = 1;
            
            // If it was in cart, remove from cart
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
                $in_cart = false;
            }
            
            return [
                'success' => true, 
                'message' => 'Added to wishlist', 
                'in_wishlist' => true,
                'in_cart' => false,
                'cart_count' => count($_SESSION['cart']),
                'wishlist_count' => count($_SESSION['wishlist'])
            ];
        } else {
            unset($_SESSION['wishlist'][$product_id]);
            return [
                'success' => true, 
                'message' => 'Removed from wishlist', 
                'in_wishlist' => false,
                'in_cart' => isset($_SESSION['cart'][$product_id]),
                'cart_count' => count($_SESSION['cart']),
                'wishlist_count' => count($_SESSION['wishlist'])
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Action failed'];
}

// Helper functions to get counts
function getCartCount($user_id) {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id");
    return $result->fetch_assoc()['count'];
}

function getWishlistCount($user_id) {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE user_id = $user_id");
    return $result->fetch_assoc()['count'];
}
// --------------------
// 3. Handle search
// --------------------
$search = trim($_GET['q'] ?? '');
$category_filter = $_GET['category'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';
$products = [];

// Build SQL query
$sql = "
SELECT p.*, 
       mc.name AS main_category_name, 
       sc.name AS sub_cat_name,
       mc.id AS main_cat_id,
       sc.id AS sub_cat_id
FROM products p
LEFT JOIN main_categories mc ON p.main_category_id = mc.id
LEFT JOIN sub_categories sc ON p.sub_category_id = sc.id
WHERE p.stock_quantity > 0
";

$params = [];
$types = "";

// Add search conditions if search exists
if ($search !== '') {
    $sql .= " AND (
        p.product_name LIKE CONCAT('%', ?, '%') OR
        p.description LIKE CONCAT('%', ?, '%') OR
        p.color LIKE CONCAT('%', ?, '%') OR
        mc.name LIKE CONCAT('%', ?, '%') OR
        sc.name LIKE CONCAT('%', ?, '%')
    )";
    $types = str_repeat('s', 5);
    $params = [$search, $search, $search, $search, $search];
}

// Add category filter
if ($category_filter && is_numeric($category_filter)) {
    $sql .= " AND p.main_category_id = ?";
    $types .= 'i';
    $params[] = $category_filter;
}

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    default: // 'newest'
        $sql .= " ORDER BY p.created_at DESC";
}

// Prepare statement
$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL prepare error: " . $conn->error);

// Bind params if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

// Execute
if (!$stmt->execute()) die("SQL execute error: " . $stmt->error);

$res = $stmt->get_result();
if ($res) $products = $res->fetch_all(MYSQLI_ASSOC);

// Get product count
$product_count = count($products);

// Get categories for filter dropdown
$categories = $conn->query("SELECT id, name FROM main_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// --------------------
// 4. Fetch user cart/wishlist for current state
// --------------------
$userCart = [];
$userWishlist = [];

if ($user_id) {
    $cartRes = $conn->query("SELECT product_id FROM cart WHERE user_id=$user_id");
    $userCart = array_column($cartRes->fetch_all(MYSQLI_ASSOC), 'product_id');

    $wishRes = $conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id");
    $userWishlist = array_column($wishRes->fetch_all(MYSQLI_ASSOC), 'product_id');
} else {
    $userCart = array_keys($_SESSION['cart']);
    $userWishlist = array_keys($_SESSION['wishlist']);
}

// --------------------
// 5. HYBRID RECOMMENDATIONS FOR SEARCH PAGE
// --------------------
$recommended_products = [];

// Only show recommendations if we have search results
if (!empty($products) && $user_id) {
    // Get all product IDs from current search results
    $search_product_ids = array_column($products, 'product_id');
    
    if (!empty($search_product_ids)) {
        // Prepare placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($search_product_ids), '?'));
        $types = str_repeat('i', count($search_product_ids));
        
        // Get hybrid recommendations for products in search results
        // FIX: Separate 0 from NOT IN clause
        $hybrid_stmt = $conn->prepare("
            SELECT 
                r.recommended_product_id,
                r.model,
                r.score,
                CASE 
                    WHEN r.model = 'collaborative' THEN r.score * 0.7  -- Collaborative gets 70% weight
                    WHEN r.model = 'content' THEN r.score * 0.3        -- Content gets 30% weight
                END as weighted_score
            FROM recommendations r
            WHERE r.product_id IN ($placeholders)
              AND r.recommended_product_id NOT IN ($placeholders)
              AND r.recommended_product_id != 0
              AND r.model IN ('collaborative', 'content')
            ORDER BY weighted_score DESC
            LIMIT 8
        ");
        
        if ($hybrid_stmt) {
            // Bind parameters twice (exclude search results from recommendations)
            $all_params = array_merge($search_product_ids, $search_product_ids);
            $hybrid_stmt->bind_param($types . $types, ...$all_params);
            $hybrid_stmt->execute();
            $hybrid_result = $hybrid_stmt->get_result();
            
            // Combine scores and get unique recommendations
            $scores = [];
            while ($row = $hybrid_result->fetch_assoc()) {
                $product_id = $row['recommended_product_id'];
                
                if (!isset($scores[$product_id])) {
                    $scores[$product_id] = [
                        'collaborative' => 0,
                        'content' => 0,
                        'total' => 0
                    ];
                }
                
                $scores[$product_id][$row['model']] = $row['score'];
                $scores[$product_id]['total'] += $row['weighted_score'];
            }
            
            // Sort by total hybrid score
            uasort($scores, function($a, $b) {
                return $b['total'] <=> $a['total'];
            });
            
            // Get top 6 unique recommendations
            $top_recommendations = array_slice(array_keys($scores), 0, 6, true);
            
            // Fetch full product details for recommendations
            foreach ($top_recommendations as $product_id => $key) {
                $detail_stmt = $conn->prepare("
                    SELECT p.*, m.name as main_category_name 
                    FROM products p 
                    LEFT JOIN main_categories m ON p.main_category_id = m.id 
                    WHERE product_id = ?
                ");
                $detail_stmt->bind_param("i", $product_id);
                $detail_stmt->execute();
                $detail_result = $detail_stmt->get_result();
                $detail = $detail_result->fetch_assoc();
                $detail_stmt->close();
                
                if ($detail) {
                    $detail['recommended'] = true;
                    // FIX: Use null coalescing operator
                    $detail['hybrid_score'] = $scores[$product_id]['total'] ?? 0;
                    $recommended_products[] = $detail;
                }
            }
            
            $hybrid_stmt->close();
        }
    }
}

// If no hybrid recommendations, show popular products
if (empty($recommended_products)) {
    $popular = $conn->query("
        SELECT p.*, m.name as main_category_name,
               (SELECT COUNT(*) FROM user_activity WHERE product_id = p.product_id) as views,
               (SELECT COUNT(*) FROM cart WHERE product_id = p.product_id) as carts
        FROM products p 
        LEFT JOIN main_categories m ON p.main_category_id = m.id 
        WHERE p.stock_quantity > 0
        ORDER BY views DESC, carts DESC
        LIMIT 6
    ");
    
    while ($product = $popular->fetch_assoc()) {
        $product['recommended'] = false;
        $recommended_products[] = $product;
    }
}

// HTML escape helper
function e($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// Product card rendering function - FIXED VERSION
function render_product_card($product, $userCart, $userWishlist, $is_recommended = false) {
    $inCart = in_array($product['product_id'], $userCart);
    $inWishlist = in_array($product['product_id'], $userWishlist);
    
    // FIX: Get hybrid_score safely with null coalescing operator
    $hybrid_score = $product['hybrid_score'] ?? 0;
    ?>
    <div class="product-card" data-id="<?= $product['product_id'] ?>">
        <?php if($is_recommended && $hybrid_score > 0): ?>
        <?php endif; ?>
        
        <div class="product-image">
            <a href="product_description.php?id=<?= $product['product_id'] ?>">
                <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['product_name']) ?>" loading="lazy">
                <?php if(isset($product['main_category_name']) && $product['main_category_name']): ?>
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
                <span class="product-color"><?= e($product['color'] ?? '') ?></span>
                <span class="product-size">Size: <?= e($product['size'] ?? '') ?></span>
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
    <title><?= $search !== '' ? "Search: " . e($search) . " | " : "" ?>Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        /* Search Page Specific Styles */
        .search-header-section {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .search-breadcrumb {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .search-breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .search-breadcrumb a:hover {
            text-decoration: underline;
        }

        .search-header-content {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 2rem;
        }

        .search-header-content h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .search-results-count {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .search-highlight {
            color: var(--accent-color);
            font-weight: 600;
        }

        /* Search Filters */
        .search-filters-section {
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 2rem;
        }

        .filters-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .filter-select {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background: white;
            color: var(--text-color);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            min-width: 160px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn-clear-filters {
            margin-left: auto;
            padding: 0.6rem 1.2rem;
            background: var(--light-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-clear-filters:hover {
            background: #e9ecef;
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }

        /* Search Results */
        .search-results-section {
            max-width: 1200px;
            margin: 0 auto 3rem;
            padding: 0 2rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }

        /* Recommendation Badge */
        .recommendation-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .recommendation-badge i {
            font-size: 0.9rem;
        }

        /* You Might Also Like Section */
        .recommendations-section {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .recommendations-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .recommendations-header h3 {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
        }

        .algorithm-info {
            background: rgba(52, 152, 219, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .algorithm-info i {
            font-size: 1rem;
        }

        /* No Results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .no-results i {
            font-size: 4rem;
            color: var(--border-color);
            margin-bottom: 1.5rem;
        }

        .no-results h3 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .no-results p {
            color: var(--text-light);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Search Tips */
        .search-tips {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow);
        }

        .search-tips h4 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .tip-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .tip-item i {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-top: 0.2rem;
        }

        .tip-text {
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Suggestions */
        .search-suggestions {
            margin-top: 2rem;
        }

        .search-suggestions h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .suggestions-list {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .suggestion-btn {
            padding: 0.8rem 1.5rem;
            background: var(--light-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .suggestion-btn:hover {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .search-header-section,
            .search-filters-section,
            .search-results-section,
            .recommendations-section {
                padding: 0 1.5rem;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .btn-clear-filters {
                margin-left: 0;
                justify-content: center;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .search-header-content h1 {
                font-size: 1.8rem;
            }
            
            .search-filters-section,
            .recommendations-section {
                padding: 0 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 1.5rem;
            }
            
            .recommendations-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
            
            .recommendation-badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
            }
            
            .tips-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .search-header-section,
            .search-filters-section,
            .search-results-section,
            .recommendations-section {
                padding: 0 1rem;
            }
            
            .search-header-content {
                padding: 1.5rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .suggestions-list {
                flex-direction: column;
            }
            
            .suggestion-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 400px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- ============================================
     HEADER SECTION (COMPLETE)
============================================ -->
<header>
    <div class="logo">
        <h1><i class="fas fa-tshirt"></i> Clothify</h1>
    </div>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php" class="active"><i class="fas fa-shopping-bag"></i> Shop</a></li>
            <li><a href="about_us.php"><i class="fas fa-phone"></i> About Us</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <!-- Wishlist icon with count -->
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= count($userWishlist) ?></span>
            </div>
            
            <!-- Cart icon with count -->
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="cart-count"><?= count($userCart) ?></span>
            </div>
            
            <!-- User dropdown for logged-in users -->
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
                <!-- Login/Signup buttons for guests -->
                <div class="auth-buttons">
                    <a href="login.php" class="btn-login">Login</a>
                    <a href="register.php" class="btn-signup">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Search bar -->
        <form class="search-bar" action="search.php" method="GET">
            <input type="text" name="q" placeholder="Search products..." value="<?= e($search) ?>" required>
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</header>

<!-- ============================================
     SEARCH HEADER SECTION
============================================ -->
<section class="search-header-section">
    <div class="search-breadcrumb">
        <a href="index.php">Home</a> &gt;
        <a href="product.php">Shop</a> &gt;
        <span>Search</span>
    </div>
    
    <div class="search-header-content">
        <?php if($search !== ''): ?>
            <h1>Search Results</h1>
            <div class="search-results-count">
                Found <span class="search-highlight"><?= $product_count ?></span> product<?= $product_count != 1 ? 's' : '' ?> for "<span class="search-highlight"><?= e($search) ?></span>"
            </div>
        <?php else: ?>
            <h1>Browse Products</h1>
            <div class="search-results-count">
                Showing <span class="search-highlight"><?= $product_count ?></span> product<?= $product_count != 1 ? 's' : '' ?>
            </div>
        <?php endif; ?>
    </div>
</section>


<!-- ============================================
     SEARCH RESULTS SECTION
============================================ -->
<section class="search-results-section">
    <?php if(empty($products)): ?>
        <div class="no-results">
            <i class="fas fa-search"></i>
            <h3>No Products Found</h3>
            <p>We couldn't find any products matching your search.</p>
            
            <div class="search-suggestions">
                <h4>Try These Instead:</h4>
                <div class="suggestions-list">
                    <a href="product.php" class="suggestion-btn">
                        <i class="fas fa-box"></i> Browse All Products
                    </a>
                    <a href="index.php" class="suggestion-btn">
                        <i class="fas fa-star"></i> View Featured Products
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Search Tips -->
        <?php if($search !== ''): ?>
        <div class="search-tips">
            <h4><i class="fas fa-lightbulb"></i> Search Tips</h4>
            <div class="tips-grid">
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div class="tip-text">Check for typos or try a different spelling</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div class="tip-text">Use more general terms (e.g., "shirt" instead of "blue cotton shirt")</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div class="tip-text">Try searching by color (e.g., "red", "blue", "black")</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div class="tip-text">Browse by category using the filter above</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="products-grid">
            <?php foreach($products as $product): ?>
                <?php render_product_card($product, $userCart, $userWishlist, false); ?>
            <?php endforeach; ?>
        </div>

        <!-- ============================================
             HYBRID RECOMMENDATIONS SECTION
             Shows "You might also like" based on search
        ============================================ -->
        <?php if(!empty($recommended_products)): ?>
        <div class="recommendations-section">
            <div class="recommendations-header">
                <h3>You Might Also Like</h3>
        
            </div>
            <div class="products-grid">
                <?php foreach($recommended_products as $product): ?>
                    <?php render_product_card($product, $userCart, $userWishlist, true); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if(count($products) > 12): ?>
            <div class="pagination">
                <a href="#" class="page-btn active">1</a>
                <a href="#" class="page-btn">2</a>
                <a href="#" class="page-btn">3</a>
                <a href="#" class="page-btn next">Next <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>

<script>
// ============================================
// SEARCH PAGE JAVASCRIPT
// ============================================

// Initialize page with cart/wishlist functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Search page loaded');
    
    // User dropdown
    const userDropdown = document.querySelector('.user-dropdown');
    if (userDropdown) {
        const dropdownToggle = userDropdown.querySelector('.user-info');
        const dropdownMenu = userDropdown.querySelector('.dropdown-menu');
        
        dropdownToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            dropdownMenu.classList.remove('show');
        });
    }
    
    // Hover effect for recommendation badge
    document.querySelectorAll('.recommendation-badge').forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Initialize cart and wishlist buttons
    initializeCartWishlistButtons();
});

// Filter functions
function updateCategoryFilter() {
    const category = document.getElementById('categoryFilter').value;
    const search = '<?= e($search) ?>';
    const sort = '<?= $sort_by ?>';
    
    let url = 'search.php?';
    if (search) url += `q=${encodeURIComponent(search)}&`;
    if (category) url += `category=${category}&`;
    if (sort !== 'newest') url += `sort=${sort}`;
    
    window.location.href = url;
}

function updateSortFilter() {
    const sort = document.getElementById('sortFilter').value;
    const search = '<?= e($search) ?>';
    const category = '<?= $category_filter ?>';
    
    let url = 'search.php?';
    if (search) url += `q=${encodeURIComponent(search)}&`;
    if (category) url += `category=${category}&`;
    if (sort !== 'newest') url += `sort=${sort}`;
    
    window.location.href = url;
}

// Cart and Wishlist functionality
function initializeCartWishlistButtons() {
    // Cart buttons
    document.querySelectorAll('.cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const productId = this.dataset.id;
            const isActive = this.classList.contains('active');
            const action = isActive ? 'remove_from_cart' : 'add_to_cart';
            
            toggleCart(productId, action, this);
        });
    });
    
    // Wishlist buttons
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const productId = this.dataset.id;
            const isActive = this.classList.contains('active');
            const action = isActive ? 'remove_from_wishlist' : 'add_to_wishlist';
            
            toggleWishlist(productId, action, this);
        });
    });
    
    // Quick view buttons
    document.querySelectorAll('.quick-view').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const productId = this.dataset.id;
            showQuickView(productId);
        });
    });
    
    // Product card click (redirect to product page)
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (!e.target.closest('.action-btn') && 
                !e.target.closest('.quick-view') && 
                !e.target.closest('.btn-buy')) {
                const productId = card.dataset.id;
                window.location.href = `product_description.php?id=${productId}`;
            }
        });
    });
}

function toggleCart(productId, action, button) {
    fetch('search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart button state
            button.classList.toggle('active');
            button.title = data.in_cart ? 'Remove from cart' : 'Add to cart';
            
            // Update cart count
            const cartCount = document.getElementById('cart-count');
            cartCount.textContent = data.cart_count;
            cartCount.style.display = data.cart_count > 0 ? 'flex' : 'none';
            
            // Update wishlist count
            const wishlistCount = document.getElementById('wishlist-count');
            wishlistCount.textContent = data.wishlist_count;
            wishlistCount.style.display = data.wishlist_count > 0 ? 'flex' : 'none';
            
            // Find and update the corresponding wishlist button for this product
            const wishlistBtn = document.querySelector(`.wishlist-btn[data-id="${productId}"]`);
            if (wishlistBtn) {
                // If we just added to cart, remove from wishlist
                if (data.in_cart) {
                    wishlistBtn.classList.remove('active');
                    wishlistBtn.title = 'Add to wishlist';
                }
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    });
}

function toggleWishlist(productId, action, button) {
    fetch('search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update wishlist button state
            button.classList.toggle('active');
            button.title = data.in_wishlist ? 'Remove from wishlist' : 'Add to wishlist';
            
            // Update wishlist count
            const wishlistCount = document.getElementById('wishlist-count');
            wishlistCount.textContent = data.wishlist_count;
            wishlistCount.style.display = data.wishlist_count > 0 ? 'flex' : 'none';
            
            // Update cart count
            const cartCount = document.getElementById('cart-count');
            cartCount.textContent = data.cart_count;
            cartCount.style.display = data.cart_count > 0 ? 'flex' : 'none';
            
            // Find and update the corresponding cart button for this product
            const cartBtn = document.querySelector(`.cart-btn[data-id="${productId}"]`);
            if (cartBtn) {
                // If we just added to wishlist, remove from cart
                if (data.in_wishlist) {
                    cartBtn.classList.remove('active');
                    cartBtn.title = 'Add to cart';
                }
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    });
}
function updateCartCount(added) {
    const cartCount = document.getElementById('cart-count');
    let count = parseInt(cartCount.textContent) || 0;
    
    if (added) {
        count++;
    } else {
        count = Math.max(0, count - 1);
    }
    
    cartCount.textContent = count > 0 ? count : '';
    cartCount.style.display = count > 0 ? 'flex' : 'none';
}

function updateWishlistCount(added) {
    const wishlistCount = document.getElementById('wishlist-count');
    let count = parseInt(wishlistCount.textContent) || 0;
    
    if (added) {
        count++;
    } else {
        count = Math.max(0, count - 1);
    }
    
    wishlistCount.textContent = count > 0 ? count : '';
    wishlistCount.style.display = count > 0 ? 'flex' : 'none';
}

function showQuickView(productId) {
    // You can implement a modal or redirect to product page
    window.location.href = `product_description.php?id=${productId}`;
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Add notification styles if not already in CSS
const style = document.createElement('style');
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
    
    .notification span {
        color: #2c3e50;
        font-weight: 500;
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>