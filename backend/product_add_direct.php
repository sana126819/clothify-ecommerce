<?php
require 'db.php';

/*
========================================
STEP 0: DISABLE FK CHECKS
========================================
*/
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

/*
========================================
STEP 1: RESET TABLES
========================================
*/
$conn->query("TRUNCATE TABLE product_tfidf");
$conn->query("TRUNCATE TABLE products");

/*
========================================
STEP 2: ENABLE FK CHECKS BACK
========================================
*/
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

/*
========================================
STEP 3: PRODUCT DATA
(keyword-optimized for TF-IDF)
========================================
*/

$products = [

    // MEN
    ['Men T-Shirt', 'men tshirt topwear cotton blue casual regular fit summer daily wear', 1200, 20, 'Blue', 'M', 1, 1, 'men_tshirt.jpg'],
    ['Men Shirt', 'men shirt topwear cotton polyester white formal slim fit office casual', 1500, 15, 'White', 'M', 1, 2, 'men_shirt.jpg'],
    ['Men Jeans', 'men jeans bottomwear denim blue straight fit casual daily', 1800, 12, 'Blue', '32', 1, 4, 'men_jeans.jpg'],
    ['Men Jacket', 'men jacket outerwear polyester black zip casual sporty winter', 2500, 10, 'Black', 'L', 1, 5, 'men_jacket.jpg'],
    ['Men Hoodie', 'men hoodie fleece gray casual warm winter comfort', 1600, 18, 'Gray', 'M', 1, 6, 'men_hoodie.jpg'],
    ['Men Pants', 'men pants bottomwear cotton khaki slim fit casual daily', 1300, 15, 'Khaki', 'M', 1, 3, 'men_pants.jpg'],
    ['Men Shorts', 'men shorts bottomwear cotton blue casual summer breathable', 1000, 20, 'Blue', 'M', 1, 3, 'men_shorts.jpg'],
    ['Men Sweater', 'men sweater knit red warm winter casual', 1700, 10, 'Red', 'L', 1, 6, 'men_sweater.jpg'],

    // WOMEN
    ['Women T-Shirt', 'women tshirt topwear cotton pink slim fit casual summer', 1100, 20, 'Pink', 'M', 2, 7, 'women_tshirt.jpg'],
    ['Women Shirt', 'women shirt topwear polyester white formal casual office', 1700, 15, 'White', 'M', 2, 8, 'women_shirt.jpg'],
    ['Women Dress', 'women dress onepiece red knee length casual party summer', 2000, 12, 'Red', 'M', 2, 11, 'women_dress.jpg'],
    ['Women Skirt', 'women skirt bottomwear blue aline casual daily wear', 1500, 10, 'Blue', 'M', 2, 12, 'women_skirt.jpg'],
    ['Women One-Piece', 'women onepiece cotton green casual daily comfort', 1800, 14, 'Green', 'M', 2, 13, 'women_onepiece.jpg'],
    ['Women Jeans', 'women jeans bottomwear denim blue skinny fit casual', 1900, 12, 'Blue', '32', 2, 10, 'women_jeans.jpg'],
    ['Women Jacket', 'women jacket outerwear black zip casual winter', 2200, 10, 'Black', 'L', 2, 5, 'women_jacket.jpg'],
    ['Women Hoodie', 'women hoodie fleece gray warm casual winter', 1600, 15, 'Gray', 'M', 2, 6, 'women_hoodie.jpg'],
    ['Women Shorts', 'women shorts cotton white casual summer', 1200, 20, 'White', 'M', 2, 9, 'women_shorts.jpg'],
    ['Women Sweater', 'women sweater knit red warm winter casual', 1700, 12, 'Red', 'L', 2, 6, 'women_sweater.jpg'],

    // CHILD
    ['Child T-Shirt', 'child tshirt cotton yellow casual kids daily wear', 900, 25, 'Yellow', 'S', 3, 14, 'child_tshirt.jpg'],
    ['Child Pants', 'child pants cotton blue durable casual kids', 1200, 20, 'Blue', 'S', 3, 16, 'child_pants.jpg'],
    ['Child Dress', 'child dress pink lightweight casual kids', 1400, 15, 'Pink', 'S', 3, 18, 'child_dress.jpg'],

    // OTHERS
    ['Others Hat', 'unisex hat accessory cotton white casual daily', 500, 12, 'White', 'One Size', 4, 21, 'others_hat.jpg'],
    ['Others Scarf', 'unisex scarf accessory polyester gray warm winter', 600, 15, 'Gray', 'One Size', 4, 21, 'others_scarf.jpg'],
];

/*
========================================
STEP 4: INSERT PRODUCTS
========================================
*/

$stmt = $conn->prepare("
    INSERT INTO products
    (product_name, description, price, stock_quantity, image_url, color, size, main_category_id, sub_category_id, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$stmt->bind_param(
    "ssdisssii",
    $name,
    $desc,
    $price,
    $stock,
    $image,
    $color,
    $size,
    $main_cat,
    $sub_cat
);

foreach ($products as $p) {
    [$name, $desc, $price, $stock, $color, $size, $main_cat, $sub_cat, $img] = $p;
    $image = "uploads/" . $img;
    $stmt->execute();
}

$stmt->close();
$conn->close();

echo "<b>Products + TF-IDF reset ready. Now run TF-IDF builder once.</b>";
?>
