<?php
include 'connection.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_repository_id'])) {
        $conn = getConnection();
        $repo_id = intval($_POST['update_repository_id']);
        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description']));
        $user_id = $_SESSION['user_id'];

        if (empty($name) || empty($description)) {
            throw new Exception("Both Name and Description are required.");
        }

        // Verify ownership
        $stmt = $conn->prepare("SELECT repo_id FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $repo_id, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            $updateStmt = $conn->prepare("UPDATE Repositories SET name = ?, description = ? WHERE repo_id = ?");
            $updateStmt->bind_param("ssi", $name, $description, $repo_id);
            if ($updateStmt->execute()) {
                $updateStmt->close();
                closeConnection($conn);
                header("Location: my_repositories.php?success=1");
                exit();
            } else {
                throw new Exception("Error updating repository: " . $updateStmt->error);
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
