<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $review_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'reject', 'delete'])) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->bind_param("i", $review_id);
        } else {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $review_id);
        }
        
        if ($stmt->execute()) {
            $message = "Review $action" . ($action === 'delete' ? 'd' : 'ed') . " successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get filter values
$status_filter = $_GET['status'] ?? 'pending';
$search_term = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT r.*, u.username, p.product_name, p.image_url
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN products p ON r.product_id = p.product_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    $query .= " AND (u.username LIKE ? OR p.product_name LIKE ? OR r.review LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= str_repeat("s", 3);
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count statistics
$count_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM reviews
");
$count_stmt->execute();
$stats = $count_stmt->get_result()->fetch_assoc();
$count_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }
        
        .sidebar-menu {
            padding: 0;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover {
            background: #34495e;
        }
        
        .sidebar-menu a.active {
            background: #34495e;
            border-left-color: #3498db;
        }
        
        .sidebar-menu i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #2c3e50;
        }
        
        /* Statistics */
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-box h3 {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .total { color: #3498db; }
        .pending { color: #f39c12; }
        .approved { color: #27ae60; }
        .rejected { color: #e74c3c; }
        
        /* Filters */
        .filters {
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters select, 
        .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .filters select {
            min-width: 150px;
        }
        
        .filters input {
            flex: 1;
            min-width: 200px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        /* Messages */
        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #2c3e50;
            color: white;
        }
        
        th {
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        /* Review content */
        .review-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Status badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Action buttons */
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-warning {
            background: #95a5a6;
            color: white;
        }
        
        .btn-success:hover { background: #229954; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning:hover { background: #7f8c8d; }
        
        /* Empty state */
        .empty {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .empty i {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #ddd;
        }
        
        /* Product image */
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats {
                flex-wrap: wrap;
            }
            
            .stat-box {
                flex: 1 1 calc(50% - 15px);
                min-width: 150px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filters select,
            .filters input,
            .filters .btn {
                width: 100%;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-tshirt"></i> Clothify Admin</h2>
        </div>
        <div class="sidebar-menu">
            <a href="overview.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="add_product.php"><i class="fas fa-plus"></i> Add Product</a>
            <a href="view_products.php"><i class="fas fa-box"></i> Products</a>
            <a href="admin_review.php" class="active"><i class="fas fa-comments"></i> Reviews</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-comments"></i> Customer Reviews</h1>
        </div>
        
        <!-- Messages -->
        <?php if(isset($message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-box">
                <h3>Total Reviews</h3>
                <div class="stat-number total"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending</h3>
                <div class="stat-number pending"><?= $stats['pending'] ?></div>
            </div>
            <div class="stat-box">
                <h3>Approved</h3>
                <div class="stat-number approved"><?= $stats['approved'] ?></div>
            </div>
            <div class="stat-box">
                <h3>Rejected</h3>
                <div class="stat-number rejected"><?= $stats['rejected'] ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <select id="statusFilter" onchange="applyFilters()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Reviews</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            
            <input type="text" id="searchInput" placeholder="Search by user, product, or review..." 
                   value="<?= htmlspecialchars($search_term) ?>">
            
            <button class="btn btn-primary" onclick="applyFilters()">
                <i class="fas fa-search"></i> Search
            </button>
            
            <button class="btn btn-secondary" onclick="window.location.href='admin_review.php'">
                <i class="fas fa-sync-alt"></i> Reset
            </button>
        </div>
        
        <!-- Reviews Table -->
        <div class="table-container">
            <?php if(empty($reviews)): ?>
                <div class="empty">
                    <i class="far fa-comments"></i>
                    <h3>No reviews found</h3>
                    <p>No reviews match your current filters.</p>
                </div>
            <?php else: ?>
                 <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Product</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $review): ?>
                         <tr>
                            <td>#<?= $review['id'] ?></td>
                            <td><?= htmlspecialchars($review['username']) ?></td>
                            <td>
                                <a href="../product_description.php?product_id=<?= $review['product_id'] ?>" target="_blank">
                                    <?= htmlspecialchars($review['product_name']) ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $rating = $review['rating'];
                                for($i = 1; $i <= 5; $i++): 
                                    if($i <= $rating): ?>
                                        <i class="fas fa-star" style="color: #ffd700;"></i>
                                    <?php else: ?>
                                        <i class="far fa-star" style="color: #ffd700;"></i>
                                    <?php endif;
                                endfor; ?>
                                (<?= $rating ?>)
                            </td>
                            <td>
                                <div class="review-preview" title="<?= htmlspecialchars($review['review']) ?>">
                                    <?= htmlspecialchars(substr($review['review'], 0, 50)) ?>
                                    <?= strlen($review['review']) > 50 ? '...' : '' ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $review['status'] ?>">
                                    <?= ucfirst($review['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($review['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <?php if($review['status'] !== 'approved'): ?>
                                        <a href="?action=approve&id=<?= $review['id'] ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_term) ?>" 
                                           class="btn-sm btn-success" title="Approve">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($review['status'] !== 'rejected'): ?>
                                        <a href="?action=reject&id=<?= $review['id'] ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_term) ?>" 
                                           class="btn-sm btn-danger" title="Reject">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?action=delete&id=<?= $review['id'] ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_term) ?>" 
                                       class="btn-sm btn-warning" title="Delete"
                                       onclick="return confirm('Delete this review?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Filter functions
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value;
    
    let url = 'admin_review.php?';
    if (status !== 'all') url += 'status=' + status + '&';
    if (search) url += 'search=' + encodeURIComponent(search);
    
    window.location.href = url;
}

// Add enter key support for search
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
}
</script>

</body>
</html>