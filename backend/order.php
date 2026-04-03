<?php
session_start();
include 'db.php';

// Update order status if admin submitted a change
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    $stmt = $conn->prepare("UPDATE orders SET order_status=?, updated_at=NOW() WHERE order_id=?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    header("Location: order.php");
    exit;

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
<title>View Products</title>
<link rel="stylesheet" href="../css/admin_style.css">
</head>
<body>

<div class="side-menu">
    <div class="brand-name">Clothify Admin</div>
    <ul>
        <li><a href="overview.php">Overview</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="update_product.php">Update Product</a></li>
        <li><a href="view_products.php" >View Products</a></li>
        <li><a href="order.php" class="active">Orders</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>
<div class="main-content">
<h1>Admin - Orders</h1>

<table border="1">
<tr>
    <th>Order ID</th>
    <th>Customer</th>
    <th>Product</th>
    <th>Quantity</th>
    <th>Total</th>
    <th>Order Status</th>
    <th>Payment Status</th>
    <th>Shipping Address</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['order_id'] ?></td>
    <td><?= htmlspecialchars($row['username']) ?></td>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td><?= $row['quantity'] ?></td>
    <td><?= $row['total_price'] ?></td>
    <td><?= $row['order_status'] ?></td>
    <td><?= ucfirst(strtolower($row['payment_status'] ?? 'pending')) ?></td>
    <td><?= htmlspecialchars($row['shipping_address']) ?></td>
    <td><?= $row['created_at'] ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
           <select name="order_status">
    <option value="pending" <?= $row['order_status']=='pending'?'selected':'' ?>>Pending</option>
    <option value="processing" <?= $row['order_status']=='processing'?'selected':'' ?>>Processing</option>
    <option value="completed" <?= $row['order_status']=='completed'?'selected':'' ?>>Completed</option>
    <option value="cancelled" <?= $row['order_status']=='cancelled'?'selected':'' ?>>Cancelled</option>
</select>
            <button type="submit" name="update_status">Update</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</table>
</div>
</body>
</html>