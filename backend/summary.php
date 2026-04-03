<?php

require 'db.php';
// Fetch summary metrics for dashboard
$total_products = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_orders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$total_revenue = $conn->query("SELECT SUM(total_price) as sum FROM orders")->fetch_assoc()['sum'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin:0; font-family: Arial, sans-serif; }
        .sidebar { width:220px; height:100vh; background:#222; color:#fff; position:fixed; top:0; left:0; padding-top:20px; }
        .sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
        .sidebar a:hover, .sidebar a.active { background:#e60023; }
        .main { margin-left:220px; padding:20px; }
        .card { background:#f4f4f4; padding:20px; border-radius:8px; margin-bottom:15px; display:inline-block; width:200px; text-align:center; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        table th, table td { padding:10px; border:1px solid #ddd; text-align:center; }
        .btn { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; }
        .btn-edit { background:#004c97;color:#fff; }
        .btn-delete { background:#e60023;color:#fff; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2 style="text-align:center;">Admin Panel</h2>
    <a href="?page=dashboard" class="active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="?page=products"><i class="fa fa-box"></i> Products</a>
    <a href="?page=orders"><i class="fa fa-shopping-cart"></i> Orders</a>
    <a href="?page=users"><i class="fa fa-users"></i> Users</a>
    <a href="?page=wishlist_cart"><i class="fa fa-heart"></i> Wishlist & Cart</a>
    <a href="?page=analytics"><i class="fa fa-chart-bar"></i> Analytics</a>
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
<?php
$page = $_GET['page'] ?? 'dashboard';

switch($page){
    case 'dashboard':
        echo "<h1>Dashboard</h1>";
        echo "<div class='card'>Total Products<br><strong>$total_products</strong></div>";
        echo "<div class='card'>Total Users<br><strong>$total_users</strong></div>";
        echo "<div class='card'>Total Orders<br><strong>$total_orders</strong></div>";
        echo "<div class='card'>Revenue<br><strong>Rs ".number_format($total_revenue,2)."</strong></div>";
        break;

    case 'products':
        echo "<h1>Products</h1>";
        echo '<a href="add_product.php" class="btn btn-edit">Add Product</a>';
        $res = $conn->query("SELECT * FROM products");
        echo "<table><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr>";
        while($row = $res->fetch_assoc()){
            echo "<tr>
                <td>{$row['product_id']}</td>
                <td>{$row['product_name']}</td>
                <td>Rs ".number_format($row['price'],2)."</td>
                <td>{$row['stock_quantity']}</td>
                <td>
                    <a href='edit_product.php?id={$row['product_id']}' class='btn btn-edit'>Edit</a>
                    <a href='delete_product.php?id={$row['product_id']}' class='btn btn-delete' onclick='return confirm(\"Delete product?\");'>Delete</a>
                </td>
            </tr>";
        }
        echo "</table>";
        break;

    case 'orders':
        echo "<h1>Orders</h1>";
        $res = $conn->query("
            SELECT o.order_id, u.username, o.total_price, o.status
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            ORDER BY o.order_id DESC
        ");
        echo "<table><tr><th>Order ID</th><th>User</th><th>Total</th><th>Status</th><th>Actions</th></tr>";
        while($row = $res->fetch_assoc()){
            echo "<tr>
                <td>{$row['order_id']}</td>
                <td>{$row['username']}</td>
                <td>Rs ".number_format($row['total_price'],2)."</td>
                <td>{$row['status']}</td>
                <td>
                    <a href='view_order.php?id={$row['order_id']}' class='btn btn-edit'>View</a>
                </td>
            </tr>";
        }
        echo "</table>";
        break;

    case 'users':
        echo "<h1>Users</h1>";
        $res = $conn->query("SELECT user_id, username, email, created_at FROM users");
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Registered</th><th>Actions</th></tr>";
        while($row = $res->fetch_assoc()){
            echo "<tr>
                <td>{$row['user_id']}</td>
                <td>{$row['username']}</td>
                <td>{$row['email']}</td>
                <td>{$row['created_at']}</td>
                <td>
                    <a href='edit_user.php?id={$row['user_id']}' class='btn btn-edit'>Edit</a>
                    <a href='block_user.php?id={$row['user_id']}' class='btn btn-delete'>Block</a>
                </td>
            </tr>";
        }
        echo "</table>";
        break;

    case 'wishlist_cart':
        echo "<h1>Wishlist & Cart Analytics</h1>";
        echo "<p>Here you can see user wishlists and carts for analytics purposes (implement queries as needed).</p>";
        break;

    case 'analytics':
        echo "<h1>Analytics</h1>";
        echo "<p>Charts, top-selling products, orders per day, low stock alerts (use chart.js or placeholders for fast demo).</p>";
        break;

    default:
        echo "<h1>Dashboard</h1>";
        break;
}
?>
</div>

</body>
</html>
