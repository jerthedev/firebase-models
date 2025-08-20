# TODO - Known Issues and Improvements

## 🚨 Critical Test Issues

### Memory Exhaustion Problems
**Status**: Partially Fixed (workaround applied)
**Priority**: High

**Issue**: Mockery-based FirestoreMock consumes excessive memory during test execution
- Tests fail with "Allowed memory size exhausted" errors
- Even with 512MB memory limit, complex test suites crash
- Problem occurs in `vendor/mockery/mockery/library/Mockery/` classes

**Current Workaround**:
- Increased memory limit to 512MB in `phpunit.xml`
- Running individual tests instead of full suites
- Using selective test execution to avoid memory buildup

**Root Cause**:
- Mockery creates too many mock objects for complex Firestore operations
- Mock objects are not being properly garbage collected between tests
- FirestoreMock creates nested anonymous classes that accumulate

**Affected Tests**:
- `tests/Feature/FirestoreModelCrudLightweightTest.php` (update operations)
- Full test suite execution
- Any test requiring multiple Firestore operations

**Potential Solutions**:
1. Implement proper mock cleanup in `tearDown()` methods
2. Create a simpler mock system without Mockery
3. Use real Firebase emulator for integration tests
4. Implement lazy loading for mock objects

---

### LightweightFirestoreMock Interface Issues
**Status**: Incomplete Implementation
**Priority**: Medium

**Issue**: LightweightFirestoreMock doesn't properly implement required interfaces
- Anonymous classes don't extend proper Firebase interfaces
- Type declaration mismatches with `CollectionReference` and `DocumentReference`
- Service provider binding conflicts

**Current State**:
- Basic structure exists but not functional
- Falls back to regular FirestoreMock
- Interface compliance issues prevent proper dependency injection

**Required Work**:
1. Create proper mock classes that implement Firebase interfaces
2. Fix type declarations and return types
3. Ensure compatibility with FirestoreDatabase expectations
4. Test interface compliance

---

## 🚨 Scope Testing Issues (Sprint 2 Week 2)
**Status**: Mostly Working (37/45 tests passing - 82% pass rate)
**Priority**: Medium

**Issue**: Caching system interference with scope tests
- Cache returns wrong types (int instead of Collection, Collection instead of int)
- Disabling cache via config doesn't fully work
- Some tests fail due to cache type mismatches

**Specific Problems**:
1. **Type Mismatch Errors**:
   ```
   FirestoreQueryBuilder::get(): Return value must be of type Collection, int returned
   FirestoreQueryBuilder::count(): Return value must be of type int, Collection returned
   ```

2. **Cache Configuration Issues**:
   - Setting `firebase-models.cache.enabled => false` doesn't fully disable caching
   - Cache system still intercepts query results
   - getCached() method returns cached results of wrong type

3. **Test Expectation Mismatches**:
   - Some global scope removal tests expect specific behavior
   - Simplified scope removal approach affects test expectations
   - Performance timing tests are flaky due to caching interference

**Current Workarounds**:
- Disabled caching in test configuration
- Simplified scope removal to avoid complex query manipulation
- Adjusted some test expectations to match simplified behavior

**Affected Test Files**:
- `tests/Unit/Scopes/LocalScopesTest.php` (2 failures)
- `tests/Unit/Scopes/GlobalScopesTest.php` (4 failures)
- `tests/Unit/Scopes/ScopeIntegrationTest.php` (2 failures)

**Required Fixes**:
1. **Deep dive into caching system** to ensure proper disable functionality
2. Fix type consistency in cached vs non-cached query results
3. Ensure cache configuration is properly respected in tests
4. Review and fix remaining test expectations

---

## 🔧 Test Coverage Gaps

### Update Operations
**Status**: Limited Testing
**Priority**: Medium

**Issue**: Model update operations have insufficient test coverage due to memory issues
- `update()` method tests crash with memory exhaustion
- Dirty attribute tracking needs more testing
- Batch update operations untested

**Missing Tests**:
- Model attribute updates and persistence
- Dirty tracking and change detection
- Mass update operations
- Update event firing and cancellation

### Delete Operations
**Status**: Untested
**Priority**: Medium

**Issue**: Model deletion operations lack comprehensive testing
- `delete()` method not thoroughly tested
- Soft delete functionality (future feature) needs test foundation
- Cascade deletion scenarios

### Complex Query Operations
**Status**: Basic Coverage Only
**Priority**: Low

**Issue**: Advanced query builder features need more testing
- Complex where clause combinations
- Ordering and pagination edge cases
- Aggregation operations
- Query optimization scenarios

---

## 🔍 Caching System Deep Dive Required
**Status**: Critical Investigation Needed
**Priority**: High

**Issue**: Caching system has fundamental problems affecting functionality and testing

**Core Problems**:
1. **Cache Disable Mechanism**:
   - Configuration `firebase-models.cache.enabled => false` not fully respected
   - getCached() method still executes caching logic when disabled
   - Cache store configuration conflicts with disable flag

