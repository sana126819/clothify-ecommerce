<?php
session_start();

// Get order IDs from session
$order_ids = $_SESSION['order_ids'] ?? [];
if (empty($order_ids)) {
    die("No order information found.");
}

// Clear order IDs from session after displaying
unset($_SESSION['order_ids']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>COD Order Successful</title>
<style>
:root {
  --bg: #F7F6F2;
  --text: #2C3E50;
  --primary: #8FAACB;
  --primary-hover: #B0C9D6;
  --success: #4CAF50;
}
body {
  background-color: var(--bg);
  font-family: Arial, sans-serif;
  margin: 0;
  padding: 0;
  display: flex;
  height: 100vh;
  align-items: center;
  justify-content: center;
  color: var(--text);
}
.success-container {
  background-color: #ffffff;
  padding: 40px 60px;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
  text-align: center;
  max-width: 500px;
}
.success-container h1 {
  color: var(--success);
  font-size: 2em;
  margin-bottom: 20px;
}
.success-container p {
  font-size: 1.1em;
  margin-bottom: 10px;
}
.btn {
  display: inline-block;
  margin-top: 30px;
  padding: 10px 25px;
  background-color: var(--primary);
  color: white;
  text-decoration: none;
  border-radius: 6px;
  font-weight: bold;
}
.btn:hover {
  background-color: var(--primary-hover);
}
</style>
</head>
<body>
  <div class="success-container">
    <h1>✅ COD Order Successful</h1>
    <p>Your order has been placed successfully.</p>
    <p><strong>Order IDs:</strong> <?= implode(", ", $order_ids) ?></p>
    <p>Payment will be collected on delivery.</p>
    <a href="../php1/index.php" class="btn">Back to Home</a>
  </div>
</body>
</html>
