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
            // Get file content first to check if it's readable
            $fileContent = $fileHandler->getRepoFile($filePath);

            // Validate PDF and check content before setting headers
            if (!$fileHandler->isValidPDF($filePath) || empty($fileContent)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'Cannot open PDF file. The file might be corrupted or inaccessible.']);
                exit();
            }

            // If we get here, the PDF is valid and readable
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
            $fileContent = $fileHandler->getRepoFile($filePath);
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
