<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Failure</title>
  <style>
    /* ------------------ COLOR PALETTE ------------------ */
    :root {
      --bg: #F7F6F2;        /* soft background */
      --text: #2C3E50;      /* main text */
      --primary: #8FAACB;   /* main buttons */
      --primary-hover: #B0C9D6; /* hover */
      --accent-pink: #F5D6D6;   /* soft highlight */
      --error: #D96C6C;     /* soft red for error */
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
      color: var(--error);
      font-size: 2em;
      margin-bottom: 20px;
    }

    .success-container p {
      font-size: 1.1em;
      color: var(--text);
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
      transition: background-color 0.3s;
    }

    .btn:hover {
      background-color: var(--primary-hover);
    }
  </style>
</head>
<body>
  <div class="success-container">
    <h1>❌ Order Failed</h1>
    <p>Your payment did not go through. Please try again.</p>

    <a href="../php1/cart.php" class="btn">Back to Cart</a>
  </div>
</body>
</html>
