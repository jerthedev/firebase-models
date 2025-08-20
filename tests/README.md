# Testing Guide

This document provides comprehensive guidance for testing the JTD Firebase Models package.

## Overview

The testing suite uses **Pest PHP** as the primary testing framework, built on top of PHPUnit. The tests are designed to run without requiring actual Firebase connections through comprehensive mocking.

## Test Structure

```
tests/
├── Feature/           # Integration tests
├── Unit/             # Unit tests
├── Helpers/          # Test utilities and mocks
├── Pest.php          # Pest configuration
├── TestCase.php      # Base test case
└── README.md         # This file
```

## Running Tests

### Basic Test Execution

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
vendor/bin/pest tests/Unit/FirestoreModelTest.php

# Run tests with specific filter
vendor/bin/pest --filter="FirestoreModel"
```

### Coverage Reports

```bash
# Generate HTML coverage report
vendor/bin/pest --coverage --coverage-html=coverage-html

# Generate text coverage report
vendor/bin/pest --coverage --coverage-text

# Generate Clover XML coverage report
vendor/bin/pest --coverage --coverage-clover=coverage.xml
```

## Test Categories

### Unit Tests (`tests/Unit/`)

Test individual classes and methods in isolation:

- **FirestoreModelTest.php**: Tests the core FirestoreModel functionality
- **FirestoreDBTest.php**: Tests the FirestoreDB facade
- **ServiceProviderTest.php**: Tests the service provider registration
- **QueryBuilderTest.php**: Tests query builder components

### Feature Tests (`tests/Feature/`)

Test integration between components:

- **FirestoreQueryBuilderTest.php**: Tests complete query workflows
- **ModelCRUDTest.php**: Tests full CRUD operations
- **EventsTest.php**: Tests model event system
- **CastingTest.php**: Tests attribute casting functionality

## Firestore Mocking

The test suite includes comprehensive Firestore mocking through the `FirestoreMock` helper class.

### Basic Mocking

```php
// Mock a document
$this->createMockDocument('users', '123', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Mock a query response
$this->mockFirestoreQuery('users', [
    ['id' => '1', 'name' => 'John'],
    ['id' => '2', 'name' => 'Jane']
]);

// Mock CRUD operations
$this->mockFirestoreCreate('users', '123');
$this->mockFirestoreUpdate('users', '123');
$this->mockFirestoreDelete('users', '123');
```

### Assertions

```php
// Assert operations were called
$this->assertFirestoreOperationCalled('create', 'users', '123');
$this->assertFirestoreQueryExecuted('users', [
    ['field' => 'active', 'operator' => '==', 'value' => true]
]);

// Model-specific assertions
expect($user)->toBeFirestoreModel();
expect($user)->toHaveAttribute('name', 'John Doe');
expect($user)->toBeDirty('name');
expect($user)->toExistInFirestore();
```

## Custom Expectations

The test suite includes custom Pest expectations for Firebase Models:

```php
// Model type checking
expect($model)->toBeFirestoreModel();

// Attribute checking
expect($model)->toHaveAttribute('name', 'John');
expect($model)->toHaveCast('active', 'boolean');

// State checking
expect($model)->toBeDirty(['name', 'email']);
expect($model)->toBeClean();
expect($model)->toExistInFirestore();
expect($model)->toBeRecentlyCreated();
```

## Test Helpers

### TestCase Base Class

All tests extend the `TestCase` class which provides:

- Automatic Firestore mocking setup
- Helper methods for common operations
- Proper test environment configuration
- Cleanup between tests

### Available Helper Methods

```php
// Firestore mocking
$this->createMockDocument($collection, $id, $data);
$this->mockFirestoreQuery($collection, $documents);
$this->clearFirestoreMocks();

// Model testing
$this->createTestModel($class, $attributes);
$this->assertModelHasAttributes($model, $attributes);
$this->assertModelIsDirty($model, $attributes);

// Time testing
$this->freezeTime('2023-01-01 12:00:00');
$this->unfreezeTime();
$this->createTestDate('2023-01-01');
$this->createTestTimestamp('2023-01-01');
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

## Troubleshooting

### Common Issues

1. **Mock not working**: Ensure `clearFirestoreMocks()` is called in `beforeEach()`
2. **Tests failing randomly**: Check for shared state between tests
3. **Coverage too low**: Add tests for uncovered code paths
4. **Slow tests**: Verify no actual Firebase calls are being made

### Getting Help

1. Check the test output for specific error messages
2. Review the mock setup in `FirestoreMock.php`
3. Verify test environment configuration in `TestCase.php`
4. Check CI logs for environment-specific issues

## Future Enhancements

- **Browser Testing**: Add Dusk tests for frontend integration
- **Performance Testing**: Add benchmarking for query performance
- **Load Testing**: Test with large datasets
- **Integration Testing**: Test with actual Firebase in staging environment
