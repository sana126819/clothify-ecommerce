<?php
session_start();

// Preserve guest cart/wishlist if they exist
$guestCart = $_SESSION['cart'] ?? [];
$guestWishlist = $_SESSION['wishlist'] ?? [];

// Clear all session data
session_unset();
session_destroy();

// Start a new session for guest
session_start();
$_SESSION['cart'] = $guestCart;
$_SESSION['wishlist'] = $guestWishlist;

// Redirect to login page
header("Location: login.php");
exit();
?>
