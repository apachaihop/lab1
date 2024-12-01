<?php
include '../connection.php';
include '../../includes/header.php';
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

try {
    $conn = getConnection();

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $view_weight = floatval($_POST['view_weight']);
        $like_weight = floatval($_POST['like_weight']);
        $subscription_weight = floatval($_POST['subscription_weight']);

        // Ensure weights sum up to 1
        $total_weight = $view_weight + $like_weight + $subscription_weight;


        // First, check if any weights exist
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM UserPreferencesWeights");
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $checkStmt->close();

        if ($row['count'] == 0) {
            // If no weights exist, do a simple INSERT
            $stmt = $conn->prepare("INSERT INTO UserPreferencesWeights (view_weight, like_weight, subscription_weight) VALUES (?, ?, ?)");
        } else {
            // If weights exist, do an UPDATE
            $stmt = $conn->prepare("UPDATE UserPreferencesWeights SET view_weight = ?, like_weight = ?, subscription_weight = ?");
        }

        $stmt->bind_param("ddd", $view_weight, $like_weight, $subscription_weight);
        $stmt->execute();
        $stmt->close();

        $success = "Weights updated successfully.";
    }

    // When fetching weights, handle case where no weights exist
    $stmt = $conn->prepare("SELECT * FROM UserPreferencesWeights LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $weights = $result->fetch_assoc();

    if (!$weights) {
        // Set default weights if none exist
        $weights = [
            'view_weight' => 0.33,
            'like_weight' => 0.33,
            'subscription_weight' => 0.34
        ];
    }
    $stmt->close();

    closeConnection($conn);
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1>Admin Panel - User Preference Weights</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" action="">
    <div class="form-group">
        <label for="view_weight">View Weight:</label>
        <input type="number" step="0.01" class="form-control" id="view_weight" name="view_weight" value="<?= $weights['view_weight']  ?>" required>
    </div>
    <div class="form-group">
        <label for="like_weight">Like Weight:</label>
        <input type="number" step="0.01" class="form-control" id="like_weight" name="like_weight" value="<?= $weights['like_weight'] ?>" required>
    </div>
    <div class="form-group">
        <label for="subscription_weight">Subscription Weight:</label>
        <input type="number" step="0.01" class="form-control" id="subscription_weight" name="subscription_weight" value="<?= $weights['subscription_weight'] ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Weights</button>
</form>