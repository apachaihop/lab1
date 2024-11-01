<?php
include 'connection.php';
include 'FileHandler.php';
session_start();

if (isset($_GET['repo_id']) && isset($_GET['file_name'])) {
    try {
        $conn = getConnection();
        $repoId = intval($_GET['repo_id']);
        $fileName = htmlspecialchars($_GET['file_name']);

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
            header("HTTP/1.0 403 Forbidden");
            exit("Access denied");
        }

        $fileHandler = new FileHandler();
        $fileContent = $fileHandler->getRepoFile($repoId . '/' . $fileName);

        // Update the content type header
        header('Content-Type: text/plain; charset=UTF-8');
        echo $fileContent;
    } catch (Exception $e) {
        header("HTTP/1.0 500 Internal Server Error");
        echo "Error: " . $e->getMessage();
    }
}
