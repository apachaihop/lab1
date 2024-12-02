<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $hashedTheme = hash('sha256', $theme);

    setcookie('theme', $hashedTheme, [
        'expires' => time() + 31536000, // 1 year
        'path' => '/',
        'secure' => true,
        'httponly' => false,
        'samesite' => 'Strict'
    ]);

    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false]);
}
