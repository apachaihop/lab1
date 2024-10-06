<?php
include 'connection.php';
session_start();
header('Content-Type: application/json');

try {
    $conn = getConnection();

    // Retrieve and sanitize input parameters
    $term = isset($_GET['term']) ? htmlspecialchars($_GET['term']) : '';
    $field = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : 'name';

    // Define allowed fields for autocomplete
    $allowedFields = ['name', 'language'];

    // Validate the field parameter
    if (!in_array($field, $allowedFields)) {
        echo json_encode([]);
        exit;
    }

    // Prepare the SQL statement based on the field
    $sql = "SELECT DISTINCT $field FROM Repositories WHERE $field LIKE ? ORDER BY $field ASC LIMIT 10";
    $stmt = $conn->prepare($sql);

    // Bind parameters and execute the query
    $searchTerm = "%$term%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch suggestions
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row[$field];
    }

    // Return suggestions as JSON
    echo json_encode($suggestions);
    $stmt->close();
    closeConnection($conn);
} catch (Exception $e) {
    echo json_encode([]);
}
