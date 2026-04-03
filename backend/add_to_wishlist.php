<?php
session_start();
require 'db.php';

// Get product ID
$product_id = $_GET['id'] ?? $_POST['product_id'] ?? null;
if (!$product_id || !is_numeric($product_id)) {
    echo json_encode(['success' => false, 'wishlist_count' => 0, 'active' => false]);
    exit;
}
$product_id = intval($product_id);

$user_id = $_SESSION['user_id'] ?? null;
$active = false;

if ($user_id) {

    // Check if product already in wishlist
    $check_stmt = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id=? AND product_id=?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($exists) {
        // Remove from wishlist
        $delete_stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id=?");
        $delete_stmt->bind_param("i", $exists['wishlist_id']);
        $delete_stmt->execute();
        $delete_stmt->close();

        $active = false;
    } else {
        // Add to wishlist
        $add_stmt = $conn->prepare("
            INSERT INTO wishlist(user_id, product_id, added_at)
            VALUES (?, ?, NOW())
        ");
        $add_stmt->bind_param("ii", $user_id, $product_id);
        $add_stmt->execute();
        $add_stmt->close();

        // Log activity
        $activity_stmt = $conn->prepare("
            INSERT INTO user_activity (user_id, product_id, action)
            VALUES (?, ?, 'wishlist')
        ");
        $activity_stmt->bind_param("ii", $user_id, $product_id);
        $activity_stmt->execute();
        $activity_stmt->close();

        $active = true;
    }

    // Updated wishlist count
    $res = $conn->query("SELECT COUNT(*) AS total FROM wishlist WHERE user_id=$user_id");
    $total = $res->fetch_assoc()['total'] ?? 0;

} else {
    // Guest user
    if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

    if (isset($_SESSION['wishlist'][$product_id])) {
        unset($_SESSION['wishlist'][$product_id]);
        $active = false;
    } else {
        $_SESSION['wishlist'][$product_id] = true;
        $active = true;
    }

    $total = count($_SESSION['wishlist']);
}

echo json_encode([
    'success' => true,
    'wishlist_count' => $total,
    'active' => $active
]);
exit;
