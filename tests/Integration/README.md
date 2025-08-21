# Sprint 3 Integration Tests

This directory contains comprehensive integration tests for all Sprint 3 features, ensuring that sync mode, transactions, batch operations, and relationships work together seamlessly.

## Test Structure

### Core Integration Tests

1. **Sprint3IntegrationTest.php** - Main integration test suite
   - Tests interaction between all Sprint 3 features
   - Comprehensive workflow testing
   - Error handling and recovery
   - Performance validation

2. **SyncTransactionIntegrationTest.php** - Sync + Transactions
   - Transaction creation with immediate sync
   - Conflict resolution during sync
   - Version-based conflict handling
   - Error recovery scenarios

3. **BatchRelationshipIntegrationTest.php** - Batch + Relationships
   - Batch operations with relationship setup
   - Eager loading optimization
   - Relationship validation
   - Cleanup operations

4. **Sprint3PerformanceTest.php** - Performance & Stress Testing
   - Large dataset handling
   - Concurrent operation testing
   - Memory usage validation
   - Scaling performance analysis

## Running the Tests

### Run All Integration Tests
```bash
# Run all Sprint 3 integration tests
php artisan test tests/Integration/

# Run with verbose output
php artisan test tests/Integration/ --verbose

# Run with coverage
php artisan test tests/Integration/ --coverage
```

### Run Specific Test Suites
```bash
# Main integration tests
php artisan test tests/Integration/Sprint3IntegrationTest.php

# Sync + Transaction tests
php artisan test tests/Integration/SyncTransactionIntegrationTest.php

# Batch + Relationship tests
php artisan test tests/Integration/BatchRelationshipIntegrationTest.php

# Performance tests
php artisan test tests/Integration/Sprint3PerformanceTest.php
```

### Run Individual Test Methods
```bash
# Test specific workflow
php artisan test --filter test_complete_workflow

# Test performance scenarios
php artisan test --filter test_batch_operation_performance

# Test conflict resolution
php artisan test --filter test_conflict_resolution_with_transactions
```

## Test Coverage

### Feature Integration Coverage

| Feature Combination | Test Coverage | Status |
|---------------------|---------------|--------|
| Sync + Transactions | ✅ Complete | Passing |
| Batch + Relationships | ✅ Complete | Passing |
| Transactions + Relationships | ✅ Complete | Passing |
| Sync + Batch Operations | ✅ Complete | Passing |
| All Features Combined | ✅ Complete | Passing |

### Scenario Coverage

| Scenario | Test Method | Status |
|----------|-------------|--------|
| Basic workflow | `test_complete_workflow` | ✅ |
| Conflict resolution | `test_conflict_resolution_with_transactions` | ✅ |
| Large datasets | `test_performance_with_large_datasets` | ✅ |
| Error recovery | `test_error_handling_and_recovery` | ✅ |
| Concurrent operations | `test_transaction_performance_under_load` | ✅ |
| Memory efficiency | `test_memory_usage_under_load` | ✅ |

## Performance Benchmarks

### Expected Performance Metrics

| Operation | Dataset Size | Expected Time | Memory Usage |
|-----------|--------------|---------------|--------------|
| Batch Insert | 1,000 records | < 30 seconds | < 50 MB |
| Sync Operation | 2,000 records | < 3 minutes | < 100 MB |
| Transaction | 10 operations | < 10 seconds | < 10 MB |
| Relationship Loading | 100 users + 1,000 posts | < 5 seconds | < 25 MB |

### Performance Test Results

Performance test results are logged to `storage/logs/sprint3-performance.json` for analysis.

## Test Environment Setup

### Prerequisites

1. **Firestore Emulator** (for safe testing)
   ```bash
   firebase emulators:start --only firestore
   ```

2. **Test Database** (for sync testing)
   ```bash
   php artisan migrate:fresh --env=testing
   ```

3. **Configuration**
   ```php
   // config/firebase-models.php (testing)
   'sync' => [
       'enabled' => true,
       'mode' => 'two_way',
       'conflict_resolution' => [
           'policy' => 'last_write_wins',
       ],
   ],
   ```

### Environment Variables

```env
# Testing environment
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Firebase testing
FIREBASE_PROJECT_ID=test-project
FIRESTORE_EMULATOR_HOST=localhost:8080
```

## Test Data Management

### Test Models

The integration tests use dynamically created test models:

- `TestUser` - User model with relationships
- `TestPost` - Post model belonging to users
- `TestCategory` - Category model for posts

### Data Cleanup

All tests include automatic cleanup:
- Firestore collections are cleared after each test
- Local database is refreshed between tests
- Memory usage is monitored and reported

## Debugging Test Failures

### Common Issues

1. **Firestore Emulator Not Running**
   ```bash
   # Start emulator
   firebase emulators:start --only firestore
   ```

2. **Database Migration Issues**
   ```bash
   # Refresh test database
   php artisan migrate:fresh --env=testing
   ```

3. **Memory Limit Exceeded**
   ```bash
   # Increase PHP memory limit
   php -d memory_limit=512M artisan test
   ```

### Debug Logging

Enable debug logging for detailed test output:

```php
// In test methods
Log::debug('Test checkpoint', ['data' => $testData]);
```

### Performance Analysis

Performance metrics are automatically logged. To analyze:

```bash
# View performance log
cat storage/logs/sprint3-performance.json | jq '.'

# Monitor memory usage
php -d memory_limit=1G artisan test --filter performance
```

## Continuous Integration

### GitHub Actions

```yaml
name: Sprint 3 Integration Tests

on: [push, pull_request]

jobs:
  integration-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1
        extensions: mbstring, xml, ctype, json
        
    - name: Install dependencies
      run: composer install
      
    - name: Setup Firebase Emulator
      run: |
        npm install -g firebase-tools
        firebase emulators:start --only firestore &
        
    - name: Run integration tests
      run: php artisan test tests/Integration/
```

### Local CI

```bash
# Run full test suite with coverage
./scripts/run-integration-tests.sh

# Run performance benchmarks
./scripts/run-performance-tests.sh
```

## Contributing

### Adding New Integration Tests

1. **Create test file** in `tests/Integration/`
2. **Extend TestCase** and use `RefreshDatabase`
3. **Follow naming convention**: `FeatureIntegrationTest.php`
4. **Include cleanup** in `tearDown()` method
5. **Add performance metrics** where applicable

### Test Guidelines

- **Test real scenarios** that users will encounter
- **Include error cases** and edge conditions
- **Validate performance** for large datasets
- **Ensure cleanup** to prevent test pollution
- **Document complex scenarios** with comments

### Performance Testing

- **Set realistic limits** based on expected usage
- **Monitor memory usage** for large operations
- **Test concurrent scenarios** where applicable
- **Log metrics** for trend analysis
- **Include scaling tests** for different dataset sizes

## Support

For issues with integration tests:

1. Check the [troubleshooting guide](../docs/troubleshooting.md)
2. Review test logs in `storage/logs/`
3. Verify environment setup
4. Run individual test methods to isolate issues
5. Check performance metrics for bottlenecks
