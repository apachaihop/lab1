<?php
include 'connection.php';
include 'FileHandler.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

try {
    $conn = getConnection();
    $fileHandler = new FileHandler();

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileHandler->saveAvatar($conn, $_SESSION['user_id'], $_FILES['avatar']);
        header("Location: /lab1/index.php?success=Avatar updated successfully");
    } else {
        throw new Exception("No file uploaded or error in upload");
    }
} catch (Exception $e) {
    header("Location: /lab1/index.php?error=" . urlencode($e->getMessage()));
}
exit();
