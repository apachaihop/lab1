<?php
include '../../includes/header.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: /lab1/index.php");
    exit();
}

echo "<h1>Admin Dashboard</h1>";
echo "<ul>
    <li><a href='manage_users.php'>Manage Users</a></li>
    <li><a href='manage_repositories.php'>Manage Repositories</a></li>
    <li><a href='manage_issues.php'>Manage Issues</a></li>
    <li><a href='manage_pull_requests.php'>Manage Pull Requests</a></li>
</ul>";

include '../../includes/footer.php';
?>
