# End-to-End (E2E) Testing

This directory contains End-to-End tests that run against a **real Firebase project** using the Firebase API directly. These tests provide the highest level of confidence that the package works correctly in production environments.

## 🔧 Setup

### 1. Firebase Project Setup

1. Create a Firebase project (or use an existing **test** project)
2. Go to Project Settings → Service Accounts
3. Click "Generate new private key"
4. Download the JSON file

### 2. Credentials Setup

1. Copy the example credentials file:
   ```bash
   cp tests/credentials/e2e-credentials.example.json tests/credentials/e2e-credentials.json
   ```

2. Replace the content of `tests/credentials/e2e-credentials.json` with your downloaded service account JSON

3. The file is already in `.gitignore` so your credentials won't be committed

### 3. Verify Setup

Run a basic connectivity test:
```bash
composer test-e2e --filter="BasicModelE2ETest::it_can_create_a_model_in_real_firestore"
```

## 🚀 Running E2E Tests

### All E2E Tests
```bash
composer test-e2e
```

### E2E Tests with Coverage
```bash
composer test-e2e-coverage
```

### Specific Test Groups
```bash
# Models only
vendor/bin/pest --group=e2e --group=models

# Auth only  
vendor/bin/pest --group=e2e --group=auth

# Cloud mode only
vendor/bin/pest --group=e2e --group=cloud

# Sync mode only
vendor/bin/pest --group=e2e --group=sync
```

### Specific Test Files
```bash
vendor/bin/pest tests/E2E/Models/BasicModelE2ETest.php
vendor/bin/pest tests/E2E/Auth/FirebaseAuthE2ETest.php
```

## 📁 Test Structure

```
tests/E2E/
├── README.md                 # This file
├── BaseE2ETestCase.php      # Base class for all E2E tests
├── E2ETestConfig.php        # Configuration helper
├── Models/                  # Model-related E2E tests
│   ├── TestUser.php        # Test model for E2E testing
│   ├── BasicModelE2ETest.php
│   ├── QueryBuilderE2ETest.php
│   └── RelationshipsE2ETest.php
├── Auth/                    # Authentication E2E tests
│   ├── FirebaseAuthE2ETest.php
│   └── GuardE2ETest.php
├── Cloud/                   # Cloud mode E2E tests
│   ├── CloudModeE2ETest.php
│   └── CachingE2ETest.php
├── Sync/                    # Sync mode E2E tests
│   ├── SyncModeE2ETest.php
│   └── SyncOperationsE2ETest.php
└── Advanced/                # Advanced features E2E tests
    ├── TransactionsE2ETest.php
    ├── BatchOperationsE2ETest.php
    └── PerformanceE2ETest.php
```

## 🔒 Security & Safety

### ⚠️ Important Warnings

1. **Never use production data** - Always use a dedicated test Firebase project
2. **Credentials are sensitive** - The service account has full admin access to your Firebase project
3. **Data will be created and deleted** - Tests create real data and clean it up automatically
4. **Rate limits apply** - Firebase has API rate limits that may affect test performance

### 🛡️ Safety Features

- **Automatic cleanup**: All test data is automatically deleted after each test
- **Unique prefixes**: Test collections use unique prefixes to avoid conflicts
- **Gitignore protection**: Credentials file is automatically ignored by git
- **Confirmation prompts**: Interactive test runner asks for confirmation

## 🧪 Test Categories

### Models Tests (`tests/E2E/Models/`)
- CRUD operations with real Firestore
- Query builder functionality
- Model relationships
- Events and observers
- Accessors and mutators
- Scopes and filtering

### Authentication Tests (`tests/E2E/Auth/`)
- Firebase Auth integration
- User creation and management
- Token validation
- Guard functionality
- Middleware behavior

### Cloud Mode Tests (`tests/E2E/Cloud/`)
- Direct Firestore operations
- Caching behavior
- Performance characteristics
- Error handling

### Sync Mode Tests (`tests/E2E/Sync/`)
- Local database mirroring
- Sync operations
- Conflict resolution
- Data consistency

### Advanced Tests (`tests/E2E/Advanced/`)
- Transaction handling
- Batch operations
- Complex queries
- Performance benchmarks

## 🔧 Configuration

### Test Modes

E2E tests can run in different modes:

```php
// Cloud mode (default)
Config::set('firebase-models.mode', 'cloud');

// Sync mode
Config::set('firebase-models.mode', 'sync');
```

### Environment Variables

```bash
# Enable E2E mode
FIREBASE_E2E_MODE=true

# Override project ID
FIREBASE_PROJECT_ID=my-test-project
```

## 🐛 Troubleshooting

### Common Issues

1. **"E2E credentials not found"**
   - Ensure `tests/credentials/e2e-credentials.json` exists
   - Verify the file contains valid JSON

2. **"Permission denied" errors**
   - Check that your service account has the necessary permissions
   - Verify the project ID matches your Firebase project

3. **"Rate limit exceeded"**
   - Firebase has API rate limits
   - Run fewer tests concurrently
   - Add delays between test runs

4. **Tests hanging or timing out**
   - Check your internet connection
   - Verify Firebase project is accessible
   - Increase test timeouts if needed

### Debug Mode

Enable verbose output:
```bash
vendor/bin/pest tests/E2E/ --verbose
```

### Manual Cleanup

If tests fail to clean up automatically:
```bash
# Check Firebase Console for test collections
# They will have prefixes like: e2e_test_20241223_143022_abc12345
```

## 📊 Coverage and Reporting

### Generate Coverage Reports
```bash
composer test-e2e-coverage
```

### HTML Coverage Report
```bash
vendor/bin/pest --group=e2e --coverage --coverage-html=coverage-e2e
```

### Performance Metrics
E2E tests include performance assertions to ensure the package meets performance requirements in real-world scenarios.

## 🤝 Contributing

When adding new E2E tests:

1. Extend `BaseE2ETestCase`
2. Use appropriate test groups (`#[Group('e2e')]`, `#[Group('models')]`, etc.)
3. Follow the naming convention: `*E2ETest.php`
4. Include proper cleanup in tearDown
5. Add performance assertions where relevant
6. Document any special setup requirements
