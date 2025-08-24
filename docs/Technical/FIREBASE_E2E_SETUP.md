# Firebase E2E Testing Setup Guide

This guide walks you through setting up your Firebase project for comprehensive End-to-End testing with the Firebase Models package.

## 🎯 Quick Start

1. **Run the setup script:**
   ```bash
   php scripts/setup-firebase-e2e.php
   ```

2. **Follow the manual setup instructions** that the script provides

3. **Verify setup:**
   ```bash
   vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php
   ```

## 📋 Detailed Setup Instructions

### 1. Firebase Project Creation

#### Option A: Create New Test Project (Recommended)
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project"
3. Name: `your-app-name-e2e-testing`
4. Disable Google Analytics (not needed for testing)
5. Create project

#### Option B: Use Existing Project
⚠️ **Warning**: Only use existing projects if you understand the implications. E2E tests will create/delete data.

### 2. Firestore Database Setup

#### Enable Firestore
1. Go to **Firestore Database** in Firebase Console
2. Click "Create database"
3. Choose **Test mode** (allows read/write access)
4. Select your preferred region
5. Click "Done"

#### Create Required Indexes
The E2E tests require these composite indexes:

**Index 1: Basic Query Operations**
- Collection group: `e2e_test_*`
- Fields:
  - `category` (Ascending)
  - `priority` (Ascending)
  - `__name__` (Ascending)

**Index 2: User Management**
- Collection group: `users_*`
- Fields:
  - `active` (Ascending)
  - `role` (Ascending)
  - `created_at` (Descending)

**Index 3: Content Management**
- Collection group: `posts_*`
- Fields:
  - `status` (Ascending)
  - `category_id` (Ascending)
  - `published_at` (Descending)

**Index 4: Relationship Queries**
- Collection group: `categories_*`
- Fields:
  - `active` (Ascending)
  - `sort_order` (Ascending)
  - `name` (Ascending)

#### Security Rules for Testing
```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // E2E testing collections - allow all operations
    match /e2e_test_{suffix=**} {
      allow read, write: if true;
    }
    
    match /users_{suffix=**} {
      allow read, write: if true;
    }
    
    match /posts_{suffix=**} {
      allow read, write: if true;
    }
    
    match /categories_{suffix=**} {
      allow read, write: if true;
    }
    
    // Batch operation support
    match /{document=**} {
      allow read, write: if resource.id.matches('.*_test_.*');
    }
  }
}
```

### 3. Firebase Authentication Setup

#### Enable Sign-in Methods
1. Go to **Authentication** → **Sign-in method**
2. Enable these providers:
   - ✅ **Email/Password**
   - ✅ **Anonymous** 
   - ✅ **Custom Token**

#### Optional: Create Test Users
For authentication E2E tests, you can pre-create test users:
- `e2e-test@example.com` / `e2etest123`
- `e2e-admin@example.com` / `e2eadmin123`

### 4. Service Account Configuration

#### Download Service Account Key
1. Go to **Project Settings** → **Service accounts**
2. Click "Generate new private key"
3. Download the JSON file
4. Save as `tests/credentials/e2e-credentials.json`

#### Required IAM Permissions
Your service account needs these roles:
- **Firebase Admin SDK Administrator Service Agent**
- **Cloud Datastore User**
- **Firebase Authentication Admin**
- **Service Account Token Creator**

#### Enable Required APIs
In Google Cloud Console, enable:
- Cloud Firestore API
- Firebase Authentication API
- Identity and Access Management (IAM) API

### 5. Project Configuration

#### Environment Variables (Optional)
```bash
# .env.testing
FIREBASE_E2E_MODE=true
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_E2E_CLEANUP=true
```

#### Composer Scripts
Add to your `composer.json`:
```json
{
  "scripts": {
    "test-e2e": "pest tests/E2E/ --group=e2e",
    "test-e2e-coverage": "pest tests/E2E/ --group=e2e --coverage",
    "setup-firebase": "php scripts/setup-firebase-e2e.php"
  }
}
```

## 🧪 Testing Your Setup

### Basic Connectivity Test
```bash
vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php::it_can_connect_to_firebase
```

### Full E2E Test Suite
```bash
composer test-e2e
```

### Specific Test Categories
```bash
# Models only
vendor/bin/pest --group=e2e --group=models

# Authentication only  
vendor/bin/pest --group=e2e --group=auth

# Cloud mode only
vendor/bin/pest --group=e2e --group=cloud
```

## 🔧 Troubleshooting

### Common Issues

#### "The query requires an index"
**Solution**: Create the missing composite index using the URL provided in the error message.

#### "Permission denied"
**Solution**: 
1. Check Firestore security rules
2. Verify service account permissions
3. Ensure APIs are enabled

#### "Service account file not found"
**Solution**: Ensure `tests/credentials/e2e-credentials.json` exists and contains valid JSON.

#### "Field data must be provided as a list of arrays"
**Solution**: This is a known API format issue. The E2E tests will be updated to handle the correct format.

### Performance Considerations

#### Quota Limits
Monitor these quotas during E2E testing:
- **Firestore operations**: 50,000 reads/writes per day (free tier)
- **Authentication**: 10,000 verifications per month (free tier)

#### Test Data Cleanup
E2E tests automatically clean up test data, but you can manually clean up:
```bash
# Clean up test collections
php scripts/cleanup-e2e-data.php
```

## 📊 What Gets Tested

The E2E test suite covers:

### Core Functionality
- ✅ Document CRUD operations
- ✅ Collection queries and filtering  
- ✅ Complex query operations (where, orderBy, limit)
- ✅ Batch operations
- ✅ Transaction handling

### Authentication
- ✅ User creation and management
- ✅ Token validation and refresh
- ✅ Guard functionality
- ✅ Middleware behavior

### Advanced Features
- ✅ Model relationships
- ✅ Events and observers
- ✅ Accessors and mutators
- ✅ Scopes and filtering
- ✅ Caching behavior
- ✅ Error handling

### Performance
- ✅ Query performance benchmarks
- ✅ Memory usage monitoring
- ✅ Batch operation efficiency
- ✅ Connection pooling

## 🎉 Next Steps

Once your Firebase project is set up:

1. **Run the full E2E test suite** to verify everything works
2. **Review test results** and fix any remaining configuration issues
3. **Integrate E2E tests** into your CI/CD pipeline
4. **Monitor Firebase usage** to stay within quota limits

For questions or issues, refer to the [E2E Testing Guide](E2E_TESTING.md) or check the troubleshooting section above.
