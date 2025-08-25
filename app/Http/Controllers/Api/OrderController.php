<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Location;
use App\Models\Order;
use App\Services\PrepTimeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private PrepTimeCalculator $prepTimeCalculator
    ) {}

    /**
     * Store a newly created order
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $location = Location::findOrFail($request->location_id);
                
                // Validate that all location products belong to this location
                if (!$this->prepTimeCalculator->validateLocationProducts(
                    $request->location_products,
                    $location->id
                )) {
                    return response()->json([
                        'error' => 'One or more products are not available at this location'
                    ], 422);
                }

                // Calculate estimated ready time
                $estimatedReadyAt = $this->prepTimeCalculator->calculateReadyTime(
                    $location,
                    $request->location_products
                );

                // Create the order
                $order = Order::create([
                    'location_id' => $location->id,
                    'source' => $request->source,
                    'status' => OrderStatus::RECEIVED,
                    'estimated_ready_at' => $estimatedReadyAt,
                ]);

                // Attach location products to the order
                foreach ($request->location_products as $item) {
                    $order->locationProducts()->attach(
                        $item['location_product_id'],
                        ['quantity' => $item['quantity']]
                    );
                }

                // Load relationships for response
                $order->load(['location', 'locationProducts.product']);

                return response()->json([
                    'data' => [
                        'order' => $order,
                        'load_info' => $this->prepTimeCalculator->getLoadInfo($location),
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['location', 'locationProducts.product']);
        
        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:received,preparing,ready,completed']
        ]);

        $order->update([
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $order
        ]);
    }
}
