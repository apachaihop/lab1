<?php
include 'connection.php';
include '../includes/header.php';
try {
    $conn = getConnection();

    // Add vulnerable search functionality
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sql = "SELECT review FROM Reviews";
    if ($search) {
        $sql .= " WHERE review LIKE '$search'";  // Vulnerable to SQL injection
    }

    // Add debug output
    echo "<div class='alert alert-info'>Debug - SQL Query: " . htmlspecialchars($sql) . "</div>";

    $result = $conn->query($sql);

    // Add error checking
    if (!$result) {
        echo "<div class='alert alert-danger'>MySQL Error: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    $error = "Error: Sql connection refused";
}

// Add search form
echo "<h1>Reviews</h1>";
echo "<form method='GET' class='mb-3'>
        <div class='input-group'>
            <input type='text' name='search' class='form-control' placeholder='Search reviews...' value='" . htmlspecialchars($search) . "'>
            <button class='btn btn-primary' type='submit'>Search</button>
        </div>
      </form>";

if ($error) {
    echo "<div class='alert alert-danger'> $error</div>";
}
if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='thead-light'>
                <tr>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["review"] . "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-warning mt-2' role='alert'>No reviews found.</div>";
}

closeConnection($conn);
include '../includes/footer.php';
