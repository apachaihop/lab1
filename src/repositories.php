<?php
include 'connection.php';
include '../includes/header.php';

try{
$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'])) {
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);

    $stmt = $conn->prepare("INSERT INTO Repositories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    $stmt->close();
}

$searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
$searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$sql = "SELECT repo_id, name, description FROM Repositories";
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . htmlspecialchars($row["name"]) . "</td>
                <td>" . htmlspecialchars($row["description"]) . "</td>
                <td>
                    <form method='post' action='delete_repository.php' style='display:inline;'>
                        <input type='hidden' name='repo_id' value='" . htmlspecialchars($row["repo_id"]) . "'>
                        <button type='submit' class='btn btn-danger'>Delete</button>
                    </form>
                    <button type='button' class='btn btn-warning' data-toggle='modal' data-target='#updateModal" . htmlspecialchars($row["repo_id"]) . "'>Update</button>
                    
                    <!-- Update Modal -->
                    <div class='modal fade' id='updateModal" . htmlspecialchars($row["repo_id"]) . "' tabindex='-1' role='dialog' aria-labelledby='updateModalLabel" . htmlspecialchars($row["repo_id"]) . "' aria-hidden='true'>
                        <div class='modal-dialog' role='document'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='updateModalLabel" . htmlspecialchars($row["repo_id"]) . "'>Update Repository</h5>
                                    <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                        <span aria-hidden='true'>&times;</span>
                                    </button>
                                </div>
                                <div class='modal-body'>
                                    <form method='post' action='update_repository.php'>
                                        <input type='hidden' name='repo_id' value='" . htmlspecialchars($row["repo_id"]) . "'>
                                        <div class='form-group'>
                                            <label for='name'>Name:</label>
                                            <input type='text' class='form-control' id='name' name='name' value='" . htmlspecialchars($row["name"]) . "' required>
                                        </div>
                                        <div class='form-group'>
                                            <label for='description'>Description:</label>
                                            <input type='text' class='form-control' id='description' name='description' value='" . htmlspecialchars($row["description"]) . "' required>
                                        </div>
                                        <button type='submit' class='btn btn-primary'>Update</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning mt-2' role='alert'>No repositories found.</div>";
}

$stmt->close();
closeConnection($conn);
}
catch(Exception $e){
    $errorMessage = $e->getMessage();
    echo "<div class='alert alert-danger mt-2' role='alert'>An error occurred: " . $errorMessage . "</div>";
}
include '../includes/footer.php';
?>