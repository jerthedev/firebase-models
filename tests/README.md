# Testing Guide

This document provides comprehensive guidance for testing the JTD Firebase Models package with the new restructured test organization.

## Overview

The testing suite uses **Pest PHP** as the primary testing framework, built on top of PHPUnit. The tests are designed to run efficiently without requiring actual Firebase connections through a sophisticated three-tier mocking system.

## New Test Organization Structure

```
tests/
├── TestSuites/              # Standardized test base classes
│   ├── BaseTestSuite.php    # Common functionality and memory management
│   ├── UnitTestSuite.php    # Ultra-fast unit tests (Ultra-Light Mock)
│   ├── IntegrationTestSuite.php # Balanced integration tests (Lightweight Mock)
│   └── PerformanceTestSuite.php # Performance and memory tests (Ultra-Light Mock)
├── Utilities/               # Test utilities and factories
│   ├── TestDataFactory.php # Standardized test data generation
│   └── TestConfigManager.php # Environment-aware configuration
├── Unit/
│   ├── Restructured/        # New organized unit tests
│   ├── Cache/              # Cache-related tests
│   ├── Auth/               # Authentication tests
│   └── Scopes/             # Scope tests
├── Feature/                # Feature tests
├── Integration/            # Integration tests
├── Performance/            # Performance tests
├── Helpers/                # Mock system and utilities
├── Pest.php               # Pest configuration
├── TestCase.php           # Legacy base test case
└── README.md              # This file
```

## Three-Tier Mock System

The package uses an advanced three-tier mock system optimized for different testing scenarios:

### Mock Types

| Mock Type | Memory Usage | Features | Use Case |
|-----------|--------------|----------|----------|
| **Ultra-Light** | Minimal (128MB) | Basic operations | Unit tests, CI/CD |
| **Lightweight** | Balanced (256MB) | Good feature set | Integration tests |
| **Full** | High (512MB) | Complete Mockery | Complex scenarios |

### Automatic Mock Selection

The system automatically selects the optimal mock type based on:
- Environment (CI vs local)
- Test requirements (document count, memory constraints)
- Performance needs

## Running Tests

### Basic Test Execution

```bash
# Run all tests with optimized organization
composer test

# Run specific test suites
vendor/bin/pest --testsuite=Unit-UltraLight    # Fast unit tests
vendor/bin/pest --testsuite=Integration-Lightweight # Integration tests
vendor/bin/pest --testsuite=Performance        # Performance tests

# Run restructured tests
vendor/bin/pest tests/Unit/Restructured/

# Run with memory monitoring
TEST_MEMORY_LIMIT=256M vendor/bin/pest tests/Unit/
```

### Advanced Test Execution

```bash
# Run with specific mock type
TEST_MOCK_TYPE=ultra vendor/bin/pest tests/Unit/
TEST_MOCK_TYPE=lightweight vendor/bin/pest tests/Feature/

# Run with memory constraints
TEST_MEMORY_WARNING=50M TEST_MEMORY_CRITICAL=100M vendor/bin/pest

# Run performance tests
vendor/bin/pest tests/Performance/ --stop-on-failure
```

## Memory Requirements

### Test Suite Memory Guidelines

| Test Suite | Memory Limit | Mock Type | Typical Usage |
|------------|--------------|-----------|---------------|
| **Unit Tests** | 128-256MB | Ultra-Light | Core logic testing |
| **Integration Tests** | 256-512MB | Lightweight | Component interaction |
| **Feature Tests** | 512MB-1GB | Full/Lightweight | End-to-end scenarios |
| **Performance Tests** | 256-512MB | Ultra-Light | Memory/speed validation |

### Environment-Specific Settings

```bash
# Local Development (recommended)
TEST_MEMORY_LIMIT=512M
TEST_MOCK_TYPE=full

# CI/CD Pipeline (optimized)
TEST_MEMORY_LIMIT=256M
TEST_MOCK_TYPE=ultra

# Memory-Constrained Environments
TEST_MEMORY_LIMIT=128M
TEST_MOCK_TYPE=ultra
TEST_MEMORY_WARNING=50M
TEST_MEMORY_CRITICAL=100M
```

