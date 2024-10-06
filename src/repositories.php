<?php
include 'connection.php';
include '../includes/header.php';
session_start();

try {
    $conn = getConnection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Handle subscribe/unsubscribe actions
    if ($user_id && isset($_POST['subscribe_repo_id'])) {
        $repo_id = intval($_POST['subscribe_repo_id']);

        // Check if the repository belongs to the current user
        $checkOwnerStmt = $conn->prepare("SELECT user_id, language FROM Repositories WHERE repo_id = ?");
        $checkOwnerStmt->bind_param("i", $repo_id);
        $checkOwnerStmt->execute();
        $ownerResult = $checkOwnerStmt->get_result();
        $repoData = $ownerResult->fetch_assoc();
        $checkOwnerStmt->close();

        if ($repoData['user_id'] != $user_id) {
            // Check if already subscribed
            $checkStmt = $conn->prepare("SELECT * FROM RepositorySubscriptions WHERE repo_id = ? AND user_id = ?");
            $checkStmt->bind_param("ii", $repo_id, $user_id);
            $checkStmt->execute();
            $checkStmt->store_result();
            $isSubscribed = $checkStmt->num_rows > 0;
            $checkStmt->close();

            if ($isSubscribed) {
                // Unsubscribe
                $unsubStmt = $conn->prepare("DELETE FROM RepositorySubscriptions WHERE repo_id = ? AND user_id = ?");
                $unsubStmt->bind_param("ii", $repo_id, $user_id);
                $unsubStmt->execute();
                $unsubStmt->close();

                // Decrease user preference for the repository's language
                $updatePrefStmt = $conn->prepare("
                    UPDATE UserPreferences 
                    SET preference_count = GREATEST(preference_count - 1, 0)
                    WHERE user_id = ? AND language = ?
                ");
                $updatePrefStmt->bind_param("is", $user_id, $repoData['language']);
                $updatePrefStmt->execute();
                $updatePrefStmt->close();
            } else {
                // Subscribe
                $subStmt = $conn->prepare("INSERT INTO RepositorySubscriptions (repo_id, user_id) VALUES (?, ?)");
                $subStmt->bind_param("ii", $repo_id, $user_id);
                $subStmt->execute();
                $subStmt->close();

                // Increase user preference for the repository's language
                $updatePrefStmt = $conn->prepare("
                    INSERT INTO UserPreferences (user_id, language, preference_count) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE preference_count = preference_count + 1
                ");
                $updatePrefStmt->bind_param("is", $user_id, $repoData['language']);
                $updatePrefStmt->execute();
                $updatePrefStmt->close();
            }
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['repo_id']) ? "?repo_id=" . $_GET['repo_id'] : ""));
        exit();
    }

    // Check if repo_id is set to determine the view
    if (isset($_GET['repo_id'])) {
        // ----------------------------
        // Single Repository View
        // ----------------------------
        $repo_id = intval($_GET['repo_id']);

        // Fetch repository details
        $repoStmt = $conn->prepare("
            SELECT R.repo_id, R.name, R.description, R.language, R.created_at, U.username 
            FROM Repositories AS R
            JOIN Users AS U ON R.user_id = U.user_id 
            WHERE R.repo_id = ?
        ");
        $repoStmt->bind_param("i", $repo_id);
        $repoStmt->execute();
        $repoResult = $repoStmt->get_result();

        if ($repoResult->num_rows > 0) {
            $repo = $repoResult->fetch_assoc();

            // Update user preferences based on repo language
            if ($user_id) {
                $language = $repo['language'];
                $prefStmt = $conn->prepare("
                    INSERT INTO UserPreferences (user_id, language, preference_count) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE preference_count = preference_count + 1
                ");
                $prefStmt->bind_param("is", $user_id, $language);
                $prefStmt->execute();
                $prefStmt->close();
            }

?>
            <div class="container">
                <h1><?= htmlspecialchars($repo['name']) ?></h1>
                <p><?= htmlspecialchars($repo['description']) ?></p>
                <p><strong>Language:</strong> <?= htmlspecialchars($repo['language']) ?></p>
                <p><strong>Created by:</strong> <?= htmlspecialchars($repo['username']) ?></p>
                <p><strong>Created at:</strong> <?= htmlspecialchars($repo['created_at']) ?></p>

                <!-- Subscribe/Unsubscribe Button -->
                <?php if ($user_id && $repo['user_id'] != $user_id): ?>
                    <?php
                    $subscribeCheckStmt = $conn->prepare("
                        SELECT * FROM RepositorySubscriptions 
                        WHERE repo_id = ? AND user_id = ?
                    ");
                    $subscribeCheckStmt->bind_param("ii", $repo['repo_id'], $user_id);
                    $subscribeCheckStmt->execute();
                    $subscribeCheckStmt->store_result();
                    $isSubscribed = $subscribeCheckStmt->num_rows > 0;
                    $subscribeCheckStmt->close();
                    ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="subscribe_repo_id" value="<?= $repo['repo_id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $isSubscribed ? 'btn-danger' : 'btn-primary' ?>">
                            <?= $isSubscribed ? 'Unsubscribe' : 'Subscribe' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Go to All Repositories Button -->
                <br><br>
                <a href="repositories.php" class="btn btn-secondary">Go to All Repositories</a>

                <!-- Comments Section -->
                <h5>Comments</h5>
                <?php
                // Fetch comments for this repository
                $commentsStmt = $conn->prepare("
                    SELECT C.comment_id, C.comment, C.stars, U.username, C.created_at 
                    FROM RepositoryComments AS C
                    JOIN Users AS U ON C.user_id = U.user_id
                    WHERE C.repo_id = ?
                    ORDER BY C.stars DESC, C.created_at DESC
                ");
                $commentsStmt->bind_param("i", $repo_id);
                $commentsStmt->execute();
                $commentsResult = $commentsStmt->get_result();

                // Fetch user's liked comments for the current repository
                if ($user_id) {
                    $likedCommentsStmt = $conn->prepare("
                        SELECT comment_id 
                        FROM CommentLikes 
                        WHERE user_id = ? AND comment_id IN (
                            SELECT comment_id FROM RepositoryComments WHERE repo_id = ?
                        )
                    ");
                    $likedCommentsStmt->bind_param("ii", $user_id, $repo_id);
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
                                    <?php if ($user_id): ?>
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

                                    <?php if ($user_id && $_SESSION['user_id'] == $repo['user_id']): ?>
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
                <?php if ($user_id): ?>
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
        <?php
        } else {
            echo "<div class='alert alert-warning' role='alert'>Repository not found.</div>";
        }

        $repoStmt->close();
    } else {
        // ----------------------------
        // Repository Listing View
        // ----------------------------

        // Fetch search parameters
        $searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
        $searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

        // Handle sorting preferences
        $sortOrder = "R.created_at DESC"; // Default sort
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'likes') {
                $sortOrder = "R.stars DESC";
            } elseif ($_GET['sort'] == 'language') {
                $sortOrder = "R.language ASC";
            }
        }

        // Modify the repository fetching query
        $sql = "
            SELECT R.repo_id, R.name, R.description, R.language, R.user_id, U.username,
                   COALESCE(UP.preference_count, 0) as user_preference
            FROM Repositories AS R 
            JOIN Users AS U ON R.user_id = U.user_id
            LEFT JOIN UserPreferences AS UP ON R.language = UP.language AND UP.user_id = ?
        ";

        $params = [$user_id];
        $types = "i";

        if ($searchField && $searchTerm) {
            $sql .= " WHERE R.$searchField LIKE ?";
            $params[] = "%$searchTerm%";
            $types .= "s";
        }

        $sql .= " ORDER BY user_preference DESC, $sortOrder";

        $stmt = $conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        ?>

        <div class="container">
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
                        <option value='language' <?= ($searchField == 'language') ? 'selected' : '' ?>>Language</option>
                    </select>
                </div>
                <div class='form-group'>
                    <label for='sort'>Sort Repositories By:</label>
                    <select class='form-control' id='sort' name='sort'>
                        <option value='date' <?= (!isset($_GET['sort']) || $_GET['sort'] == 'date') ? 'selected' : '' ?>>Date</option>
                        <option value='likes' <?= (isset($_GET['sort']) && $_GET['sort'] == 'likes') ? 'selected' : '' ?>>Likes</option>
                        <option value='language' <?= (isset($_GET['sort']) && $_GET['sort'] == 'language') ? 'selected' : '' ?>>Language</option>
                    </select>
                </div>
                <button type='submit' class='btn btn-primary'>Search</button>
            </form>

            <br>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($repo = $result->fetch_assoc()): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><a href="repositories.php?repo_id=<?= $repo['repo_id'] ?>"><?= htmlspecialchars($repo['name']) ?></a></h3>
                        </div>
                        <div class="card-body">
                            <p><?= htmlspecialchars($repo['description']) ?></p>
                            <p>Created by: <?= htmlspecialchars($repo['username']) ?></p>

                            <!-- Subscribe/Unsubscribe Button -->
                            <?php if ($user_id && $repo['user_id'] != $user_id): ?>
                                <?php
                                $subscribeCheckStmt = $conn->prepare("
                                    SELECT * FROM RepositorySubscriptions 
                                    WHERE repo_id = ? AND user_id = ?
                                ");
                                $subscribeCheckStmt->bind_param("ii", $repo['repo_id'], $user_id);
                                $subscribeCheckStmt->execute();
                                $subscribeCheckStmt->store_result();
                                $isSubscribed = $subscribeCheckStmt->num_rows > 0;
                                $subscribeCheckStmt->close();
                                ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="subscribe_repo_id" value="<?= $repo['repo_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isSubscribed ? 'btn-danger' : 'btn-primary' ?>">
                                        <?= $isSubscribed ? 'Unsubscribe' : 'Subscribe' ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Comments Section -->
                            <h5>Comments</h5>
                            <?php
                            // Fetch comments for this repository
                            $commentsStmt = $conn->prepare("
                                SELECT C.comment_id, C.comment, C.stars, U.username, C.created_at 
                                FROM RepositoryComments AS C
                                JOIN Users AS U ON C.user_id = U.user_id
                                WHERE C.repo_id = ?
                                ORDER BY C.stars DESC, C.created_at DESC
                            ");
                            $commentsStmt->bind_param("i", $repo['repo_id']);
                            $commentsStmt->execute();
                            $commentsResult = $commentsStmt->get_result();

                            // Fetch user's liked comments for the current repository
                            if ($user_id) {
                                $likedCommentsStmt = $conn->prepare("
                                    SELECT comment_id 
                                    FROM CommentLikes 
                                    WHERE user_id = ? AND comment_id IN (
                                        SELECT comment_id FROM RepositoryComments WHERE repo_id = ?
                                    )
                                ");
                                $likedCommentsStmt->bind_param("ii", $user_id, $repo['repo_id']);
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
                                                <?php if ($user_id): ?>
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

                                                <?php if ($user_id && $_SESSION['user_id'] == $repo['user_id']): ?>
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
                            <?php if ($user_id): ?>
                                <form method="post" action="">
                                    <input type="hidden" name="repo_id" value="<?= $repo['repo_id'] ?>">
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

            <?php $stmt->close(); ?>
        </div>

<?php
    }

    closeConnection($conn);
    include '../includes/footer.php';
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!-- Add jQuery and jQuery UI for Autocomplete -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<link rel='stylesheet' href='https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css'>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<script>
    $(function() {
        $("#search").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "autocomplete.php",
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 2,
        });
    });
</script>