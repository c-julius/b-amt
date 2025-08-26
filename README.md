# Automatic Make Times - Laravel Microservice

A Laravel-based microservice that automatically calculates order preparation times based on current load at restaurant locations.

## Approach & Assumptions

### Core Design Decisions
- **Location-Isolated Load Calculation**: Each restaurant location's prep times scale independently based on its active order count
- **Redis-Cached Load Tracking**: Real-time active order counts cached with atomic operations to prevent race conditions
- **Linear Load Scaling**: 20% prep time increase per 5 active orders, capped at 3x base time

### Key Assumptions
- Orders in `received`, `preparing`, or `ready` status contribute to kitchen load
- Kitchen capacity degrades linearly with order volume
- Customers prefer accurate estimates over optimistic ones

## Scaling to Thousands of Locations & Customers

### Horizontal Scaling Strategy
```
Load Balancer → Multiple API Instances → Read Replicas + Master DB
                         ↓
                   Redis Cluster (Load Cache)
```

This system scales by adding more servers as demand increases, with each server handling customer requests independently. The architecture separates order creation (writes) from load calculations (reads) across different databases to prevent bottlenecks. Redis caching stores active order counts in memory for instant lookups, eliminating the need to count orders in the database every time. As the platform grows to thousands of locations, data can be distributed across multiple database servers by location, with older orders archived to maintain fast response times even during peak hours.

## Performance Bottlenecks & Solutions

### 1. Database Query Performance 
**Bottleneck**: Active order counting with complex WHERE clauses
```sql
-- Slow: Full table scan
SELECT COUNT(*) FROM orders WHERE location_id = ? AND status IN ('preparing', 'ready')
```

**Solution (implemented)**: Redis cache with observer pattern
- Cache active counts in Redis with automatic invalidation
- O(1) lookups instead of O(n) database queries
- Lua scripts for atomic increment/decrement operations

### 2. Order Creation Contention
**Bottleneck**: Multiple simultaneous orders hitting database writes

**Solutions (not implemented)**:
- **Write Queues**: Async order processing for non-critical operations
- **Batch Operations**: Group multiple order items into single transactions

### 3. Load Calculation Race Conditions
**Bottleneck**: Cache inconsistency from concurrent order status changes

**Solution (implemented)**: Implemented atomic Redis operations
```php
// Atomic increment with expiration
$luaScript = "
    local newCount = redis.call('INCR', KEYS[1])
    redis.call('EXPIRE', KEYS[1], ARGV[1])
    return newCount
";
```

## Order Volume Spike Scenarios

### What Breaks First: Database Connections
**Problem**: During peak hours (lunch/dinner), database connection pool exhaustion occurs first

**Immediate Fix**:
```php
// Circuit breaker pattern
if ($activeConnections > $threshold) {
    return $this->fallbackToStaticEstimate($location);
}
```

**Long-term Solution**:
- **Connection Pooling**: PgBouncer/MySQL Proxy with connection limits
- **Async Processing**: Move non-critical operations to queues
- **Read Replicas**: Separate estimation queries from order writes

### Secondary Failure: Redis Memory
**Problem**: Active order cache grows beyond Redis memory limits

**Solution**:
- **TTL Safety Net**: 1-hour expiration prevents memory leaks
- **Redis Clustering**: Distribute cache across multiple nodes
- **Graceful Degradation**: Fall back to database if Redis fails

## Quick Start

### Automated Setup (Recommended)
```bash
# Clone the repository
git clone <repository-url>
cd b-amt

# Run the automated setup script
./setup.sh
```

### Manual Setup
```bash
# Copy environment file
cp .env.example .env

# Start environment
./vendor/bin/sail up -d

# Install dependencies
./vendor/bin/sail composer install

# Generate app key
./vendor/bin/sail artisan key:generate

# Setup database
./vendor/bin/sail artisan migrate:fresh --seed

# Run tests to verify setup
./vendor/bin/sail test
```

## Production Readiness Checklist

- [x] Race condition prevention with Lua scripts
- [x] Graceful degradation when Redis fails  
- [x] Test coverage (Unit + Feature)
- [x] Observer pattern for cache invalidation
- [ ] Connection pooling configuration
- [ ] Horizontal auto-scaling setup
- [ ] Monitoring and alerting
- [ ] Load testing validation

