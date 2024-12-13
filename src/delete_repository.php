<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $repo_id = htmlspecialchars($_POST['repo_id']);
    $user_id = $_SESSION['user_id'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        // First, delete all files associated with this repository
        $filesStmt = $conn->prepare("SELECT file_name FROM RepositoryFiles WHERE repo_id = ?");
        $filesStmt->bind_param("i", $repo_id);
        $filesStmt->execute();
        $filesResult = $filesStmt->get_result();

        $fileHandler = new FileHandler();
        while ($file = $filesResult->fetch_assoc()) {
            $fileHandler->deleteRepoFile($conn, $repo_id, $file['file_name']);
        }
        $filesStmt->close();

        // Delete all subscriptions for this repository
        $deleteSubscriptionsStmt = $conn->prepare("DELETE FROM RepositorySubscriptions WHERE repo_id = ?");
        $deleteSubscriptionsStmt->bind_param("i", $repo_id);
        $deleteSubscriptionsStmt->execute();
        $deleteSubscriptionsStmt->close();

        // Now, delete the repository
        $deleteRepoStmt = $conn->prepare("DELETE FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $deleteRepoStmt->bind_param("ii", $repo_id, $user_id);
        $deleteRepoStmt->execute();

        if ($deleteRepoStmt->affected_rows == 0) {
            throw new Exception("Unable to delete repository. It may not exist or you may not have permission.");
        }

        $deleteRepoStmt->close();
        $conn->commit();

        $_SESSION['success_message'] = "Repository deleted successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting repository: " . $e->getMessage();
    }
}

closeConnection($conn);

// Redirect back to the repositories page
header("Location: my_repositories.php");
exit();
