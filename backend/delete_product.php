<?php
session_start();
include 'db.php';

// Check product ID
if (!isset($_GET['product_id'])) {
    header("Location: view_products.php");
    exit;
}

$product_id = intval($_GET['product_id']);

// Delete product
$stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->close();

header("Location: view_products.php");
exit;
?>
