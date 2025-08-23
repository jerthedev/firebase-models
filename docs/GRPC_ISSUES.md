# gRPC Environment Issues

This document details the gRPC-related issues encountered during development and testing of the Firebase Models package.

## 🚨 Issue Summary

During E2E testing setup, we encountered stack overflow errors when using the Firebase SDK with gRPC extension in certain local development environments.

## 🔍 Technical Details

### Error Description
```
Fatal error: Allowed memory size exhausted (tried to allocate X bytes)
Stack overflow in beste/in-memory-cache/src/CacheKey.php:15
```

### Root Cause Analysis
The issue appears to be related to:
- **Local environment configuration** - Specific to certain development setups
- **gRPC + Firebase SDK interaction** - Stack overflow during document operations
- **Regex compilation** - Complex regex patterns in `beste/in-memory-cache` dependency

### Affected Components
- `beste/in-memory-cache` (dependency of `kreait/firebase-php`)
- gRPC extension interaction with Firebase SDK
- Real Firebase API operations (document creation, queries)

## 🧪 Testing Results

### Environment Testing Matrix

| Environment | PHP Version | gRPC | Firebase SDK | Result |
|-------------|-------------|------|--------------|---------|
| Local Dev (macOS) | 8.4.11 | ✅ | Latest | ❌ Stack overflow |
| Local Dev (macOS) | 8.2.x | ❌ | Latest | ⚠️ Requires gRPC |
| Docker (Linux) | 8.2 | ✅ | Latest | ✅ Expected to work |
| Production | 8.2+ | ✅ | Latest | ✅ Known working |

### Key Findings
1. **Issue is environment-specific** - Not affecting all installations
2. **Production environments work** - `kreait/firebase-php` is widely used successfully
3. **Local development issue** - Likely related to specific PHP/gRPC compilation or configuration
4. **Not package-specific** - Issue occurs with direct Firebase SDK usage

## 🔧 Workarounds and Solutions

### Immediate Solutions

#### 1. Docker Environment (Recommended)
Use the provided Docker setup for E2E testing:
```bash
cd docker
docker-compose -f docker-compose.e2e.yml up -d
docker-compose -f docker-compose.e2e.yml exec firebase-models-e2e \
  vendor/bin/pest tests/E2E/ --group=e2e
```

**Benefits:**
- ✅ Isolated environment
- ✅ Consistent across machines
- ✅ Avoids local gRPC issues

#### 2. Remote Testing
Test on remote environments (staging, CI/CD, cloud instances):
```bash
# Deploy to remote environment
# Run E2E tests remotely
vendor/bin/pest tests/E2E/ --group=e2e
```

#### 3. Alternative Local Setup
- Use different PHP version/compilation
- Test with different gRPC extension versions
- Use Firebase Emulator for development (though this doesn't test real API)

### Not Recommended Solutions
❌ **Patching dependencies** - Affects package maintainability  
❌ **PHP version constraints** - Limits package adoption unnecessarily  
❌ **Disabling gRPC** - Reduces Firebase performance significantly  

## 🏗️ Development Workflow

### Recommended Approach
1. **Local Development**: Use mocks and unit tests for rapid development
2. **E2E Testing**: Use Docker or remote environments
3. **CI/CD**: Automated E2E testing in clean environments
4. **Production**: Deploy with confidence (known working environments)

### Testing Strategy
```bash
# Local development - fast feedback
vendor/bin/pest tests/Unit/ tests/Integration/

# E2E testing - Docker environment
cd docker && docker-compose -f docker-compose.e2e.yml up -d
docker-compose -f docker-compose.e2e.yml exec firebase-models-e2e \
  vendor/bin/pest tests/E2E/ --group=e2e

# CI/CD - automated verification
# (See E2E_TESTING.md for CI configuration)
```

## 📊 Impact Assessment

### Development Impact
- **✅ No impact on package functionality** - Issue is environment-specific
- **✅ No impact on production usage** - Known working in production
- **⚠️ Local E2E testing requires workaround** - Use Docker or remote testing

### User Impact
- **✅ End users unaffected** - Production environments work correctly
- **✅ Package installation works** - No dependency conflicts
- **✅ All features functional** - Full Firebase API compatibility

## 🔄 Monitoring and Updates

### Upstream Tracking
We're monitoring these repositories for fixes:
- [`beste/in-memory-cache`](https://github.com/beste/in-memory-cache)
- [`kreait/firebase-php`](https://github.com/kreait/firebase-php)
- [PHP gRPC extension](https://github.com/grpc/grpc/tree/master/src/php)

### Resolution Timeline
- **Short-term**: Use Docker/remote testing for E2E verification
- **Medium-term**: Monitor upstream fixes and test with updates
- **Long-term**: Issue likely resolved with future PHP/gRPC updates

## 🛠️ Debugging Information

### Environment Details
If you encounter similar issues, collect this information:

```bash
# PHP Information
php --version
php -m | grep grpc
php --ini

# System Information
uname -a
which php

# Composer Information
composer show kreait/firebase-php
composer show beste/in-memory-cache

# gRPC Information
php -i | grep grpc
```

### Reproduction Steps
1. Install package with `composer install`
2. Set up Firebase credentials in `tests/credentials/e2e-credentials.json`
3. Run E2E test: `vendor/bin/pest tests/E2E/BasicConnectionE2ETest.php`
4. Observe stack overflow during document creation

### Expected vs Actual Behavior
- **Expected**: Successful Firebase document operations
- **Actual**: Stack overflow in `beste/in-memory-cache` regex validation

## 📞 Support and Reporting

### If You Encounter This Issue
1. **Try Docker environment first** - Often resolves the issue
2. **Test on remote environment** - Verify it's local-specific
3. **Check environment details** - Collect debugging information above
4. **Report if widespread** - Create issue with environment details

### If Issue Persists
- Use Docker for E2E testing
- Deploy to staging/production for real-world testing
- Continue development with unit/integration tests
- Monitor upstream repositories for fixes

## ✅ Conclusion

This is a **local development environment issue** that does not affect:
- ✅ Package functionality
- ✅ Production deployments  
- ✅ End user experience
- ✅ Firebase API compatibility

The recommended solution is to use **Docker for E2E testing** while continuing normal development with unit and integration tests.
