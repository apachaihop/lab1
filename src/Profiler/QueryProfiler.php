<?php

namespace Anko\Lab1\Profiler;

use Symfony\Component\Stopwatch\Stopwatch;
use Monolog\Logger;
use Anko\Lab1\Debug\DebugBarManager;

class QueryProfiler
{
    private $stopwatch;
    private $timeCollector;
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->stopwatch = new Stopwatch(true);
        $this->timeCollector = DebugBarManager::getTimeCollector();
        $this->logger = $logger;
    }

    public function startProfiling(string $queryName): void
    {
        $this->stopwatch->start($queryName);
        if ($this->timeCollector) {
            $this->timeCollector->startMeasure($queryName);
        }
    }

    public function stopProfiling(string $queryName): array
    {
        $event = $this->stopwatch->stop($queryName);
        if ($this->timeCollector) {
            $this->timeCollector->stopMeasure($queryName);
        }

        $metrics = [
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'memory_peak' => memory_get_peak_usage(true)
        ];

        $this->logger->debug('Query profiling results', [
            'query' => $queryName,
            'metrics' => $metrics
        ]);

        return $metrics;
    }
}
