# Test Migration Guide

This document provides a comprehensive plan for migrating existing tests to the new restructured test organization.

## Current Status

✅ **Infrastructure Complete**: Test suite base classes, utilities, and documentation are ready
🔄 **Migration In Progress**: First test successfully migrated as example
� **Progress**: 1/25 tests migrated (4% complete)

### ✅ **COMPLETED EXAMPLE**: DeleteOperationsTest.php Migration

**Source Files**:
- `tests/Unit/DeleteOperationsTest.php` (525 lines, 34 test cases)
- `tests/Unit/DeleteOperationsSimpleTest.php` (307 lines, 20 test cases)

**Target File**:
- `tests/Unit/Restructured/DeleteOperationsMigrated.php` (300 lines, 8 consolidated test cases)

**Migration Results**:
- ✅ Extended UnitTestSuite instead of TestCase
- ✅ Used TestDataFactory for consistent test data
- ✅ Added performance assertions and benchmarking
- ✅ Consolidated duplicate test patterns
- ✅ Implemented memory monitoring
- ✅ Added automatic cleanup procedures
- ✅ Reduced code duplication by 60%
- ✅ Improved test organization and readability

**Performance Improvements**:
- Memory usage monitoring with thresholds
- Execution time benchmarking
- Automatic garbage collection
- Optimized mock type selection (Ultra-Light)

## Migration Overview

### Phase 1: Infrastructure ✅ COMPLETE
- [x] BaseTestSuite, UnitTestSuite, IntegrationTestSuite, PerformanceTestSuite
- [x] TestDataFactory and TestConfigManager utilities
- [x] Documentation and configuration templates

### Phase 2: Test Migration 🔄 IN PROGRESS
- [ ] Migrate existing tests to new structure
- [ ] Consolidate duplicate test patterns
- [ ] Remove redundant files
- [ ] Update configurations

### Phase 3: Cleanup and Optimization
- [ ] Performance optimization
- [ ] Documentation updates
- [ ] Final validation

## Comprehensive Test Inventory

### Unit Tests (Priority: HIGH)

#### Core Model Operations
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| ✅ `tests/Unit/DeleteOperationsTest.php` | ✅ `tests/Unit/Restructured/DeleteOperationsMigrated.php` | **COMPLETED** - Migrated + Consolidated | ✅ 45 min |
| ✅ `tests/Unit/DeleteOperationsSimpleTest.php` | *(Ready for deletion)* | **COMPLETED** - Merged into main | ✅ 15 min |
| `tests/Unit/UpdateOperationsTest.php` | `tests/Unit/Restructured/UpdateOperationsTest.php` | Migrate + Consolidate with Simple | 45 min |
| `tests/Unit/UpdateOperationsSimpleTest.php` | *(Delete after consolidation)* | Merge into main | 15 min |
| `tests/Unit/FirestoreModelTest.php` | `tests/Unit/Restructured/FirestoreModelTest.php` | Migrate to UnitTestSuite | 30 min |

#### Query and Database Operations
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| `tests/Unit/ComplexQueryLogicTest.php` | `tests/Unit/Restructured/QueryLogicTest.php` | Migrate to UnitTestSuite | 30 min |
| `tests/Unit/ComplexQueryOperationsTest.php` | `tests/Integration/QueryOperationsTest.php` | Migrate to IntegrationTestSuite | 45 min |
| `tests/Unit/FirestoreDBTest.php` | `tests/Unit/Restructured/FirestoreDBTest.php` | Migrate to UnitTestSuite | 20 min |

#### Infrastructure and Services
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| `tests/Unit/ServiceProviderTest.php` | `tests/Unit/Restructured/ServiceProviderTest.php` | Migrate to UnitTestSuite | 20 min |
| `tests/Unit/FirebaseMockTest.php` | `tests/Unit/Restructured/FirebaseMockTest.php` | Migrate to UnitTestSuite | 15 min |
| `tests/Unit/MemoryOptimizationTest.php` | `tests/Performance/MemoryOptimizationTest.php` | Migrate to PerformanceTestSuite | 30 min |
| `tests/Unit/MockSystemConsolidationTest.php` | `tests/Performance/MockSystemTest.php` | Migrate to PerformanceTestSuite | 20 min |

