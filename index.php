<?php
include './includes/header.php';
include './src/connection.php';
try {
    $conn = getConnection();

    $user_id = $_SESSION['user_id'];
    $stmt;
    if (isset($_SESSION['is_admin']) || $_SESSION['is_admin']) {
        $stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM Repositories ) AS repoCount,
                (SELECT COUNT(*) FROM Issues) AS issueCount,
                (SELECT COUNT(*) FROM PullRequests ) AS prCount
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT 
            (SELECT COUNT(*) FROM Repositories WHERE user_id = ?) AS repoCount,
            (SELECT COUNT(*) FROM Issues WHERE user_id = ?) AS issueCount,
                (SELECT COUNT(*) FROM PullRequests WHERE user_id = ?) AS prCount
        ");
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    }



    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $repoCount = $result['repoCount'];
    $issueCount = $result['issueCount'];
    $prCount = $result['prCount'];
    $userCount = $conn->query("SELECT COUNT(*) AS count FROM Users")->fetch_assoc()['count'];

    closeConnection($conn);
} catch (Exception $e) {
    $error = "Error: Sql connection refused";
    $repoCount = $issueCount = $prCount = $userCount = 0;
}
?>


<h1>Welcome to the VCS Project</h1>
<p>Select an option from the navigation menu to get started.</p>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<div class="row">
    <!-- Repositories Card -->
    <div class="col-md-3 d-flex">
        <div class="card text-white bg-primary mb-3 flex-fill d-flex flex-column">
            <div class="card-header">Repositories</div>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo $repoCount; ?></h5>
                <p class="card-text">Total Repositories</p>
                <a href="./src/repositories.php" class="btn btn-light mt-auto">View Repositories</a>
            </div>
        </div>
    </div>

    <!-- Issues Card -->
    <div class="col-md-3 d-flex">
        <div class="card text-white bg-secondary mb-3 flex-fill d-flex flex-column">
            <div class="card-header">Issues</div>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo $issueCount; ?></h5>
                <p class="card-text">Total Issues</p>
                <a href="./src/issues.php" class="btn btn-light mt-auto">View Issues</a>
            </div>
        </div>
    </div>

    <!-- Pull Requests Card -->
    <div class="col-md-3 d-flex">
        <div class="card text-white bg-success mb-3 flex-fill d-flex flex-column">
            <div class="card-header">Pull Requests</div>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo $prCount; ?></h5>
                <p class="card-text">Total Pull Requests</p>
                <a href="./src/pull_requests.php" class="btn btn-light mt-auto">View Pull Requests</a>
            </div>
        </div>
    </div>

    <!-- Users Card -->
    <div class="col-md-3 d-flex">
        <div class="card text-white bg-danger mb-3 flex-fill d-flex flex-column">
            <div class="card-header">Users</div>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo $userCount; ?></h5>
                <p class="card-text">Total Users</p>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <a href="./src/users.php" class="btn btn-light mt-auto">View Users</a>
                <?php else: ?>
                    <div class="mt-auto invisible">
                        <span>&nbsp;</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h2>Submit a Review</h2>


<form action="./src/submit_review.php" method="post">
    <div class="form-group">
        <div class="row">
            <div class="col-md-6">
                <label for="review">Review</label>
            </div>
            <div class="col-md-6 text-right">
                <a href="./src/reviews.php">View Reviews</a>
            </div>
        </div>
        <textarea class="form-control" id="review" name="review" rows="3" required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>

<?php
include './includes/footer.php';
?>