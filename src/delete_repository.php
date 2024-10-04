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

    $sql = "DELETE FROM Repositories WHERE repo_id = ?";
    if (!$_SESSION['is_admin']) {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    if ($_SESSION['is_admin']) {
        $stmt->bind_param("i", $repo_id);
    } else {
        $stmt->bind_param("ii", $repo_id, $_SESSION['user_id']);
    }

    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_repository_id'])) {
        $conn = getConnection();
        $repo_id = intval($_POST['delete_repository_id']);
        $user_id = $_SESSION['user_id'];

        // Verify ownership
        $stmt = $conn->prepare("SELECT repo_id FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $repo_id, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            $deleteStmt = $conn->prepare("DELETE FROM Repositories WHERE repo_id = ?");
            $deleteStmt->bind_param("i", $repo_id);
            if ($deleteStmt->execute()) {
                $deleteStmt->close();
                closeConnection($conn);
                header("Location: my_repositories.php?success=1");
                exit();
            } else {
                throw new Exception("Error deleting repository: " . $deleteStmt->error);
            }
        } else {
            throw new Exception("Unauthorized action.");
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    // Optionally, redirect back with error message
    header("Location: my_repositories.php?error=" . urlencode($error));
    exit();
}
