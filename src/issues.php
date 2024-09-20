<?php
include 'connection.php';
include '../includes/header.php';
try{
$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $status = htmlspecialchars($_POST['status']);

    $stmt = $conn->prepare("INSERT INTO Issues (title, description, status) VALUES (?, ?, ?)");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("sss", $title, $description, $status);
    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

$searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
$searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$sql = "SELECT issue_id, title, description, status FROM Issues";
if ($searchField && $searchTerm) {
    $sql .= " WHERE $searchField LIKE ?";
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

if ($searchField && $searchTerm) {
    $searchTermWrapped = "%$searchTerm%";
    $stmt->bind_param("s", $searchTermWrapped);
}

if ($stmt->execute() === false) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}

$result = $stmt->get_result();
}
catch (Exception $e) {
    $error = "Error: Sql connection refused";
}

echo "<h1>Issues</h1>";
if($error)
{
    echo "<div class='alert alert-danger'> $error</div>";
}

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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . htmlspecialchars($row["title"]) . "</td>
                <td>" . htmlspecialchars($row["description"]) . "</td>
                <td>" . htmlspecialchars($row["status"]) . "</td>
                <td>
                    <form method='post' action='delete_issue.php' style='display:inline;'>
                        <input type='hidden' name='issue_id' value='" . htmlspecialchars($row["issue_id"]) . "'>
                        <button type='submit' class='btn btn-danger'>Delete</button>
                    </form>
                    <button type='button' class='btn btn-warning' data-toggle='modal' data-target='#updateModal" . htmlspecialchars($row["issue_id"]) . "'>Update</button>
                    
                    <!-- Update Modal -->
                    <div class='modal fade' id='updateModal" . htmlspecialchars($row["issue_id"]) . "' tabindex='-1' role='dialog' aria-labelledby='updateModalLabel" . htmlspecialchars($row["issue_id"]) . "' aria-hidden='true'>
                        <div class='modal-dialog' role='document'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='updateModalLabel" . htmlspecialchars($row["issue_id"]) . "'>Update Issue</h5>
                                    <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                        <span aria-hidden='true'>&times;</span>
                                    </button>
                                </div>
                                <div class='modal-body'>
                                    <form method='post' action='update_issue.php'>
                                        <input type='hidden' name='issue_id' value='" . htmlspecialchars($row["issue_id"]) . "'>
                                        <div class='form-group'>
                                            <label for='title'>Title:</label>
                                            <input type='text' class='form-control' id='title' name='title' value='" . htmlspecialchars($row["title"]) . "' required>
                                        </div>
                                        <div class='form-group'>
                                            <label for='description'>Description:</label>
                                            <input type='text' class='form-control' id='description' name='description' value='" . htmlspecialchars($row["description"]) . "' required>
                                        </div>
                                        <div class='form-group'>
                                            <label for='status'>Status:</label>
                                            <select class='form-control' id='status' name='status' required>
                                                <option value='open'" . ($row["status"] == 'open' ? ' selected' : '') . ">Open</option>
                                                <option value='closed'" . ($row["status"] == 'closed' ? ' selected' : '') . ">Closed</option>
                                            </select>
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
    echo "<div class='alert alert-warning mt-2' role='alert'>No issues found.</div>";
}

$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>