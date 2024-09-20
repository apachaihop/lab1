<?php
include 'connection.php';
include '../includes/header.php';
try{
$conn = getConnection();
$sql = "SELECT review FROM Reviews";
$result = $conn->query($sql);
}
catch (Exception $e) {
    $error = "Error: Sql connection refused";
}
echo "<h1>Reviews</h1>";
if($error)
{
    echo "<div class='alert alert-danger'> $error</div>";
}
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
include '../includes/footer.php';
?>