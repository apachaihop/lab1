<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'connection.php';
include '../includes/header.php';
include 'FileHandler.php';

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

    // Handle Add Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_repository'])) {
        try {
            $conn->begin_transaction();

            // Get and validate form data
            $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : null;
            $description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description'])) : null;
            $language = isset($_POST['language']) ? htmlspecialchars(trim($_POST['language'])) : null;

            // Validate required fields
            if (empty($name) || empty($description) || empty($language)) {
                throw new Exception("Name, Description, and Language are required fields.");
            }

            // Insert repository
            $stmt = $conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $description, $language, $user_id);
            $stmt->execute();
            $repoId = $stmt->insert_id;
            $stmt->close();

            // Handle file uploads
            $fileHandler = new FileHandler();
            if (isset($_FILES['files'])) {
                foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileData = [
                            'name' => $_FILES['files']['name'][$key],
                            'type' => $_FILES['files']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $_FILES['files']['error'][$key],
                            'size' => $_FILES['files']['size'][$key]
                        ];
                        $fileHandler->saveRepoFile($conn, $repoId, $fileData);
                    }
                }
            }

            $conn->commit();
            $success = "Repository created successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }

    // Handle Delete Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_repository_id'])) {
        $repo_id = intval($_POST['delete_repository_id']);

        // Verify ownership
        $stmt = $conn->prepare("SELECT repo_id FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $repo_id, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            $deleteStmt = $conn->prepare("DELETE FROM Repositories WHERE repo_id = ?");
            $deleteStmt->bind_param("i", $repo_id);
            if ($deleteStmt->execute()) {
                $success = "Repository deleted successfully.";
            } else {
                $error = "Error deleting repository: " . $deleteStmt->error;
            }
            $deleteStmt->close();
        } else {
            $error = "Unauthorized action.";
            $stmt->close();
        }
    }

    // Handle Update Repository
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_repository_id'])) {
        $repo_id = intval($_POST['update_repository_id']);
        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description']));
        $language = htmlspecialchars(trim($_POST['language']));

        if (empty($name) || empty($description) || empty($language)) {
            $error = "Both Name, Description, and Language are required.";
        } else {
            // Verify ownership
            $stmt = $conn->prepare("SELECT repo_id FROM Repositories WHERE repo_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $repo_id, $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->close();
                $hasErrors = false;  // Flag to track if any errors occurred

                // Update repository
                $updateStmt = $conn->prepare("UPDATE Repositories SET name = ?, description = ?, language = ? WHERE repo_id = ?");
                $updateStmt->bind_param("sssi", $name, $description, $language, $repo_id);
                if (!$updateStmt->execute()) {
                    $error = "Error updating repository: " . $updateStmt->error;
                    $hasErrors = true;
                }
                $updateStmt->close();

                // Handle file uploads if there were no errors with repository update
                if (!$hasErrors && isset($_FILES['new_files'])) {
                    $fileHandler = new FileHandler();
                    foreach ($_FILES['new_files']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['new_files']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileData = [
                                'name' => $_FILES['new_files']['name'][$key],
                                'type' => $_FILES['new_files']['type'][$key],
                                'tmp_name' => $tmp_name,
                                'error' => $_FILES['new_files']['error'][$key],
                                'size' => $_FILES['new_files']['size'][$key]
                            ];
                            try {
                                $fileHandler->saveRepoFile($conn, $repo_id, $fileData);
                            } catch (Exception $e) {
                                $error .= "Error uploading " . htmlspecialchars($fileData['name']) . ": " . $e->getMessage() . "<br>";
                                $hasErrors = true;
                            }
                        }
                    }
                }

                // Only show success message if there were no errors
                if (!$hasErrors) {
                    $success = "Repository updated successfully.";
                }
            } else {
                $error = "Unauthorized action.";
                $stmt->close();
            }
        }
    }

    // Fetch User's Repositories
    $stmt = $conn->prepare("SELECT repo_id, name, description, language FROM Repositories WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reposResult = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
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
        <form method="post" action="my_repositories.php" enctype="multipart/form-data" onsubmit="return validateFileUpload(document.getElementById('files'))">
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
        <?php if ($reposResult->num_rows > 0): ?>
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
                    <?php while ($repo = $reposResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($repo['name']) ?></td>
                            <td><?= htmlspecialchars($repo['description']) ?></td>
                            <td><?= htmlspecialchars($repo['language']) ?></td>
                            <td>
                                <!-- Update Button triggers modal -->
                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal"
                                    data-target="#updateModal<?= $repo['repo_id'] ?>">
                                    Update
                                </button>

                                <!-- Update Modal -->
                                <div class="modal fade" id="updateModal<?= $repo['repo_id'] ?>" tabindex="-1"
                                    role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <form method="post" action="my_repositories.php" enctype="multipart/form-data" onsubmit="return validateFileUpload(document.getElementById('new_files_<?= $repo['repo_id'] ?>'))">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Repository</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="update_repository_id" value="<?= $repo['repo_id'] ?>">
                                                    <div class="form-group">
                                                        <label>Name:</label>
                                                        <input type="text" class="form-control" name="name"
                                                            value="<?= htmlspecialchars($repo['name']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Description:</label>
                                                        <textarea class="form-control" name="description" required><?= htmlspecialchars($repo['description']) ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Programming Language:</label>
                                                        <input type="text" class="form-control" name="language"
                                                            value="<?= htmlspecialchars($repo['language']) ?>" required>
                                                    </div>

                                                    <!-- File Management Section -->
                                                    <hr>
                                                    <h6>Manage Files</h6>

                                                    <!-- Existing Files -->
                                                    <?php
                                                    $filesStmt = $conn->prepare("SELECT file_name FROM RepositoryFiles WHERE repo_id = ?");
                                                    $filesStmt->bind_param("i", $repo['repo_id']);
                                                    $filesStmt->execute();
                                                    $filesResult = $filesStmt->get_result();
                                                    ?>

                                                    <?php if ($filesResult->num_rows > 0): ?>
                                                        <div class="list-group mb-3">
                                                            <?php while ($file = $filesResult->fetch_assoc()): ?>
                                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <?= htmlspecialchars($file['file_name']) ?>
                                                                    <button type="button" class="btn btn-danger btn-sm delete-file"
                                                                        data-repo-id="<?= $repo['repo_id'] ?>"
                                                                        data-file-name="<?= htmlspecialchars($file['file_name']) ?>">
                                                                        Delete
                                                                    </button>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Add New Files -->
                                                    <div class="form-group">
                                                        <label>Add New Files:</label>
                                                        <input type="file" class="form-control-file" id="new_files_<?= $repo['repo_id'] ?>" name="new_files[]" multiple>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Update Repository</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Form -->
                                <form method="post" action="my_repositories.php" style="display:inline;">
                                    <input type="hidden" name="delete_repository_id" value="<?= $repo['repo_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this repository?');">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script>
    $(document).ready(function() {
        // File deletion handler
        $('.delete-file').click(function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            const button = $(this);
            const repoId = button.data('repo-id');
            const fileName = button.data('file-name');

            $.ajax({
                url: 'delete_repo_file.php',
                type: 'POST',
                data: {
                    repo_id: repoId,
                    file_name: fileName
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('.list-group-item').remove();
                    } else {
                        alert('Error deleting file: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error communicating with server');
                }
            });
        });
    });
</script>

<script>
    function validateFileUpload(input) {
        if (input.files.length > 0) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                if (!file || file.size === 0) {
                    alert('One or more selected files are no longer available. Please select your files again.');
                    input.value = ''; // Clear the input
                    return false;
                }
            }
        }
        return true;
    }
</script>
?>