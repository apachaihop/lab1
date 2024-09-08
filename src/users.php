<?php
include 'connection.php';
include '../header.php';

$conn = getConnection();

$sql = "SELECT username, email FROM Users";
$result = $conn->query($sql);

echo "<h1>Users</h1>";
if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='thead-light'>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["username"]. "</td>
                <td>" . $row["email"]. "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning' role='alert'>No users found.</div>";
}

closeConnection($conn);
include '../footer.php';
?>