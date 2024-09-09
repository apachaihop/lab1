<?php
include 'connection.php';
include '../includes/header.php';

$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['title'])) {
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $status = htmlspecialchars($_POST['status']);

    $stmt = $conn->prepare("INSERT INTO PullRequests (title, description, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $status);
    $stmt->execute();
    $stmt->close();
}

$searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
$searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$sql = "SELECT pr_id, title, description, status FROM PullRequests";
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . htmlspecialchars($row["title"]) . "</td>
                <td>" . htmlspecialchars($row["description"]) . "</td>
                <td>" . htmlspecialchars($row["status"]) . "</td>
                <td>
                    <form method='post' action='delete_pull_request.php' style='display:inline;'>
                        <input type='hidden' name='id' value='" . htmlspecialchars($row["pr_id"]) . "'>
                        <button type='submit' class='btn btn-danger'>Delete</button>
                    </form>
                    <button type='button' class='btn btn-warning' data-toggle='modal' data-target='#updateModal" . htmlspecialchars($row["pr_id"]) . "'>Update</button>
                    
                    <!-- Update Modal -->
                    <div class='modal fade' id='updateModal" . htmlspecialchars($row["pr_id"]) . "' tabindex='-1' role='dialog' aria-labelledby='updateModalLabel" . htmlspecialchars($row["pr_id"]) . "' aria-hidden='true'>
                        <div class='modal-dialog' role='document'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='updateModalLabel" . htmlspecialchars($row["pr_id"]) . "'>Update Pull Request</h5>
                                    <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                        <span aria-hidden='true'>&times;</span>
                                    </button>
                                </div>
                                <div class='modal-body'>
                                    <form method='post' action='update_pull_request.php'>
                                        <input type='hidden' name='id' value='" . htmlspecialchars($row["pr_id"]) . "'>
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
                                                <option value='merged'" . ($row["status"] == 'merged' ? ' selected' : '') . ">Merged</option>
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
    echo "<div class='alert alert-warning mt-2' role='alert'>No pull requests found.</div>";
}

$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>