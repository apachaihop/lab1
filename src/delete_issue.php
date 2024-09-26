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
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM Issues WHERE issue_id = ? AND user_id = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("ii", $issue_id, $user_id);
    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: issues.php");
exit();
?>