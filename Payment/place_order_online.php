<?php
session_start();
require '../php1/db.php';

// -----------------------------
// SECURITY CHECK
// -----------------------------
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ../php/login.php");
    exit();
}

// -----------------------------
// CHECK REQUIRED DATA
// -----------------------------
$shipping_address = trim($_SESSION['shipping_address'] ?? '');
$total_amount     = floatval($_SESSION['total_amount'] ?? 0);

if (empty($shipping_address) || $total_amount <= 0) {
    die("Checkout data missing.");
}

// -----------------------------
// FETCH CART ITEMS
// -----------------------------
$stmt = $conn->prepare("
    SELECT c.product_id, c.quantity, p.price 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($cart_items)) {
    die("Your cart is empty.");
}

// -----------------------------
// START TRANSACTION
// -----------------------------
$conn->begin_transaction();

try {
    $order_ids = [];
    $payment_method = 'online';
    $payment_status = 'paid';
    $order_status   = 'pending';

    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $quantity   = $item['quantity'];
        $total_price = $item['price'] * $quantity;

        // Insert order
        $order_stmt = $conn->prepare("
            INSERT INTO orders 
            (user_id, product_id, quantity, total_price, payment_method, payment_status, order_status, shipping_address, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $order_stmt->bind_param(
            "iiidssss",
            $user_id,
            $product_id,
            $quantity,
            $total_price,
            $payment_method,
            $payment_status,
            $order_status,
            $shipping_address
        );
        $order_stmt->execute();
        $order_ids[] = $order_stmt->insert_id;
        $order_stmt->close();

// Update stock
$stock_stmt = $conn->prepare("
    UPDATE products 
    SET stock_quantity = stock_quantity - ? 
    WHERE product_id = ?
");
$stock_stmt->bind_param("ii", $quantity, $product_id);
$stock_stmt->execute();
$stock_stmt->close();

// Log purchase activity (VERY IMPORTANT)
$activity_stmt = $conn->prepare("
    INSERT INTO user_activity (user_id, product_id, action)
    VALUES (?, ?, 'purchase')
");
$activity_stmt->bind_param("ii", $user_id, $product_id);
$activity_stmt->execute();
$activity_stmt->close();

    }

    // -----------------------------
    // CLEAR CART
    // -----------------------------
    $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear_cart->bind_param("i", $user_id);
    $clear_cart->execute();
    $clear_cart->close();

    // COMMIT TRANSACTION
    $conn->commit();

    // -----------------------------
    // STORE ORDER IDS IN SESSION FOR SUCCESS PAGE
    // -----------------------------
    $_SESSION['order_ids'] = $order_ids;

    // -----------------------------
    // CLEAN CHECKOUT SESSION DATA
    // -----------------------------
    unset(
        $_SESSION['total_amount'],
        $_SESSION['shipping_address'],
        $_SESSION['payment_method'],
        $_SESSION['amount'],
        $_SESSION['product_delivery_charge'],
        $_SESSION['tax_amount']
    );

    // -----------------------------
    // REDIRECT TO SUCCESS PAGE
    // -----------------------------
    header("Location: order_success.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Order failed: " . $e->getMessage());
}
?>