## Test Categories

### Unit Tests (`tests/Unit/Restructured/`)

Ultra-fast tests with minimal memory usage:

- **DeleteOperationsMigrated.php**: Comprehensive delete operations testing
- **UpdateOperationsTest.php**: Model update and dirty tracking
- **FirestoreModelTest.php**: Core model functionality
- **QueryLogicTest.php**: Query builder logic

### Integration Tests (`tests/Integration/`)

Balanced tests for component interaction:

- **QueryOperationsTest.php**: Complex query scenarios
- **ModelRelationshipsTest.php**: Model relationship testing
- **CacheIntegrationTest.php**: Cache system integration

### Performance Tests (`tests/Performance/`)

Memory and performance validation:

- **MemoryOptimizationTest.php**: Memory usage validation
- **BulkOperationsTest.php**: Large dataset performance
- **MockSystemTest.php**: Mock system performance

## Mock Type Selection Guide

### When to Use Each Mock Type

#### Ultra-Light Mock
**Use for**: Unit tests, CI/CD, memory-constrained environments
```php
class MyUnitTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);
        parent::setUp();
    }
}
```

#### Lightweight Mock
**Use for**: Integration tests, balanced performance needs
```php
class MyIntegrationTest extends IntegrationTestSuite
{
    protected function setUp(): void
    {
        $this->setTestRequirements([
            'document_count' => 200,
            'memory_constraint' => false,
            'needs_full_mockery' => false,
        ]);
        parent::setUp();
    }
}
```

#### Full Mock
**Use for**: Complex scenarios requiring complete Mockery features
```php
class MyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableFullMock(); // Only when needed
    }
}
```

### Automatic Mock Selection

```php
// Let the system choose optimal mock type
$this->enableAutoMock([
    'document_count' => 1000,
    'memory_constraint' => true,
    'needs_full_mockery' => false
]); // Automatically selects Ultra-Light Mock
```

## New Test Utilities

### TestDataFactory

Standardized test data generation:

```php
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;

// Create consistent test data
$userData = TestDataFactory::createUser([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$postData = TestDataFactory::createPost([
    'title' => 'Test Post',
    'author_id' => $userData['id'],
]);

// Create multiple related items
$dataset = TestDataFactory::createRelatedDataSet();

// Create hierarchical data
$categories = TestDataFactory::createHierarchicalCategories(3, 2);
```

### TestConfigManager

Environment-aware configuration:

```php
use JTD\FirebaseModels\Tests\Utilities\TestConfigManager;

$config = TestConfigManager::getInstance();

// Get environment-specific settings
$mockType = $config->getRecommendedMockType([
    'document_count' => 500,
    'memory_constraint' => true
]);

// Check environment
if ($config->isCI()) {
    // Optimized for CI/CD
}

// Get memory thresholds
$thresholds = $config->getMemoryThresholds();
```

## Writing Tests with New Structure

### Unit Test Example

```php
<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;

class MyFeatureTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        $this->setTestRequirements([
            'document_count' => 20,
            'memory_constraint' => true,
        ]);

        parent::setUp();
    }

    /** @test */
    public function it_performs_operation_efficiently()
    {
        // Use TestDataFactory for consistent data
        $modelData = TestDataFactory::createUser();
        $model = $this->createTestModel(MyModel::class, $modelData);

        // Measure performance
        $executionTime = $this->benchmark(function () use ($model) {
            return $model->doSomething();
        });

        // Assert results
        expect($model->result)->toBeTrue();
        $this->assertDocumentExists('my_collection', $model->id);

        // Performance assertions
        $this->assertLessThan(0.1, $executionTime);
        $this->assertMemoryUsageWithinThreshold(5 * 1024 * 1024);
    }
}
```

### Performance Test Example

