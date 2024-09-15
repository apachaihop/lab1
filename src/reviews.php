<?php
include 'connection.php';
include '../includes/header.php';
try{
$conn = getConnection();
$sql = "SELECT review FROM Reviews";
$result = $conn->query($sql);

echo "<h1>Reviews</h1>";
if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='thead-light'>
                <tr>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["review"]. "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning mt-2' role='alert'>No reviews found.</div>";
}

closeConnection($conn);
}
catch(Exception $e){
    $errorMessage = $e->getMessage();
    echo "<div class='alert alert-danger mt-2' role='alert'>An error occurred: " . $errorMessage . "</div>";
}
include '../includes/footer.php';
?>