### Feature Tests (Priority: MEDIUM)

#### CRUD Operations
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| `tests/Feature/FirestoreModelCRUDTest.php` | `tests/Feature/Restructured/ModelCRUDTest.php` | Consolidate with Lightweight version | 30 min |
| `tests/Feature/FirestoreModelCrudLightweightTest.php` | *(Delete after consolidation)* | Merge unique tests into main | 15 min |

#### Events and Lifecycle
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| `tests/Feature/FirestoreModelEventsTest.php` | `tests/Feature/Restructured/ModelEventsTest.php` | Consolidate with Simple version | 30 min |
| `tests/Feature/FirestoreModelEventsSimpleTest.php` | *(Delete after consolidation)* | Merge unique tests into main | 15 min |

#### Query Builder
| Current File | Target Location | Action | Estimated Effort |
|--------------|-----------------|--------|------------------|
| `tests/Feature/FirestoreQueryBuilderTest.php` | `tests/Feature/Restructured/QueryBuilderTest.php` | Consolidate with Enhanced version | 30 min |
| `tests/Feature/FirestoreQueryBuilderEnhancedTest.php` | *(Delete after consolidation)* | Merge unique tests into main | 15 min |

### Specialized Tests (Priority: LOW)

#### Cache Tests (Already Well-Organized)
| Current Location | Action | Estimated Effort |
|------------------|--------|------------------|
| `tests/Unit/Cache/` | Update to use new base classes | 30 min |

#### Auth Tests (Already Well-Organized)
| Current Location | Action | Estimated Effort |
|------------------|--------|------------------|
| `tests/Unit/Auth/` | Update to use new base classes | 30 min |

#### Scope Tests (Already Well-Organized)
| Current Location | Action | Estimated Effort |
|------------------|--------|------------------|
| `tests/Unit/Scopes/` | Update to use new base classes | 20 min |

#### Accessor Tests (Already Well-Organized)
| Current Location | Action | Estimated Effort |
|------------------|--------|------------------|
| `tests/Unit/Accessors/` | Update to use new base classes | 20 min |

## Migration Instructions

### Step 0: Review Completed Example

Before starting your migration, review the completed example:

**File**: `tests/Unit/Restructured/DeleteOperationsMigrated.php`

**Key Changes Made**:
1. **Namespace**: Changed to `JTD\FirebaseModels\Tests\Unit\Restructured`
2. **Base Class**: Extended `UnitTestSuite` instead of `TestCase`
3. **Test Requirements**: Added `setTestRequirements()` in setUp()
4. **Test Data**: Used `TestDataFactory::createUser()` for consistent data
5. **Assertions**: Used new helper methods like `assertDocumentExists()`
6. **Performance**: Added `benchmark()` and performance assertions
7. **Memory**: Added memory monitoring with `enableMemoryMonitoring()`
8. **Cleanup**: Used `clearTestData()` for proper cleanup
9. **Consolidation**: Merged 54 test cases into 8 comprehensive tests

**Benefits Achieved**:
- 60% reduction in code duplication
- Standardized test data generation
- Automatic performance monitoring
- Memory usage optimization
- Consistent test patterns

### Step 1: Prepare Migration Environment

```bash
# Create target directories
mkdir -p tests/Unit/Restructured
mkdir -p tests/Feature/Restructured
mkdir -p tests/Integration
mkdir -p tests/Performance

# Backup existing tests
cp -r tests/Unit tests/Unit.backup
cp -r tests/Feature tests/Feature.backup
```

### Step 2: Migration Template

For each test file, follow this pattern:

#### Before (Legacy Test)
```php
<?php

use JTD\FirebaseModels\Tests\TestCase;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
    }
    
    public function test_something()
    {
        // Test logic
    }
}
```

#### After (Restructured Test)
```php
<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;

class MyTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
        ]);
        
        parent::setUp();
    }
    
    public function test_something()
    {
        $testData = TestDataFactory::createUser();
        $model = $this->createTestModel(MyModel::class, $testData);
        
        // Test logic with new utilities
        $this->assertDocumentExists('collection', $model->id);
    }
}
```

