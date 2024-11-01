<?php

namespace App;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CacheManager
{
    private $cache;
    private $logger;
    private $conn;
    private $cacheDuration;

    public function __construct($conn, $cacheDuration = 3600)
    {
        $this->cache = new FilesystemAdapter();
        $this->logger = new Logger('cache');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/logs/cache.log', Logger::DEBUG));
        $this->conn = $conn;
        $this->cacheDuration = $cacheDuration;
    }

    public function getUserWeights($userId)
    {
        $cacheKey = "user_weights_{$userId}";
        $isCached = $this->cache->hasItem($cacheKey);
        $cacheStatus = $this->getCacheStatus($userId);

        $weights = $this->cache->get($cacheKey, function (CacheItem $item) use ($userId) {
            return $this->calculateWeights($userId, $item);
        });

        $this->setCacheMetadataCookie($userId, $isCached);

        return [
            'weights' => $weights,
            'isCached' => $isCached,
            'cached_at' => $cacheStatus['cached_at'],
            'expires_in' => $cacheStatus['expires_in_minutes']
        ];
    }

    private function calculateWeights($userId, CacheItem $item)
    {
        $item->expiresAfter($this->cacheDuration);

        try {
            // Get weights from UserPreferencesWeights table
            $weightStmt = $this->conn->prepare("SELECT * FROM UserPreferencesWeights LIMIT 1");
            $weightStmt->execute();
            $weights = $weightStmt->get_result()->fetch_assoc();
            $weightStmt->close();

            // Get user preferences
            $debugStmt = $this->conn->prepare("
                SELECT language, view_count, like_count
                FROM UserPreferences
                WHERE user_id = ?
            ");
            $debugStmt->bind_param("i", $userId);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();

            $calculatedWeights = [];
            while ($pref = $debugResult->fetch_assoc()) {
                // Calculate weights exactly like in the debug statement
                $weightValues = calculateUserWeight($this->conn, $userId, $weights, $pref['language']);
                $baseWeight = $weightValues[0];
                $subscriptionWeight = $weightValues[1];
                $totalWeight = $baseWeight + $subscriptionWeight;

                $calculatedWeights[$pref['language']] = [
                    'baseWeight' => $baseWeight,
                    'subscriptionWeight' => $subscriptionWeight,
                    'totalWeight' => $totalWeight,
                    'views' => $pref['view_count'],
                    'likes' => $pref['like_count']
                ];
            }
            $debugStmt->close();

            $this->logger->debug('Calculated weights', [
                'user_id' => $userId,
                'weights' => $calculatedWeights
            ]);

            return $calculatedWeights;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating weights', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function calculateUserWeight($userId, $weights, $language)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT view_count, like_count 
                FROM UserPreferences 
                WHERE user_id = ? AND language = ?
            ");
            $stmt->bind_param("is", $userId, $language);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();

            if (!$data) {
                $this->logger->warning('No preference data found for user', [
                    'user_id' => $userId,
                    'language' => $language
                ]);
                return [0, 0];
            }

            $viewCount = $data['view_count'];
            $likeCount = $data['like_count'];

            $baseWeight = ($weights['view_weight'] * $viewCount) + ($weights['like_weight'] * $likeCount);
            $subscriptionWeight = $this->calculateSubscriptionWeight($userId, $language);

            $this->logger->debug('Weight calculation completed', [
                'user_id' => $userId,
                'language' => $language,
                'base_weight' => $baseWeight,
                'subscription_weight' => $subscriptionWeight,
                'view_count' => $viewCount,
                'like_count' => $likeCount
            ]);

            return [$baseWeight, $subscriptionWeight];
        } catch (\Exception $e) {
            $this->logger->error('Error calculating user weight', [
                'user_id' => $userId,
                'language' => $language,
                'error' => $e->getMessage()
            ]);
            return [0, 0];
        }
    }

    private function calculateSubscriptionWeight($userId, $language)
    {
        // Add your subscription weight logic here
        // For example:
        $stmt = $this->conn->prepare("
            SELECT subscription_level 
            FROM UserSubscriptions 
            WHERE user_id = ? AND language = ?
        ");
        $stmt->bind_param("is", $userId, $language);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        if (!$data) {
            return 0;
        }

        // Example subscription weight calculation
        switch ($data['subscription_level']) {
            case 'premium':
                return 2.0;
            case 'basic':
                return 1.0;
            default:
                return 0;
        }
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
