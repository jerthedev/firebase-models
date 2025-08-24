# E2E Testing Guide

This document provides comprehensive instructions for running End-to-End (E2E) tests with the Firebase Models package.

## 📋 Overview

E2E tests verify that the package works correctly with the real Firebase API, ensuring production readiness and compatibility across different environments.

## 🔧 Prerequisites

### Required Components
- **PHP 8.2+** with CLI support
- **gRPC extension** for optimal Firebase performance
- **Composer** for dependency management
- **Firebase project** with Firestore enabled
- **Service account credentials** with Firestore permissions

### Firebase Setup
1. Create a Firebase project at [Firebase Console](https://console.firebase.google.com)
2. Enable Firestore Database
3. Create a service account with Firestore permissions
4. Download the service account JSON file
5. Place credentials at `tests/credentials/e2e-credentials.json`

## 🚀 Running E2E Tests

### Basic Commands

```bash
# Run all E2E tests
vendor/bin/pest tests/E2E/ --group=e2e

# Run basic connection tests only
vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php --group=connection

# Run with verbose output
vendor/bin/pest tests/E2E/ --group=e2e -v

# Run without coverage (faster)
vendor/bin/pest tests/E2E/ --group=e2e --no-coverage
```

### Test Categories

- **Connection Tests**: Basic Firebase connectivity and authentication
- **Model Tests**: FirestoreModel functionality with real API
- **Auth Tests**: Firebase Authentication integration
- **Cloud Tests**: Cloud mode operations
- **Sync Tests**: Sync mode operations
- **Advanced Tests**: Complex queries, transactions, batch operations

### Environment Variables

```bash
# Optional: Set custom test collection prefix
export FIREBASE_TEST_COLLECTION_PREFIX="custom_test_"

# Optional: Enable debug output
export FIREBASE_DEBUG=true
```

## 🐳 Docker Testing

For isolated testing environments, use the provided Docker setup:

### Build and Run
```bash
# Navigate to docker directory
cd docker

# Build and start container
docker-compose -f docker-compose.e2e.yml up -d

# Run E2E tests in container
docker-compose -f docker-compose.e2e.yml exec firebase-models-e2e \
  vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php --group=e2e

# Check environment
docker-compose -f docker-compose.e2e.yml exec firebase-models-e2e php --version
docker-compose -f docker-compose.e2e.yml exec firebase-models-e2e php -m | grep grpc

# Stop container
docker-compose -f docker-compose.e2e.yml down
```

### Docker Benefits
- ✅ **Isolated environment** - No local PHP/gRPC conflicts
- ✅ **Consistent setup** - Same environment across machines
- ✅ **Clean testing** - Fresh environment for each test run

## 🔍 Test Structure

### Base Test Case
All E2E tests extend `BaseE2ETestCase` which provides:
- Firebase client initialization
- Test collection management
- Cleanup utilities
- Authentication helpers

### Test Collections
Tests use isolated collections with automatic cleanup:
```php
$testCollection = $this->getTestCollection('my_test');
// Creates: "e2e_test_my_test_1234567890"
```

### Example Test
```php
#[Test]
#[Group('e2e')]
public function it_can_create_and_read_documents(): void
{
    $collection = $this->getTestCollection('basic_test');
    
    // Create document
    $docRef = $this->getFirestore()->database()
        ->collection($collection)
        ->add(['name' => 'Test Document']);
    
    // Verify creation
    $this->assertNotNull($docRef->id());
    
    // Read document
    $snapshot = $docRef->snapshot();
    $this->assertTrue($snapshot->exists());
    $this->assertEquals('Test Document', $snapshot->data()['name']);
}
```

## 📊 Test Coverage

E2E tests cover:
- ✅ **Document CRUD operations**
- ✅ **Collection queries and filtering**
- ✅ **Real-time listeners**
- ✅ **Batch operations**
- ✅ **Transactions**
- ✅ **Authentication flows**
- ✅ **Error handling**
- ✅ **Performance scenarios**

## 🔧 Troubleshooting

### Common Issues

#### Missing Credentials
```
Error: Service account file not found
```
**Solution**: Ensure `tests/credentials/e2e-credentials.json` exists and contains valid service account credentials.

#### Permission Denied
```
Error: Permission denied to access Firestore
```
**Solution**: Verify service account has Firestore permissions in Firebase Console.

#### gRPC Extension Missing
```
Error: gRPC extension is required
```
**Solution**: Install gRPC extension or use Docker environment.

### Debug Mode
Enable detailed logging:
```bash
export FIREBASE_DEBUG=true
vendor/bin/pest tests/E2E/ --group=e2e -v
```

## 🌐 Remote Environment Testing

### CI/CD Integration
```yaml
# Example GitHub Actions
- name: Run E2E Tests
  run: |
    echo '${{ secrets.FIREBASE_CREDENTIALS }}' > tests/credentials/e2e-credentials.json
    vendor/bin/pest tests/E2E/ --group=e2e --no-coverage
  env:
    PHP_VERSION: 8.2
```

### Production-like Testing
For testing in production-like environments:
1. Use separate Firebase project for testing
2. Implement proper credential management
3. Use environment-specific test data
4. Monitor Firebase usage and costs

## 📈 Performance Considerations

- **Test Isolation**: Each test uses unique collections
- **Cleanup**: Automatic cleanup prevents data accumulation
- **Parallel Execution**: Tests can run in parallel with proper isolation
- **Resource Usage**: Monitor Firebase read/write quotas

## 🔄 Continuous Integration

### Recommended CI Setup
1. **Environment**: Use Docker for consistent testing
2. **Credentials**: Store Firebase credentials as CI secrets
3. **Isolation**: Use separate Firebase project for CI
4. **Reporting**: Generate test reports and coverage
5. **Notifications**: Alert on E2E test failures

### Example CI Configuration
```bash
#!/bin/bash
# CI E2E Test Script

# Setup credentials
echo "$FIREBASE_CREDENTIALS" > tests/credentials/e2e-credentials.json

# Run tests with timeout
timeout 300 vendor/bin/pest tests/E2E/ --group=e2e --no-coverage

# Cleanup
rm -f tests/credentials/e2e-credentials.json
```

## 📚 Additional Resources

- [Firebase Console](https://console.firebase.google.com)
- [Firebase Admin SDK Documentation](https://firebase.google.com/docs/admin)
- [Kreait Firebase PHP Documentation](https://firebase-php.readthedocs.io)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Pest Testing Framework](https://pestphp.com/docs)
