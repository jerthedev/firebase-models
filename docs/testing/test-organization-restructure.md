# Test Organization Restructure

This document outlines the restructured test organization for the Firebase Models package, designed to improve maintainability, resource management, and testing efficiency.

## Overview

The restructured test organization addresses several key issues:

1. **Inconsistent test setup** across different test files
2. **Scattered memory management** without standardized approaches
3. **Mixed test types** without clear separation
4. **Duplicate test patterns** for different mock types
5. **Lack of test utilities** for common operations

## New Test Structure

### 1. Test Suites by Type and Performance

```
tests/
├── TestSuites/
│   ├── BaseTestSuite.php           # Common base with memory management
│   ├── UnitTestSuite.php           # Ultra-fast unit tests
│   ├── IntegrationTestSuite.php    # Balanced integration tests
│   └── PerformanceTestSuite.php    # Performance and memory tests
├── Utilities/
│   ├── TestDataFactory.php        # Standardized test data generation
│   └── TestConfigManager.php      # Environment-aware configuration
├── Unit/
│   ├── Restructured/               # New organized unit tests
│   ├── Cache/                      # Cache-related tests
│   ├── Auth/                       # Authentication tests
│   └── Scopes/                     # Scope tests
├── Integration/                    # Integration tests
├── Feature/                        # Feature tests
└── Performance/                    # Performance tests
```

### 2. Standardized Test Base Classes

#### BaseTestSuite
- **Memory Management**: Automatic memory monitoring and cleanup
- **Mock Configuration**: Environment-aware mock type selection
- **Resource Cleanup**: Standardized teardown procedures
- **Performance Tracking**: Built-in performance metrics

#### UnitTestSuite (extends BaseTestSuite)
- **Mock Type**: Ultra-Light (maximum memory efficiency)
- **Target**: Fast, isolated unit tests
- **Memory Limit**: 128MB
- **Features**: Test model creation, assertion helpers, performance benchmarking

#### IntegrationTestSuite (extends BaseTestSuite)
- **Mock Type**: Lightweight (balanced performance)
- **Target**: Integration tests with realistic scenarios
- **Memory Limit**: 256MB
- **Features**: Complex data scenarios, relationship testing, batch operations

#### PerformanceTestSuite (extends BaseTestSuite)
- **Mock Type**: Ultra-Light (memory optimized)
- **Target**: Performance and memory testing
- **Memory Limit**: 256MB
- **Features**: Large dataset creation, performance measurement, memory profiling

## Key Features

### 1. Automatic Mock Type Selection

```php
class MyUnitTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Automatically configures ultra-light mock
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
        ]);
        
        parent::setUp();
    }
}
```

### 2. Standardized Test Data Generation

```php
// Create consistent test data
$userData = TestDataFactory::createUser([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Create multiple related items
$dataset = TestDataFactory::createRelatedDataSet();
```

### 3. Environment-Aware Configuration

```php
$config = TestConfigManager::getInstance();

// Automatically selects appropriate mock type based on environment
$mockType = $config->getRecommendedMockType([
    'document_count' => 1000,
    'memory_constraint' => true,
]);

// CI environments automatically use ultra-light mocks
if ($config->isCI()) {
    // Optimized for CI/CD pipelines
}
```

### 4. Memory and Performance Monitoring

```php
class PerformanceTest extends PerformanceTestSuite
{
    public function test_large_dataset_performance()
    {
        // Automatic performance monitoring
        $this->measureOperation('bulk_create', function() {
            $this->createLargeDataset('users', 1000);
        });
        
        // Assert performance within limits
        $this->assertPerformanceWithinLimits('bulk_create', 2.0, 10 * 1024 * 1024);
    }
}
```

## Test Suite Organization

### By Performance Characteristics

1. **Unit-UltraLight**: Ultra-fast tests with minimal memory usage
   - Mock Type: Ultra-Light
   - Memory Limit: 128MB
   - Execution Time: < 30 seconds
   - Use Case: Core logic testing

