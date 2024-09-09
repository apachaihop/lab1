<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO Repositories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    $stmt->close();
}

$searchField = isset($_GET['field']) ? $_GET['field'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT name, description FROM Repositories";
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

echo "<br>";

echo "<form method='get' action=''>
        <div class='form-group'>
            <label for='search'>Search:</label>
            <input type='text' class='form-control' id='search' name='search' value='" . htmlspecialchars($searchTerm) . "'>
        </div>
        <div class='form-group'>
            <label for='field'>Search By:</label>
            <select class='form-control' id='field' name='field'>
                <option value='name'" . ($searchField == 'name' ? ' selected' : '') . ">Name</option>
                <option value='description'" . ($searchField == 'description' ? ' selected' : '') . ">Description</option>
            </select>
        </div>
        <button type='submit' class='btn btn-primary'>Search</button>
      </form>";

echo "<br>";

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

$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>