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
            // Log the error attempt
            error_log("Attempting to serve default avatar from: " . $defaultPath);

            if (!file_exists($defaultPath) || !is_readable($defaultPath)) {
                // Log the specific error
                $errorMsg = !file_exists($defaultPath) ?
                    "Default avatar file missing" :
                    "Default avatar file not readable";

                error_log("Avatar System Error: " . $errorMsg . " at path: " . $defaultPath);

                // Send error to monitoring system if you have one
                // notifyAdmins($errorMsg); // You would need to implement this

                // Generate a simple colored initial avatar as fallback
                header("Content-Type: image/png");

                // Create an image with error indication
                $img = imagecreatetruecolor(150, 150);
                $bgColor = imagecolorallocate($img, 220, 53, 69); // Bootstrap danger red
                $textColor = imagecolorallocate($img, 255, 255, 255);

                // Fill background
                imagefill($img, 0, 0, $bgColor);

                // Add error text
                $text = "!";
                $font = 5; // Built-in font

                // Center the text
                $textWidth = imagefontwidth($font) * strlen($text);
                $textHeight = imagefontheight($font);
                $x = (150 - $textWidth) / 2;
                $y = (150 - $textHeight) / 2;

                imagestring($img, $font, $x, $y, $text, $textColor);

                // Output the generated image
                imagepng($img);
                imagedestroy($img);

                // Also notify the client side of the error
                header("X-Avatar-Error: " . $errorMsg);
                exit;
            }

            $defaultData = @file_get_contents($defaultPath);
            if ($defaultData === false) {
                $errorMsg = "Failed to read default avatar data";
                error_log("Avatar System Error: " . $errorMsg . " at path: " . $defaultPath);

                // Similar error image generation as above
                header("Content-Type: image/png");
                $img = imagecreatetruecolor(150, 150);
                $bgColor = imagecolorallocate($img, 220, 53, 69);
                $textColor = imagecolorallocate($img, 255, 255, 255);

                imagefill($img, 0, 0, $bgColor);

                $text = "!";
                $font = 5;

                $textWidth = imagefontwidth($font) * strlen($text);
                $textHeight = imagefontheight($font);
                $x = (150 - $textWidth) / 2;
                $y = (150 - $textHeight) / 2;

                imagestring($img, $font, $text, $x, $y, $textColor);

                imagepng($img);
                imagedestroy($img);

                header("X-Avatar-Error: " . $errorMsg);
                exit;
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
