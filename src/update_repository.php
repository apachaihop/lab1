<?php
include 'connection.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $repo_id = htmlspecialchars($_POST['repo_id']);
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);

    $stmt = $conn->prepare("UPDATE Repositories SET name = ?, description = ? WHERE repo_id = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("ssi", $name, $description, $repo_id);
    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: repositories.php");
exit();
?>