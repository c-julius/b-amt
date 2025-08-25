<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationProduct;
use Carbon\Carbon;

/**
 * PrepTimeCalculator
 * 
 * Core business logic service that calculates order preparation times based on
 * menu items and current kitchen load at restaurant locations.
 * 
 * Calculation Algorithm:
 * 1. Sum base preparation times for all ordered items
 * 2. Apply load-based scaling factor based on active orders in kitchen
 * 3. Enforce minimum 10-minute ready time for customer expectations
 * 
 * Load Scaling Formula:
 * - Base multiplier: 1.0x (no load)
 * - +20% for every 5 active orders (preparing/ready/received status)
 * - Maximum cap: 3.0x base time (prevents unrealistic estimates)
 * 
 * Examples:
 * - 0-4 active orders: 1.0x base time
 * - 5-9 active orders: 1.2x base time  
 * - 10-14 active orders: 1.4x base time
 * - 25+ active orders: 3.0x base time (capped)
 * 
 * Performance Features:
 * - Uses LoadCacheService for O(1) active order lookups
 * - Provides load information for monitoring and analytics
 * - Gracefully handles cache failures with database fallback
 * 
 * Used by: OrderController for order creation and estimation endpoints
 */
class PrepTimeCalculator
{
    public const MINIMUM_READY_TIME_MINUTES = 10;
    public const LOAD_SCALING_THRESHOLD = 5; // Every 5 active orders
    public const LOAD_SCALING_MULTIPLIER = 1.2; // 20% increase
    public const MAX_SCALING_MULTIPLIER = 3.0; // Cap at 3x base time

    public function __construct(
        private LoadCacheService $loadCacheService
    ) {}

    /**
     * Calculate estimated ready time for an order at a location
     */
    public function calculateReadyTime(Location $location, array $locationProductsData): Carbon
    {
        $basePrepTimeSeconds = $this->calculateBasePrepTime($locationProductsData);
        $loadMultiplier = $this->calculateLoadMultiplier($location);
        $adjustedPrepTimeSeconds = $basePrepTimeSeconds * $loadMultiplier;
        
        // Enforce minimum ready time
        $minimumReadyTimeSeconds = self::MINIMUM_READY_TIME_MINUTES * 60;
        $finalPrepTimeSeconds = max($adjustedPrepTimeSeconds, $minimumReadyTimeSeconds);
        
        return Carbon::now()->addSeconds($finalPrepTimeSeconds);
    }

    /**
     * Calculate base preparation time from location products
     */
    private function calculateBasePrepTime(array $locationProductsData): int
    {
        $totalPrepTime = 0;
        
        foreach ($locationProductsData as $item) {
            $locationProduct = LocationProduct::with('product')
                ->find($item['location_product_id']);
            
            if ($locationProduct && $locationProduct->is_available) {
                $quantity = $item['quantity'] ?? 1;
                // Use the product's base prep time
                $totalPrepTime += $locationProduct->product->base_prep_time_seconds * $quantity;
            }
        }
        
        return $totalPrepTime;
    }

    /**
     * Calculate load multiplier based on current active orders at location
     */
    private function calculateLoadMultiplier(Location $location): float
    {
        // Use Redis cache for active order count - major performance improvement!
        $activeOrdersCount = $this->loadCacheService->getActiveOrderCount($location);
        
        // Calculate multiplier: e.g. 1.2x for every 5 active orders
        $multiplierIncrements = floor($activeOrdersCount / self::LOAD_SCALING_THRESHOLD);
        $multiplier = 1.0 + ($multiplierIncrements * (self::LOAD_SCALING_MULTIPLIER - 1.0));
        
        // Cap the multiplier at maximum value
        return min($multiplier, self::MAX_SCALING_MULTIPLIER);
    }

    /**
     * Get current load information for a location
     */
    public function getLoadInfo(Location $location): array
    {
        // Use Redis cache for active order count
        $activeOrdersCount = $this->loadCacheService->getActiveOrderCount($location);
        $loadMultiplier = $this->calculateLoadMultiplier($location);
        
        return [
            'active_orders_count' => $activeOrdersCount,
            'load_multiplier' => round($loadMultiplier, 2),
            'is_high_load' => $loadMultiplier > 2.0,
        ];
    }

    /**
     * Validate location products data
     */
    public function validateLocationProducts(array $locationProductsData, int $locationId): bool
    {
        if (empty($locationProductsData)) {
            return false;
        }

        $locationProductIds = collect($locationProductsData)
            ->pluck('location_product_id')
            ->unique();

        // Check if all location products exist and belong to the location
        $validLocationProducts = LocationProduct::where('location_id', $locationId)
            ->where('is_available', true)
            ->whereIn('id', $locationProductIds)
            ->count();

        return $validLocationProducts === $locationProductIds->count();
    }
}
