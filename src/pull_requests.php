<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['title'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO PullRequests (title, description, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $status);
    $stmt->execute();
    $stmt->close();
}

$searchField = isset($_GET['field']) ? $_GET['field'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT title, description, status FROM PullRequests";
if ($searchField && $searchTerm) {
    $sql .= " WHERE $searchField LIKE ?";
}

$stmt = $conn->prepare($sql);
if ($searchField && $searchTerm) {
    $searchTermWrapped = "%$searchTerm%";
    $stmt->bind_param("s", $searchTermWrapped);
}
$stmt->execute();
$result = $stmt->get_result();

echo "<h1>Pull Requests</h1>";

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
                <option value='merged'>Merged</option>
            </select>
        </div>
        <button type='submit' class='btn btn-primary'>Add Pull Request</button>
      </form>";

echo "<br>";

echo "<form method='get' action=''>
        <div class='form-group'>
            <label for='search'>Search:</label>
            <input type='text' class='form-control' id='search' name='search' value='" . htmlspecialchars($searchTerm) . "'>
        </div>
        <div class='form-group'>
            <label for='field'>Search By:</label>
            <select class='form-control' id='field' name='field'>
                <option value='title'" . ($searchField == 'title' ? ' selected' : '') . ">Title</option>
                <option value='description'" . ($searchField == 'description' ? ' selected' : '') . ">Description</option>
                <option value='status'" . ($searchField == 'status' ? ' selected' : '') . ">Status</option>
            </select>
        </div>
        <button type='submit' class='btn btn-primary'>Search</button>
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
    echo "<div class='alert alert-warning mt-2' role='alert'>No pull requests found.</div>";
}

$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>