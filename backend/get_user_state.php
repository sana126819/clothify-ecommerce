<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    $cart_items = $conn->query("SELECT product_id FROM cart WHERE user_id = $user_id")->fetch_all(MYSQLI_ASSOC);
    $wishlist_items = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $user_id")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'cart_count' => count($cart_items),
        'wishlist_count' => count($wishlist_items),
        'cart_items' => array_column($cart_items, 'product_id'),
        'wishlist_items' => array_column($wishlist_items, 'product_id')
    ]);
} else {
    echo json_encode([
        'success' => true,
        'cart_count' => count($_SESSION['cart']),
        'wishlist_count' => count($_SESSION['wishlist']),
        'cart_items' => array_keys($_SESSION['cart']),
        'wishlist_items' => array_keys($_SESSION['wishlist'])
    ]);
}
?>