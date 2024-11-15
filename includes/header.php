<?php
session_start();

// Check for remember me cookies if not logged in
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['is_admin'] = $_COOKIE['is_admin'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VCS Project</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css'>
    <link rel="stylesheet" href="/lab1/styles/styles.css">
    <!-- Bootstrap CSS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand" href="#">VCS</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="/lab1/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/lab1/src/repositories.php">Repositories</a></li>
                    <li class="nav-item"><a class="nav-link" href="/lab1/src/issues.php">Issues</a></li>
                    <li class="nav-item"><a class="nav-link" href="/lab1/src/pull_requests.php">Pull Requests</a></li>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                        <li class="nav-item"><a class="nav-link" href="/lab1/src/admin/dashboard.php">Admin Panel</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="/lab1/src/users.php">Users</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="/lab1/src/my_repositories.php">My Repos</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="/lab1/src/my_subscribers.php">My Subscribers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/lab1/src/my_subscriptions.php">My Subscriptions</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="/lab1/src/auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/lab1/src/auth/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="/lab1/src/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container">