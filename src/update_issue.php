<?php
include 'connection.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = htmlspecialchars($_POST['id']);
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $status = htmlspecialchars($_POST['status']);

    $stmt = $conn->prepare("UPDATE Issues SET title = ?, description = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $description, $status, $id);
    $stmt->execute();
    $stmt->close();
}

closeConnection($conn);

header("Location: issues.php");
exit();
?>