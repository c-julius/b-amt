<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Location $location;
    private array $locationProducts;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->company = Company::create(['name' => 'Test Restaurant']);
        $this->location = Location::create([
            'company_id' => $this->company->id,
            'name' => 'Test Location',
            'address' => '123 Test Street'
        ]);
        
        // Create products
        $pizza = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Test Pizza',
            'base_prep_time_seconds' => 600 // 10 minutes
        ]);
        
        $salad = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Test Salad',
            'base_prep_time_seconds' => 300 // 5 minutes
        ]);
        
        // Create location products
        $this->locationProducts = [
            LocationProduct::create([
                'location_id' => $this->location->id,
                'product_id' => $pizza->id,
                'is_available' => true
            ]),
            LocationProduct::create([
                'location_id' => $this->location->id,
                'product_id' => $salad->id,
                'is_available' => true
            ])
        ];
    }

    public function test_can_create_order_successfully()
    {
        $orderData = [
            'location_id' => $this->location->id,
            'source' => 'online',
            'products' => [
                [
                    'product_id' => $this->locationProducts[0]->product_id,
                    'quantity' => 2
                ],
                [
                    'product_id' => $this->locationProducts[1]->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'order' => [
                             'id',
                             'location_id',
                             'source',
                             'status',
                             'estimated_ready_at',
                             'location',
                             'location_products'
                         ],
                         'load_info' => [
                             'active_orders_count',
                             'load_multiplier',
                             'is_high_load'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('orders', [
            'location_id' => $this->location->id,
            'source' => 'online',
            'status' => 'received'
        ]);
    }

    public function test_order_creation_validates_required_fields()
    {
        $response = $this->postJson('/api/orders', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['location_id', 'source', 'products']);
    }

    public function test_order_creation_validates_products()
    {
        $orderData = [
            'location_id' => $this->location->id,
            'source' => 'online',
            'products' => [
                [
                    'product_id' => 999, // Non-existent
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(422)
                 ->assertSee('The selected product does not exist.');
    }

    public function test_can_estimate_ready_time_without_creating_order()
    {
        $estimationData = [
            'products' => [
                [
                    'product_id' => $this->locationProducts[0]->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->postJson("/api/locations/{$this->location->id}/estimate-ready-at", $estimationData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'estimated_ready_at',
                         'estimated_ready_at_human',
                         'load_info'
                     ]
                 ]);

        // Ensure no order was created
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_can_get_location_products()
    {
        $response = $this->getJson("/api/locations/{$this->location->id}/products");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'location_id',
                             'product_id',
                             'is_available',
                             'product' => [
                                 'id',
                                 'name',
                                 'base_prep_time_seconds'
                             ]
                         ]
                     ]
                 ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_load_scaling_affects_prep_time()
    {
        // Create multiple active orders to trigger load scaling
        for ($i = 0; $i < 6; $i++) {
            Order::create([
                'location_id' => $this->location->id,
                'source' => 'online',
                'status' => 'preparing',
                'estimated_ready_at' => now()->addMinutes(30)
            ]);
        }

        $estimationData = [
            'products' => [
                [
                    'product_id' => $this->locationProducts[0]->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->postJson("/api/locations/{$this->location->id}/estimate-ready-at", $estimationData);

        $response->assertStatus(200);
        
        $loadInfo = $response->json('data.load_info');
        $this->assertEquals(6, $loadInfo['active_orders_count']);
        $this->assertEquals(1.2, $loadInfo['load_multiplier']);
        $this->assertFalse($loadInfo['is_high_load']);
    }

    public function test_can_update_order_status()
    {
        $order = Order::create([
            'location_id' => $this->location->id,
            'source' => 'online',
            'status' => 'received',
            'estimated_ready_at' => now()->addMinutes(15)
        ]);

        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'preparing'
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'status' => 'preparing'
                 ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'preparing'
        ]);
    }

    public function test_order_status_validation()
    {
        $order = Order::create([
            'location_id' => $this->location->id,
            'source' => 'online',
            'status' => 'received',
            'estimated_ready_at' => now()->addMinutes(15)
        ]);

        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'invalid_status'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['status']);
    }

    public function test_different_order_sources_are_accepted()
    {
        $onlineOrder = [
            'location_id' => $this->location->id,
            'source' => 'online',
            'products' => [
                [
                    'product_id' => $this->locationProducts[0]->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $posOrder = [
            'location_id' => $this->location->id,
            'source' => 'pos',
            'products' => [
                [
                    'product_id' => $this->locationProducts[1]->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $onlineResponse = $this->postJson('/api/orders', $onlineOrder);
        $posResponse = $this->postJson('/api/orders', $posOrder);

        $onlineResponse->assertStatus(201);
        $posResponse->assertStatus(201);

        $this->assertDatabaseHas('orders', ['source' => 'online']);
        $this->assertDatabaseHas('orders', ['source' => 'pos']);
    }

    public function test_minimum_prep_time_is_enforced()
    {
        // Create a product with very short prep time
        $quickItem = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Quick Item',
            'base_prep_time_seconds' => 60 // 1 minute
        ]);

        $quickLocationProduct = LocationProduct::create([
            'location_id' => $this->location->id,
            'product_id' => $quickItem->id,
            'is_available' => true
        ]);

        $estimationData = [
            'products' => [
                [
                    'product_id' => $quickLocationProduct->product_id,
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->postJson("/api/locations/{$this->location->id}/estimate-ready-at", $estimationData);

        $response->assertStatus(200);
        
        $estimatedTime = $response->json('data.estimated_ready_at');
        $minimumTime = now()->addMinutes(10);
        
        $this->assertGreaterThanOrEqual(
            $minimumTime->timestamp,
            strtotime($estimatedTime)
        );
    }
}
