<?php
session_start();
require '../php1/db.php';

/* -----------------------------
   SECURITY CHECK
----------------------------- */
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ../php1/login.php");
    exit();
}

/* -----------------------------
   REQUIRED SESSION DATA
----------------------------- */
$total_amount     = $_SESSION['total_amount'] ?? 0;
$shipping_address = $_SESSION['shipping_address'] ?? '';

if ($total_amount <= 0 || empty($shipping_address)) {
    die("Invalid checkout data.");
}

/* -----------------------------
   FETCH CART ITEMS
----------------------------- */
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

/* -----------------------------
   START TRANSACTION
----------------------------- */
$conn->begin_transaction();

try {
    $order_ids = [];

    foreach ($cart_items as $item) {
        $product_id   = $item['product_id'];
        $quantity     = $item['quantity'];
        $total_price  = $item['price'] * $quantity;

        $payment_method = 'cash_on_delivery';
        $payment_status = 'pending';
        $order_status   = 'pending';

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

    // Clear cart
    $clearCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clearCart->bind_param("i", $user_id);
    $clearCart->execute();
    $clearCart->close();

    $conn->commit();

    // Store order IDs in session for success page
    $_SESSION['order_ids'] = $order_ids;

    // Clean checkout-related session data
    unset(
        $_SESSION['total_amount'],
        $_SESSION['shipping_address'],
        $_SESSION['payment_method'],
        $_SESSION['amount'],
        $_SESSION['product_delivery_charge'],
        $_SESSION['tax_amount']
    );

    // Redirect to COD success page
    header("Location: cod_order_success.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Order failed: " . $e->getMessage());
}