### Step 3: Consolidation Strategy

When consolidating duplicate tests:

1. **Compare test files** to identify unique test cases
2. **Merge unique tests** into the more comprehensive version
3. **Update test names** to avoid conflicts
4. **Verify coverage** is maintained or improved
5. **Delete redundant file** after verification

### Step 4: Configuration Updates

#### Update phpunit.xml
```xml
<testsuites>
    <testsuite name="Unit-Restructured">
        <directory>tests/Unit/Restructured</directory>
    </testsuite>
    <testsuite name="Feature-Restructured">
        <directory>tests/Feature/Restructured</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="Performance">
        <directory>tests/Performance</directory>
    </testsuite>
    <testsuite name="Legacy">
        <directory>tests/Unit</directory>
        <exclude>tests/Unit/Restructured</exclude>
        <exclude>tests/Unit/Cache</exclude>
        <exclude>tests/Unit/Auth</exclude>
        <exclude>tests/Unit/Scopes</exclude>
        <exclude>tests/Unit/Accessors</exclude>
    </testsuite>
</testsuites>
```

## Migration Checklist

### High Priority Tasks (Start Here)
- [x] **COMPLETED**: Migrate DeleteOperationsTest.php + DeleteOperationsSimpleTest.php ✅
- [ ] Migrate UpdateOperationsTest.php + UpdateOperationsSimpleTest.php
- [ ] Migrate FirestoreModelTest.php
- [ ] Migrate ComplexQueryLogicTest.php
- [ ] Migrate ServiceProviderTest.php

### Medium Priority Tasks
- [ ] Consolidate Feature CRUD tests
- [ ] Consolidate Feature Events tests
- [ ] Consolidate Feature Query Builder tests
- [ ] Migrate ComplexQueryOperationsTest.php to Integration
- [ ] Update Cache tests to use new base classes

### Low Priority Tasks
- [ ] Update Auth tests to use new base classes
- [ ] Update Scope tests to use new base classes
- [ ] Update Accessor tests to use new base classes
- [ ] Migrate performance tests
- [ ] Clean up legacy files

### Configuration and Documentation
- [ ] Update phpunit.xml configuration
- [ ] Update CI/CD test commands
- [ ] Update README testing section
- [ ] Update contribution guidelines
- [ ] Create developer migration guide

## Validation Steps

After each migration:

1. **Run migrated test** to ensure it passes
2. **Run original test** to compare results
3. **Check test coverage** is maintained
4. **Verify performance** improvements
5. **Update documentation** if needed

## Success Metrics

- [x] **EXAMPLE COMPLETED**: DeleteOperations tests migrated successfully ✅
- [x] **Code Reduction**: 60% reduction in duplicate code achieved ✅
- [x] **Performance Monitoring**: Benchmarking and memory tracking added ✅
- [x] **Standardization**: TestDataFactory integration completed ✅
- [ ] All tests pass in new structure (4% complete)
- [ ] Test execution time improved by 20%+ (in progress)
- [ ] Memory usage reduced by 30%+ (in progress)
- [ ] No duplicate test patterns remain (4% complete)
- [ ] Clear test organization by type (infrastructure complete)
- [ ] Comprehensive documentation updated (in progress)

## Estimated Timeline

| Phase | Duration | Effort |
|-------|----------|--------|
| High Priority Migration | 3-4 hours | 5 core test files |
| Medium Priority Migration | 2-3 hours | 6 feature test files |
| Low Priority Updates | 1-2 hours | Existing organized tests |
| Configuration & Cleanup | 1 hour | Config files and docs |
| **Total** | **7-10 hours** | **~25 files affected** |

## Getting Started

To begin migration immediately:

1. **Start with DeleteOperationsTest.php** (highest impact)
2. **Follow the migration template** above
3. **Test thoroughly** before proceeding
4. **Document any issues** encountered
5. **Continue with next high-priority file**

The migration can be done incrementally, allowing the team to validate each step before proceeding to the next.
