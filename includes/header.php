<?php
session_start();

// Check for remember me token if not logged in
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $connectionPath = '';
    if (strpos($_SERVER['SCRIPT_NAME'], '/src/') !== false) {
        $connectionPath = '../connection.php';
    } else if (strpos($_SERVER['SCRIPT_NAME'], '/src/admin/') !== false) {
        $connectionPath = '../../connection.php';
    } else {
        $connectionPath = './src/connection.php';
    }

    require_once $connectionPath;

    try {
        $conn = getConnection();

        // Basic cookie validation
        if (empty($_COOKIE['remember_me']) || strpos($_COOKIE['remember_me'], ':') === false) {
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
            return;
        }

        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);

        // Check for valid token
        $stmt = $conn->prepare("SELECT t.token, t.user_id, u.is_admin 
            FROM RememberMeTokens t 
            JOIN Users u ON t.user_id = u.user_id 
            WHERE t.selector = ? AND t.expires > NOW()");

        if (!$stmt) {
            throw new Exception('Failed to prepare statement');
        }

        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && hash_equals($row['token'], hash('sha256', $validator))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['is_admin'] = $row['is_admin'];
        } else {
            // Invalid token - remove cookie
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        error_log('Remember Me Error: ' . $e->getMessage());
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    } finally {
        if (isset($conn)) {
            closeConnection($conn);
        }
    }
}

// Ensure session variables are set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = null;
    $_SESSION['is_admin'] = null;
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