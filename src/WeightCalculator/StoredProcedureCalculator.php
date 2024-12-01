<?php

namespace Anko\Lab1\WeightCalculator;

use Anko\Lab1\Debug\DebugBarManager;

class StoredProcedureCalculator implements WeightCalculatorInterface
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

        $timeCollector->startMeasure('stored_procedure_total', 'Stored Procedure Total Time');

        if ($this->profiler) {
            $this->profiler->startProfiling('stored_procedure_calculation');
        }

        $debugbar['messages']->addMessage('Starting stored procedure calculation', 'info');
        $debugbar['messages']->addMessage([
            'user_id' => $userId,
            'weights' => $weights,
            'timestamp' => date('Y-m-d H:i:s')
        ], 'debug');

        $stmt = $this->conn->prepare("CALL CalculateUserWeights(?, ?, ?, ?)");
        $stmt->bind_param(
            "iddd",
            $userId,
            $weights['view_weight'],
            $weights['like_weight'],
            $weights['subscription_weight']
        );

        $startTime = microtime(true);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // Convert the result array to match the recursive calculator format
        $formattedResult = [];
        foreach ($result as $row) {
            $formattedResult[$row['language']] = [
                'baseWeight' => (float)$row['baseWeight'],
                'subscriptionWeight' => (float)$row['subscriptionWeight'],
                'totalWeight' => (float)$row['totalWeight']
            ];
        }

        $executionTime = microtime(true) - $startTime;
        $stmt->close();

        $debugbar['messages']->addMessage([
            'result_count' => count($result),
            'calculation_type' => 'stored_procedure',
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'memory_usage' => memory_get_usage(true)
        ], 'info');

        if ($this->profiler) {
            $this->profiler->stopProfiling('stored_procedure_calculation');
        }

        $timeCollector->stopMeasure('stored_procedure_total');
        return $formattedResult;
    }
}
