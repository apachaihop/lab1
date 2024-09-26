<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $issue_id = htmlspecialchars($_POST['issue_id']);
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $status = htmlspecialchars($_POST['status']);

    $sql = "UPDATE Issues SET title = ?, description = ?, status = ? WHERE issue_id = ?";
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $stmt->bind_param("sssi", $title, $description, $status, $issue_id);
    } else {
        $stmt->bind_param("sssii", $title, $description, $status, $issue_id, $_SESSION['user_id']);
    }

    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: issues.php");
exit();
?>