<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO Issues (title, description, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $status);
    $stmt->execute();
    $stmt->close();
}

$sql = "SELECT title, description, status FROM Issues";
$result = $conn->query($sql);

echo "<h1>Issues</h1>";

echo "<form method='post' action=''>
        <div class='form-group'>
            <label for='title'>Title:</label>
            <input type='text' class='form-control' id='title' name='title' required>
        </div>
        <div class='form-group'>
            <label for='description'>Description:</label>
            <input type='text' class='form-control' id='description' name='description' required>
        </div>
        <div class='form-group'>
            <label for='status'>Status:</label>
            <select class='form-control' id='status' name='status' required>
                <option value='open'>Open</option>
                <option value='closed'>Closed</option>
            </select>
        </div>
        <button type='submit' class='btn btn-primary'>Add Issue</button>
      </form>";

echo "<br>";

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
    echo "<div class='alert alert-warning mt-2' role='alert'>No issues found.</div>";
}

closeConnection($conn);
include '../includes/footer.php';
?>