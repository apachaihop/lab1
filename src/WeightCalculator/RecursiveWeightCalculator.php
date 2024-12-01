<?php

namespace Anko\Lab1\WeightCalculator;

use Anko\Lab1\Debug\DebugBarManager;

class RecursiveWeightCalculator implements WeightCalculatorInterface
{
    private $conn;
    private $profiler;

    public function __construct($conn, $profiler = null)
    {
        $this->conn = $conn;
        $this->profiler = $profiler;
    }

    public function calculateWeights(int $userId, array $weights): array
    {
        $debugbar = DebugBarManager::getDebugBar();
        $timeCollector = DebugBarManager::getTimeCollector();

        $timeCollector->startMeasure('recursive_total', 'Recursive Method Total Time');

        if ($this->profiler) {
            $this->profiler->startProfiling('recursive_weight_calculation');
        }

        $debugbar['messages']->addMessage('Starting recursive calculation', 'info');
        $debugbar['messages']->addMessage([
            'user_id' => $userId,
            'weights' => $weights,
            'timestamp' => date('Y-m-d H:i:s')
        ], 'debug');

        $startTime = microtime(true);
        $result = [];

        $stmt = $this->conn->prepare("
            SELECT DISTINCT language 
            FROM (
                SELECT language FROM UserPreferences WHERE user_id = ?
                UNION
                SELECT R.language 
                FROM RepositorySubscriptions RS
                JOIN Repositories R ON RS.repo_id = R.repo_id
                WHERE RS.user_id = ?
            ) all_languages
        ");
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $languages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($languages as $lang) {
            $weightValues = calculateUserWeight($this->conn, $userId, $weights, $lang['language'], [], $this->profiler);
            if ($weightValues[0] + $weightValues[1] > 0) {
                $result[$lang['language']] = [
                    'baseWeight' => round($weightValues[0], 2),
                    'subscriptionWeight' => round($weightValues[1], 2),
                    'totalWeight' => round($weightValues[0] + $weightValues[1], 2)
                ];
            }
        }

        $executionTime = microtime(true) - $startTime;

        $debugbar['messages']->addMessage([
            'result_count' => count($result),
            'calculation_type' => 'recursive',
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'memory_usage' => memory_get_usage(true)
        ], 'info');

        if ($this->profiler) {
            $this->profiler->stopProfiling('recursive_weight_calculation');
        }

        $timeCollector->stopMeasure('recursive_total');
        return $result;
    }
}
