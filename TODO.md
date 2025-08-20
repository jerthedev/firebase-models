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

### ⚠️ Partially Working
- Model updates (memory limited)
- Complex query operations (basic coverage)
- Batch operations (untested edge cases)

### ❌ Not Working
- Full test suite execution (memory issues)
- LightweightFirestoreMock (interface issues)
- Complex update scenarios
- Delete operation comprehensive testing

---

## 🎯 Immediate Action Items

1. **Before Sprint 2**: Fix memory issues or implement workarounds
2. **During Sprint 2**: Create proper mock interfaces for Auth testing
3. **After Sprint 2**: Redesign mock system for better performance
4. **Long-term**: Implement Firebase emulator integration

---

## 📋 Notes for Future Development

- Consider using Firebase emulator for integration tests
- Evaluate alternatives to Mockery for lighter mocking
- Plan for test performance optimization
- Document memory requirements clearly
- Create test environment setup automation

**Last Updated**: Sprint 1 completion
**Next Review**: Before Sprint 2 Week 2
