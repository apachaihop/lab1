<?php
include 'connection.php';
include '../header.php';
$conn = getConnection();

$sql = "SELECT title, description, status FROM PullRequests";
$result = $conn->query($sql);

echo "<h1>Pull Requests</h1>";
if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='thead-light'>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["title"]. "</td>
                <td>" . $row["description"]. "</td>
                <td>" . $row["status"]. "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning' role='alert'>No pull requests found.</div>";
}

closeConnection($conn);
include '../footer.php';
?>