<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location:login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? null;

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Build query based on filters
$query = "
    SELECT o.*, p.product_name, p.image_url, p.price as unit_price
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    WHERE o.user_id = ?
";

$params = [$user_id];
$types = "i";

// Apply filters
if ($filter === 'completed') {
    $query .= " AND o.order_status = 'completed'";
} elseif ($filter === 'pending') {
    $query .= " AND o.order_status = 'pending'";
}

if ($status && in_array($status, ['completed', 'pending', 'cancelled', 'shipped'])) {
    $query .= " AND o.order_status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $query .= " AND (p.product_name LIKE ? OR o.order_id = ?)";
    $params[] = "%$search%";
    $params[] = $search;
    $types .= "si";
}

// Add ordering
$query .= " ORDER BY o.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if ($stmt) {
    if ($types === "i") {
        $stmt->bind_param($types, $params[0]);
    } else {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $orders = $stmt->get_result();
    $stmt->close();
} else {
    die("Query preparation failed: " . $conn->error);
}

// Get order statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped,
        SUM(total_price) as total_spent
    FROM orders 
    WHERE user_id = $user_id
")->fetch_assoc();

// Get wishlist and cart counts for header
$wishlist_count = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE user_id = $user_id")->fetch_assoc()['count'];
$cart_count = $conn->query("SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id")->fetch_assoc()['count'];

function formatDate($date) {
    return date('d M Y, h:i A', strtotime($date));
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => ['warning', 'Pending'],
        'completed' => ['success', 'Completed'],
        'cancelled' => ['danger', 'Cancelled'],
        'shipped' => ['info', 'Shipped']
    ];
    
    if (isset($badges[$status])) {
        list($color, $text) = $badges[$status];
        return "<span class='status-badge status-$color'>$text</span>";
    }
    return "<span class='status-badge status-secondary'>$status</span>";
}

function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => ['warning', 'Pending'],
        'paid' => ['success', 'Paid'],
        'failed' => ['danger', 'Failed'],
        'refunded' => ['info', 'Refunded']
    ];
    
    if (isset($badges[$status])) {
        list($color, $text) = $badges[$status];
        return "<span class='status-badge status-$color'>$text</span>";
    }
    return "<span class='status-badge status-secondary'>$status</span>";
}

