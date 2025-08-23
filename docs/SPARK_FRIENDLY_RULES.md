# Spark-Friendly Firestore Security Rules for E2E Testing

Since you're using a Firebase Spark (free) account, here are the security rules that will work perfectly for E2E testing while keeping your project secure.

## 🔐 Recommended Security Rules

Go to: https://console.firebase.google.com/project/my-learning-sphere/firestore/rules

Replace your current rules with:

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    
    // E2E Testing Collections - Allow all operations for testing
    // These collections are only used during automated testing
    match /e2e_test_{suffix=**} {
      allow read, write: if true;
    }
    
    match /users_test_{suffix=**} {
      allow read, write: if true;
    }
    
    match /posts_test_{suffix=**} {
      allow read, write: if true;
    }
    
    match /categories_test_{suffix=**} {
      allow read, write: if true;
    }
    
    match /setup_test_{suffix=**} {
      allow read, write: if true;
    }
    
    // Your Production Collections (examples - customize as needed)
    // Uncomment and modify these based on your actual collections
    
    /*
    // Example: Users collection with authentication
    match /users/{userId} {
      allow read, write: if request.auth != null && request.auth.uid == userId;
    }
    
    // Example: Public read, authenticated write
    match /posts/{postId} {
      allow read: if true;
      allow write: if request.auth != null;
    }
    
    // Example: Admin-only collection
    match /admin/{document} {
      allow read, write: if request.auth != null && 
        request.auth.token.admin == true;
    }
    */
    
    // Default: Deny all other operations (secure by default)
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

## 🎯 What This Does

### ✅ **E2E Testing Support**
- Allows full read/write access to all E2E test collections
- Test collections use prefixes like `e2e_test_`, `users_test_`, etc.
- Only affects collections created during automated testing

### 🔒 **Production Security**
- **Secure by default**: Denies access to all other collections
- **Customizable**: Add your production rules in the commented section
- **No impact on existing data**: Your current collections remain protected

### 🚀 **Spark Account Friendly**
- No complex authentication logic that might hit quotas
- Simple rules that are easy to understand and debug
- Minimal rule evaluations to stay within free tier limits

## 🧪 Testing the Rules

After updating the rules, test them:

```bash
# This should work (E2E test collections)
vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php

# This should be denied (non-test collections)
# Your production collections will be protected
```

## 🔧 Customizing for Your App

When you're ready to add production collections, uncomment and modify the example rules:

```javascript
// Example: Your actual users collection
match /users/{userId} {
  allow read, write: if request.auth != null && request.auth.uid == userId;
}

// Example: Your actual posts collection  
match /posts/{postId} {
  allow read: if true;  // Public read
  allow write: if request.auth != null;  // Authenticated write
}
```

## 💡 Pro Tips for Spark Accounts

1. **Keep rules simple** - Complex rules use more quota
2. **Use specific collection names** - Avoid wildcards when possible
3. **Test incrementally** - Add one rule at a time
4. **Monitor usage** - Check Firebase Console for quota usage

This setup gives you full E2E testing capability while keeping your production data secure! 🎉
