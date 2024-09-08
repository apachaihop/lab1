<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

$sql = "SELECT name, description FROM Repositories";
$result = $conn->query($sql);

echo "<h1>Repositories</h1>";
if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='thead-light'>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["name"]. "</td>
                <td>" . $row["description"]. "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning' role='alert'>No repositories found.</div>";
}

closeConnection($conn);
include '../includes/footer.php';
?>