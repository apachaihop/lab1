<?php

use Anko\Lab1\Profiler\QueryProfiler;

function calculateUserWeight($conn, $user_id, $weights, $language, $visited = array(), ?QueryProfiler $profiler = null)
{
    if ($profiler) {
        $profiler->startProfiling("weight_calc_{$user_id}_{$language}");
    }

    // Calculate base weight
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

    // Calculate subscription weight (first level only)
    $subscriptionsStmt = $conn->prepare("
        SELECT 
            SUM(COALESCE(UP.view_count, 0) * ? + COALESCE(UP.like_count, 0) * ?) as weight_sum
        FROM RepositorySubscriptions RS
        JOIN Repositories R ON RS.repo_id = R.repo_id
        JOIN UserPreferences UP ON R.user_id = UP.user_id AND R.language = UP.language
        WHERE RS.user_id = ? AND R.language = ?
    ");
    $subscriptionsStmt->bind_param(
        "ddis",
        $weights['view_weight'],
        $weights['like_weight'],
        $user_id,
        $language
    );
    $subscriptionsStmt->execute();
    $result = $subscriptionsStmt->get_result()->fetch_assoc();
    $subscriptionWeight = ($result['weight_sum'] ?? 0) * $weights['subscription_weight'];

    if ($profiler) {
        $profiler->stopProfiling("weight_calc_{$user_id}_{$language}");
    }

    return [$baseWeight, $subscriptionWeight];
}
