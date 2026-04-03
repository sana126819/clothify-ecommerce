<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location:login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? null;
$success_msg = $error_msg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profile Information
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        $city = $_POST['city'] ?? '';
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone_number = ?, city = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $full_name, $phone_number, $city, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $full_name ?: $user_name;
            $success_msg = "Profile updated successfully!";
        } else {
            $error_msg = "Error updating profile!";
        }
        $stmt->close();
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Get current password hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
        
        // Verify current password
        if (!password_verify($current_password, $hashed_password)) {
            $error_msg = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $error_msg = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_msg = "Password must be at least 6 characters!";
        } else {
            // Update password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Error changing password!";
            }
            $stmt->close();
        }
    }
}

// Get user details
$user = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

// Get order statistics
$order_stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(total_price) as total_spent
    FROM orders 
    WHERE user_id = $user_id
")->fetch_assoc();

// Get wishlist count
$wishlist_count = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE user_id = $user_id")->fetch_assoc()['count'];

// Get cart count
$cart_count = $conn->query("SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id")->fetch_assoc()['count'];

function e($x){ return htmlspecialchars($x,ENT_QUOTES,'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Clothify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .profile-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB, #2C3E50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .profile-details h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .profile-details p {
            color: var(--text-light);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--secondary-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--text-light);
            font-weight: 500;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }

        .profile-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .profile-nav {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 0.8rem 1.5rem;
            background: var(--light-color);
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: var(--secondary-color);
            color: white;
        }

        .nav-link.active {
            background: var(--secondary-color);
            color: white;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .tab-btn {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-light);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .tab-btn:hover {
            color: var(--secondary-color);
        }

        .tab-btn.active {
            color: var(--secondary-color);
            border-bottom-color: var(--secondary-color);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            <li><a href="contact.php"><i class="fas fa-phone"></i> Contact</a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="header-icons">
            <div class="icon-wrapper" onclick="location.href='wishlist.php'" title="Wishlist">
                <i class="fas fa-heart"></i>
                <span class="count" id="wishlist-count"><?= $wishlist_count ?></span>
            </div>
            <div class="icon-wrapper" onclick="location.href='cart.php'" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="cart-count"><?= $cart_count ?></span>
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

<div class="profile-container">
    <!-- Success/Error Messages -->
    <?php if($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= e($success_msg) ?>
        </div>
    <?php endif; ?>
    
    <?php if($error_msg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= e($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-info">
            <div class="profile-avatar">
                <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
            </div>
            <div class="profile-details">
                <h1><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h1>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                <?php if($user['phone_number']): ?>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone_number']) ?></p>
                <?php endif; ?>
                <?php if($user['city']): ?>
                    <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['city']) ?></p>
                <?php endif; ?>
                <p><i class="fas fa-calendar"></i> Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="profile-stats">
            <a href="user_orders.php" class="stat-card">
                <h3><?= $order_stats['total_orders'] ?? 0 ?></h3>
                <p>Total Orders</p>
            </a>
            <a href="user_orders.php?filter=completed" class="stat-card">
                <h3><?= $order_stats['completed_orders'] ?? 0 ?></h3>
                <p>Completed Orders</p>
            </a>
            <div class="stat-card">
                <h3>Rs <?= number_format($order_stats['total_spent'] ?? 0, 2) ?></h3>
                <p>Total Spent</p>
            </div>
            <a href="wishlist.php" class="stat-card">
                <h3><?= $wishlist_count ?></h3>
                <p>Wishlist Items</p>
            </a>
        </div>

        <!-- Navigation -->
        <div class="profile-nav">
            <a href="user_profile.php" class="nav-link active">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="user_orders.php" class="nav-link">
                <i class="fas fa-shopping-bag"></i> My Orders
            </a>
            <a href="wishlist.php" class="nav-link">
                <i class="fas fa-heart"></i> Wishlist
            </a>
            <a href="cart.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i> Cart
            </a>
        </div>
    </div>

    <!-- Tabs for different actions -->
    <div class="tabs">
        <button class="tab-btn active" onclick="openTab('profile-tab')">
            <i class="fas fa-user-edit"></i> Edit Profile
        </button>
        <button class="tab-btn" onclick="openTab('password-tab')">
            <i class="fas fa-key"></i> Change Password
        </button>
    </div>

    <!-- Edit Profile Tab -->
    <div id="profile-tab" class="tab-content active">
        <div class="profile-section">
            <h2 class="section-title">Edit Profile Information</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?= e($user['full_name'] ?? '') ?>" 
                           placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" value="<?= e($user['email']) ?>" 
                           disabled style="background: #f8f9fa; cursor: not-allowed;">
                    <small style="color: #6c757d;">Email cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" 
                           value="<?= e($user['phone_number'] ?? '') ?>" 
                           placeholder="Enter your phone number">
                </div>
                
                <input type="hidden" name="update_profile" value="1">
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Tab -->
    <div id="password-tab" class="tab-content">
        <div class="profile-section">
            <h2 class="section-title">Change Password</h2>
            
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" 
                           placeholder="Enter current password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" 
                           placeholder="Enter new password" required
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength" id="passwordStrength"></div>
                    <small style="color: #6c757d;">Password must be at least 6 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm new password" required>
                </div>
                
                <input type="hidden" name="change_password" value="1">
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">
                        <i class="fas fa-redo"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="profile-section" style="margin-top: 2rem;">
        <h2 class="section-title">Quick Actions</h2>
        <div class="btn-group">
            <a href="user_orders.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> View All Orders
            </a>
            <a href="wishlist.php" class="btn btn-primary">
                <i class="fas fa-heart"></i> View Wishlist
            </a>
            <a href="cart.php" class="btn btn-primary">
                <i class="fas fa-shopping-cart"></i> View Cart
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Tab functionality
function openTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => tab.classList.remove('active'));
    
    // Deactivate all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show the selected tab content
    document.getElementById(tabName).classList.add('active');
    
    // Activate the clicked tab button
    event.currentTarget.classList.add('active');
}

// Password strength checker
function checkPasswordStrength(password) {
    const strength = document.getElementById('passwordStrength');
    if (password.length === 0) {
        strength.textContent = '';
        return;
    }
    
    let score = 0;
    if (password.length >= 6) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    if (score <= 1) {
        strength.textContent = 'Weak';
        strength.className = 'password-strength strength-weak';
    } else if (score <= 3) {
        strength.textContent = 'Medium';
        strength.className = 'password-strength strength-medium';
    } else {
        strength.textContent = 'Strong';
        strength.className = 'password-strength strength-strong';
    }
}

// Reset profile form
function resetForm() {
    document.getElementById('full_name').value = '<?= e($user['full_name'] ?? '') ?>';
    document.getElementById('phone_number').value = '<?= e($user['phone_number'] ?? '') ?>';
}

// Reset password form
function resetPasswordForm() {
    document.getElementById('current_password').value = '';
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('passwordStrength').textContent = '';
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Profile form validation
    const profileForm = document.querySelector('form[action*="update_profile"]');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            if (!fullName) {
                e.preventDefault();
                alert('Full name is required!');
                document.getElementById('full_name').focus();
                return false;
            }
        });
    }
    
    // Password form validation
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const currentPass = document.getElementById('current_password').value;
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (!currentPass || !newPass || !confirmPass) {
                e.preventDefault();
                alert('All password fields are required!');
                return false;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters!');
                document.getElementById('new_password').focus();
                return false;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New passwords do not match!');
                document.getElementById('confirm_password').focus();
                return false;
            }
        });
    }
});
</script>
<script src="../js/main.js"></script>
</body>
</html>