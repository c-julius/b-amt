<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Product;
use App\Services\LoadCacheService;
use App\Services\PrepTimeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepTimeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PrepTimeCalculator $calculator;
    private Location $location;
    private array $locationProducts;

    private const FAST_PREP_TIME = PrepTimeCalculator::MINIMUM_READY_TIME_MINUTES - 5; // 5 minutes below minimum
    private const SLOW_PREP_TIME = PrepTimeCalculator::MINIMUM_READY_TIME_MINUTES + 5; // 5 minutes above minimum

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create calculator with mocked LoadCacheService
        $loadCacheService = $this->createMock(LoadCacheService::class);
        $loadCacheService->method('getActiveOrderCount')
            ->willReturn(0); // Default to 0 active orders for tests
        
        $this->calculator = new PrepTimeCalculator($loadCacheService);
        
        // Create test data
        $company = Company::create(['name' => 'Test Company']);
        $this->location = Location::create([
            'company_id' => $company->id,
            'name' => 'Test Location',
            'address' => '123 Test St'
        ]);
        
        // Create products with different prep times
        $fastProduct = Product::create([
            'company_id' => $company->id,
            'name' => 'Fast Item',
            'base_prep_time_seconds' => self::FAST_PREP_TIME * 60,
        ]);
        
        $slowProduct = Product::create([
            'company_id' => $company->id,
            'name' => 'Slow Item',
            'base_prep_time_seconds' => self::SLOW_PREP_TIME * 60,
        ]);
        
        // Create location products
        $this->locationProducts = [
            LocationProduct::create([
                'location_id' => $this->location->id,
                'product_id' => $fastProduct->id,
                'is_available' => true
            ]),
            LocationProduct::create([
                'location_id' => $this->location->id,
                'product_id' => $slowProduct->id,
                'is_available' => true
            ])
        ];
    }

    public function test_calculates_correct_base_prep_time_for_single_item()
    {
        $locationProductsData = [
            [
                'location_product_id' => $this->locationProducts[0]->id,
                'quantity' => 1
            ]
        ];
        
        $readyTime = $this->calculator->calculateReadyTime($this->location, $locationProductsData);
        
        // Should be minimum time if below threshold
        $expectedTime = Carbon::now()->addMinutes(PrepTimeCalculator::MINIMUM_READY_TIME_MINUTES);
        $this->assertEquals($expectedTime->format('Y-m-d H:i'), $readyTime->format('Y-m-d H:i'));
    }

    public function test_calculates_correct_base_prep_time_for_multiple_items()
    {
        $locationProductsData = [
            [
                'location_product_id' => $this->locationProducts[0]->id,
                'quantity' => 2
            ],
            [
                'location_product_id' => $this->locationProducts[1]->id,
                'quantity' => 1
            ]
        ];
        
        $readyTime = $this->calculator->calculateReadyTime($this->location, $locationProductsData);
        
        // 2 fast items (5 min each) + 1 slow item (15 min) = 25 minutes
        $expectedTime = Carbon::now()->addMinutes(2 * self::FAST_PREP_TIME + self::SLOW_PREP_TIME);
        $this->assertEquals($expectedTime->format('Y-m-d H:i'), $readyTime->format('Y-m-d H:i'));
    }

    public function test_enforces_minimum_ready_time()
    {
        $locationProductsData = [
            [
                'location_product_id' => $this->locationProducts[0]->id,
                'quantity' => 1
            ]
        ];
        
        $readyTime = $this->calculator->calculateReadyTime($this->location, $locationProductsData);
        
        // Fast item is 5 minutes, but minimum is enforced
        $minimumTime = Carbon::now()->addMinutes(PrepTimeCalculator::MINIMUM_READY_TIME_MINUTES);
        $this->assertGreaterThanOrEqual($minimumTime->timestamp, $readyTime->timestamp);
    }

    public function test_applies_load_multiplier_correctly()
    {
        // Mock LoadCacheService to return enough orders to trigger load scaling
        $mockLoadCacheService = $this->createMock(LoadCacheService::class);
        $mockLoadCacheService->method('getActiveOrderCount')
            ->willReturn(PrepTimeCalculator::LOAD_SCALING_THRESHOLD + 1);
        
        $calculator = new PrepTimeCalculator($mockLoadCacheService);
        
        $locationProductsData = [
            [
                'location_product_id' => $this->locationProducts[1]->id, // slow item
                'quantity' => 1
            ]
        ];
        
        $expectedMinutes = self::SLOW_PREP_TIME * PrepTimeCalculator::LOAD_SCALING_MULTIPLIER;
        $readyTime = $calculator->calculateReadyTime($this->location, $locationProductsData);
        $expectedTime = Carbon::now()->addMinutes($expectedMinutes);
        $this->assertEquals($expectedTime->format('Y-m-d H:i'), $readyTime->format('Y-m-d H:i'));
    }

    public function test_caps_load_multiplier_at_maximum()
    {
        // Mock LoadCacheService to return enough orders to hit the max multiplier
        $ordersNeeded = ceil((PrepTimeCalculator::MAX_SCALING_MULTIPLIER - 1.0) / (PrepTimeCalculator::LOAD_SCALING_MULTIPLIER - 1.0)) * PrepTimeCalculator::LOAD_SCALING_THRESHOLD;
        
        $mockLoadCacheService = $this->createMock(LoadCacheService::class);
        $mockLoadCacheService->method('getActiveOrderCount')
            ->willReturn((int) $ordersNeeded); // Cast to int to match return type
        
        $calculator = new PrepTimeCalculator($mockLoadCacheService);
        
        $locationProductsData = [
            [
                'location_product_id' => $this->locationProducts[1]->id, // slow item
                'quantity' => 1
            ]
        ];
        
        $expectedMinutes = self::SLOW_PREP_TIME * PrepTimeCalculator::MAX_SCALING_MULTIPLIER;
        $readyTime = $calculator->calculateReadyTime($this->location, $locationProductsData);
        $expectedTime = Carbon::now()->addMinutes($expectedMinutes);
        $this->assertEquals($expectedTime->format('Y-m-d H:i'), $readyTime->format('Y-m-d H:i'));
    }

    public function test_validates_location_products_correctly()
    {
        $validLocationProducts = [
            [
                'location_product_id' => $this->locationProducts[0]->id,
                'quantity' => 1
            ]
        ];
        
        $invalidLocationProducts = [
            [
                'location_product_id' => 999, // Non-existent ID
                'quantity' => 1
            ]
        ];
        
        $this->assertTrue(
            $this->calculator->validateLocationProducts($validLocationProducts, $this->location->id)
        );
        
        $this->assertFalse(
            $this->calculator->validateLocationProducts($invalidLocationProducts, $this->location->id)
        );
    }

    public function test_gets_correct_load_info()
    {
        // Mock LoadCacheService to return 2 active orders
        $mockLoadCacheService = $this->createMock(LoadCacheService::class);
        $mockLoadCacheService->method('getActiveOrderCount')
            ->willReturn(2);
        
        $calculator = new PrepTimeCalculator($mockLoadCacheService);
        
        $loadInfo = $calculator->getLoadInfo($this->location);
        
        $this->assertEquals(2, $loadInfo['active_orders_count']);
        $this->assertEquals(1.0, $loadInfo['load_multiplier']);
        $this->assertFalse($loadInfo['is_high_load']);
    }
}