```php
<?php

namespace JTD\FirebaseModels\Tests\Performance;

use JTD\FirebaseModels\Tests\TestSuites\PerformanceTestSuite;

class BulkOperationsTest extends PerformanceTestSuite
{
    /** @test */
    public function it_handles_large_datasets_efficiently()
    {
        $this->setMemoryThresholds([
            'warning' => 50 * 1024 * 1024,  // 50MB
            'critical' => 100 * 1024 * 1024, // 100MB
        ]);

        // Create large dataset
        $documents = $this->createLargeDataset('test_collection', 1000);

        // Perform bulk operations
        $results = $this->performBulkOperations('test_collection', 500);

        // Performance assertions
        $this->assertBatchOperationsSuccessful($results);
        $this->assertMemoryWithinThresholds();

        // Get performance report
        $report = $this->getPerformanceReport();
        expect($report['summary']['total_time'])->toBeLessThan(5.0);
    }
}
```

## Performance Testing Guidelines

### Memory Monitoring

```php
// Enable memory monitoring
$this->enableMemoryMonitoring();

// Set custom thresholds
$this->setMemoryThresholds([
    'warning' => 50 * 1024 * 1024,   // 50MB
    'critical' => 100 * 1024 * 1024, // 100MB
]);

// Assert memory usage
$this->assertMemoryUsageWithinThreshold(10 * 1024 * 1024); // 10MB
$this->assertMemoryWithinThresholds();
```

### Performance Benchmarking

```php
// Benchmark operations
$executionTime = $this->benchmark(function () {
    // Your operation here
});

// Assert performance
$this->assertExecutionTimeWithinThreshold(function () {
    // Your operation
}, 0.5); // 500ms max

// Measure specific operations
$this->measureOperation('bulk_create', function () {
    // Bulk creation logic
});

// Assert operation performance
$this->assertPerformanceWithinLimits('bulk_create', 2.0, 10 * 1024 * 1024);
```

## Writing Tests

### Basic Test Structure

```php
<?php

use App\Models\User;

describe('User Model', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    it('can create a user', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        expect($user)->toBeFirestoreModel();
        expect($user->name)->toBe('John Doe');
        expect($user)->toExistInFirestore();
    });
});
```

### Testing Model Events

```php
it('fires creating event', function () {
    $eventFired = false;
    
    User::creating(function ($user) use (&$eventFired) {
        $eventFired = true;
        $user->uuid = 'test-uuid';
    });
    
    $user = User::create(['name' => 'John']);
    
    expect($eventFired)->toBeTrue();
    expect($user->uuid)->toBe('test-uuid');
});
```

### Testing Relationships (Future)

```php
it('can load relationships', function () {
    $this->mockFirestoreQuery('posts', [
        ['id' => '1', 'title' => 'Post 1', 'user_id' => '123']
    ]);
    
    $user = User::find('123');
    $posts = $user->posts;
    
    expect($posts)->toHaveCount(1);
    expect($posts->first()->title)->toBe('Post 1');
});
```

## Configuration

### PHPUnit Configuration (`phpunit.xml`)

- Configures test suites (Unit, Feature)
- Sets up coverage reporting
- Defines test environment variables
- Configures Firebase test credentials

### Pest Configuration (`tests/Pest.php`)

- Sets up base test case for all tests
- Defines custom expectations
- Configures global test helpers

## Continuous Integration

### GitHub Actions (`.github/workflows/tests.yml`)

The CI pipeline runs:

1. **Tests** across multiple PHP versions (8.2, 8.3)
2. **Code Quality** checks with Pint and PHPStan
3. **Security Audit** with Composer
4. **Coverage Reporting** to Codecov

### Local Quality Checks

```bash
# Code formatting
composer format

# Static analysis
composer analyse

# Security audit
composer audit

# All quality checks
composer format && composer analyse && composer test
```

## Best Practices

### Test Organization

1. **Group related tests** using `describe()` blocks
2. **Use descriptive test names** that explain the behavior
3. **Keep tests focused** on single behaviors
4. **Use setup/teardown** appropriately with `beforeEach()`

### Mocking Strategy

