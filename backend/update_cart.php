<?php
session_start();
include 'db.php';

$pid = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if ($pid <= 0) die("Invalid product ID");

$user_id = $_SESSION['user_id'] ?? null;

// Fetch stock
$stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id=?");
$stmt->bind_param("i", $pid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) die("Product does not exist.");
$product = $res->fetch_assoc();
$stock = $product['stock_quantity'];

if ($user_id) {
    // Logged-in user cart
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
    $stmt->bind_param("ii", $user_id, $pid);
    $stmt->execute();
    $res = $stmt->get_result();

    $qty = 0;
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $qty = $row['quantity'];
    }

    if ($action == 'increase') {
        if ($qty < $stock) $qty++;
    } elseif ($action == 'decrease') {
        $qty = max(1, $qty - 1);
    }

    if ($res->num_rows > 0) {
        // Update quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
        $stmt->bind_param("iii", $qty, $user_id, $pid);
        $stmt->execute();
    } else {
        // Add new item
        if ($action == 'increase') {
            $stmt = $conn->prepare("INSERT INTO cart(user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmt->bind_param("ii", $user_id, $pid);
            $stmt->execute();
        }
    }
} else {
    // Guest cart
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $currentQty = $_SESSION['cart'][$pid] ?? 0;

    if ($action == 'increase') {
        if ($currentQty < $stock) $_SESSION['cart'][$pid] = $currentQty + 1;
    } elseif ($action == 'decrease' && $currentQty > 1) {
        $_SESSION['cart'][$pid] = $currentQty - 1;
    }
}

header("Location: cart.php");
exit;
