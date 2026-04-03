<?php
session_start();
require '../php/db.php';

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    // Optionally, store intended URL to redirect after login
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: ../php1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch cart items
$stmt = $conn->prepare("
    SELECT c.product_id, c.quantity, p.product_name, p.price, p.image_url
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($cart_items)) {
    echo "<p style='text-align:center; margin-top:50px;'>Your cart is empty. <a href='../php1/index.php'>Go shopping</a></p>";
    exit();
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clothify - Checkout</title>
<link rel="stylesheet" href="../css/checkout.css">
<link rel="stylesheet" href="../css/style.css">

<script>
document.addEventListener('DOMContentLoaded', () => {
    const totalPHP = <?= json_encode($total) ?>;
    const shippingSelect = document.getElementById("shipping_address");
    const shippingFeeEl = document.getElementById("shipping_fee");
    const taxEl = document.getElementById("tax");
    const grandTotalEl = document.getElementById("grand_total");
    const inputShipping = document.getElementById("input_shipping_fee");
    const inputTax = document.getElementById("input_tax");
    const inputGrandTotal = document.getElementById("input_grand_total");

    function calculateTotal() {
        const city = shippingSelect.value.trim().toLowerCase();
        let shippingFee = 80; // default

        switch(city) {
            case 'kathmandu': shippingFee = 50; break;
            case 'pokhara': shippingFee = 70; break;
            case 'bhaktapur': shippingFee = 60; break;
            case 'lalitpur': shippingFee = 55; break;
        }

        const tax = 0.13 * totalPHP;
        const grandTotal = totalPHP + tax + shippingFee;

        shippingFeeEl.textContent = shippingFee.toFixed(2);
        taxEl.textContent = tax.toFixed(2);
        grandTotalEl.textContent = grandTotal.toFixed(2);

        inputShipping.value = shippingFee.toFixed(2);
        inputTax.value = tax.toFixed(2);
        inputGrandTotal.value = grandTotal.toFixed(2);
    }

    shippingSelect.addEventListener('change', calculateTotal);
    calculateTotal();
});
</script>
</head>
<body>
<header>
     <div class="logo"><h1>Clothify</h1></div>

    <nav class="main-nav">
        <ul class="menu">
            <li><a href="../php1/index.php">Home</a></li>
            <li><a href="../php1/category.php">Category</a></li>
            <li><a href="../php1/logout.php">Log Out</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <i class="fa-regular fa-heart" onclick="location.href='../php1/wishlist.php'"></i>
            <i class="fa fa-shopping-cart" onclick="location.href='../php1/cart.php'"></i>
        </div>
</div>
</div>
</header>

<div class="main-content">
    <center><h2>Review Your Order</h2></center>
    <form method="post" action="esewa_payment.php" id="checkout-form">
        <table>
            <tr>
                <th>Image</th>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
            <?php foreach($cart_items as $item): 
                $subtotal = $item['price'] * $item['quantity']; ?>
                <tr>
                    <td>
                        <img src="<?= file_exists("../uploads/" . $item['image_url']) ? "../uploads/" . htmlspecialchars($item['image_url']) : "../default/default.png" ?>" width="80" alt="">
                    </td>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td>Rs. <?= number_format($item['price'],2) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>Rs. <?= number_format($subtotal,2) ?></td>
                </tr>
                <input type="hidden" name="product_id[]" value="<?= $item['product_id'] ?>">
                <input type="hidden" name="quantity[]" value="<?= $item['quantity'] ?>">
                <input type="hidden" name="sub_total[]" value="<?= $subtotal ?>">
            <?php endforeach; ?>

            <tr>
                <td colspan="5">
                    <h3>Shipping Address</h3>
                    <select name="shipping_address" id="shipping_address" required style="height:40px; width:50%;">
                        <option value="">Select your city</option>
                        <option value="kathmandu">Kathmandu</option>
                        <option value="pokhara">Pokhara</option>
                        <option value="bhaktapur">Bhaktapur</option>
                        <option value="lalitpur">Lalitpur</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <p>Total</p>
                    <p>Shipping Fee</p>
                    <p>Tax (13%)</p>
                    <p><strong>Grand Total</strong></p>
                </td>
                <td colspan="2">
                    <p>: Rs. <span id="total"><?= number_format($total,2) ?></span></p>
                    <p>: Rs. <span id="shipping_fee">0.00</span></p>
                    <p>: Rs. <span id="tax">0.00</span></p>
                    <p><strong>: Rs. <span id="grand_total">0.00</span></strong></p>

                    <input type="hidden" name="total" value="<?= $total ?>">
                    <input type="hidden" name="calculated_shipping_fee" id="input_shipping_fee">
                    <input type="hidden" name="calculated_tax" id="input_tax">
                    <input type="hidden" name="calculated_grand_total" id="input_grand_total">
                </td>
            </tr>

            <tr>
                <td colspan="5">
                    <h3>Payment Method</h3>
                    <label><input type="radio" name="payment_method" value="cash_on_delivery" required> Cash on Delivery</label><br>
                    <label><input type="radio" name="payment_method" value="khalti"> Khalti</label><br><br>
                    <button type="submit" class="buy-btn">Place Order</button>
                </td>
            </tr>
        </table>
    </form>
</div>
</body>
</html>
