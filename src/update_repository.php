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
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);

    $sql = "UPDATE Repositories SET name = ?, description = ? WHERE repo_id = ?";
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $stmt->bind_param("ssi", $name, $description, $repo_id);
    } else {
        $stmt->bind_param("ssii", $name, $description, $repo_id, $_SESSION['user_id']);
    }

    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: repositories.php");
exit();
?>