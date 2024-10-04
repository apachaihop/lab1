<?php
include 'connection.php';
include '../includes/header.php';
session_start();

try {
    $conn = getConnection();

    // Fetch repositories
    $searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
    $searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

    $sql = "SELECT repo_id, name, description, user_id FROM Repositories";
    if ($searchField && $searchTerm) {
        // Prevent SQL Injection by allowing only specific fields
        $allowedFields = ['name', 'description'];
        if (!in_array($searchField, $allowedFields)) {
            throw new Exception("Invalid search field.");
        }
        $sql .= " WHERE $searchField LIKE ?";
    }

    $stmt = $conn->prepare($sql);
    if ($searchField && $searchTerm) {
        $searchTermWrapped = "%$searchTerm%";
        $stmt->bind_param("s", $searchTermWrapped);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Handle new comment submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment']) && isset($_POST['repo_id'])) {
        if (!isset($_SESSION['user_id'])) {
            $error = "You must be logged in to comment.";
        } else {
            $repo_id = intval($_POST['repo_id']);
            $user_id = $_SESSION['user_id'];
            $comment = htmlspecialchars(trim($_POST['comment']));
            if (!empty($comment)) {
                $commentStmt = $conn->prepare("INSERT INTO RepositoryComments (repo_id, user_id, comment) VALUES (?, ?, ?)");
                $commentStmt->bind_param("iis", $repo_id, $user_id, $comment);
                $commentStmt->execute();
                $commentStmt->close();
            }
        }
    }

    // Handle comment like
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['like_comment_id'])) {
        if (!isset($_SESSION['user_id'])) {
            $error = "You must be logged in to like comments.";
        } else {
            $comment_id = intval($_POST['like_comment_id']);
            $user_id = $_SESSION['user_id'];

            // Attempt to insert like; handle duplicate entry gracefully
            $likeStmt = $conn->prepare("INSERT INTO CommentLikes (comment_id, user_id) VALUES (?, ?)");
            $likeStmt->bind_param("ii", $comment_id, $user_id);
            if ($likeStmt->execute()) {
                // Increment stars count if like is successful
                $updateStarsStmt = $conn->prepare("UPDATE RepositoryComments SET stars = stars + 1 WHERE comment_id = ?");
                $updateStarsStmt->bind_param("i", $comment_id);
                $updateStarsStmt->execute();
                $updateStarsStmt->close();
            } else {
                // Duplicate like attempt
                if ($conn->errno == 1062) { // 1062 is duplicate entry error code
                    $error = "You have already liked this comment.";
                } else {
                    $error = "Error liking comment: " . htmlspecialchars($conn->error);
                }
            }
            $likeStmt->close();
        }
    }

    // Handle comment deletion (only by repo owner)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_comment_id']) && isset($_SESSION['user_id'])) {
        $comment_id = intval($_POST['delete_comment_id']);
        $user_id = $_SESSION['user_id'];

        // Verify if the logged-in user is the owner of the repository
        $ownerCheckStmt = $conn->prepare("
            SELECT R.user_id 
            FROM RepositoryComments C
            JOIN Repositories R ON C.repo_id = R.repo_id
            WHERE C.comment_id = ? AND R.user_id = ?
        ");
        $ownerCheckStmt->bind_param("ii", $comment_id, $user_id);
        $ownerCheckStmt->execute();
        $ownerCheckStmt->store_result();

        if ($ownerCheckStmt->num_rows > 0) {
            // Delete the comment
            $deleteStmt = $conn->prepare("DELETE FROM RepositoryComments WHERE comment_id = ?");
            $deleteStmt->bind_param("i", $comment_id);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        $ownerCheckStmt->close();
    }

    // Handle comment starring (only by repo owner)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['star_comment_id']) && isset($_SESSION['user_id'])) {
        $comment_id = intval($_POST['star_comment_id']);
        $user_id = $_SESSION['user_id'];

        // Verify if the logged-in user is the owner of the repository
        $ownerCheckStmt = $conn->prepare("
            SELECT R.user_id 
            FROM RepositoryComments C
            JOIN Repositories R ON C.repo_id = R.repo_id
            WHERE C.comment_id = ? AND R.user_id = ?
        ");
        $ownerCheckStmt->bind_param("ii", $comment_id, $user_id);
        $ownerCheckStmt->execute();
        $ownerCheckStmt->store_result();

        if ($ownerCheckStmt->num_rows > 0) {
            // Check if the owner has already starred the comment
            $starCheckStmt = $conn->prepare("SELECT like_id FROM CommentLikes WHERE comment_id = ? AND user_id = ?");
            $starCheckStmt->bind_param("ii", $comment_id, $user_id);
            $starCheckStmt->execute();
            $starCheckStmt->store_result();

            if ($starCheckStmt->num_rows == 0) {
                // Add star (similar to like)
                $starStmt = $conn->prepare("INSERT INTO CommentLikes (comment_id, user_id) VALUES (?, ?)");
                $starStmt->bind_param("ii", $comment_id, $user_id);
                if ($starStmt->execute()) {
                    // Increment stars count
                    $updateStarsStmt = $conn->prepare("UPDATE RepositoryComments SET stars = stars + 1 WHERE comment_id = ?");
                    $updateStarsStmt->bind_param("i", $comment_id);
                    $updateStarsStmt->execute();
                    $updateStarsStmt->close();
                }
                $starStmt->close();
            } else {
                $error = "You have already starred this comment.";
            }

            $starCheckStmt->close();
        }

        $ownerCheckStmt->close();
    }

    // Handle sorting
    $sortOrder = "C.created_at DESC"; // Default sort
    if (isset($_GET['sort']) && $_GET['sort'] == 'likes') {
        $sortOrder = "C.stars DESC";
    }
} catch (Exception $e) {
    $error = "Error: Sql connection refused";
}

