<?php
session_start();
include 'db.php';

// --------------------
// 1. Fetch main categories
// --------------------
$categories = [];
$catRes = $conn->query("SELECT id, name FROM main_categories ORDER BY name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}
// --------------------
// 2. Handle form submission
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name    = trim($_POST['product_name']);
    $description     = trim($_POST['description']);
    $price           = floatval($_POST['price']);
    $stock_quantity  = intval($_POST['stock_quantity']);
    $main_category_id = intval($_POST['main_category_id']);
    $sub_category_id = intval($_POST['sub_category_id']);
    $color           = trim($_POST['color']);
    $size            = trim($_POST['size']);

    // Validation
    if ($price < 0 || $stock_quantity < 0) {
        echo "<script>alert('Price and stock must be non-negative'); history.back();</script>";
        exit;
    }

    // --------------------
    // Image upload
    // --------------------
    if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== 0) {
        echo "<script>alert('Please upload a product image.'); history.back();</script>";
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

    $file_name = time() . "_" . basename($_FILES['product_image']['name']);
    $target_file = $upload_dir . $file_name;

    if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
        echo "<script>alert('Image upload failed.'); history.back();</script>";
        exit;
    }
    $image_url = 'uploads/' . $file_name;

    // --------------------
    // Insert product
    // --------------------
    $stmt = $conn->prepare("INSERT INTO products 
        (product_name, description, price, stock_quantity, main_category_id, sub_category_id, color, size, image_url, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $stmt->bind_param(
        "ssdiiisss",
        $product_name,
        $description,
        $price,
        $stock_quantity,
        $main_category_id,
        $sub_category_id,
        $color,
        $size,
        $image_url
    );

    if ($stmt->execute()) {
        echo "<script>alert('Product added successfully!'); window.location.href='view_products.php';</script>";
    } else {
        echo "<script>alert('Error: ".$stmt->error."'); history.back();</script>";
    }

    $stmt->close();
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product - Admin</title>
<link rel="stylesheet" href="../css/admin_style.css">

</head>
<body>
<div class="side-menu">
   <div class="brand-name">Clothify Admin</div>
    <ul>
        <li><a href="overview.php">Overview</a></li>
        <li><a href="add_product.php" class="active">Add Product</a></li>
        <li><a href="admin_review.php">Review</a></li>
        <li><a href="view_products.php">View Products</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <h1>Add Product</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="product_name" placeholder="Product Name" required />
        <textarea name="description" placeholder="Product Description" required></textarea>
        <input type="number" step="0.01" min="0" name="price" placeholder="Price" required />
        <input type="number" min="0" name="stock_quantity" placeholder="Stock Quantity" required />

        <!-- Main Category -->
        <select name="main_category_id" id="mainCategory" required>
            <option value="">--Select Main Category--</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Subcategory -->
        <select name="sub_category_id" id="subCategory" required>
            <option value="">--Select Subcategory--</option>
        </select>

        <input type="text" name="color" placeholder="Color (e.g., Red)" required />
        <select name="size" required>
            <option value="">--Select Size--</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="Free Size">Free Size</option>
        </select>

        <input type="file" name="product_image" accept="image/*" required />
        <button type="submit">Add Product</button>
    </form>
</div>

<script>
// Dynamically fetch subcategories based on main category
const mainCategory = document.getElementById('mainCategory');
const subCategory  = document.getElementById('subCategory');

mainCategory.addEventListener('change', function() {
    const catId = this.value;
    subCategory.innerHTML = '<option value="">--Select Subcategory--</option>';
    if (!catId) return;

    fetch('get_subcategories.php?main_category_id=' + catId)
        .then(res => res.json())
        .then(data => {
            data.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.innerText = sub.name;
                subCategory.appendChild(opt);
            });
        })
        .catch(err => console.error(err));
});
</script>
</body>
</html>
