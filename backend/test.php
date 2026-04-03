<?php
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<pre>SESSION:";
print_r($_SESSION);
echo "</pre>";

echo "<p>User logged in? ";
echo (isset($_SESSION['user_id']) ? "YES" : "NO");
echo "</p>";
