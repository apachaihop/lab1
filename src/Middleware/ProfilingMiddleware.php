<?php

namespace Anko\Lab1\Middleware;

use Anko\Lab1\Profiler\QueryProfiler;
use Monolog\Logger;

class ProfilingMiddleware
{
    private $profiler;
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->profiler = new QueryProfiler($logger);
    }

    public function profile(callable $callback, string $name)
    {
        $this->profiler->startProfiling($name);
        $result = $callback();
        $metrics = $this->profiler->stopProfiling($name);

        return [
            'result' => $result,
            'metrics' => $metrics
        ];
    }
}
