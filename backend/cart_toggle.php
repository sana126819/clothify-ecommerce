<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$product_id = intval($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($user_id) {
    // Check current states
    $check_cart = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $check_cart->bind_param("ii", $user_id, $product_id);
    $check_cart->execute();
    $in_cart = $check_cart->get_result()->num_rows > 0;

    $check_wish = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_wish->bind_param("ii", $user_id, $product_id);
    $check_wish->execute();
    $in_wishlist = $check_wish->get_result()->num_rows > 0;

    if ($action === 'add_to_cart' && !$in_cart) {
        // Add to cart
        $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $user_id, $product_id);
        $insert->execute();

        // Remove from wishlist if present
        if ($in_wishlist) {
            $delete = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $delete->bind_param("ii", $user_id, $product_id);
            $delete->execute();
            $in_wishlist = false;
        }

        $message = 'Added to cart';
        $in_cart = true;
    } elseif ($action === 'remove_from_cart' && $in_cart) {
        // Remove from cart
        $delete = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $delete->bind_param("ii", $user_id, $product_id);
        $delete->execute();
        $message = 'Removed from cart';
        $in_cart = false;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // Get updated counts
    $cart_count = $conn->query("SELECT COUNT(*) FROM cart WHERE user_id = $user_id")->fetch_row()[0];
    $wishlist_count = $conn->query("SELECT COUNT(*) FROM wishlist WHERE user_id = $user_id")->fetch_row()[0];

    echo json_encode([
        'success' => true,
        'message' => $message,
        'in_cart' => $in_cart,
        'in_wishlist' => $in_wishlist,
        'cart_count' => $cart_count,
        'wishlist_count' => $wishlist_count
    ]);

} else {
    // Guest user
    if ($action === 'add_to_cart') {
        $_SESSION['cart'][$product_id] = 1;
        unset($_SESSION['wishlist'][$product_id]);
        $message = 'Added to cart';
        $in_cart = true;
    } else {
        unset($_SESSION['cart'][$product_id]);
        $message = 'Removed from cart';
        $in_cart = false;
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'in_cart' => $in_cart,
        'in_wishlist' => isset($_SESSION['wishlist'][$product_id]),
        'cart_count' => count($_SESSION['cart']),
        'wishlist_count' => count($_SESSION['wishlist'])
    ]);
}
?>