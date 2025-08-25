<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstimateReadyTimeRequest;
use App\Models\Location;
use App\Services\PrepTimeCalculator;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(
        private PrepTimeCalculator $prepTimeCalculator
    ) {}

    /**
     * Get products available at a location with pricing
     */
    public function products(Location $location): JsonResponse
    {
        $locationProducts = $location->locationProducts()
            ->with('product')
            ->where('is_available', true)
            ->get();

        return response()->json([
            'data' => $locationProducts
        ]);
    }

    /**
     * Estimate ready time for a hypothetical order
     */
    public function estimateReadyTime(EstimateReadyTimeRequest $request, Location $location): JsonResponse
    {
        // Validate that all location products belong to this location
        if (!$this->prepTimeCalculator->validateLocationProducts(
            $request->location_products,
            $location->id
        )) {
            return response()->json([
                'error' => 'One or more products are not available at this location'
            ], 422);
        }

        $estimatedReadyAt = $this->prepTimeCalculator->calculateReadyTime(
            $location,
            $request->location_products
        );

        $loadInfo = $this->prepTimeCalculator->getLoadInfo($location);

        return response()->json([
            'data' => [
                'estimated_ready_at' => $estimatedReadyAt->toISOString(),
                'estimated_ready_at_human' => $estimatedReadyAt->diffForHumans(),
                'load_info' => $loadInfo,
            ]
        ]);
    }
}