?>
<h1>Repositories</h1>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Search and Sort Form -->
<form method='get' action=''>
    <div class='form-group'>
        <label for='search'>Search:</label>
        <input type='text' class='form-control' id='search' name='search' value='<?= htmlspecialchars($searchTerm) ?>'>
    </div>
    <div class='form-group'>
        <label for='field'>Search By:</label>
        <select class='form-control' id='field' name='field'>
            <option value='name' <?= ($searchField == 'name') ? 'selected' : '' ?>>Name</option>
            <option value='description' <?= ($searchField == 'description') ? 'selected' : '' ?>>Description</option>
        </select>
    </div>
    <div class='form-group'>
        <label for='sort'>Sort Comments By:</label>
        <select class='form-control' id='sort' name='sort' onchange="this.form.submit()">
            <option value='date' <?= (!isset($_GET['sort']) || $_GET['sort'] == 'date') ? 'selected' : '' ?>>Date</option>
            <option value='likes' <?= (isset($_GET['sort']) && $_GET['sort'] == 'likes') ? 'selected' : '' ?>>Likes</option>
        </select>
    </div>
    <button type='submit' class='btn btn-primary'>Search</button>
</form>

<br>

<?php if ($result->num_rows > 0): ?>
    <?php while ($repo = $result->fetch_assoc()): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><?= htmlspecialchars($repo['name']) ?></h3>
            </div>
            <div class="card-body">
                <p><?= htmlspecialchars($repo['description']) ?></p>
                <h5>Comments</h5>

                <!-- Display Comments -->
                <?php
                $repo_id = $repo['repo_id'];
                $commentsStmt = $conn->prepare("SELECT C.comment_id, C.comment, C.stars, U.username, C.created_at 
                                                FROM RepositoryComments C
                                                JOIN Users U ON C.user_id = U.user_id
                                                WHERE C.repo_id = ?
                                                ORDER BY $sortOrder");
                $commentsStmt->bind_param("i", $repo_id);
                $commentsStmt->execute();
                $commentsResult = $commentsStmt->get_result();

                // Fetch user's liked comments for the current repository
                if (isset($_SESSION['user_id'])) {
                    $current_user_id = $_SESSION['user_id'];
                    $likedCommentsStmt = $conn->prepare("SELECT comment_id FROM CommentLikes WHERE user_id = ? AND comment_id IN (SELECT comment_id FROM RepositoryComments WHERE repo_id = ?)");
                    $likedCommentsStmt->bind_param("ii", $current_user_id, $repo_id);
                    $likedCommentsStmt->execute();
                    $likedCommentsResult = $likedCommentsStmt->get_result();
                    $likedComments = [];
                    while ($liked = $likedCommentsResult->fetch_assoc()) {
                        $likedComments[] = $liked['comment_id'];
                    }
                    $likedCommentsStmt->close();
                }
                ?>

                <?php if ($commentsResult->num_rows > 0): ?>
                    <ul class="list-group mb-3">
                        <?php while ($comment = $commentsResult->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                <em><?= htmlspecialchars($comment['created_at']) ?></em>
                                <p><?= htmlspecialchars($comment['comment']) ?></p>
                                <div>
                                    <!-- Like Button -->
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="like_comment_id" value="<?= $comment['comment_id'] ?>">
                                            <button type="submit" class="btn btn-sm 
                                                <?= in_array($comment['comment_id'], $likedComments) ? 'btn-success' : 'btn-outline-primary' ?>"
                                                <?= in_array($comment['comment_id'], $likedComments) ? 'disabled' : '' ?>>
                                                <?= in_array($comment['comment_id'], $likedComments) ? '<i class="fas fa-thumbs-up"></i> Liked' : '<i class="fas fa-thumbs-up"></i> Like' ?>
                                                (<?= $comment['stars'] ?>)
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" disabled>
                                            <i class="fas fa-thumbs-up"></i> Like (<?= $comment['stars'] ?>)
                                        </button>
                                    <?php endif; ?>

                                    <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $repo['user_id'])): ?>
                                        <!-- Delete Comment Button -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_comment_id" value="<?= $comment['comment_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Are you sure you want to delete this comment?');">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                        <!-- Star Comment Button -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="star_comment_id" value="<?= $comment['comment_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-star"></i> Star
                                            </button>
                                        </form>
                                        <!-- Display Star Icon if Comment is Starred -->
                                        <?php if ($comment['stars'] > 0): ?>
                                            <span class="ml-2"><i class="fas fa-star text-warning"></i> <?= $comment['stars'] ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No comments yet.</p>
                <?php endif; ?>

                <?php $commentsStmt->close(); ?>

                <!-- Add Comment Form -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="post" action="">
                        <input type="hidden" name="repo_id" value="<?= $repo_id ?>">
                        <div class="form-group">
                            <label for="comment">Add a Comment:</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Submit Comment</button>
                    </form>
                <?php else: ?>
                    <p><a href="/lab1/src/auth/login.php">Login</a> to add a comment.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="alert alert-warning" role="alert">No repositories found.</div>
<?php endif; ?>

<?php
$stmt->close();
closeConnection($conn);
include '../includes/footer.php';
?>