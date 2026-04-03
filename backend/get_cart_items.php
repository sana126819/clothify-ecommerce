<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$cart_items = [];

if ($user_id) {
    // Logged in user - get from database
    $stmt = $conn->prepare("SELECT product_id FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row['product_id'];
    }
    $stmt->close();
} else {
    // Guest user - get from session
    $cart_items = array_keys($_SESSION['cart'] ?? []);
}

echo json_encode([
    'success' => true,
    'cart_items' => $cart_items
]);
?>