<?php
include 'connection.php';
include 'FileHandler.php';

if (!isset($_GET['repo_id']) || !isset($_GET['file_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $fileHandler = new FileHandler();
    $repoId = $_GET['repo_id'];
    $fileName = $_GET['file_name'];
    $filePath = $repoId . '/' . $fileName;

    $isPDF = $fileHandler->isPDF($filePath);

    if ($isPDF && !$fileHandler->isValidPDF($filePath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid PDF file']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'isPDF' => $isPDF
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
