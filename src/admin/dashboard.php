<?php
include '../../includes/header.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: /lab1/index.php");
    exit();
}

echo "<h1>Admin Dashboard</h1>";
echo "Update soon";

include '../../includes/footer.php';
?>
