<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$product_id = intval($_POST['product_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? null;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

if ($user_id) {

    // Check if already in cart
    $check_stmt = $conn->prepare("
        SELECT cart_id FROM cart WHERE user_id=? AND product_id=? LIMIT 1
    ");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $cart_item = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($cart_item) {
        echo json_encode(['success' => false, 'error' => 'Item already in cart']);
        exit;
    }

    // Add to cart
    $cart_stmt = $conn->prepare("
        INSERT INTO cart(user_id, product_id, quantity)
        VALUES (?, ?, 1)
    ");
    $cart_stmt->bind_param("ii", $user_id, $product_id);
    $cart_stmt->execute();
    $cart_stmt->close();

    // Log activity
    $activity_stmt = $conn->prepare("
        INSERT INTO user_activity (user_id, product_id, action)
        VALUES (?, ?, 'cart')
    ");
    $activity_stmt->bind_param("ii", $user_id, $product_id);
    $activity_stmt->execute();
    $activity_stmt->close();

    // Remove from wishlist if exists
    $remove_stmt = $conn->prepare("
        DELETE FROM wishlist WHERE user_id=? AND product_id=?
    ");
    $remove_stmt->bind_param("ii", $user_id, $product_id);
    $remove_stmt->execute();
    $remove_stmt->close();

    $cart_total = $conn->query("
        SELECT SUM(quantity) AS total FROM cart WHERE user_id=$user_id
    ")->fetch_assoc()['total'] ?? 0;

    $wishlist_total = $conn->query("
        SELECT COUNT(*) AS total FROM wishlist WHERE user_id=$user_id
    ")->fetch_assoc()['total'] ?? 0;

} else {
    // Guest user
    $_SESSION['cart'] ??= [];
    $_SESSION['wishlist'] ??= [];

    if (isset($_SESSION['cart'][$product_id])) {
        echo json_encode(['success' => false, 'error' => 'Item already in cart']);
        exit;
    }

    $_SESSION['cart'][$product_id] = 1;
    unset($_SESSION['wishlist'][$product_id]);

    $cart_total = count($_SESSION['cart']);
    $wishlist_total = count($_SESSION['wishlist']);
}

echo json_encode([
    'success' => true,
    'cart_count' => $cart_total,
    'wishlist_count' => $wishlist_total
]);
exit;
