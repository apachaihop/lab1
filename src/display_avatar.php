<?php
include 'connection.php';
session_start();

if (isset($_GET['user_id'])) {
    try {
        $conn = getConnection();
        $userId = intval($_GET['user_id']);
        $defaultAvatarPath = __DIR__ . '/../assets/default_avatar.png';

        // Function to serve default avatar with proper error handling
        function serveDefaultAvatar($defaultPath)
        {
            if (!file_exists($defaultPath)) {
                error_log("Default avatar not found at: " . $defaultPath);
                header("HTTP/1.1 500 Internal Server Error");
                die("Avatar system error");
            }

            if (!is_readable($defaultPath)) {
                error_log("Default avatar not readable at: " . $defaultPath);
                header("HTTP/1.1 500 Internal Server Error");
                die("Avatar system error");
            }

            $defaultData = @file_get_contents($defaultPath);
            if ($defaultData === false) {
                error_log("Failed to read default avatar at: " . $defaultPath);
                header("HTTP/1.1 500 Internal Server Error");
                die("Avatar system error");
            }

            header("Content-Type: image/png");
            echo $defaultData;
            exit;
        }

        $stmt = $conn->prepare("SELECT avatar_data, avatar_type FROM Users WHERE user_id = ?");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $conn->error);
            serveDefaultAvatar($defaultAvatarPath);
        }

        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            error_log("Failed to execute statement: " . $stmt->error);
            $stmt->close();
            serveDefaultAvatar($defaultAvatarPath);
        }

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Check if user exists and has avatar data
        if ($user && $user['avatar_data']) {
            // Validate image type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($user['avatar_type'], $allowedTypes)) {
                error_log("Invalid avatar type for user $userId: " . $user['avatar_type']);
                serveDefaultAvatar($defaultAvatarPath);
            }

            // Validate image data
            if (@getimagesizefromstring($user['avatar_data']) === false) {
                error_log("Invalid image data for user $userId");
                serveDefaultAvatar($defaultAvatarPath);
            }

            // Serve the avatar
            header("Content-Type: " . $user['avatar_type']);
            header("Cache-Control: public, max-age=3600"); // Cache for 1 hour
            echo $user['avatar_data'];
            exit;
        }

        // If we get here, serve default avatar
        serveDefaultAvatar($defaultAvatarPath);
    } catch (Exception $e) {
        error_log("Error in display_avatar.php: " . $e->getMessage());
        serveDefaultAvatar($defaultAvatarPath);
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    die("User ID not provided");
}
