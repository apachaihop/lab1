<?php
session_start();
include '../connection.php';
if (isset($_SESSION['user_id'])) {
    header("Location: /lab1/index.php");
    exit();
}
try{
$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);

    $stmt = $conn->prepare("SELECT user_id, password_hash, is_admin FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $hashed_password, $is_admin);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['is_admin'] = $is_admin;
        header("Location: /lab1/index.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }

    $stmt->close();
}
}
catch(Exception $e)
{
    $error= "Error while SQL connection processing";
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/lab1/styles/styles.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
    <div class="d-flex justify-content-between align-items-center">
            <h2>Login</h2>
            <button onclick="window.location.href='/lab1/index.php'" type="button" class="btn btn-secondary">Back to Main Page</button>
        </div>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="mt-3">Don't have an account? <a href="/lab1/src/auth/register.php">Register here</a></p>
    </div>
</body>
</html>
