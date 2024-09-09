<?php
include 'connection.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $repo_id = htmlspecialchars($_POST['repo_id']);

    $stmt = $conn->prepare("DELETE FROM Repositories WHERE repo_id = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("i", $repo_id);
    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

closeConnection($conn);

header("Location: repositories.php");
exit();
?>