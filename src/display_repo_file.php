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
        echo json_encode(['error' => 'File not found in database']);
        exit();
    }

    $fileHandler = new FileHandler();
    $filePath = $repoId . '/' . $fileName;

    try {
        // Check if file is readable before attempting to access it
        $fileHandler->isFileReadable($filePath);

        if ($fileHandler->isPDF($filePath)) {
            error_log("File identified as PDF: " . $filePath);

            // Get file content first to validate
            $fileContent = $fileHandler->getRepoFile($filePath);

            // Check for binary content that's not a valid PDF
            $firstBytes = substr($fileContent, 0, 4);
            if ($firstBytes !== '%PDF') {
                error_log("Invalid PDF content detected. First bytes: " . bin2hex($firstBytes));
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid PDF content',
                    'details' => 'File appears to be corrupted or in wrong format'
                ]);
                exit();
            }

            // Clear any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($fileContent));
            header('Content-Disposition: inline; filename="' . basename($fileName) . '"');

            print($fileContent);
            exit();
        } else {
            // For non-PDF files, check if it's binary content
            $fileContent = $fileHandler->getRepoFile($filePath);
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/', substr($fileContent, 0, 512))) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid file format',
                    'details' => 'File appears to be binary and cannot be displayed'
                ]);
                exit();
            }

            header('Content-Type: text/plain; charset=UTF-8');
            echo $fileContent;
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'error' => 'Error accessing file',
            'message' => $e->getMessage(),
            'type' => 'file_access_error'
        ]);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
