<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothify</title>
    <link rel="stylesheet" href="../css/register.css">
    <script>
        function check() {
            let isValid = true;
            const username = document.getElementById("user_identifier").value.trim();
            const regExpUser = /^[a-zA-Z0-9_\.]{3,20}$/;
            const regExpEmail = /^[a-zA-Z]+[a-zA-Z0-9_!#$%&’*+/=?^`{|}~-]+@[a-zA-Z]+\.[a-zA-Z]{2,}$/;

            if (!regExpUser.test(username) && !regExpEmail.test(username)) {
                alert("!!! Invalid username or email format !!!");
                isValid = false;
            }
            return isValid;
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="login1">
            <h2>Login</h2>

            <!-- Show error messages if passed in URL -->
            <?php if (isset($_GET['error'])): ?>
                <p style="color:red;">
                    <?php
                        if ($_GET['error'] === 'invalid_password') echo "Invalid password.";
                        elseif ($_GET['error'] === 'user_not_found') echo "User not found.";
                        elseif ($_GET['error'] === 'invalid_request') echo "Invalid request.";
                    ?>
                </p>
            <?php endif; ?>

            <form action="login_handle.php" method="POST" onsubmit="return check()">
                <label>Username or Email:</label>
                <input type="text" name="user_identifier" id="user_identifier" required><br>

                <label>Password:</label>
                <input type="password" name="password" id="password" required><br>

                <!-- Removed onclick that interferes with form submission -->
                <button type="submit" class="fod">Login</button>

                <div class="link-buttons">
                    <a href="register.php" class="link-btn">Register</a>
                    |
                    <a href="index.php" class="link-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
