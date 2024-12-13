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

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Please log in to access files']);
        exit();
    }

    // Check if file exists in database
    $fileStmt = $conn->prepare("SELECT file_path FROM RepositoryFiles WHERE repo_id = ? AND file_name = ?");
    $fileStmt->bind_param("is", $repoId, $fileName);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();

    if ($fileResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit();
    }

    // Check if user has access to this repository
    $stmt = $conn->prepare("
        SELECT R.repo_id 
        FROM Repositories R
        LEFT JOIN RepositorySubscriptions RS ON R.repo_id = RS.repo_id AND RS.user_id = ?
        WHERE R.repo_id = ? AND (R.user_id = ? OR RS.repo_id IS NOT NULL)
    ");
    $stmt->bind_param("iii", $_SESSION['user_id'], $repoId, $_SESSION['user_id']);
    $stmt->execute();
    $hasAccess = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to access this file']);
        exit();
    }

    $fileHandler = new FileHandler();
    $filePath = $repoId . '/' . $fileName;

    if (!$fileHandler->isPDF($filePath)) {
        echo json_encode([
            'readable' => false,
            'error' => 'Not a PDF file'
        ]);
        exit();
    }

    $isReadable = $fileHandler->isPDFReadable($filePath);
    if (!$isReadable) {
        echo json_encode([
            'readable' => false,
            'error' => 'PDF file is not accessible or may be corrupted'
        ]);
        exit();
    }

    echo json_encode([
        'readable' => true,
        'success' => true
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
