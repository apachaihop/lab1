<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO Repositories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    $stmt->close();
}

$sql = "SELECT name, description FROM Repositories";
$result = $conn->query($sql);

echo "<h1>Repositories</h1>";

echo "<form method='post' action=''>
        <div class='form-group'>
            <label for='name'>Name:</label>
            <input type='text' class='form-control' id='name' name='name' required>
        </div>
        <div class='form-group'>
            <label for='description'>Description:</label>
            <input type='text' class='form-control' id='description' name='description' required>
        </div>
        <button type='submit' class='btn btn-primary'>Add Repository</button>
      </form>";

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
    echo "<div class='alert alert-warning mt-2' role='alert'>No repositories found.</div>";
}

closeConnection($conn);
include '../includes/footer.php';
?>