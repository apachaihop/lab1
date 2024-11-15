<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../connection.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /lab1/index.php");
    exit();
}

try {
    $conn = getConnection();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $username = htmlspecialchars($_POST['username']);
            $password = htmlspecialchars($_POST['password']);
        } else {
            throw new Exception("Username or password not set in POST data.");
        }

        $stmt = $conn->prepare("SELECT user_id, password_hash, is_admin FROM Users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }

        $stmt->bind_result($user_id, $hashed_password, $is_admin);
        if (!$stmt->fetch()) {
            throw new Exception("Fetch failed: No user found with that username.");
        }

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['is_admin'] = $is_admin;

            // Handle remember me functionality
            if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                // Set cookies that expire in 30 days
                setcookie('user_id', $user_id, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                setcookie('is_admin', $is_admin, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            }

            header("Location: /lab1/index.php");
            exit();
        } else {
            $error = "Invalid username or password";
        }

        $stmt->close();
    }
} catch (Exception $e) {
    $error = "Error while SQL connection processing: " . $e->getMessage();
} finally {
    if (isset($conn)) {
        closeConnection($conn);
    }
}
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
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember Me</label>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="mt-3">Don't have an account? <a href="/lab1/src/auth/register.php">Register here</a></p>
    </div>
</body>

</html>