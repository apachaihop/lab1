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


    $fileHandler = new FileHandler();
    $filePath = $repoId . '/' . $fileName;

    try {
        $fileContent = $fileHandler->getRepoFile($filePath);

        if ($fileHandler->isPDF($filePath)) {
            // For PDFs, first verify we can actually read the content and it's valid
            if (!is_readable($fileHandler->repoFilesPath . $filePath)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. You do not have permission to view this file.']);
                exit();
            }

            if (empty($fileContent) || !$fileHandler->isValidPDF($filePath)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or corrupted PDF file. The file cannot be displayed.']);
                exit();
            }

            // If PDF content is readable and valid, proceed with display
            header_remove();
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($fileContent));
            header('Content-Disposition: inline; filename="' . basename($fileName) . '"');

            // Clear any buffered output
            if (ob_get_level()) {
                ob_end_clean();
            }

            print($fileContent);
            exit();
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo $fileContent;
        }
    } catch (Exception $e) {
        // Reset content type to JSON for error responses
        header('Content-Type: application/json');

        if (strpos($e->getMessage(), 'permission') !== false || !is_readable($fileHandler->repoFilesPath . $filePath)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Access denied. You do not have permission to view this file.',
                'type' => 'permission_error'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error reading file',
                'message' => $e->getMessage(),
                'type' => 'file_access_error'
            ]);
        }
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
