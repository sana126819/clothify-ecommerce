<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone_number"]);
    $address = trim($_POST["address"]);
    $role = 'consumer'; // fixed role

    // Server-side validations
    if(strlen($password) < 6 || !preg_match("/[0-9]/", $password)) {
        die("<script>alert('Password must be at least 6 characters and include a number'); window.history.back();</script>");
    }

    if($password !== $confirm_password) {
        die("<script>alert('Passwords do not match'); window.history.back();</script>");
    }

    if(!preg_match('/^\+?(977)?98[0-9]{8}$/', $phone)) {
        die("<script>alert('Invalid phone number'); window.history.back();</script>");
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Check duplicate username/email
    $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if($count > 0) {
        echo "<script>alert('Username or email already exists'); window.history.back();</script>";
        exit;
    }

    // Insert user
    $insert = $conn->prepare("
    INSERT INTO users (username, email, password_hash, full_name, phone_number, city, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
");
$insert->bind_param("ssssss", $username, $email, $password_hash, $full_name, $phone, $city);

    if($insert->execute()) {
        echo "<script>alert('Registration successful!'); window.location.href = 'login.php';</script>";
    } else {
        echo "<script>alert('Error: Could not register'); window.history.back();</script>";
    }
}
?>
