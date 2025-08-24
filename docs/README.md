# JTD Firebase Models Documentation

Welcome to the comprehensive documentation for JTD Firebase Models - a Laravel package that provides Eloquent-like models and Laravel Auth integration for Firebase.

## 📚 Core Documentation

### Getting Started
- **[01. Overview](01-overview.md)** - What is JTD Firebase Models and key features
- **[02. Installation](02-installation.md)** - Complete setup and installation guide
- **[03. Configuration](03-configuration.md)** - Detailed configuration options and environment setup

### Core Features
- **[04. Models](04-models.md)** - FirestoreModel usage, attributes, casts, and events
- **[05. Query Builder](05-query-builder.md)** - Advanced querying, filtering, and pagination
- **[06. Events](06-events.md)** - Model events, observers, and event handling
- **[07. Facades](07-facades.md)** - FirestoreDB facade and direct Firestore operations

### Authentication & Security
- **[08. Authentication](08-authentication.md)** - Firebase Auth integration with Laravel
- **[09. Caching](09-caching.md)** - Intelligent caching system for performance optimization

### Advanced Features
- **[10. Sync Mode](10-sync-mode.md)** - Local database mirroring and synchronization
- **[11. Testing](11-testing.md)** - Testing with FirestoreMock and test utilities
- **[12. Transactions](12-transactions.md)** - Firestore transactions and batch operations

### Reference
- **[13. API Reference](13-api-reference.md)** - Complete API documentation and quick reference

## 🚀 Examples

Practical code examples for common use cases:

- **[Basic CRUD Operations](Examples/basic-crud.md)** - Create, read, update, delete operations
- **[Advanced Querying](Examples/advanced-querying.md)** - Complex queries, pagination, and aggregations
- **[Authentication Examples](Examples/authentication-examples.md)** - Login, registration, and user management
- **[Caching Examples](Examples/caching-examples.md)** - Performance optimization with caching
- **[Testing Examples](Examples/testing-examples.md)** - Comprehensive testing strategies

## 📋 Planning & Development

Project planning, roadmaps, and development documentation:

- **[Planning/](Planning/)** - Sprint plans, EPICs, and project roadmap
  - [Sprint 1 (Complete)](Planning/Sprint1-Done.md) - Foundation and FirestoreModel MVP
  - [Sprint 2 (Complete)](Planning/Sprint2-Done.md) - Advanced features and Firebase Auth
  - [Sprint 3 (Complete)](Planning/Sprint3-Done.md) - Relationships and soft deletes
  - [Sprint 4](Planning/Sprint4.md) - E2E testing and production readiness
  - [EPICs](Planning/EPICS.md) - Major feature epics and requirements
  - [Project Overview](Planning/PROJECT_OVERVIEW.md) - Package goals and vision
  - [Eloquent Compatibility](Planning/ELOQUENT_COMPATIBILITY.md) - Laravel feature mapping
  - [Laravel Compatibility](Planning/LARAVEL_COMPATIBILITY.md) - Framework integration details

## 🔧 Technical Reference

Technical setup guides and troubleshooting:

- **[Technical/](Technical/)** - Advanced setup and troubleshooting
  - [E2E Testing Setup](Technical/E2E_TESTING.md) - End-to-end testing configuration
  - [Firebase E2E Setup](Technical/FIREBASE_E2E_SETUP.md) - Firebase project setup for testing
  - [gRPC Issues](Technical/GRPC_ISSUES.md) - Common gRPC problems and solutions
  - [Manual Index Setup](Technical/MANUAL_INDEX_SETUP.md) - Firestore index configuration
  - [Spark-Friendly Rules](Technical/SPARK_FRIENDLY_RULES.md) - Firebase Spark plan optimization
  - [Conflict Resolution](Technical/conflict-resolution.md) - Handling data conflicts

## 📖 Additional Resources

### Detailed References
- **[Reference/](Reference/)** - Detailed technical references
  - [Auth API Reference](Reference/AUTH.md) - Complete authentication API
  - [Auth How-To Guide](Reference/AUTH_HOWTO.md) - Practical authentication scenarios
  - [Auth Setup Guide](Reference/AUTH_SETUP.md) - Detailed authentication configuration

### Command Line Tools
- **[Artisan Commands](artisan-commands/)** - Custom Artisan commands and usage

### Testing Resources
- **[Testing/](testing/)** - Advanced testing documentation
  - [Mock System Architecture](testing/mock-system-architecture.md) - Testing infrastructure
  - [Test Organization](testing/test-organization-restructure.md) - Test suite structure

## 🎯 Quick Navigation

### By Use Case

**🚀 Getting Started**
1. [Overview](01-overview.md) → [Installation](02-installation.md) → [Configuration](03-configuration.md)
2. [Basic CRUD Examples](Examples/basic-crud.md)

**🔐 Authentication**
1. [Authentication Guide](08-authentication.md) → [Auth Examples](Examples/authentication-examples.md)
2. [Auth Reference](Reference/AUTH.md)

**⚡ Performance**
1. [Caching Guide](09-caching.md) → [Caching Examples](Examples/caching-examples.md)
2. [Query Optimization](Examples/advanced-querying.md)

**🧪 Testing**
1. [Testing Guide](11-testing.md) → [Testing Examples](Examples/testing-examples.md)
2. [E2E Testing Setup](Technical/E2E_TESTING.md)

**🔄 Advanced Features**
1. [Sync Mode](10-sync-mode.md) → [Transactions](12-transactions.md)
2. [Query Builder](05-query-builder.md) → [Advanced Querying](Examples/advanced-querying.md)

### By Experience Level

**👶 Beginner**
- Start with [Overview](01-overview.md) and [Installation](02-installation.md)
- Follow [Basic CRUD Examples](Examples/basic-crud.md)
- Learn [Models](04-models.md) and [Query Builder](05-query-builder.md)

**🧑‍💻 Intermediate**
- Explore [Authentication](08-authentication.md) and [Caching](09-caching.md)
- Try [Advanced Querying Examples](Examples/advanced-querying.md)
- Set up [Testing](11-testing.md)

**🚀 Advanced**
- Implement [Sync Mode](10-sync-mode.md)
- Use [Transactions](12-transactions.md)
- Contribute using [Planning](Planning/) documentation

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/jerthedev/firebase-models/issues)
- **Discussions**: [GitHub Discussions](https://github.com/jerthedev/firebase-models/discussions)
- **Documentation**: This documentation site

## 🤝 Contributing

See the main [CONTRIBUTING.md](../CONTRIBUTING.md) for contribution guidelines and development setup instructions.
