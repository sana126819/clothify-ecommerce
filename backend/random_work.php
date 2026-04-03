<?php
require 'db.php';

// -----------------------------
// Fetch users
// -----------------------------
$users = [];
$result = $conn->query("SELECT user_id FROM users");
while($row = $result->fetch_assoc()) {
    $users[] = $row['user_id'];
}

// -----------------------------
// Fetch products
// -----------------------------
$products = [];
$result = $conn->query("SELECT product_id FROM products");
while($row = $result->fetch_assoc()) {
    $products[] = $row['product_id'];
}

// -----------------------------
// Simulate activity
// -----------------------------
$activities = ['view', 'cart', 'wishlist', 'order'];
$count = 0;

$stmt = $conn->prepare("INSERT INTO user_activity (user_id, product_id, action, timestamp) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $user_id, $product_id, $activity_type);

foreach ($users as $user) {
    // Each user interacts with 10 random products
    $sampleProducts = array_rand(array_flip($products), min(30, count($products)));

    foreach ((array)$sampleProducts as $product_id) {
        // Each product gets 1-3 random activities
        $numActivities = rand(1,3);
        for ($i=0; $i<$numActivities; $i++) {
            $user_id = $user;
            $activity_type = $activities[array_rand($activities)];
            $stmt->execute();
            $count++;
        }
    }
}

$stmt->close();
$conn->close();

echo "<b>$count user activity records inserted successfully.</b>";
?>
