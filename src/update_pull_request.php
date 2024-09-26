<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = htmlspecialchars($_POST['id']);
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $status = htmlspecialchars($_POST['status']);

    $sql = "UPDATE PullRequests SET title = ?, description = ?, status = ? WHERE pr_id = ?";
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $stmt->bind_param("sssi", $title, $description, $status, $id);
    } else {
        $stmt->bind_param("sssii", $title, $description, $status, $id, $_SESSION['user_id']);
    }

    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: pull_requests.php");
exit();
?>