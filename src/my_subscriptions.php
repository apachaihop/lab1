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

    $sql = "SELECT R.repo_id, R.name, R.description, U.username as owner
            FROM Repositories R
            JOIN RepositorySubscriptions RS ON R.repo_id = RS.repo_id
            JOIN Users U ON R.user_id = U.user_id
            WHERE RS.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1>My Subscriptions</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result->num_rows > 0): ?>
    <div class="row">
        <?php while ($repo = $result->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($repo['name']) ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted">Owner: <?= htmlspecialchars($repo['owner']) ?></h6>
                        <p class="card-text"><?= htmlspecialchars($repo['description']) ?></p>
                        <a href="repositories.php?repo_id=<?= $repo['repo_id'] ?>" class="btn btn-primary">View Repository</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info" role="alert">You haven't subscribed to any repositories yet.</div>
<?php endif; ?>

<?php
$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>