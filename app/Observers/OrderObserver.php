<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\LoadCacheService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private LoadCacheService $loadCacheService
    ) {}

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // When an order is created, it starts as 'received' which is not active
        // No cache update needed yet
        Log::debug("Order {$order->id} created for location {$order->location_id} with status '{$order->status->value}'");
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if status changed
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status'); // This might be a string or enum
            $newStatus = $order->status; // Keep as enum
            
            // Convert oldStatus string to enum for consistency
            if (is_string($oldStatus)) {
                $oldStatus = \App\Enums\OrderStatus::from($oldStatus);
            }
            
            $wasActive = $oldStatus->isActive();
            $isActive = $newStatus->isActive();
            
            if (!$wasActive && $isActive) {
                // Order became active
                $this->loadCacheService->incrementActiveOrders($order->location_id);
                Log::info("Order {$order->id} became active ({$oldStatus->value} → {$newStatus->value}), incremented cache for location {$order->location_id}");
                
            } elseif ($wasActive && !$isActive) {
                // Order became inactive
                $this->loadCacheService->decrementActiveOrders($order->location_id);
                Log::info("Order {$order->id} became inactive ({$oldStatus->value} → {$newStatus->value}), decremented cache for location {$order->location_id}");
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // If deleted order was active, decrement the cache
        if ($order->status->isActive()) {
            $this->loadCacheService->decrementActiveOrders($order->location_id);
            Log::info("Active order {$order->id} deleted, decremented cache for location {$order->location_id}");
        }
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
