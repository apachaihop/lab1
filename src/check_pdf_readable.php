<?php
include 'connection.php';
include 'FileHandler.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['repo_id']) || !isset($_GET['file_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    $conn = getConnection();
    $repoId = intval($_GET['repo_id']);
    $fileName = htmlspecialchars($_GET['file_name']);

    // Verify user access (similar to display_repo_file.php)
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Please log in to access files']);
        exit();
    }

    $fileHandler = new FileHandler();
    $filePath = $repoId . '/' . $fileName;

    if (!$fileHandler->isPDF($filePath)) {
        echo json_encode(['readable' => false, 'error' => 'Not a PDF file']);
        exit();
    }

    $isReadable = $fileHandler->isPDFReadable($filePath);
    echo json_encode(['readable' => $isReadable]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
