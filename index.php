<?php
include './includes/header.php';
include './src/connection.php';
try {
    $conn = getConnection();

    if (isset($_SESSION['user_id'])) {
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
    }

    closeConnection($conn);
} catch (Exception $e) {
    $error = "Error: Sql connection refused";
    $repoCount = $issueCount = $prCount = $userCount = 0;
}
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_message']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Existing authenticated user view -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Your Profile</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <img src="src/display_avatar.php?user_id=<?= $_SESSION['user_id'] ?>"
                            onerror="if (this.src != 'assets/images/default-avatar.png') this.src='assets/images/default-avatar.png';"
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
                            <!-- Your existing cards code stays exactly the same -->
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
    <?php else: ?>
        <!-- Non-authenticated user view -->
        <div class="jumbotron">
            <h1 class="display-4">Welcome to VCS Project</h1>
            <p class="lead">A powerful version control system for managing your code repositories.</p>
            <hr class="my-4">
            <p>Join our community to start managing your repositories, track issues, and collaborate with other developers.</p>
            <div class="mt-4">
                <a class="btn btn-primary btn-lg mr-3" href="/lab1/src/auth/register.php" role="button">Sign Up</a>
                <a class="btn btn-outline-primary btn-lg" href="/lab1/src/auth/login.php" role="button">Login</a>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Repository Management</h5>
                        <p class="card-text">Create and manage your code repositories with ease. Support for multiple programming languages.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Issue Tracking</h5>
                        <p class="card-text">Keep track of bugs, feature requests, and tasks with our comprehensive issue tracking system.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Collaboration</h5>
                        <p class="card-text">Work together with other developers through pull requests and code reviews.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include './includes/footer.php'; ?>