2. **Integration-Lightweight**: Balanced integration tests
   - Mock Type: Lightweight
   - Memory Limit: 256MB
   - Execution Time: < 60 seconds
   - Use Case: Component interaction testing

3. **Feature-Full**: Comprehensive feature tests
   - Mock Type: Full (in local) / Lightweight (in CI)
   - Memory Limit: 512MB
   - Execution Time: < 120 seconds
   - Use Case: End-to-end feature testing

4. **Performance**: Memory and performance tests
   - Mock Type: Ultra-Light
   - Memory Limit: 256MB
   - Execution Time: < 180 seconds
   - Use Case: Performance validation

## Migration Strategy

### Phase 1: Infrastructure Setup ✅
- [x] Create base test suite classes
- [x] Implement test utilities and factories
- [x] Set up configuration management
- [x] Create documentation

### Phase 2: Gradual Migration
- [ ] Migrate high-value tests to new structure
- [ ] Update CI/CD configuration
- [ ] Train team on new patterns
- [ ] Monitor performance improvements

### Phase 3: Legacy Cleanup
- [ ] Remove duplicate test patterns
- [ ] Consolidate remaining legacy tests
- [ ] Update documentation
- [ ] Final performance optimization

## Benefits

### 1. **Improved Performance**
- Faster test execution through optimized mock selection
- Reduced memory usage in CI/CD environments
- Better resource utilization

### 2. **Better Maintainability**
- Standardized test patterns
- Consistent setup and teardown
- Centralized configuration management

### 3. **Enhanced Developer Experience**
- Clear test organization
- Helpful utilities and factories
- Automatic performance monitoring

### 4. **Scalability**
- Environment-aware configuration
- Efficient resource management
- Support for large test suites

## Usage Examples

### Creating a New Unit Test

```php
<?php

namespace Tests\Unit\MyFeature;

use Tests\TestSuites\UnitTestSuite;
use Tests\Utilities\TestDataFactory;

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
    
    public function test_feature_works()
    {
        $model = $this->createTestModel(MyModel::class, 
            TestDataFactory::createUser()
        );
        
        $result = $model->doSomething();
        
        $this->assertTrue($result);
        $this->assertDocumentExists('my_collection', $model->id);
    }
}
```

### Creating a Performance Test

```php
<?php

namespace Tests\Performance;

use Tests\TestSuites\PerformanceTestSuite;

class BulkOperationsTest extends PerformanceTestSuite
{
    public function test_bulk_operations_performance()
    {
        $this->setMemoryThresholds([
            'warning' => 50 * 1024 * 1024,  // 50MB
            'critical' => 100 * 1024 * 1024, // 100MB
        ]);
        
        $results = $this->performBulkOperations('test_collection', 500);
        
        $this->assertBatchOperationsSuccessful($results);
        $this->assertMemoryWithinThresholds();
    }
}
```

## Configuration

### Environment Variables

```bash
# Test configuration
TEST_MOCK_TYPE=ultra
TEST_MEMORY_LIMIT=256M
TEST_TIMEOUT=30
TEST_ENABLE_LOGGING=false
TEST_ENABLE_PROFILING=false
TEST_MEMORY_WARNING=50M
TEST_MEMORY_CRITICAL=100M
```

### PHPUnit Configuration

```xml
<!-- Optimized test suite configuration -->
<testsuite name="Unit-UltraLight">
    <directory>tests/Unit/Restructured</directory>
</testsuite>
<testsuite name="Integration-Lightweight">
    <directory>tests/Integration</directory>
</testsuite>
<testsuite name="Performance">
    <directory>tests/Performance</directory>
</testsuite>
```

## Conclusion

The restructured test organization provides a solid foundation for scalable, maintainable, and efficient testing. By organizing tests by performance characteristics and providing standardized utilities, we can ensure consistent test quality while optimizing resource usage across different environments.

The new structure supports both immediate improvements and long-term scalability, making it easier to maintain high test coverage as the codebase grows.