1. **Mock at the Firestore level** rather than Laravel level
2. **Use realistic test data** that matches production patterns
3. **Test both success and failure scenarios**
4. **Verify mock interactions** with assertions

### Performance

1. **Clear mocks between tests** to prevent interference
2. **Use minimal test data** for faster execution
3. **Group similar tests** to reduce setup overhead
4. **Avoid actual Firebase calls** in unit tests

## Debugging Tests

### Verbose Output

```bash
# Run with verbose output
vendor/bin/pest --verbose

# Show test progress
vendor/bin/pest --testdox
```

### Debug Specific Issues

```php
// Add debug output in tests
it('debugs the issue', function () {
    $user = User::create(['name' => 'John']);
    
    // Debug model state
    dump($user->toArray());
    dump($user->getDirty());
    
    // Debug mock state
    dump($this->getFirestoreMock()->getOperations());
});
```

### Coverage Analysis

```bash
# Generate detailed coverage report
vendor/bin/pest --coverage --coverage-html=coverage-html

# Open coverage report
open coverage-html/index.html
```

## Troubleshooting Guide

### Memory Issues

#### Problem: "Fatal error: Allowed memory size exhausted"

**Symptoms**:
- Tests fail with memory exhaustion
- CI/CD pipeline runs out of memory
- Local tests consume excessive RAM

**Solutions**:
```bash
# 1. Switch to Ultra-Light mock
TEST_MOCK_TYPE=ultra vendor/bin/pest

# 2. Increase memory limit temporarily
php -d memory_limit=1G vendor/bin/pest

# 3. Run tests in smaller batches
vendor/bin/pest tests/Unit/Cache/
vendor/bin/pest tests/Unit/Auth/

# 4. Enable garbage collection
TEST_ENABLE_PROFILING=true vendor/bin/pest
```

**Code Solutions**:
```php
// Force garbage collection in memory-intensive tests
$this->forceGarbageCollection();

// Monitor memory usage
$memoryInfo = $this->getMockInfo();
if ($memoryInfo['memory_usage'] > 50 * 1024 * 1024) {
    $this->clearTestData();
}

// Use memory-efficient test patterns
$this->setTestRequirements([
    'document_count' => 10, // Reduce test data
    'memory_constraint' => true,
]);
```

#### Problem: "Memory usage exceeds threshold"

**Solutions**:
```php
// Adjust memory thresholds
$this->setMemoryThresholds([
    'warning' => 100 * 1024 * 1024,  // 100MB
    'critical' => 200 * 1024 * 1024, // 200MB
]);

// Clear data more frequently
$this->clearTestData();
$this->clearFirestoreMocks();
```

### Mock System Issues

#### Problem: "Stub FirestoreClient should not be used directly"

**Cause**: Mock system not properly initialized

**Solutions**:
```php
// Ensure proper mock setup in setUp()
protected function setUp(): void
{
    parent::setUp(); // Call parent first
    $this->enableUltraLightMock(); // Then enable mock
}

// Or use new test suite structure
class MyTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
        ]);
        parent::setUp(); // Handles mock setup automatically
    }
}
```

#### Problem: "Call to undefined method getCollectionName()"

**Cause**: Using wrong method name

**Solution**:
```php
// Wrong
$collection = $model::getCollectionName();

// Correct
$model = new MyModel();
$collection = $model->getCollection();
```

### Performance Issues

#### Problem: Tests running slowly

**Diagnosis**:
```bash
# Run with profiling
TEST_ENABLE_PROFILING=true vendor/bin/pest

# Check for actual Firebase calls
vendor/bin/pest --testdox | grep -i "firebase\|firestore"
```

**Solutions**:
```php
// Use Ultra-Light mock for speed
$this->enableUltraLightMock();

// Reduce test data size
$this->setTestRequirements([
    'document_count' => 10, // Instead of 100
]);

// Benchmark slow operations
$time = $this->benchmark(function () {
    // Your slow operation
});
if ($time > 1.0) {
    $this->fail("Operation too slow: {$time}s");
}
```

### Test Failures

#### Problem: "Failed asserting that null is false"

