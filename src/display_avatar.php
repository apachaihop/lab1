<?php
include 'connection.php';
session_start();

if (isset($_GET['user_id'])) {
    try {
        $conn = getConnection();
        $userId = intval($_GET['user_id']);

        $stmt = $conn->prepare("SELECT avatar_data, avatar_type FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Check if user exists and has avatar data
        if ($user && $user['avatar_data']) {
            // Validate image data
            if (@getimagesizefromstring($user['avatar_data'])) {
                header("Content-Type: " . $user['avatar_type']);
                echo $user['avatar_data'];
                exit;
            }
        }

        // If we get here, either no avatar or invalid image data
        header("Content-Type: image/png");
        echo file_get_contents(__DIR__ . '/../assets/default_avatar.png');
    } catch (Exception $e) {
        header("Content-Type: image/png");
        echo file_get_contents(__DIR__ . '/../assets/default_avatar.png');
    }
}
