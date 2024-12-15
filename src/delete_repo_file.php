<?php
include 'connection.php';
include 'FileHandler.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log("Delete repo file failed: User not authenticated");
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Delete repo file failed: Invalid request method " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $conn = getConnection();
    $repoId = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
    $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
    $userId = $_SESSION['user_id'];

    error_log("Attempting to delete file. RepoID: $repoId, FileName: $fileName, UserID: $userId");

    // Verify repository ownership
    $stmt = $conn->prepare("SELECT repo_id FROM Repositories WHERE repo_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $repoId, $userId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        error_log("Delete repo file failed: Unauthorized access. User $userId does not own repository $repoId");
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    $stmt->close();

    // Delete the file
    $fileHandler = new FileHandler();
    error_log("Calling FileHandler->deleteRepoFile with repoId: $repoId, fileName: $fileName");

    try {
        $success = $fileHandler->deleteRepoFile($conn, $repoId, $fileName);
        error_log("FileHandler->deleteRepoFile result: " . ($success ? "success" : "failure"));
    } catch (Exception $e) {
        error_log("FileHandler->deleteRepoFile threw exception: " . $e->getMessage());
        throw $e;
    }

    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error in delete_repo_file.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

closeConnection($conn);
