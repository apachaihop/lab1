<?php

namespace App;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Anko\Lab1\Profiler\QueryProfiler;
use Anko\Lab1\Debug\DebugBarManager;
use Anko\Lab1\WeightCalculator\StoredProcedureCalculator;
use Anko\Lab1\WeightCalculator\RecursiveWeightCalculator;

class CacheManager
{
    private $cache;
    private $logger;
    private $conn;
    private $cacheDuration;
    private $profiler;

    public function __construct($conn, $cacheDuration = 3600, $profiler = null)
    {
        $this->cache = new FilesystemAdapter();
        $this->logger = new Logger('cache');
        $this->logger->pushHandler(new StreamHandler(dirname(__DIR__) . '/public/cache.log', Logger::DEBUG));
        $this->conn = $conn;
        $this->cacheDuration = $cacheDuration;
        $this->profiler = $profiler;
    }

    public function getUserWeights($userId)
    {
        $cacheKey = "user_weights_{$userId}";
        $isCached = $this->cache->hasItem($cacheKey);
        $cacheStatus = $this->getCacheStatus($userId);

        // Get the weights from admin settings
        $weightStmt = $this->conn->prepare("SELECT * FROM UserPreferencesWeights LIMIT 1");
        $weightStmt->execute();
        $weights = $weightStmt->get_result()->fetch_assoc();
        $weightStmt->close();

        $cachedWeights = $this->cache->get($cacheKey, function (CacheItem $item) use ($userId, $weights) {
            $item->expiresAfter($this->cacheDuration);
            return $this->calculateWeights($userId, $weights);
        });

        $this->setCacheMetadataCookie($userId, $isCached);

        return [
            'weights' => $cachedWeights,
            'isCached' => $isCached,
            'cached_at' => $cacheStatus['cached_at'],
            'expires_in' => $cacheStatus['expires_in_minutes']
        ];
    }

    private function calculateWeights(int $userId, array $weights): array
    {
        $strategy = $_SESSION['weight_calculator'] ?? 'stored_procedure';
        $calculatorClass = match ($strategy) {
            'stored_procedure' => StoredProcedureCalculator::class,
            'recursive' => RecursiveWeightCalculator::class,
            default => StoredProcedureCalculator::class
        };

        $calculator = new $calculatorClass($this->conn, $this->profiler);
        $result = $calculator->calculateWeights($userId, $weights);

        // Log the calculation strategy used
        $this->logger->debug('Weight calculation performed', [
            'strategy' => $strategy,
            'user_id' => $userId
        ]);

        return $result;
    }

    private function setCacheMetadataCookie($userId, $isCached)
    {
        $cacheStatus = $this->getCacheStatus($userId);

        // Get the current weights
        $weights = $this->cache->getItem("user_weights_{$userId}")->get() ?? [];

        // Format weights data for the cookie
        $languageWeights = [];
        foreach ($weights as $language => $data) {
            $languageWeights[$language] = [
                'base' => $data['baseWeight'],
                'subscription' => $data['subscriptionWeight'],
                'total' => $data['totalWeight'],
                'stats' => [
                    'views' => $data['views'],
                    'likes' => $data['likes']
                ]
            ];
        }

        $cacheMetadata = json_encode([
            'status' => $isCached ? 'cached' : 'live',
            'cached_at' => $cacheStatus['cached_at'],
            'expires_in_minutes' => $cacheStatus['expires_in_minutes'],
            'languages' => array_keys($weights),
            'weights' => $languageWeights
        ]);

        // Make sure cookie is set with correct parameters
        setcookie(
            'weights_cache_metadata',
            $cacheMetadata,
            [
                'expires' => time() + $this->cacheDuration,
                'path' => '/',
                'domain' => '',  // current domain
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => false,  // allow JavaScript access
                'samesite' => 'Lax'   // changed from Strict to Lax for better compatibility
            ]
        );

        $this->logger->debug('Cache metadata cookie set', [
            'user_id' => $userId,
            'metadata' => $cacheMetadata
        ]);
    }

    public function clearCache($userId)
    {
        $cacheKey = "user_weights_{$userId}";
        $this->cache->delete($cacheKey);
        $this->logger->info('Cache cleared for user', [
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function getCacheExpiry($cacheKey): ?int
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            $metadata = $item->getMetadata();
            return $metadata['expiry'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting cache expiry', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getRemainingMinutes($expiryTimestamp): ?int
    {
        if (!$expiryTimestamp) {
            return null;
        }

        $now = time();
        if ($expiryTimestamp <= $now) {
            return 0;
        }

        return max(0, round(($expiryTimestamp - $now) / 60));
    }

    public function getCacheStatus($userId): array
    {
        $cacheKey = "user_weights_{$userId}";
        $isCached = $this->cache->hasItem($cacheKey);
        $expiry = $this->getCacheExpiry($cacheKey);
        $now = time();

        $status = [
            'status' => $isCached ? 'cached' : 'live',
            'cached_at' => $isCached ? date('Y-m-d H:i:s', $now) : null,
            'expires_in_minutes' => $isCached ? $this->getRemainingMinutes($expiry) : null
        ];

        // Log the cache status for debugging
        $this->logger->debug('Cache status', [
            'user_id' => $userId,
            'status' => $status
        ]);

        return $status;
    }
}
