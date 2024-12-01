<?php

ob_start();
require_once 'connection.php';
require_once '../vendor/autoload.php';
require_once 'cache_manager.php';
require_once 'user_weight_calculator.php';
require_once 'Middleware/ProfilingMiddleware.php';

session_start();

use App\CacheManager;
use Anko\Lab1\Middleware\ProfilingMiddleware;
use Anko\Lab1\Debug\DebugBarManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Initialize Logger
$logger = new Logger('app');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

// Initialize profiler
$profiler = new ProfilingMiddleware($logger);

// Initialize DebugBar
try {
    DebugBarManager::initialize();
} catch (\Exception $e) {
    error_log("Failed to initialize DebugBar: " . $e->getMessage());
}

$debugbar = DebugBarManager::getDebugBar();
$debugbar['messages']->addMessage('DebugBar initialized');
if (DebugBarManager::isEnabled()) {
    $debugbar['time']->startMeasure('page_render', 'Page Rendering');
    $debugbarRenderer = DebugBarManager::getRenderer();
    echo $debugbarRenderer->renderHead();
}

try {
    $conn = getConnection();
    $cacheManager = new CacheManager($conn);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Add this near the top of the file, before displaying any content
    if (isset($_POST['clear_cache']) && $user_id) {
        $cacheManager->clearCache($user_id);
        // Clear the cache metadata cookie
        setcookie('weights_cache_metadata', '', time() - 3600, '/');
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['weight_calculator'])) {
        $_SESSION['weight_calculator'] = $_POST['weight_calculator'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Get cached weights if user is logged in
    $userWeightsData = null;
    if ($user_id) {
        try {
            $userWeightsData = $cacheManager->getUserWeights($user_id);
        } catch (\Exception $e) {
            error_log("Error getting user weights: " . $e->getMessage());
            $userWeightsData = ['weights' => [], 'isCached' => false];
        }
    }

    // Now include the header
    include '../includes/header.php';


    if ($user_id) {
        echo '<div class="container mt-3">
            <div class="row">
                <div class="col-md-6">
                    <form method="post" class="mb-3">
                        <button type="submit" name="clear_cache" class="btn btn-warning btn-sm">
                            Clear Cache
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="post" class="mb-3">
                        <div class="input-group">
                            <select name="weight_calculator" class="form-select form-select-sm">
                                <option value="stored_procedure" ' . (($_SESSION['weight_calculator'] ?? '') === 'stored_procedure' ? 'selected' : '') . '>
                                    Stored Procedure (Fast)
                                </option>
                                <option value="recursive" ' . (($_SESSION['weight_calculator'] ?? '') === 'recursive' ? 'selected' : '') . '>
                                    Recursive PHP (Detailed)
                                </option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Set Calculator
                            </button>
                        </div>
                    </form>
                </div>
            </div>';

        // Add a small indicator showing current calculation method
        $currentMethod = $_SESSION['weight_calculator'] ?? 'stored_procedure';
        echo '<div class="small text-muted mb-3">
            Current calculation method: ' . ucfirst(str_replace('_', ' ', $currentMethod)) . '
        </div>
        </div>';
    }

    // Display user weights if available
    if ($user_id && $userWeightsData && !empty($userWeightsData['weights'])) {
        echo "<div class='alert alert-info'>
            <h4>User Preferences (" . ($userWeightsData['isCached'] ? 'Cached' : 'Live') . "):</h4>
            <ul>";

        // Sort weights by total weight descending
        $weights = $userWeightsData['weights'];
        arsort($weights);

        $index = 0;
        foreach ($weights as $language => $weight) {
            echo "<li>" . $index . ". Language: {$language}, 
                 Base Weight: {$weight['baseWeight']}, 
                 Subscription Weight: {$weight['subscriptionWeight']}, 
                 Total Weight: {$weight['totalWeight']}</li>";
            $index++;
        }
        echo "</ul>";

        // Add cache status inside the preferences div
        echo "<div class='mt-2'><small>Cache Status: " .
            ($userWeightsData['isCached'] ? 'Cached' : 'Live') .
            "</small></div>";
        echo "</div>";
    }

    // Handle repository subscriptions
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

                // Clear the cache for this user
                if ($cacheManager) {
                    $cacheManager->clearCache($user_id);
                }
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

                    // Clear the cache for this user after updating preferences
                    if ($cacheManager) {
                        $cacheManager->clearCache($user_id);
                    }
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

            // Fetch repository files
            $filesStmt = $conn->prepare("
                    SELECT file_name, file_path 
                    FROM RepositoryFiles 
                    WHERE repo_id = ?
                    ORDER BY file_name ASC
                ");
            $filesStmt->bind_param("i", $repo_id);
            $filesStmt->execute();
            $filesResult = $filesStmt->get_result();
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
                <h2 class="mt-4">Repository Files</h2>
                <?php
                $filesStmt = $conn->prepare("SELECT file_name FROM RepositoryFiles WHERE repo_id = ? ORDER BY file_name");
                $filesStmt->bind_param("i", $repo['repo_id']);
                $filesStmt->execute();
                $filesResult = $filesStmt->get_result();

                if ($filesResult->num_rows > 0): ?>
                    <div class="list-group mb-4">
                        <?php while ($file = $filesResult->fetch_assoc()): ?>
                            <a href="#" class="list-group-item list-group-item-action file-preview-link"
                                onclick="previewFile(<?= $repo['repo_id'] ?>, '<?= htmlspecialchars($file['file_name']) ?>'); return false;">
                                <i class="fas fa-file me-2"></i>
                                <?= htmlspecialchars($file['file_name']) ?>
                            </a>
                        <?php endwhile; ?>
                    </div>

                    <!-- File Preview Modal -->
                    <div class="modal fade" id="filePreviewModal" tabindex="-1" role="dialog" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="filePreviewModalLabel">File Preview</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <pre id="fileContent"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No files uploaded yet.</p>
                <?php
                endif;
                $filesStmt->close();
                ?>
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

        // Profile the main repository query
        $profiled = $profiler->profile(function () use ($conn, $sql, $params, $types) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }, 'main_repository_query');

        $result = $profiled['result'];

        $repositories = [];
        while ($row = $result->fetch_assoc()) {
            $repositories[] = $row;
        }
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
                            <div class="d-flex align-items-center mb-3">
                                <img src="display_avatar.php?user_id=<?= $repo['user_id'] ?>"
                                    alt="<?= htmlspecialchars($repo['username']) ?>'s avatar"
                                    class="rounded-circle mr-2"
                                    style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($repo['name']) ?></h5>
                                    <h6 class="card-subtitle">By <?= htmlspecialchars($repo['username']) ?></h6>
                                </div>
                            </div>
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

            <?php if (isset($stmt) && $stmt): ?>
                <?php $stmt->close(); ?>
            <?php endif; ?>
        </div>
<?php
    }
    // Add the cache status indicator at the bottom
    echo "<div id='cacheStatusIndicator' class='position-fixed bottom-0 end-0 p-3'></div>";

    closeConnection($conn);
    include '../includes/footer.php';
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!-- JavaScript Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<?php if (DebugBarManager::isEnabled()): ?>
    <?php
    $debugbar = DebugBarManager::getDebugBar();
    $debugbar['time']->stopMeasure('page_render');
    $debugbarRenderer = DebugBarManager::getRenderer();
    echo $debugbarRenderer->render();
    ?>
<?php endif; ?>

<!-- Cache Status and File Preview Scripts -->
<script>
    function displayCacheStatus() {
        const cacheMetadata = getCookie('weights_cache_metadata');
        if (cacheMetadata) {
            try {
                const data = JSON.parse(cacheMetadata);
                console.log('Cache Status:', data);

                const indicator = document.getElementById('cacheStatusIndicator');
                if (indicator) {
                    indicator.innerHTML = `
                            <div class="alert alert-info">
                                <small>
                                    <strong>Cache Status:</strong> ${data.status}<br>
                                    ${data.cached_at ? `<strong>Cached at:</strong> ${data.cached_at}<br>` : ''}
                                    ${data.expires_in_minutes ? `<strong>Expires in:</strong> ${data.expires_in_minutes} minutes` : ''}
                                </small>
                            </div>
                        `;
                }
            } catch (e) {
                console.error('Error parsing cache metadata:', e);
            }
        }
    }

    function previewFile(repoId, fileName) {
        fetch(`display_repo_file.php?repo_id=${repoId}&file_name=${encodeURIComponent(fileName)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(content => {
                const preElement = document.getElementById('fileContent');
                preElement.textContent = content;
                $('#filePreviewModal').modal('show');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading file: ' + error.message);
            });
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    }

    // Initialize everything when the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        displayCacheStatus();

        // Modal handling
        $('#filePreviewModal').on('hidden.bs.modal', function() {
            document.getElementById('fileContent').textContent = '';
        });

        // Handle repository subscription buttons
        $('.subscribe-repo-btn').click(function() {
            const repoId = $(this).data('repo-id');
            const form = $('<form method="post"></form>');
            form.append(`<input type="hidden" name="subscribe_repo_id" value="${repoId}">`);
            $('body').append(form);
            form.submit();
        });
    });

    // Update cache status every minute
    setInterval(displayCacheStatus, 60000);
</script>

<style>
    #cacheStatusIndicator {
        z-index: 1000;
        opacity: 0.9;
    }

    #cacheStatusIndicator:hover {
        opacity: 1;
    }

    .file-preview-link {
        cursor: pointer;
    }

    .file-preview-link:hover {
        background-color: #f8f9fa;
    }
</style>