<?php
session_start();
include 'db.php';

// Update order status logic
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    $stmt = $conn->prepare("UPDATE orders SET order_status=?, updated_at=NOW() WHERE order_id=?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    // Redirect to refresh the page and prevent double-posting
    header("Location: overview.php");
    exit();
}

// Fetch orders
$result = $conn->query("
    SELECT o.*, u.username, p.product_name 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN products p ON o.product_id = p.product_id
    ORDER BY o.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Orders</title>
    <link rel="stylesheet" href="../css/admin_style.css">
</head>
<body>

<div class="side-menu">
    <div class="brand-name">Clothify Admin</div>
    <ul>
        <li><a href="overview.php" class="active">Overview</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="admin_review.php">Review</a></li>
        <li><a href="view_products.php">View Products</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="admin-container"> <header>
            <h1>Order Management</h1>
        </header>

        <div class="table-wrapper"> <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th>Address</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $row['order_id'] ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= $row['quantity'] ?></td>
                        <td>Rs <?= number_format($row['total_price'], 2) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($row['order_status']) ?>">
                                <?= $row['order_status'] ?>
                            </span>
                        </td>
                        <td><?= $row['payment_status'] ?></td>
                        <td><?= htmlspecialchars($row['shipping_address']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?= $row['row_order_id'] ?? $row['order_id'] ?>">
                                <select name="order_status">
                                    <option value="Pending" <?= $row['order_status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Processing" <?= $row['order_status']=='Processing'?'selected':'' ?>>Processing</option>
                                    <option value="Completed" <?= $row['order_status']=='Completed'?'selected':'' ?>>Completed</option>
                                    <option value="Cancelled" <?= $row['order_status']=='Cancelled'?'selected':'' ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div> </div> </div> </body>
</html>