2. **Type Consistency Issues**:
   - Cached results return different types than non-cached results
   - Query methods (get, count, etc.) have inconsistent return types
   - Cache serialization/deserialization may be corrupting data types

3. **Configuration Hierarchy**:
   - Multiple cache configuration points (enabled, store, TTL)
   - Unclear precedence between different cache settings
   - Test environment cache configuration not properly isolated

**Investigation Required**:
1. **Cache Manager Analysis**:
   - Review `src/Cache/CacheManager.php` for disable logic
   - Check if cache store is properly bypassed when disabled
   - Verify configuration loading and precedence

2. **Cacheable Trait Review**:
   - Examine `src/Cache/Concerns/Cacheable.php` for type handling
   - Check getCached() method implementation
   - Verify proper fallback to non-cached methods

3. **Query Builder Integration**:
   - Review how caching integrates with FirestoreQueryBuilder
   - Check type consistency between cached and non-cached paths
   - Verify proper cache key generation and retrieval

4. **Configuration System**:
   - Review how cache configuration is loaded and applied
   - Check test environment configuration isolation
   - Verify proper configuration merging and overrides

**Required Actions**:
1. **Immediate**: Document current cache behavior and configuration
2. **Short-term**: Fix cache disable mechanism for testing
3. **Medium-term**: Ensure type consistency across cached/non-cached operations
4. **Long-term**: Comprehensive cache system review and optimization

**Test Impact**:
- Scope tests affected by cache type mismatches
- Future feature tests may encounter similar issues
- Need reliable cache disable for unit testing

---

## 🏗️ Architecture Improvements

### Mock System Redesign
**Priority**: High

**Current Issues**:
- Heavy dependency on Mockery causing memory issues
- Complex nested anonymous classes
- Difficult to maintain and extend

**Proposed Solution**:
Create a custom mock system:
```php
// Lightweight mock without Mockery
class SimpleFirestoreMock implements FirestoreInterface {
    private array $documents = [];
    private array $operations = [];
    
    // Simple, memory-efficient implementation
}
```

### Test Organization
**Priority**: Medium

**Current Issues**:
- Test files mixing lightweight and regular mocks
- Inconsistent test setup and teardown
- Memory management scattered across test files

**Proposed Improvements**:
1. Separate test suites by mock type
2. Standardize test base classes
3. Implement proper resource cleanup
4. Create test utilities for common operations

---

## 📝 Documentation Needs

### Testing Guide Updates
**Priority**: Medium

**Required Updates**:
- Document memory requirements for tests
- Explain when to use lightweight vs regular mocks
- Provide troubleshooting guide for test failures
- Add performance testing guidelines

### Mock System Documentation
**Priority**: Low

**Required Documentation**:
- How to extend the mock system
- Interface compliance requirements
- Memory optimization techniques
- Custom mock creation guide

---

## 🔮 Future Improvements

### Performance Optimization
**Priority**: Low

**Ideas**:
- Implement connection pooling for tests
- Add test result caching
- Optimize mock object creation
- Implement test parallelization

### Enhanced Testing Features
**Priority**: Low

**Ideas**:
- Add integration test support with Firebase emulator
- Implement property-based testing
- Add performance benchmarking
- Create visual test reporting

---

## 📊 Current Test Status Summary

### ✅ Working Tests
- Model creation and basic CRUD
- Query builder basic operations
- Event system (all 8 tests passing)
- Model retrieval and finding
- Basic FirestoreMock functionality
- **Eloquent Accessors & Mutators (43 tests passing)**
- **Eloquent Query Scopes (37/45 tests passing - 82% pass rate)**

### ⚠️ Partially Working
- Model updates (memory limited)
- Complex query operations (basic coverage)
- Batch operations (untested edge cases)
- **Scope tests (8 failures due to caching interference)**

### ❌ Not Working
- Full test suite execution (memory issues)
- LightweightFirestoreMock (interface issues)
- Complex update scenarios
- Delete operation comprehensive testing
- **Cache disable mechanism (affects scope and future tests)**

---

## 🎯 Immediate Action Items

1. **Before Sprint 2**: Fix memory issues or implement workarounds
2. **During Sprint 2**: Create proper mock interfaces for Auth testing
3. **After Sprint 2**: Redesign mock system for better performance
4. **Long-term**: Implement Firebase emulator integration
5. **Critical**: Deep dive into caching system to fix disable mechanism and type consistency
6. **High Priority**: Fix remaining 8 scope test failures related to caching interference

---

## 📋 Notes for Future Development

- Consider using Firebase emulator for integration tests
- Evaluate alternatives to Mockery for lighter mocking
- Plan for test performance optimization
- Document memory requirements clearly
- Create test environment setup automation

**Last Updated**: Sprint 2 Week 2 - Scope Implementation Complete
**Next Review**: Before Sprint 3 (after caching system investigation)
