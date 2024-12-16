<?php
// src/log_avatar_error.php
header('Content-Type: application/json');

// Get the raw POST data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if ($data) {
    // Log the error with timestamp
    error_log(sprintf(
        "Avatar System Error - Time: %s, Error: %s",
        $data['timestamp'],
        $data['error']
    ));

    // You could also store this in a database table for tracking

    echo json_encode(['status' => 'logged']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data received']);
}
