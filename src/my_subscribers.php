<?php
include 'connection.php';
include '../includes/header.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

try {
    $conn = getConnection();

    $user_id = $_SESSION['user_id'];

    $sql = "SELECT DISTINCT U.user_id, U.username 
            FROM Users U
            JOIN RepositorySubscriptions RS ON U.user_id = RS.user_id
            JOIN Repositories R ON RS.repo_id = R.repo_id
            WHERE R.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1>My Subscribers</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result->num_rows > 0): ?>
    <ul class="list-group">
        <?php while ($subscriber = $result->fetch_assoc()): ?>
            <li class="list-group-item">
                <?= htmlspecialchars($subscriber['username']) ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <div class="alert alert-info" role="alert">You don't have any subscribers yet.</div>
<?php endif; ?>

<?php
$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>