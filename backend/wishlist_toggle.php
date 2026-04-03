<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$product_id = intval($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? 'toggle_wishlist';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($user_id) {
    // Check current states
    $check_wish = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_wish->bind_param("ii", $user_id, $product_id);
    $check_wish->execute();
    $in_wishlist = $check_wish->get_result()->num_rows > 0;

    $check_cart = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $check_cart->bind_param("ii", $user_id, $product_id);
    $check_cart->execute();
    $in_cart = $check_cart->get_result()->num_rows > 0;

    if ($action === 'add_to_wishlist' && !$in_wishlist) {
        // Add to wishlist
        $insert = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insert->bind_param("ii", $user_id, $product_id);
        $insert->execute();

        // Remove from cart if present
        if ($in_cart) {
            $delete = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $delete->bind_param("ii", $user_id, $product_id);
            $delete->execute();
            $in_cart = false;
        }

        $message = 'Added to wishlist';
        $in_wishlist = true;
    } elseif ($action === 'remove_from_wishlist' && $in_wishlist) {
        // Remove from wishlist
        $delete = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $delete->bind_param("ii", $user_id, $product_id);
        $delete->execute();
        $message = 'Removed from wishlist';
        $in_wishlist = false;
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
        'in_wishlist' => $in_wishlist,
        'in_cart' => $in_cart,
        'wishlist_count' => $wishlist_count,
        'cart_count' => $cart_count
    ]);

} else {
    // Guest user
    if ($action === 'add_to_wishlist') {
        $_SESSION['wishlist'][$product_id] = 1;
        unset($_SESSION['cart'][$product_id]);
        $message = 'Added to wishlist';
        $in_wishlist = true;
    } else {
        unset($_SESSION['wishlist'][$product_id]);
        $message = 'Removed from wishlist';
        $in_wishlist = false;
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'in_wishlist' => $in_wishlist,
        'in_cart' => isset($_SESSION['cart'][$product_id]),
        'wishlist_count' => count($_SESSION['wishlist']),
        'cart_count' => count($_SESSION['cart'])
    ]);
}
?>