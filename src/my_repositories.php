<?php

namespace App\Controllers;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connection.php';
require_once '../includes/header.php';
require_once 'FileHandler.php';
require_once './Services/RepositoryService.php';

use App\Services\RepositoryService;

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /lab1/src/auth/login.php");
    exit();
}

try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    $error = '';
    $success = '';
    $repoService = new RepositoryService($conn);

    // Handle Add Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_repository'])) {
        try {
            $repoId = $repoService->createRepository(
                $_SESSION['user_id'],
                $_POST['name'],
                $_POST['description'],
                $_POST['language']
            );
            header("Location: /lab1/src/repositories.php?success=Repository created successfully");
            exit();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Handle Delete Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_repository_id'])) {
        try {
            $repoService->deleteRepository(
                intval($_POST['delete_repository_id']),
                $_SESSION['user_id']
            );
            $success = "Repository deleted successfully.";
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Handle Update Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_repository_id'])) {
        try {
            $updated = $repoService->updateRepository(
                intval($_POST['update_repository_id']),
                $_SESSION['user_id'],
                [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'language' => $_POST['language']
                ]
            );
            if ($updated) {
                $success = "Repository updated successfully.";
            } else {
                $error = "No changes were made to the repository.";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Fetch User's Repositories
    $repositories = $repoService->getUserRepositories($_SESSION['user_id']);
} catch (\Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1>My Repositories</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- Add Repository Form -->
<div class="card mb-4">
    <div class="card-header">
        <h3>Add New Repository</h3>
    </div>
    <div class="card-body">
        <form method="post" action="my_repositories.php" enctype="multipart/form-data">
            <input type="hidden" name="add_repository" value="1">
            <div class="form-group">
                <label for="name">Repository Name:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
            </div>
            <div class="form-group">
                <label for="language">Programming Language:</label>
                <input type="text" class="form-control" id="language" name="language" required>
            </div>
            <div class="form-group">
                <label for="files">Repository Files:</label>
                <input type="file" class="form-control-file" id="files" name="files[]" multiple>
            </div>
            <button type="submit" class="btn btn-primary">Create Repository</button>
        </form>
    </div>
</div>

<!-- List of User's Repositories -->
<div class="card">
    <div class="card-header">
        <h3>Your Repositories</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($repositories)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Language</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repositories as $repo): ?>
                        <tr>
                            <td><?= htmlspecialchars($repo['name']) ?></td>
                            <td><?= htmlspecialchars($repo['description']) ?></td>
                            <td><?= htmlspecialchars($repo['language']) ?></td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal"
                                    data-target="#updateModal<?= $repo['repo_id'] ?>">
                                    Update
                                </button>
                                <form method="post" action="my_repositories.php" style="display:inline;">
                                    <input type="hidden" name="delete_repository_id" value="<?= $repo['repo_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this repository?');">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You have not added any repositories yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php
closeConnection($conn);
include '../includes/footer.php';
?>