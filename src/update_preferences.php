<?php
include 'connection.php';
session_start();

$conn = getConnection();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($user_id && isset($_POST['action']) && $_POST['action'] == 'update_like' && isset($_POST['repo_id'])) {
    $repo_id = intval($_POST['repo_id']);
    $liked = $_POST['liked'] === 'true';

    // Get the repository language
    $langStmt = $conn->prepare("SELECT language FROM Repositories WHERE repo_id = ?");
    $langStmt->bind_param("i", $repo_id);
    $langStmt->execute();
    $langResult = $langStmt->get_result();
    $repo = $langResult->fetch_assoc();
    $langStmt->close();

    if ($repo) {
        $language = $repo['language'];

        // Update UserPreferences
        $updateLikeStmt = $conn->prepare("
            INSERT INTO UserPreferences (user_id, language, like_count)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE like_count = like_count + ?
        ");
        $likeChange = $liked ? 1 : -1;
        $updateLikeStmt->bind_param("isii", $user_id, $language, $likeChange - $likeChange, $likeChange);
        $updateLikeStmt->execute();
        $updateLikeStmt->close();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Repository not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

closeConnection($conn);
