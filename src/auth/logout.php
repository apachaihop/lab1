<?php
session_start();

// Clear cookies if they exist
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('is_admin', '', time() - 3600, '/');
}

session_unset();
session_destroy();
header("Location: /lab1/index.php");
exit();
