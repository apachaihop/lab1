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

    if (!isset($_FILES['avatar'])) {
        throw new Exception("No file was submitted");
    }

    switch ($_FILES['avatar']['error']) {
        case UPLOAD_ERR_OK:
            $fileHandler->saveAvatar($conn, $_SESSION['user_id'], $_FILES['avatar']);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Avatar updated successfully'];
            header("Location: /lab1/index.php");
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception("File is too large. Maximum size allowed is " . ini_get('upload_max_filesize'));
        case UPLOAD_ERR_PARTIAL:
            throw new Exception("The file was only partially uploaded");
        case UPLOAD_ERR_NO_FILE:
            throw new Exception("No file was uploaded");
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new Exception("Missing temporary folder");
        case UPLOAD_ERR_CANT_WRITE:
            throw new Exception("Failed to write file to disk");
        default:
            throw new Exception("Unknown error occurred during upload");
    }
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header("Location: /lab1/index.php");
}
exit();