**Common Causes**:
1. Model not properly created
2. Mock not returning expected data
3. Incorrect test setup

**Solutions**:
```php
// Debug model creation
$model = $this->createTestModel(MyModel::class, $data);
dump($model->toArray()); // Check model state
dump($model->exists); // Check exists flag

// Debug mock state
dump($this->getPerformedOperations()); // Check operations
dump($this->getFirestoreMock()->getCollectionCount('my_collection'));

// Verify test data
$this->assertDocumentExists('my_collection', $model->id);
```

#### Problem: Random test failures

**Causes**:
- Shared state between tests
- Race conditions
- Improper cleanup

**Solutions**:
```php
// Ensure proper cleanup
protected function setUp(): void
{
    parent::setUp();
    $this->clearTestData(); // Clear before each test
}

protected function tearDown(): void
{
    $this->clearTestData(); // Clear after each test
    parent::tearDown();
}

// Use isolated test data
$uniqueId = 'test_' . uniqid();
$model = $this->createTestModel(MyModel::class, [
    'id' => $uniqueId,
    'name' => 'Test ' . $uniqueId,
]);
```

### Environment Issues

#### Problem: Tests pass locally but fail in CI

**Common Causes**:
- Different PHP versions
- Memory constraints in CI
- Missing environment variables

**Solutions**:
```bash
# Match CI environment locally
docker run --rm -v $(pwd):/app -w /app php:8.2-cli php vendor/bin/pest

# Use CI-optimized settings locally
TEST_MOCK_TYPE=ultra TEST_MEMORY_LIMIT=256M vendor/bin/pest

# Check environment variables
env | grep TEST_
```

#### Problem: "Class not found" errors

**Solutions**:
```bash
# Regenerate autoloader
composer dump-autoload

# Clear any caches
php artisan cache:clear
php artisan config:clear

# Verify namespace in test files
namespace JTD\FirebaseModels\Tests\Unit\Restructured;
```

## Best Practices

### Test Organization
1. **Use appropriate test suite** based on test type and requirements
2. **Group related tests** using descriptive class names
3. **Keep tests focused** on single behaviors
4. **Use TestDataFactory** for consistent test data

### Memory Management
1. **Choose optimal mock type** for your use case
2. **Monitor memory usage** in performance-critical tests
3. **Clear test data** between tests
4. **Use garbage collection** for memory-intensive operations

### Performance Optimization
1. **Use Ultra-Light mock** for unit tests
2. **Minimize test data size** when possible
3. **Benchmark critical operations**
4. **Run tests in appropriate order** (fastest first)

### Debugging
1. **Use performance profiling** to identify bottlenecks
2. **Monitor memory usage** to prevent exhaustion
3. **Add debug output** for complex test scenarios
4. **Use appropriate logging levels**

## Migration from Legacy Tests

### Step 1: Choose Test Suite Type
```php
// For unit tests
class MyTest extends UnitTestSuite

// For integration tests
class MyTest extends IntegrationTestSuite

// For performance tests
class MyTest extends PerformanceTestSuite
```

### Step 2: Update Test Setup
```php
protected function setUp(): void
{
    $this->setTestRequirements([
        'document_count' => 50,
        'memory_constraint' => true,
    ]);
    parent::setUp();
}
```

### Step 3: Use New Utilities
```php
// Use TestDataFactory
$data = TestDataFactory::createUser();

// Use new assertion methods
$this->assertDocumentExists('collection', $id);
$this->assertCollectionCount('collection', 5);
```

## Getting Help

### Debug Information
```php
// Get mock information
$info = $this->getMockInfo();
dump($info);

// Get performance report
$report = $this->getPerformanceReport();
dump($report);

// Get memory statistics
$stats = $this->getMemoryStats();
dump($stats);
```

### Resources
1. **Documentation**: `docs/testing/` directory
2. **Examples**: `tests/Unit/Restructured/` directory
3. **Migration Guide**: `TEST_MIGRATION.md`
4. **Mock Architecture**: `docs/testing/mock-system-architecture.md`
