<?php
session_start();
include 'db.php';

// The fixed SQL query to match your schema
$sql = "SELECT 
            p.*, 
            m.name AS main_cat_name, 
            s.name AS sub_cat_name 
        FROM products p
        LEFT JOIN main_categories m ON p.main_category_id = m.id
        LEFT JOIN sub_categories s ON p.sub_category_id = s.id
        ORDER BY p.product_id DESC";

$result = $conn->query($sql);
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
        <li><a href="admin_review.php">Review</a></li>
        <li><a href="view_products.php" class="active">View Products</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="admin-container">
        <header><h1>Product Inventory</h1></header>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Main Category</th>
                        <th>Subcategory</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $row['product_id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['product_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['main_cat_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['sub_cat_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['color'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['size'] ?? '-') ?></td>
                                <td>$<?= number_format($row['price'], 2) ?></td>
                                <td><?= $row['stock_quantity'] ?></td>
                                <td>
                                    <?php if(!empty($row['image_url'])): ?>
                                        <img src="<?= $row['image_url'] ?>" style="width:40px; border-radius:4px;" alt="Product">
                                    <?php else: ?>
                                        <span style="font-size:10px; color:#ccc;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a class="btn" href="update_product.php?product_id=<?= $row['product_id'] ?>">Edit</a>
                                        <a class="btn delete-btn" href="delete_product.php?product_id=<?= $row['product_id'] ?>" 
                                           onclick="return confirm('Delete this product?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10">No products found in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>