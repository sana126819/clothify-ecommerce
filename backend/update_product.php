<?php
session_start();
include 'db.php';

// Validate product ID
if (!isset($_GET['product_id'])) {
    echo "<script>alert('Invalid product.'); window.location.href='view_products.php';</script>";
    exit;
}

$product_id = intval($_GET['product_id']);

// Fetch product data
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "<script>alert('Product not found.'); window.location.href='view_products.php';</script>";
    exit;
}

// Fetch main categories
$categories = [];
$res = $conn->query("SELECT id, name FROM main_categories ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch subcategories for the current main category
$subcategories = [];
if ($product['main_category_id']) {
    $stmt = $conn->prepare("SELECT id, name FROM sub_categories WHERE main_category_id = ?");
    $stmt->bind_param("i", $product['main_category_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $subcategories[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name     = trim($_POST['product_name']);
    $description      = trim($_POST['description']);
    $price            = floatval($_POST['price']);
    $stock_quantity   = intval($_POST['stock_quantity']);
    $main_category_id = intval($_POST['main_category_id']);
    $sub_category_id  = intval($_POST['sub_category_id']);
    $color            = trim($_POST['color']);
    $size             = trim($_POST['size']);
    
    $image_url = $product['image_url']; // keep old image unless updated
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = time() . "_" . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/' . $file_name;
        }
    }

    $stmt = $conn->prepare("
        UPDATE products
        SET product_name=?, description=?, price=?, stock_quantity=?,
            main_category_id=?, sub_category_id=?, color=?, size=?, image_url=?, updated_at=NOW()
        WHERE product_id=?
    ");
    $stmt->bind_param(
        "ssdiiisssi",
        $product_name, $description, $price, $stock_quantity,
        $main_category_id, $sub_category_id, $color, $size, $image_url, $product_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Product updated successfully.'); window.location.href='view_products.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error updating product: {$stmt->error}');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Product - Admin</title>
<link rel="stylesheet" href="../css/admin_style.css">
</head>
<body>
<div class="side-menu">
    <div class="brand-name">Clothify Admin</div>
    <ul>
        <li><a href="overview.php">Overview</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="update_product.php" class="active">Edit Products</a></li>
        <li><a href="view_products.php">View Products</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="main-content">
<h1>Update Product</h1>

<form method="POST" enctype="multipart/form-data">
    <input type="text" name="product_name" placeholder="Product Name" value="<?= htmlspecialchars($product['product_name']) ?>" required>
    <textarea name="description" placeholder="Product Description" required><?= htmlspecialchars($product['description']) ?></textarea>
    <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars($product['price']) ?>" required>
    <input type="number" min="0" name="stock_quantity" value="<?= htmlspecialchars($product['stock_quantity']) ?>" required>

    <!-- Main Category -->
    <select name="main_category_id" id="mainCategory" required>
        <option value="">--Select Main Category--</option>
        <?php foreach($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($product['main_category_id']==$cat['id']) ? "selected" : "" ?>>
                <?= htmlspecialchars($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Subcategory -->
    <select name="sub_category_id" id="subCategory" required>
        <option value="">--Select Subcategory--</option>
        <?php foreach($subcategories as $sub): ?>
            <option value="<?= $sub['id'] ?>" <?= ($product['sub_category_id']==$sub['id']) ? "selected" : "" ?>>
                <?= htmlspecialchars($sub['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="color" placeholder="Color" value="<?= htmlspecialchars($product['color']) ?>" required>
    <select name="size" required>
        <option value="">--Select Size--</option>
        <?php 
        $sizes = ["S","M","L","XL","Free Size"];
        foreach($sizes as $s):
            $sel = ($product['size']==$s) ? "selected" : "";
        ?>
            <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>

    <p>Current Image:</p>
    <img src="<?= $product['image_url'] ?>" width="120" height="120" style="border-radius:5px;">
    <input type="file" name="product_image" accept="image/*">

    <button type="submit">Update Product</button>
</form>
</div>

<script>
// Fetch subcategories dynamically on main category change
const mainCat = document.getElementById('mainCategory');
const subCat = document.getElementById('subCategory');

mainCat.addEventListener('change', function(){
    const mainId = this.value;
    subCat.innerHTML = '<option value="">--Select Subcategory--</option>';
    if (!mainId) return;
    fetch('get_subcategories.php?main_category_id=' + mainId)
        .then(res => res.json())
        .then(data => {
            data.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.innerText = sub.name;
                subCat.appendChild(opt);
            });
        });
});
</script>
</body>
</html>