---

## API Documentation

### Base URL
```
http://localhost/api
```

### Endpoints

#### 1. Get Location Products
**GET** `/locations/{id}/products`

Returns all available products at a location with base prep times.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "location_id": 1,
      "product_id": 1,
      "is_available": true,
      "product": {
        "id": 1,
        "name": "Margherita Pizza",
        "base_prep_time_seconds": 600
      }
    }
  ]
}
```

#### 2. Create Order
**POST** `/orders`

Creates a new order and returns estimated ready time.

**Request:**
```json
{
  "location_id": 1,
  "source": "online",
  "products": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

**Response:**
```json
{
  "data": {
    "order": {
      "id": 1,
      "location_id": 1,
      "source": "online",
      "status": "received",
      "estimated_ready_at": "2025-08-25T10:25:42.000000Z",
      "location_products": [...],
      "location": {...}
    },
    "load_info": {
      "active_orders_count": 0,
      "load_multiplier": 1,
      "is_high_load": false
    }
  }
}
```

#### 3. Estimate Ready Time
**POST** `/locations/{id}/estimate-ready-at`

Estimates ready time without creating an order.

**Request:**
```json
{
  "products": [
    {
      "product_id": 5,
      "quantity": 1
    }
  ]
}
```

**Response:**
```json
{
  "data": {
    "estimated_ready_at": "2025-08-25T10:25:26.371147Z",
    "estimated_ready_at_human": "22 minutes from now",
    "load_info": {
      "active_orders_count": 0,
      "load_multiplier": 1,
      "is_high_load": false
    }
  }
}
```

#### 4. Update Order Status
**PATCH** `/orders/{id}/status`

Updates order status (received → preparing → ready → completed).

**Request:**
```json
{
  "status": "preparing"
}
```

#### 5. Get Company Products
**GET** `/companies/{id}/products`

Returns master product catalog for a company.

#### 6. Get Company Locations
**GET** `/companies/{id}/locations`

Returns all locations for a company.

## Load Calculation Algorithm

### Active Orders
Orders with status `received`, `preparing`, or `ready` are considered active and contribute to kitchen load.

### Scaling Formula
```
load_multiplier = 1 + (floor(active_orders / 5) * 0.2)
capped at maximum 3.0x
```

### Examples
- 0-4 active orders: 1.0x multiplier
- 5-9 active orders: 1.2x multiplier  
- 10-14 active orders: 1.4x multiplier
- 25+ active orders: 3.0x multiplier (maximum)

### Minimum Time Enforcement
All orders enforce a minimum 10-minute ready time.

## Testing

### Run Tests
```bash
# All tests
./vendor/bin/sail test

# Unit tests only
./vendor/bin/sail test --testsuite=Unit

# Feature tests only
./vendor/bin/sail test --testsuite=Feature

# With coverage
./vendor/bin/sail test --coverage
```

```
$ ./vendor/bin/sail test

   PASS  Tests\Unit\PrepTimeCalculatorTest
  ✓ calculates correct base prep time for single item         3.50s  
  ✓ calculates correct base prep time for multiple items      0.05s  
  ✓ enforces minimum ready time                               0.04s  
  ✓ applies load multiplier correctly                         0.05s  
  ✓ caps load multiplier at maximum                           0.05s  
  ✓ validates location products correctly                     0.05s  
  ✓ gets correct load info                                    0.04s  

   PASS  Tests\Feature\OrderApiTest
  ✓ can create order successfully                             0.29s  
  ✓ order creation validates required fields                  0.28s  
  ✓ order creation validates products                         0.13s  
  ✓ can estimate ready time without creating order            0.12s  
  ✓ can get location products                                 0.09s  
  ✓ load scaling affects prep time                            0.08s  
  ✓ can update order status                                   0.05s  
  ✓ order status validation                                   0.05s  
  ✓ different order sources are accepted                      0.11s  
  ✓ minimum prep time is enforced                             0.07s  

  Tests:    17 passed (76 assertions)
  Duration: 5.17s
```

### Test Coverage
- **Unit Tests**: PrepTimeCalculator service logic
- **Feature Tests**: Order API endpoint functionality
