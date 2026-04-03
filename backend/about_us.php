<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

$userCart = $user_id 
    ? array_column($conn->query("SELECT product_id FROM cart WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC),'product_id') 
    : array_keys($_SESSION['cart']);

$userWishlist = $user_id
    ? array_column($conn->query("SELECT product_id FROM wishlist WHERE user_id=$user_id")->fetch_all(MYSQLI_ASSOC),'product_id')
    : array_keys($_SESSION['wishlist']);

function e($x){ return htmlspecialchars($x,ENT_QUOTES,'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        /* Simple styling for About page */
        .page-header {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.95)),
                        url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 5rem 2rem;
            text-align: center;
        }

        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* About Section */
        .about-section {
            padding: 4rem 2rem;
            background: white;
            max-width: 1200px;
            margin: 0 auto;
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .about-image {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        .about-text h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .about-text p {
            margin-bottom: 1rem;
            line-height: 1.6;
            color: var(--text-color);
        }

        .highlight-box {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
            border-left: 4px solid var(--secondary-color);
        }

        .highlight-box h3 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        /* Contact Section */
        .contact-section {
            padding: 4rem 2rem;
            background: var(--light-color);
        }

        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .contact-info h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }

        .contact-details {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .contact-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .contact-icon {
            width: 40px;
            height: 40px;
            background: var(--secondary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .contact-content h3 {
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            color: var(--primary-color);
        }

        .contact-content p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .contact-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .contact-form h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            padding: 0.8rem;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: #2980b9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 3rem 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .about-content,
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .about-section,
            .contact-section {
                padding: 3rem 1rem;
            }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo">
        <h1><i class="fas fa-tshirt"></i> Clothify</h1>
    </div>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="product.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
            <li><a href="about_us.php" class="active"><i class="fas fa-phone"></i> About Us</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= count($userWishlist) ?></span>
            </div>
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="cart-count"><?= count($userCart) ?></span>
            </div>
            
            <?php if($user_id): ?>
                <div class="user-dropdown">
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span class="user-name"><?= e($user_name) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <a href="user_profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="user_orders.php"><i class="fas fa-box"></i> Orders</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-buttons">
                    <a href="login.php" class="btn-login">Login</a>
                    <a href="register.php" class="btn-signup">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>

        <form class="search-bar" action="search.php" method="GET">
            <input type="text" name="q" placeholder="Search products..." required>
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</header>

<!-- PAGE HEADER -->
<section class="page-header">
    <h1>About Clothify</h1>
    <p>A college project showcasing e-commerce for clothing items</p>
</section>

<!-- ABOUT SECTION -->
<section class="about-section">
    <div class="about-content">
        <div class="about-image">
            <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Clothify Clothing Store">
        </div>
        <div class="about-text">
            <h2>What is Clothify?</h2>
            <p>Clothify is a college project that simulates a real e-commerce website for selling various clothing items. This project demonstrates the implementation of a complete online shopping platform.</p>
            
            <div class="highlight-box">
                <h3>Project Features:</h3>
                <p>• User authentication system (Login/Register)</p>
                <p>• Product browsing and search functionality</p>
                <p>• Shopping cart and wishlist system</p>
                <p>• Product recommendation algorithm</p>
                <p>• Order management system</p>
                <p>• Admin panel for product management</p>
            </div>
            
            <p>The project uses PHP for backend logic, MySQL for database, and modern HTML/CSS/JavaScript for the frontend interface.</p>
            
            <p>Our recommendation algorithm suggests products based on your browsing history and what other users with similar interests have purchased, creating a personalized shopping experience.</p>
        </div>
    </div>
</section>

<!-- CONTACT SECTION -->
<section class="contact-section">
    <div class="contact-container">
        <div class="contact-info">
            <h2>Contact Information</h2>
            <div class="contact-details">
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-content">
                        <h3>Address</h3>
                        <p>Kathmandu, Nepal</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-content">
                        <h3>Email</h3>
                        <p>clothify@gmail.com</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-content">
                        <h3>Project Hours</h3>
                        <p>Available 24/7</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="contact-content">
                        <h3>Note</h3>
                        <p>This is a college project for educational purposes. Not a real e-commerce store.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="contact-form">
            <h3>Send Message</h3>
            <form id="contactForm" onsubmit="return validateForm()">
                <div class="form-group">
                    <input type="text" class="form-control" placeholder="Your Name" required>
                </div>
                <div class="form-group">
                    <input type="email" class="form-control" placeholder="Your Email" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" placeholder="Subject">
                </div>
                <div class="form-group">
                    <textarea class="form-control" placeholder="Your Message" required></textarea>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features">
    <div class="feature">
        <i class="fas fa-shipping-fast"></i>
        <h3>Free Shipping</h3>
        <p>On orders over Rs 3000</p>
    </div>
    <div class="feature">
        <i class="fas fa-exchange-alt"></i>
        <h3>Easy Returns</h3>
        <p>30-day return policy</p>
    </div>
    <div class="feature">
        <i class="fas fa-shield-alt"></i>
        <h3>Secure Payment</h3>
        <p>100% secure checkout</p>
    </div>
    <div class="feature">
        <i class="fas fa-headset"></i>
        <h3>24/7 Support</h3>
        <p>Customer support</p>
    </div>
</section>

<?php include 'footer.php'; ?>

<script src="../js/main.js"></script>
<script src="../js/contact.js"></script>

</body>
</html>