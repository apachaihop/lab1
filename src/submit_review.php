<?php
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $review = $_POST['review'];
    if (empty($review)) {
        echo "Review cannot be empty.";
        exit;
    }
    $conn = getConnection();

    $stmt = $conn->prepare("INSERT INTO Reviews (review) VALUES (?)");
    $stmt->bind_param("s",$review);

    if ($stmt->execute()) {
        echo "Review submitted successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    closeConnection($conn);
}
?>