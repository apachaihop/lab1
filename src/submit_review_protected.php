<?php
session_start();
include 'connection.php';

// CSRF Protection
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo "CSRF token validation failed";
        exit;
    }

    $review = $_POST['review'];
    if (empty($review)) {
        echo "Review cannot be empty.";
        exit;
    }

    $conn = getConnection();

    $stmt = $conn->prepare("INSERT INTO Reviews (review) VALUES (?)");
    $stmt->bind_param("s", $review);

    if ($stmt->execute()) {
        echo "Review submitted successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    closeConnection($conn);
}

// Generate CSRF token for the form
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Submit Review</title>
    <link rel="stylesheet" href="/lab1/styles/styles.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <h2>Submit a Review</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="review">Your Review:</label>
                <textarea class="form-control" id="review" name="review" required></textarea>
            </div>
            <!-- Hidden CSRF token -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    </div>
</body>

</html>