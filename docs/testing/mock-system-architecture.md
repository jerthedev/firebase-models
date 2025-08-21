# Mock System Architecture

The Firebase Models package provides a sophisticated three-tier mock system designed to balance feature completeness with memory efficiency for different testing scenarios.

## Overview

The mock system consists of three main components:

1. **FirestoreMockFactory** - Centralized factory for creating and managing mock instances
2. **AbstractFirestoreMock** - Base class providing common functionality
3. **Three Mock Implementations** - Each optimized for different use cases

## Mock Types

### 1. Full Mock (`FirestoreMockFactory::TYPE_FULL`)
- **Memory Efficiency**: Low (1/3)
- **Feature Completeness**: High (3/3)
- **Use Case**: Comprehensive testing with full Mockery features
- **Best For**: Small test suites, complex mocking scenarios

### 2. Lightweight Mock (`FirestoreMockFactory::TYPE_LIGHTWEIGHT`)
- **Memory Efficiency**: Medium (2/3)
- **Feature Completeness**: Good (2/3)
- **Use Case**: Memory-conscious testing with good feature coverage
- **Best For**: Medium-sized test suites, balanced requirements

### 3. Ultra-Light Mock (`FirestoreMockFactory::TYPE_ULTRA`)
- **Memory Efficiency**: High (3/3)
- **Feature Completeness**: Basic (2/3)
- **Use Case**: High-volume testing with maximum memory efficiency
- **Best For**: Large test suites, memory-constrained environments

## Usage

### Basic Usage

```php
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockTrait;

class MyTest extends TestCase
{
    use FirestoreMockTrait;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Choose your mock type
        $this->enableUltraLightMock();  // For memory efficiency
        // OR
        $this->enableLightweightMock(); // For balanced approach
        // OR
        $this->enableFullMock();        // For full features
    }
}
```

### Automatic Mock Selection

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Let the system choose the best mock type
    $this->enableAutoMock([
        'document_count' => 1000,
        'memory_constraint' => true,
        'needs_full_mockery' => false
    ]);
}
```

### Factory Usage

```php
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;

// Create specific mock type
$mock = FirestoreMockFactory::create(FirestoreMockFactory::TYPE_ULTRA);

// Get recommendations
$recommendedType = FirestoreMockFactory::recommendType([
    'document_count' => 500,
    'memory_constraint' => true
]);

// Get available types information
$types = FirestoreMockFactory::getAvailableTypes();
```

## Performance Characteristics

### Memory Usage
- **Full Mock**: High overhead due to Mockery
- **Lightweight Mock**: Medium overhead with anonymous classes
- **Ultra-Light Mock**: Minimal overhead with concrete classes only

### Initialization Speed
- **Full Mock**: Slow (Mockery setup)
- **Lightweight Mock**: Medium (Class creation)
- **Ultra-Light Mock**: Fast (Direct instantiation)

### Feature Support
- **Full Mock**: Complete Mockery features
- **Lightweight Mock**: Core features without heavy Mockery
- **Ultra-Light Mock**: Essential features with stub classes

## Architecture Benefits

### 1. **Unified Interface**
All mock types provide the same interface through `FirestoreMockTrait`, allowing easy switching between implementations.

### 2. **Memory Optimization**
Choose the right mock for your memory requirements without changing test code.

### 3. **Gradual Migration**
Existing tests continue to work while new tests can leverage improved architecture.

### 4. **Performance Monitoring**
Built-in memory usage tracking and performance benchmarks.

## Best Practices

### 1. **Choose the Right Mock**
- Use **Ultra-Light** for large test suites or memory-constrained CI/CD
- Use **Lightweight** for most general testing scenarios
- Use **Full** only when you need specific Mockery features

### 2. **Memory Management**
```php
// Force garbage collection in memory-intensive tests
$this->forceGarbageCollection();

// Monitor memory usage
$memoryInfo = $this->getMockInfo();
```

### 3. **Test Organization**
Group tests by mock requirements to minimize switching overhead.

## Migration Guide

### From Legacy Mocks
```php
// Old way
FirestoreMock::initialize();

// New way
$this->enableFullMock(); // or other type
```

### Updating Existing Tests
1. Add `use FirestoreMockTrait` to test classes
2. Replace direct mock calls with trait methods
3. Choose appropriate mock type in `setUp()`

## Troubleshooting

### Memory Issues
- Switch to Ultra-Light mock: `$this->enableUltraLightMock()`
- Force garbage collection: `$this->forceGarbageCollection()`
- Monitor usage: `$this->getMemoryComparison()`

### Interface Compatibility
- Ensure all mock classes extend appropriate base classes
- Check that stub classes are loaded for missing dependencies
- Verify method signatures match expected interfaces

### Performance Issues
- Use benchmarks: `$this->getBenchmarks()`
- Profile different mock types for your specific use case
- Consider test organization and batching strategies
