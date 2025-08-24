# Manual Firestore Index Setup for E2E Testing

Since your service account doesn't have index creation permissions, here's how to manually create the required indexes for E2E testing.

## 🔍 Required Indexes

Go to your Firebase Console: https://console.firebase.google.com/project/my-learning-sphere/firestore/indexes

Click **"Create Index"** and create these 4 composite indexes:

### Index 1: E2E Basic Query Index
- **Collection Group**: `e2e_test_basic_query_test`
- **Fields**:
  1. `category` - **Ascending**
  2. `priority` - **Ascending** 
  3. `__name__` - **Ascending**

### Index 2: E2E User Query Index  
- **Collection Group**: `users_test`
- **Fields**:
  1. `active` - **Ascending**
  2. `role` - **Ascending**
  3. `created_at` - **Descending**

### Index 3: E2E Posts Query Index
- **Collection Group**: `posts_test`
- **Fields**:
  1. `status` - **Ascending**
  2. `category_id` - **Ascending**
  3. `published_at` - **Descending**

### Index 4: E2E Categories Query Index
- **Collection Group**: `categories_test`
- **Fields**:
  1. `active` - **Ascending**
  2. `sort_order` - **Ascending**
  3. `name` - **Ascending**

## 🔐 Security Rules

Go to: https://console.firebase.google.com/project/my-learning-sphere/firestore/rules

Replace your rules with:

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
    
    // Your existing production rules can go here
    // match /your_production_collection/{document} {
    //   allow read, write: if request.auth != null;
    // }
  }
}
```

## 🔑 Authentication Setup

Go to: https://console.firebase.google.com/project/my-learning-sphere/authentication/providers

Enable these sign-in methods:
- ✅ **Email/Password**
- ✅ **Anonymous**
- ✅ **Custom Token**

## ✅ Verification

Once you've created the indexes and updated the rules, test your setup:

```bash
# Test basic connectivity
vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php::it_can_connect_to_firebase

# Test with indexes (should work after indexes are built)
vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php

# Run all E2E tests
composer test-e2e
```

## ⏳ Index Building Time

- Indexes typically take **2-5 minutes** to build for empty collections
- You'll see "Building" status in the Firebase Console
- Tests will fail with "requires an index" until building is complete

## 🚨 Troubleshooting

### "The query requires an index"
- **Cause**: Index is still building or wasn't created correctly
- **Solution**: Wait for index to finish building, or check index configuration

### "Permission denied"
- **Cause**: Security rules not updated
- **Solution**: Update Firestore security rules as shown above

### "Service account file not found"
- **Cause**: Missing E2E credentials
- **Solution**: Ensure `tests/credentials/e2e-credentials.json` exists

## 🎉 Ready to Test!

Once all indexes show "Enabled" status in the Firebase Console, your E2E tests should run successfully!

```bash
composer test-e2e
```
