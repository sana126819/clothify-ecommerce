<?php
session_start();

// Debug: Uncomment to see session data
// echo "<pre>"; print_r($_SESSION); echo "</pre>";

// Check if all required session data exists
if (!isset($_SESSION['total_amount']) || !isset($_SESSION['username']) || 
    !isset($_SESSION['email']) || !isset($_SESSION['phone'])) {
    
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "Missing payment information",
            showConfirmButton: false,
            timer: 1500
        });
    </script>';
    header("Location: checkout1.php");
    exit();
}

// Get values from session
$amount = (int)($_SESSION['total_amount'] * 100); // Convert to paisa AND ensure integer
$purchase_order_id = 'PO-' . strtoupper(uniqid());
$purchase_order_name = "Clothify Order";
$name = $_SESSION['username'];
$email = $_SESSION['email'];
$phone = $_SESSION['phone'];

// Validate data
if(empty($amount) || empty($name) || empty($email) || empty($phone)){
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "All fields are required",
            showConfirmButton: false,
            timer: 1500
        });
    </script>';
    header("Location: checkout.php");
    exit();
}

// Check if the amount is valid (minimum 100 paisa = 1 NPR)
if($amount < 100){
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "Minimum amount is 1 NPR",
            showConfirmButton: false,
            timer: 1500
        });
    </script>';
    header("Location: checkout.php");
    exit();
}

// Check if the phone number is valid (Nepali format)
if(!preg_match('/^(98|97|96)[0-9]{8}$/', $phone)){
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "Invalid phone number",
            text: "Please enter a valid Nepali mobile number",
            showConfirmButton: false,
            timer: 1500
        });
    </script>';
    header("Location: checkout1.php");
    exit();
}

// Check if the email is valid
if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "Email is not valid",
            showConfirmButton: false,
            timer: 1500
        });
    </script>';
    header("Location: checkout1.php");
    exit();
}

// Prepare data for Khalti API
$postFields = array(
    "return_url" => "http://localhost/clothify/payment/place_order_online.php",
    "website_url" => "http://localhost/clothify/php1/index.php",
    "amount" => $amount, // Should now be a clean integer
    "purchase_order_id" => $purchase_order_id,
    "purchase_order_name" => $purchase_order_name,
    "customer_info" => array(
        "name" => $name,
        "email" => $email,
        "phone" => $phone
    )
);

// Debug: Check the final data
// echo "<pre>Final data to Khalti: "; print_r($postFields); echo "</pre>";

$jsonData = json_encode($postFields);

// Call Khalti API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://a.khalti.com/api/v2/epayment/initiate/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => array(
        'Authorization: key live_secret_key_68791341fdd94846a146f0457ff7b455',
        'Content-Type: application/json',
    ),
));

$response = curl_exec($curl);

if (curl_errno($curl)) {
    // Handle curl error
    $_SESSION["validate_msg"] = '<script>
        Swal.fire({
            icon: "error",
            title: "Payment gateway error",
            text: "' . curl_error($curl) . '",
            showConfirmButton: false,
            timer: 2000
        });
    </script>';
    header("Location: checkout.php");
    exit();
} else {
    $responseArray = json_decode($response, true);
    
    // Debug: Check Khalti response
    // echo "<pre>Khalti response: "; print_r($responseArray); echo "</pre>";
    
    if (isset($responseArray['error'])) {
        // Handle Khalti error
        $_SESSION["validate_msg"] = '<script>
            Swal.fire({
                icon: "error",
                title: "Payment Failed",
                text: "' . $responseArray['error'] . '",
                showConfirmButton: false,
                timer: 2000
            });
        </script>';
        header("Location: checkout1.php");
        exit();
    } elseif (isset($responseArray['payment_url'])) {
        // Redirect to Khalti payment page
        header('Location: ' . $responseArray['payment_url']);
        exit;
    } else {
        // Unexpected response
        $_SESSION["validate_msg"] = '<script>
            Swal.fire({
                icon: "error",
                title: "Payment Error",
                text: "Unexpected response from payment gateway",
                showConfirmButton: false,
                timer: 2000
            });
        </script>';
        header("Location: checkout1.php");
        exit();
    }
}

curl_close($curl);
?>