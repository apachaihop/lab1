<?php
include './includes/header.php';
include './src/connection.php';
try {
    $conn = getConnection();

    $user_id = $_SESSION['user_id'];
    $stmt;
    if ($_SESSION['is_admin']) {
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


<div class="container mt-4">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3>Your Profile</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <img src="src/display_avatar.php?user_id=<?= $_SESSION['user_id'] ?>"
                            alt="Your avatar"
                            class="rounded-circle mb-3"
                            style="width: 150px; height: 150px; object-fit: cover;">

                        <form method="post" enctype="multipart/form-data" action="src/update_avatar.php">
                            <div class="form-group">
                                <label for="avatar" class="btn btn-outline-primary">
                                    Choose New Avatar
                                    <input type="file" id="avatar" name="avatar" class="d-none"
                                        accept=".jpg,.jpeg,.png,.gif" onchange="this.form.submit()">
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-9">
                        <h4>Statistics</h4>
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
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const avatarInput = document.getElementById('avatar');
        if (avatarInput) {
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

                    if (!allowedTypes.includes(file.type)) {
                        alert('Only JPG, PNG and GIF files are allowed');
                        this.value = '';
                        return;
                    }

                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        alert('File size must be less than 5MB');
                        this.value = '';
                        return;
                    }
                }
            });
        }
    });
</script>

<?php
include './includes/footer.php';
?>