function e($x){ return htmlspecialchars($x,ENT_QUOTES,'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--text-light);
            font-weight: 500;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 0.5rem 1.5rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-color);
            display: inline-block;
        }

        .filter-btn:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }

        .filter-btn.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 3rem;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .orders-table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .orders-table th {
            background: var(--light-color);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .orders-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
            vertical-align: top;
        }

        .orders-table tr:hover {
            background: var(--light-color);
        }

        .order-id {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }

        .product-details h4 {
            margin: 0 0 0.3rem 0;
            font-size: 1rem;
        }

        .product-details small {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin: 0.2rem 0;
        }

        .status-warning {
            background: #FFF3CD;
            color: #856404;
        }

        .status-success {
            background: #D4EDDA;
            color: #155724;
        }

        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .status-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--border-color);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .page-btn:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }

        .page-btn.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .shipping-address {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 0.3rem;
            max-width: 200px;
        }

        .date-time {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .orders-table-container {
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .orders-container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo">
        <h1><i class="fas fa-tshirt"></i> Clothify</h1>
    </div>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
            <li><a href="contact.php"><i class="fas fa-phone"></i> Contact</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
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

<div class="orders-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-shopping-bag"></i> My Orders</h1>
        <p>View and manage all your orders</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3><?= $stats['total'] ?? 0 ?></h3>
            <p>Total Orders</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['completed'] ?? 0 ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['pending'] ?? 0 ?></h3>
            <p>Pending</p>
        </div>
        <div class="stat-card">
            <h3>Rs <?= number_format($stats['total_spent'] ?? 0, 2) ?></h3>
            <p>Total Spent</p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" action="user_orders.php" class="filter-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by order ID or product name..." 
                       value="<?= e($search) ?>">
            </div>
            
            <div>
                <select name="status" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 5px; border: 1px solid var(--border-color);">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="shipped" <?= $status == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="user_orders.php" class="btn btn-secondary">Clear</a>
        </form>
        
        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="user_orders.php" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">All Orders</a>
            <a href="user_orders.php?filter=completed" class="filter-btn <?= $filter == 'completed' ? 'active' : '' ?>">Completed</a>
            <a href="user_orders.php?filter=pending" class="filter-btn <?= $filter == 'pending' ? 'active' : '' ?>">Pending</a>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="orders-table-container">
        <?php if($orders->num_rows > 0): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Date</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="order-id">#<?= $order['order_id'] ?></div>
                                <div class="date-time"><?= formatDate($order['created_at']) ?></div>
                            </td>
                            <td>
                                <div class="product-info">
                                    <img src="<?= htmlspecialchars($order['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($order['product_name']) ?>" 
                                         class="product-image">
                                    <div class="product-details">
                                        <h4><?= htmlspecialchars($order['product_name']) ?></h4>
                                        <small>Unit: Rs <?= number_format($order['unit_price'], 2) ?></small>
                                        <?php if($order['shipping_address']): ?>
                                            <div class="shipping-address">
                                                <i class="fas fa-map-marker-alt"></i> <?= e($order['shipping_address']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="date-time"><?= formatDate($order['created_at']) ?></div>
                            </td>
                            <td><?= $order['quantity'] ?></td>
                            <td>
                                <strong>Rs <?= number_format($order['total_price'], 2) ?></strong>
                                <div class="date-time"><?= $order['payment_method'] ?> Payment</div>
                            </td>
                            <td><?= getOrderStatusBadge($order['order_status']) ?></td>
                            <td><?= getPaymentStatusBadge($order['payment_status']) ?></td>
                        
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Orders Found</h3>
                <p><?= $search || $status ? 'Try adjusting your filters or search term.' : 'You haven\'t placed any orders yet.' ?></p>
                <?php if(!$search && !$status): ?>
                    <a href="product.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                <?php else: ?>
                    <a href="user_orders.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-redo"></i> View All Orders
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div style="margin-top: 2rem; text-align: center;">
        <a href="user_profile.php" class="btn btn-secondary">
            <i class="fas fa-user"></i> Back to Profile
        </a>
        <a href="product.php" class="btn btn-primary">
            <i class="fas fa-shopping-bag"></i> Continue Shopping
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Auto-submit search on clear
document.addEventListener('DOMContentLoaded', function() {
    // Highlight current filter
    const urlParams = new URLSearchParams(window.location.search);
    const currentFilter = urlParams.get('filter') || 'all';
    
    // Toggle order details
    document.querySelectorAll('.order-id').forEach(orderId => {
        orderId.addEventListener('click', function() {
            const row = this.closest('tr');
            const detailsRow = row.nextElementSibling;
            
            if (detailsRow && detailsRow.classList.contains('order-details')) {
                detailsRow.remove();
            } else {
                const detailsRow = document.createElement('tr');
                detailsRow.className = 'order-details';
                detailsRow.innerHTML = `
                    <td colspan="8" style="background: #f8f9fa; padding: 1rem;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div>
                                <h5>Shipping Address</h5>
                                <p>${this.closest('tr').querySelector('.shipping-address')?.textContent || 'Not specified'}</p>
                            </div>
                            <div>
                                <h5>Payment Method</h5>
                                <p>${this.closest('tr').querySelector('.date-time:nth-child(2)')?.textContent || 'Not specified'}</p>
                            </div>
                        </div>
                    </td>
                `;
                row.parentNode.insertBefore(detailsRow, row.nextSibling);
            }
        });
    });
    
    // Confirmation for cancel order
    document.querySelectorAll('a[href*="cancel_order"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to cancel this order?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
<script src="../js/main.js"></script>
</body>
</html>