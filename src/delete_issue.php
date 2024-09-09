<?php
include 'connection.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = htmlspecialchars($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM Issues WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

closeConnection($conn);

header("Location: issues.php");
exit();
?>