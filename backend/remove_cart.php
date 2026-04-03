<?php
session_start();
require 'db.php';

// Validate product_id
$product_id = $_GET['product_id'] ?? null;
if (!$product_id || !is_numeric($product_id)) {
    header("Location: cart.php");
    exit;
}
$product_id = intval($product_id);

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // Logged-in user: remove from database
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
} else {
    // Guest user: remove from session
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

// Redirect back to cart
header("Location: cart.php");
exit;
