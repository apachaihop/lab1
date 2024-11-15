<?php
session_start();
include '../connection.php';

// Clear remember me token if it exists
if (isset($_COOKIE['remember_me'])) {
    try {
        $conn = getConnection();
        list($selector,) = explode(':', $_COOKIE['remember_me']);

        // Delete token from database
        $stmt = $conn->prepare("DELETE FROM RememberMeTokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();

        // Clear the cookie
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    } catch (Exception $e) {
        // Log error if needed
    } finally {
        if (isset($conn)) {
            closeConnection($conn);
        }
    }
}

session_unset();
session_destroy();
header("Location: /lab1/index.php");
exit();
