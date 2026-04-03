<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php?error=invalid_request");
    exit();
}

$user_identifier = trim($_POST['user_identifier']);
$password = $_POST['password'];

/* -----------------------
   CHECK ADMINS
------------------------- */
$stmt = $conn->prepare("
    SELECT admin_id, username, email, password_hash
    FROM admins
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->bind_param("ss", $user_identifier, $user_identifier);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($admin_id, $username, $email, $password_hash);
    $stmt->fetch();
    if (password_verify($password, $password_hash)) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'admin';
        $_SESSION['logged_in'] = true;
        header("Location: overview.php");
        exit();
    } else {
        header("Location: login.php?error=invalid_password");
        exit();
    }
}
$stmt->close();

/* -----------------------
   CHECK USERS
------------------------- */
$stmt = $conn->prepare("
    SELECT user_id, username, email, phone_number, password_hash
    FROM users
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->bind_param("ss", $user_identifier, $user_identifier);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($user_id, $username, $email, $phone, $password_hash);
    $stmt->fetch();

    if (!password_verify($password, $password_hash)) {
        header("Location: login.php?error=invalid_password");
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;
    $_SESSION['role'] = 'user';
    $_SESSION['logged_in'] = true;

    /* ==========================
       MERGE GUEST CART/WISHLIST
    =========================== */
    $_SESSION['cart'] ??= [];
    $_SESSION['wishlist'] ??= [];

    $cart_duplicates = [];
    foreach ($_SESSION['cart'] as $pid => $qty) {
        // Check if product exists in DB to prevent foreign key error
        $check = $conn->prepare("SELECT product_id FROM products WHERE product_id=?");
        $check->bind_param("i", $pid);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            $check->close();
            continue; // Skip invalid product IDs
        }
        $check->close();

        // Insert or update quantity
        $stmt_cart = $conn->prepare("
            INSERT INTO cart (user_id, product_id, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ");
        $stmt_cart->bind_param("iiii", $user_id, $pid, $qty, $qty);
        if (!$stmt_cart->execute()) {
            // If duplicate or FK error, store in duplicates array
            $cart_duplicates[] = $pid;
        }
        $stmt_cart->close();
    }

    $wishlist_duplicates = [];
    foreach ($_SESSION['wishlist'] as $pid => $_) {
        // Check if product exists in DB
        $check = $conn->prepare("SELECT product_id FROM products WHERE product_id=?");
        $check->bind_param("i", $pid);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            $check->close();
            continue; // Skip invalid product IDs
        }
        $check->close();

        // Insert ignoring duplicates
        $stmt_wish = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt_wish->bind_param("ii", $user_id, $pid);
        if (!$stmt_wish->execute()) {
            $wishlist_duplicates[] = $pid;
        }
        $stmt_wish->close();
    }

    // Clear guest session
    $_SESSION['cart'] = [];
    $_SESSION['wishlist'] = [];

    // Optionally store duplicates in session to show to user
    $_SESSION['cart_duplicates'] = $cart_duplicates;
    $_SESSION['wishlist_duplicates'] = $wishlist_duplicates;

    header("Location: index.php");
    exit();
}

$stmt->close();
header("Location: login.php?error=user_not_found");
exit();
?>
