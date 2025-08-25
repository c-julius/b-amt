<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * LoadCacheService
 * 
 * Manages Redis-based caching of active order counts for restaurant locations.
 * This service provides high-performance load calculations by caching active order
 * counts in Redis instead of querying the database on every request.
 *
 * Used by: PrepTimeCalculator for kitchen load calculations
 * Updated by: OrderObserver when order statuses change
 */
class LoadCacheService
{
    private const CACHE_PREFIX = 'location_load:';
    private const CACHE_TTL = 3600; // 1 hour TTL as safety net

    /**
     * Get active order count for a location (with Redis caching)
     */
    public function getActiveOrderCount(Location $location): int
    {
        $cacheKey = $this->getCacheKey($location->id);
        
        try {
            $cachedCount = Redis::get($cacheKey);
            
            if ($cachedCount !== null) {
                Log::debug("Cache hit for location {$location->id}: {$cachedCount} active orders");
                return (int) $cachedCount;
            }
            
            // Cache miss - use distributed lock to prevent race conditions
            $lockKey = $cacheKey . ':lock';
            $lockAcquired = Redis::set($lockKey, '1', 'EX', 10, 'NX'); // 10 second lock
            
            if ($lockAcquired) {
                try {
                    // Double-check cache after acquiring lock
                    $cachedCount = Redis::get($cacheKey);
                    if ($cachedCount !== null) {
                        Log::debug("Cache hit after lock for location {$location->id}: {$cachedCount} active orders");
                        return (int) $cachedCount;
                    }
                    
                    // Cache miss - fall back to database and cache the result
                    $actualCount = $this->getActiveOrderCountFromDatabase($location);
                    $this->setActiveOrderCount($location->id, $actualCount);
                    
                    Log::info("Cache miss for location {$location->id}, cached DB result: {$actualCount} active orders");
                    return $actualCount;
                } finally {
                    Redis::del($lockKey); // Always release lock
                }
            } else {
                // Could not acquire lock, fall back to database without caching
                Log::debug("Could not acquire cache lock for location {$location->id}, falling back to database");
                return $this->getActiveOrderCountFromDatabase($location);
            }
            
        } catch (\Exception $e) {
            Log::warning("Redis unavailable, falling back to database for location {$location->id}: " . $e->getMessage());
            return $this->getActiveOrderCountFromDatabase($location);
        }
    }

    /**
     * Set active order count for a location in cache
     */
    public function setActiveOrderCount(int $locationId, int $count): void
    {
        $cacheKey = $this->getCacheKey($locationId);
        
        try {
            Redis::setex($cacheKey, self::CACHE_TTL, $count);
            Log::debug("Set cache for location {$locationId}: {$count} active orders");
        } catch (\Exception $e) {
            Log::warning("Failed to set cache for location {$locationId}: " . $e->getMessage());
        }
    }

    /**
     * Increment active order count when order becomes active
     */
    public function incrementActiveOrders(int $locationId): void
    {
        $cacheKey = $this->getCacheKey($locationId);
        
        try {
            // Use Lua script to atomically increment and set expiration
            $luaScript = "
                local newCount = redis.call('INCR', KEYS[1])
                redis.call('EXPIRE', KEYS[1], ARGV[1])
                return newCount
            ";
            
            $newCount = Redis::eval($luaScript, 1, $cacheKey, self::CACHE_TTL);
            Log::debug("Incremented active orders for location {$locationId} to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("Failed to increment cache for location {$locationId}: " . $e->getMessage());
            // Graceful degradation - sync from database
            $this->syncFromDatabase($locationId);
        }
    }

    /**
     * Decrement active order count when order becomes inactive
     */
    public function decrementActiveOrders(int $locationId): void
    {
        $cacheKey = $this->getCacheKey($locationId);
        
        try {
            // Use Lua script to atomically decrement, check bounds, and set expiration
            $luaScript = "
                local newCount = redis.call('DECR', KEYS[1])
                if newCount < 0 then
                    redis.call('SET', KEYS[1], 0)
                    newCount = 0
                end
                redis.call('EXPIRE', KEYS[1], ARGV[1])
                return newCount
            ";
            
            $newCount = Redis::eval($luaScript, 1, $cacheKey, self::CACHE_TTL);
            Log::debug("Decremented active orders for location {$locationId} to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("Failed to decrement cache for location {$locationId}: " . $e->getMessage());
            // Graceful degradation - sync from database
            $this->syncFromDatabase($locationId);
        }
    }

    /**
     * Sync cache with database for a location
     */
    public function syncFromDatabase(int $locationId): void
    {
        try {
            $location = Location::find($locationId);
            if ($location) {
                $actualCount = $this->getActiveOrderCountFromDatabase($location);
                $this->setActiveOrderCount($locationId, $actualCount);
                Log::info("Synced cache from database for location {$locationId}: {$actualCount} active orders");
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync cache from database for location {$locationId}: " . $e->getMessage());
        }
    }

    /**
     * Clear cache for a location (useful for testing or manual refresh)
     */
    public function clearLocationCache(int $locationId): void
    {
        $cacheKey = $this->getCacheKey($locationId);
        
        try {
            Redis::del($cacheKey);
            Log::debug("Cleared cache for location {$locationId}");
        } catch (\Exception $e) {
            Log::warning("Failed to clear cache for location {$locationId}: " . $e->getMessage());
        }
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        try {
            $pattern = self::CACHE_PREFIX . '*';
            $keys = Redis::keys($pattern);
            
            $stats = [
                'cached_locations' => count($keys),
                'total_cached_orders' => 0,
                'cache_status' => 'healthy'
            ];
            
            foreach ($keys as $key) {
                $count = Redis::get($key);
                if ($count !== null) {
                    $stats['total_cached_orders'] += (int) $count;
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'cached_locations' => 0,
                'total_cached_orders' => 0,
                'cache_status' => 'unavailable',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get active order count directly from database
     */
    private function getActiveOrderCountFromDatabase(Location $location): int
    {
        return $location->activeOrders()->count();
    }

    /**
     * Generate cache key for location
     */
    private function getCacheKey(int $locationId): string
    {
        return self::CACHE_PREFIX . $locationId;
    }
}
