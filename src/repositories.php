<?php
include 'connection.php';
include '../includes/header.php';
session_start();
include 'user_weight_calculator.php';

try {
    $conn = getConnection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Debug statement for user preferences
    if ($user_id) {
        $weightStmt = $conn->prepare("SELECT * FROM UserPreferencesWeights LIMIT 1");
        $weightStmt->execute();
        $weights = $weightStmt->get_result()->fetch_assoc();
        $weightStmt->close();

        $debugStmt = $conn->prepare("
            SELECT language, view_count, like_count
            FROM UserPreferences
            WHERE user_id = ?
        ");
        $debugStmt->bind_param("i", $user_id);
        $debugStmt->execute();
        $debugResult = $debugStmt->get_result();

        echo "<div class='alert alert-info'><h4>User Preferences:</h4><ul>";
        while ($pref = $debugResult->fetch_assoc()) {
            $calculatedWeights = calculateUserWeight($conn, $user_id, $weights, $pref['language']);
            $baseWeight = $calculatedWeights[0];
            $subscriptionWeight = $calculatedWeights[1];
            $totalWeight = $baseWeight + $subscriptionWeight;
            echo "<li>Language: {$pref['language']}, Views: {$pref['view_count']}, Likes: {$pref['like_count']}, Base Weight: {$baseWeight}, Subscription Weight: {$subscriptionWeight}, Total Weight: {$totalWeight}</li>";
        }
        echo "</ul></div>";
        $debugStmt->close();
    }

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
            } else {
                // Subscribe
                $subStmt = $conn->prepare("INSERT INTO RepositorySubscriptions (repo_id, user_id) VALUES (?, ?)");
                $subStmt->bind_param("ii", $repo_id, $user_id);
                $subStmt->execute();
                $subStmt->close();
            }
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['repo_id']) ? "?repo_id=" . $_GET['repo_id'] : ""));
        exit();
    }

    // Handle comment submission
    if ($user_id && isset($_POST['comment']) && isset($_POST['repo_id'])) {
        $comment = htmlspecialchars(trim($_POST['comment']));
        $repo_id = intval($_POST['repo_id']);
        $addCommentStmt = $conn->prepare("INSERT INTO RepositoryComments (user_id, repo_id, comment) VALUES (?, ?, ?)");
        $addCommentStmt->bind_param("iis", $user_id, $repo_id, $comment);
        $addCommentStmt->execute();
        $addCommentStmt->close();

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?repo_id=" . $repo_id);
        exit();
    }

    // Check if repo_id is set to determine the view
    if (isset($_GET['repo_id'])) {
        // Single Repository View
        $repo_id = intval($_GET['repo_id']);

        // Fetch repository details
        $repoStmt = $conn->prepare("
            SELECT R.repo_id, R.name, R.description, R.language, R.created_at, U.username, R.user_id
            FROM Repositories AS R
            JOIN Users AS U ON R.user_id = U.user_id 
            WHERE R.repo_id = ?
        ");
        $repoStmt->bind_param("i", $repo_id);
        $repoStmt->execute();
        $repoResult = $repoStmt->get_result();

        if ($repoResult->num_rows > 0) {
            $repo = $repoResult->fetch_assoc();

            // Update user preferences based on repo view
            if ($user_id) {
                $language = $repo['language'];

                // Check if the user has already viewed this repository
                $checkViewStmt = $conn->prepare("
                    SELECT * FROM RepositoryViews 
                    WHERE user_id = ? AND repo_id = ?
                ");
                $checkViewStmt->bind_param("ii", $user_id, $repo_id);
                $checkViewStmt->execute();
                $checkViewResult = $checkViewStmt->get_result();

                if ($checkViewResult->num_rows == 0) {
                    // If it's a new view, insert into RepositoryViews
                    $insertViewStmt = $conn->prepare("
                        INSERT INTO RepositoryViews (user_id, repo_id)
                        VALUES (?, ?)
                    ");
                    $insertViewStmt->bind_param("ii", $user_id, $repo_id);
                    $insertViewStmt->execute();
                    $insertViewStmt->close();

                    // Update UserPreferences
                    $updateViewStmt = $conn->prepare("
                        INSERT INTO UserPreferences (user_id, language, view_count)
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE view_count = view_count + 1
                    ");
                    $updateViewStmt->bind_param("is", $user_id, $language);
                    $updateViewStmt->execute();
                    $updateViewStmt->close();
                }

                $checkViewStmt->close();
            }

            // Fetch comments
            $commentsStmt = $conn->prepare("
                SELECT RC.comment_id, RC.comment, RC.created_at, U.username,
                       COALESCE(SUM(CASE WHEN CL.is_star = 1 THEN 1 ELSE 0 END), 0) as stars,
                       COUNT(CL.like_id) as likes
                FROM RepositoryComments RC
                JOIN Users U ON RC.user_id = U.user_id
                LEFT JOIN CommentLikes CL ON RC.comment_id = CL.comment_id
                WHERE RC.repo_id = ?
                GROUP BY RC.comment_id
                ORDER BY RC.created_at DESC
            ");
            $commentsStmt->bind_param("i", $repo_id);
            $commentsStmt->execute();
            $commentsResult = $commentsStmt->get_result();

            // Display repository details and comments
?>
            <div class="container mt-4">
                <h1><?= htmlspecialchars($repo['name']) ?></h1>
                <p><?= htmlspecialchars($repo['description']) ?></p>
                <p>Language: <?= htmlspecialchars($repo['language']) ?></p>
                <p>Created by: <?= htmlspecialchars($repo['username']) ?></p>
                <p>Created at: <?= htmlspecialchars($repo['created_at']) ?></p>

                <?php if ($user_id): ?>
                    <?php
                    $checkLikeStmt = $conn->prepare("SELECT * FROM RepositoryLikes WHERE user_id = ? AND repo_id = ?");
                    $checkLikeStmt->bind_param("ii", $user_id, $repo['repo_id']);
                    $checkLikeStmt->execute();
                    $isLiked = $checkLikeStmt->get_result()->num_rows > 0;
                    $checkLikeStmt->close();
                    ?>
                    <button type="button" class="btn btn-sm <?= $isLiked ? 'btn-primary' : 'btn-outline-primary' ?> like-repo-btn" data-repo-id="<?= $repo['repo_id'] ?>">
                        <i class="fas fa-thumbs-up"></i> <?= $isLiked ? 'Unlike' : 'Like' ?>
                    </button>
                <?php endif; ?>

                <h2 class="mt-4">Comments</h2>
                <?php if ($commentsResult->num_rows > 0): ?>
                    <ul class="list-group mb-3">
                        <?php while ($comment = $commentsResult->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                        <em class="text-muted ml-2"><?= htmlspecialchars($comment['created_at']) ?></em>
                                    </div>
                                    <div>
                                        <span class="badge badge-primary badge-pill mr-2">
                                            <i class="fas fa-thumbs-up"></i> <?= $comment['likes'] ?>
                                        </span>
                                        <span class="badge badge-warning badge-pill">
                                            <i class="fas fa-star"></i> <?= $comment['stars'] ?>
                                        </span>
                                    </div>
                                </div>
                                <p class="mt-2"><?= htmlspecialchars($comment['comment']) ?></p>
                                <?php if ($user_id): ?>
                                    <div class="btn-group" role="group">
                                        <?php
                                        $checkCommentLikeStmt = $conn->prepare("SELECT * FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 0");
                                        $checkCommentLikeStmt->bind_param("ii", $user_id, $comment['comment_id']);
                                        $checkCommentLikeStmt->execute();
                                        $isCommentLiked = $checkCommentLikeStmt->get_result()->num_rows > 0;
                                        $checkCommentLikeStmt->close();

                                        $checkCommentStarStmt = $conn->prepare("SELECT * FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 1");
                                        $checkCommentStarStmt->bind_param("ii", $user_id, $comment['comment_id']);
                                        $checkCommentStarStmt->execute();
                                        $isCommentStarred = $checkCommentStarStmt->get_result()->num_rows > 0;
                                        $checkCommentStarStmt->close();
                                        ?>
                                        <button type="button" class="btn btn-sm <?= $isCommentLiked ? 'btn-primary' : 'btn-outline-primary' ?> like-comment-btn" data-comment-id="<?= $comment['comment_id'] ?>">
                                            <i class="fas fa-thumbs-up"></i> <?= $isCommentLiked ? 'Unlike' : 'Like' ?>
                                        </button>
                                        <button type="button" class="btn btn-sm <?= $isCommentStarred ? 'btn-warning' : 'btn-outline-warning' ?> star-comment-btn" data-comment-id="<?= $comment['comment_id'] ?>">
                                            <i class="fas fa-star"></i> <?= $isCommentStarred ? 'Unstar' : 'Star' ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No comments yet.</p>
                <?php endif; ?>

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
        <?php
            $commentsStmt->close();
        } else {
            echo "<div class='alert alert-warning'>Repository not found.</div>";
        }
        $repoStmt->close();
    } else {
        // Repositories List View
        $searchField = isset($_GET['field']) ? htmlspecialchars($_GET['field']) : '';
        $searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

        $weightStmt = $conn->prepare("SELECT * FROM UserPreferencesWeights LIMIT 1");
        $weightStmt->execute();
        $weightResult = $weightStmt->get_result();
        $weights = $weightResult->fetch_assoc();
        $weightStmt->close();

        $sql = "
            SELECT R.repo_id, R.name, R.description, R.language, R.user_id, U.username,
                   (
                       SELECT COUNT(*)
                       FROM RepositoryLikes RL
                       WHERE RL.repo_id = R.repo_id
                   ) as total_likes,
                   CASE WHEN ? IS NOT NULL THEN
                       (
                           SELECT 
                               (COALESCE(UP.view_count, 0) * ? + COALESCE(UP.like_count, 0) * ?) +
                               (
                                   SELECT COALESCE(SUM(
                                       (COALESCE(UPS.view_count, 0) * ? + COALESCE(UPS.like_count, 0) * ?)
                                   ), 0) * ?
                                   FROM UserPreferences UPS
                                   JOIN RepositorySubscriptions RS ON RS.user_id = ?
                                   JOIN Repositories RS_R ON RS.repo_id = RS_R.repo_id
                                   WHERE UPS.user_id = RS_R.user_id AND UPS.language = R.language
                               )
                           FROM UserPreferences UP
                           WHERE UP.user_id = ? AND UP.language = R.language
                       )
                   ELSE 0 END as user_preference
            FROM Repositories AS R 
            JOIN Users AS U ON R.user_id = U.user_id
        ";

        $params = [
            $user_id,
            $weights['view_weight'],
            $weights['like_weight'],
            $weights['view_weight'],
            $weights['like_weight'],
            $weights['subscription_weight'],
            $user_id,
            $user_id
        ];
        $types = "idddddii";

        if ($searchField && $searchTerm) {
            $sql .= " WHERE R.$searchField LIKE ?";
            $params[] = "%$searchTerm%";
            $types .= "s";
        }

        if (isset($_GET['sort'])) {
            switch ($_GET['sort']) {
                case 'language':
                    $sql .= " ORDER BY R.language ASC, user_preference DESC, total_likes DESC";
                    break;
                case 'likes':
                    $sql .= " ORDER BY total_likes DESC, user_preference DESC";
                    break;
                default:
                    $sql .= " ORDER BY user_preference DESC, total_likes DESC, R.created_at DESC";
            }
        } else {
            $sql .= " ORDER BY user_preference DESC, total_likes DESC, R.created_at DESC";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();

        $repositories = [];
        while ($row = $result->fetch_assoc()) {
            $repositories[] = $row;
        }

        // Remove this sorting
        // usort($repositories, function ($a, $b) {
        //     return $b['user_preference'] - $a['user_preference'];
        // });

        ?>

        <div class="container mt-4">
            <h1>Repositories</h1>
            <form method="get" class="mb-3">
                <div class="form-row">
                    <div class="col">
                        <select name="field" class="form-control">
                            <option value="name" <?= $searchField == 'name' ? 'selected' : '' ?>>Name</option>
                            <option value="description" <?= $searchField == 'description' ? 'selected' : '' ?>>Description</option>
                            <option value="language" <?= $searchField == 'language' ? 'selected' : '' ?>>Language</option>
                        </select>
                    </div>
                    <div class="col">
                        <input type="text" name="search" class="form-control" placeholder="Search term" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <div class="col">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>

            <div class="mb-3">
                <a href="?sort=likes" class="btn btn-secondary">Sort by Likes</a>
                <a href="?sort=language" class="btn btn-secondary">Sort by Language</a>
            </div>

            <?php if ($repositories): ?>
                <?php foreach ($repositories as $repo): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($repo['name']) ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">By <?= htmlspecialchars($repo['username']) ?></h6>
                            <p class="card-text"><?= htmlspecialchars($repo['description']) ?></p>
                            <p class="card-text"><small class="text-muted">Language: <?= htmlspecialchars($repo['language']) ?></small></p>
                            <p class="card-text"><small class="text-muted">Total Likes: <?= $repo['total_likes'] ?></small></p>
                            <a href="?repo_id=<?= $repo['repo_id'] ?>" class="btn btn-primary">View Details</a>
                            <?php if ($user_id && $user_id != $repo['user_id']): ?>
                                <?php
                                $checkSubStmt = $conn->prepare("SELECT * FROM RepositorySubscriptions WHERE user_id = ? AND repo_id = ?");
                                $checkSubStmt->bind_param("ii", $user_id, $repo['repo_id']);
                                $checkSubStmt->execute();
                                $isSubscribed = $checkSubStmt->get_result()->num_rows > 0;
                                $checkSubStmt->close();
                                ?>
                                <button type="button" class="btn btn-sm <?= $isSubscribed ? 'btn-secondary' : 'btn-outline-secondary' ?> subscribe-repo-btn" data-repo-id="<?= $repo['repo_id'] ?>">
                                    <?= $isSubscribed ? 'Unsubscribe' : 'Subscribe' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                        term: request.term,
                        field: $("#searchField").val()
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 2,
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Like repository
        document.querySelectorAll('.like-repo-btn').forEach(button => {
            button.addEventListener('click', function() {
                const repoId = this.getAttribute('data-repo-id');
                fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=like_repo&repo_id=${repoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.toggle('btn-outline-primary', !data.liked);
                            this.classList.toggle('btn-primary', data.liked);
                            this.innerHTML = data.liked ? '<i class="fas fa-thumbs-up"></i> Unlike' : '<i class="fas fa-thumbs-up"></i> Like';

                            // Update UserPreferences for likes
                            fetch('update_preferences.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=update_like&repo_id=${repoId}&liked=${data.liked}`
                            });
                        }
                    });
            });
        });

        // Subscribe to repository
        document.querySelectorAll('.subscribe-repo-btn').forEach(button => {
            button.addEventListener('click', function() {
                const repoId = this.getAttribute('data-repo-id');
                fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=subscribe_repo&repo_id=${repoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.toggle('btn-outline-secondary', !data.subscribed);
                            this.classList.toggle('btn-secondary', data.subscribed);
                            this.innerHTML = data.subscribed ? 'Unsubscribe' : 'Subscribe';
                        }
                    });
            });
        });

        // Like comment
        document.querySelectorAll('.like-comment-btn').forEach(button => {
            button.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=like_comment&comment_id=${commentId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.toggle('btn-outline-primary', !data.liked);
                            this.classList.toggle('btn-primary', data.liked);
                            this.innerHTML = data.liked ? '<i class="fas fa-thumbs-up"></i> Unlike' : '<i class="fas fa-thumbs-up"></i> Like';
                            // Update like count
                            const likeCountSpan = this.closest('.list-group-item').querySelector('.badge-primary');
                            if (likeCountSpan) {
                                const currentLikes = parseInt(likeCountSpan.textContent);
                                likeCountSpan.textContent = data.liked ? currentLikes + 1 : currentLikes - 1;
                            }
                        }
                    });
            });
        });

        // Star comment
        document.querySelectorAll('.star-comment-btn').forEach(button => {
            button.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=star_comment&comment_id=${commentId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.toggle('btn-outline-warning', !data.starred);
                            this.classList.toggle('btn-warning', data.starred);
                            this.innerHTML = data.starred ? '<i class="fas fa-star"></i> Unstar' : '<i class="fas fa-star"></i> Star';
                            // Update star count
                            const starCountSpan = this.closest('.list-group-item').querySelector('.badge-warning');
                            if (starCountSpan) {
                                starCountSpan.textContent = data.starCount;
                            }
                        }
                    });
            });
        });
    });
</script>