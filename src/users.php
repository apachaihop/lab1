<?php
include 'connection.php';
include '../includes/header.php';
try{
$conn = getConnection();

$searchField = isset($_GET['field']) ? $_GET['field'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT username, email FROM Users";
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
}
catch (Exception $e) {
    $error = "Error: Sql connection refused";
}

echo "<h1>Users</h1>";

if($error)
{
    echo "<div class='alert alert-danger'> $error</div>";
}
echo "<form method='get' action=''>
        <div class='form-group'>
            <label for='search'>Search:</label>
            <input type='text' class='form-control' id='search' name='search' value='" . htmlspecialchars($searchTerm) . "'>
        </div>
        <div class='form-group'>
            <label for='field'>Search By:</label>
            <select class='form-control' id='field' name='field'>
                <option value='username'" . ($searchField == 'username' ? ' selected' : '') . ">Username</option>
                <option value='email'" . ($searchField == 'email' ? ' selected' : '') . ">Email</option>
            </select>
        </div>
        <button type='submit' class='btn btn-primary'>Search</button>
      </form>";

echo "<br>";

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
    echo "<div class='alert alert-warning mt-2' role='alert'>No users found.</div>";
}

$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>