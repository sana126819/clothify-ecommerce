<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $product_id = intval($_POST['product_id']);
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review']);
    
    // Validate input
    if ($rating < 1 || $rating > 5) {
        die("Rating must be between 1 and 5");
    }
    
    if (empty($review) || strlen($review) > 500) {
        die("Review must be between 1 and 500 characters");
    }
    
    // Check if user already reviewed this product
    $check = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        die("You have already reviewed this product");
    }
    $check->close();
    
    // Insert review with pending status (requires admin approval)
    $stmt = $conn->prepare("
        INSERT INTO reviews (user_id, product_id, rating, review, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiis", $user_id, $product_id, $rating, $review);
    
    if ($stmt->execute()) {
        // Success - redirect back with success message
        header("Location: product_description.php?product_id=$product_id&review=submitted");
        exit();
    } else {
        die("Failed to save review: " . $stmt->error);
    }
    
    $stmt->close();
}

header('Location: product_description.php');
exit();
?>