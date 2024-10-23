<?php
include 'connection.php';
session_start();

$conn = getConnection();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'like_repo':
                handleRepoLike($conn, $user_id);
                break;
            case 'subscribe_repo':
                handleRepoSubscribe($conn, $user_id);
                break;
            case 'like_comment':
                handleCommentLike($conn, $user_id);
                break;
            case 'star_comment':
                handleCommentStar($conn, $user_id);
                break;
        }
    }
}

function handleRepoLike($conn, $user_id)
{
    if (!$user_id || !isset($_POST['repo_id'])) {
        echo json_encode(['success' => false]);
        return;
    }

    $repo_id = intval($_POST['repo_id']);

    // Check if already liked
    $checkLikeStmt = $conn->prepare("SELECT * FROM RepositoryLikes WHERE user_id = ? AND repo_id = ?");
    $checkLikeStmt->bind_param("ii", $user_id, $repo_id);
    $checkLikeStmt->execute();
    $isLiked = $checkLikeStmt->get_result()->num_rows > 0;
    $checkLikeStmt->close();

    if ($isLiked) {
        // Unlike
        $unlikeStmt = $conn->prepare("DELETE FROM RepositoryLikes WHERE user_id = ? AND repo_id = ?");
        $unlikeStmt->bind_param("ii", $user_id, $repo_id);
        $unlikeStmt->execute();
        $unlikeStmt->close();

        // Update UserPreferences
        updateUserPreference($conn, $user_id, $repo_id, -1);

        echo json_encode(['success' => true, 'liked' => false]);
    } else {
        // Like
        $likeStmt = $conn->prepare("INSERT INTO RepositoryLikes (user_id, repo_id) VALUES (?, ?)");
        $likeStmt->bind_param("ii", $user_id, $repo_id);
        $likeStmt->execute();
        $likeStmt->close();

        // Update UserPreferences
        updateUserPreference($conn, $user_id, $repo_id, 1);

        echo json_encode(['success' => true, 'liked' => true]);
    }
}

function updateUserPreference($conn, $user_id, $repo_id, $likeChange)
{
    // Get repository language
    $langStmt = $conn->prepare("SELECT language FROM Repositories WHERE repo_id = ?");
    $langStmt->bind_param("i", $repo_id);
    $langStmt->execute();
    $langResult = $langStmt->get_result()->fetch_assoc();
    $langStmt->close();

    if ($langResult) {
        $language = $langResult['language'];
        $updatePrefStmt = $conn->prepare("
            INSERT INTO UserPreferences (user_id, language, like_count)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE like_count = like_count + ?
        ");
        $updatePrefStmt->bind_param("isii", $user_id, $language, $likeChange, $likeChange);
        $updatePrefStmt->execute();
        $updatePrefStmt->close();
    }
}

function handleRepoSubscribe($conn, $user_id)
{
    if (!$user_id || !isset($_POST['repo_id'])) {
        echo json_encode(['success' => false]);
        return;
    }

    $repo_id = intval($_POST['repo_id']);

    // Check if already subscribed
    $checkSubStmt = $conn->prepare("SELECT * FROM RepositorySubscriptions WHERE user_id = ? AND repo_id = ?");
    $checkSubStmt->bind_param("ii", $user_id, $repo_id);
    $checkSubStmt->execute();
    $checkSubStmt->store_result();
    $isSubscribed = $checkSubStmt->num_rows > 0;
    $checkSubStmt->close();

    if ($isSubscribed) {
        // Unsubscribe
        $unsubStmt = $conn->prepare("DELETE FROM RepositorySubscriptions WHERE user_id = ? AND repo_id = ?");
        $unsubStmt->bind_param("ii", $user_id, $repo_id);
        $unsubStmt->execute();
        $unsubStmt->close();
        echo json_encode(['success' => true, 'subscribed' => false]);
    } else {
        // Subscribe
        $subStmt = $conn->prepare("INSERT INTO RepositorySubscriptions (user_id, repo_id) VALUES (?, ?)");
        $subStmt->bind_param("ii", $user_id, $repo_id);
        $subStmt->execute();
        $subStmt->close();
        echo json_encode(['success' => true, 'subscribed' => true]);
    }

    // Update UserPreferences
    $updatePrefStmt = $conn->prepare("
        INSERT INTO UserPreferences (user_id, language, preference_count)
        SELECT ?, R.language, 1
        FROM Repositories R
        WHERE R.repo_id = ?
        ON DUPLICATE KEY UPDATE preference_count = preference_count + IF(?, 1, -1)
    ");
    $updatePrefStmt->bind_param("iii", $user_id, $repo_id, $isSubscribed);
    $updatePrefStmt->execute();
    $updatePrefStmt->close();
}

function handleCommentLike($conn, $user_id)
{
    if (!$user_id || !isset($_POST['comment_id'])) {
        echo json_encode(['success' => false]);
        return;
    }

    $comment_id = intval($_POST['comment_id']);

    // Check if already liked
    $checkLikeStmt = $conn->prepare("SELECT * FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 0");
    $checkLikeStmt->bind_param("ii", $user_id, $comment_id);
    $checkLikeStmt->execute();
    $checkLikeStmt->store_result();
    $isLiked = $checkLikeStmt->num_rows > 0;
    $checkLikeStmt->close();

    if ($isLiked) {
        // Unlike
        $unlikeStmt = $conn->prepare("DELETE FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 0");
        $unlikeStmt->bind_param("ii", $user_id, $comment_id);
        $unlikeStmt->execute();
        $unlikeStmt->close();
        echo json_encode(['success' => true, 'liked' => false]);
    } else {
        // Like
        $likeStmt = $conn->prepare("INSERT INTO CommentLikes (user_id, comment_id, is_star) VALUES (?, ?, 0)");
        $likeStmt->bind_param("ii", $user_id, $comment_id);
        $likeStmt->execute();
        $likeStmt->close();
        echo json_encode(['success' => true, 'liked' => true]);
    }
}

function handleCommentStar($conn, $user_id)
{
    if (!$user_id || !isset($_POST['comment_id'])) {
        echo json_encode(['success' => false]);
        return;
    }

    $comment_id = intval($_POST['comment_id']);

    // Check if already starred
    $checkStarStmt = $conn->prepare("SELECT * FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 1");
    $checkStarStmt->bind_param("ii", $user_id, $comment_id);
    $checkStarStmt->execute();
    $checkStarStmt->store_result();
    $isStarred = $checkStarStmt->num_rows > 0;
    $checkStarStmt->close();

    if ($isStarred) {
        // Unstar
        $unstarStmt = $conn->prepare("DELETE FROM CommentLikes WHERE user_id = ? AND comment_id = ? AND is_star = 1");
        $unstarStmt->bind_param("ii", $user_id, $comment_id);
        $unstarStmt->execute();
        $unstarStmt->close();
    } else {
        // Star
        $starStmt = $conn->prepare("INSERT INTO CommentLikes (user_id, comment_id, is_star) VALUES (?, ?, 1)");
        $starStmt->bind_param("ii", $user_id, $comment_id);
        $starStmt->execute();
        $starStmt->close();
    }

    // Get updated star count
    $starCountStmt = $conn->prepare("SELECT COUNT(*) as star_count FROM CommentLikes WHERE comment_id = ? AND is_star = 1");
    $starCountStmt->bind_param("i", $comment_id);
    $starCountStmt->execute();
    $starCountResult = $starCountStmt->get_result()->fetch_assoc();
    $starCount = $starCountResult['star_count'];
    $starCountStmt->close();

    echo json_encode(['success' => true, 'starred' => !$isStarred, 'starCount' => $starCount]);
}
