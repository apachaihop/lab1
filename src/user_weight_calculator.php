<?php
function calculateUserWeight($conn, $user_id, $weights, $language, $visited = array())
{
    if (in_array($user_id, $visited)) {
        return [0, 0];
    }
    $visited[] = $user_id;

    // Calculate base weight for the user for the specific language
    $baseWeightStmt = $conn->prepare("
        SELECT 
            COALESCE(view_count, 0) as total_views,
            COALESCE(like_count, 0) as total_likes
        FROM UserPreferences
        WHERE user_id = ? AND language = ?
    ");
    $baseWeightStmt->bind_param("is", $user_id, $language);
    $baseWeightStmt->execute();
    $baseWeightResult = $baseWeightStmt->get_result()->fetch_assoc();
    $baseWeight = ($baseWeightResult['total_views'] * $weights['view_weight']) +
        ($baseWeightResult['total_likes'] * $weights['like_weight']);

    // Get subscriptions
    $subscriptionsStmt = $conn->prepare("
        SELECT DISTINCT R.user_id
        FROM RepositorySubscriptions RS
        JOIN Repositories R ON RS.repo_id = R.repo_id
        WHERE RS.user_id = ? AND R.language = ?
    ");
    $subscriptionsStmt->bind_param("is", $user_id, $language);
    $subscriptionsStmt->execute();
    $subscriptionsResult = $subscriptionsStmt->get_result();

    $subscriptionWeight = 0;
    while ($subscription = $subscriptionsResult->fetch_assoc()) {
        $subWeight = calculateUserWeight($conn, $subscription['user_id'], $weights, $language, $visited);
        $subscriptionWeight += $subWeight[0] + $subWeight[1];
    }

    $totalSubscriptionWeight = $subscriptionWeight * $weights['subscription_weight'];

    return [$baseWeight, $totalSubscriptionWeight];
}
