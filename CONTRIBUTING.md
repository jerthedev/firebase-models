# Contributing to JTD Firebase Models

Thank you for your interest in contributing to JTD Firebase Models! This guide will help you get started with contributing to this Laravel package for Firebase integration.

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 12.x knowledge
- Basic understanding of Firebase/Firestore

### Development Setup

```bash
# Clone the repository
git clone https://github.com/jerthedev/firebase-models.git
cd firebase-models

# Install dependencies
composer install

# Run tests to ensure everything works
composer test
```

## 🧪 Testing Guidelines

This project uses a **restructured test architecture** with specialized test suites for optimal performance and maintainability.

### Test Structure

```
tests/
├── Unit/Restructured/          # Optimized unit tests (UnitTestSuite)
├── Feature/Restructured/       # End-to-end tests (FeatureTestSuite)
├── Integration/                # Firebase integration tests (IntegrationTestSuite)
├── Performance/                # Performance benchmarks (PerformanceTestSuite)
├── Unit/ (Legacy)              # Original unit tests (being migrated)
├── Feature/ (Legacy)           # Original feature tests (being migrated)
└── TestSuites/                 # Base test suite classes
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit --testsuite=Unit-Restructured
vendor/bin/phpunit --testsuite=Feature-Restructured
vendor/bin/phpunit --testsuite=Integration
vendor/bin/phpunit --testsuite=Performance

# Run with coverage
composer test-coverage
```

### Writing Tests

#### For New Features

**Always use the restructured test suites for new features:**

```php
<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;

class MyNewFeatureTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    /** @test */
    public function it_does_something_awesome()
    {
        // Your test code here
        expect(true)->toBeTrue();
    }
}
```

#### Test Suite Selection Guide

- **UnitTestSuite**: For isolated unit tests with minimal dependencies
- **FeatureTestSuite**: For comprehensive end-to-end feature testing
- **IntegrationTestSuite**: For tests requiring real Firebase connections
- **PerformanceTestSuite**: For performance benchmarks and memory profiling

### Test Requirements Configuration

Each test suite supports configuration for optimal resource usage:

```php
$this->setTestRequirements([
    'document_count' => 100,        // Number of test documents to create
    'memory_constraint' => true,    // Enable memory optimization
    'needs_full_mockery' => false,  // Use selective mocking for performance
]);
```

## 📝 Code Style

This project follows Laravel coding standards with additional Firebase-specific conventions.

### Running Code Quality Checks

```bash
# Format code
composer format

# Run static analysis
composer analyse

# Check code style
vendor/bin/pint --test
```

### Coding Standards

- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add proper PHPDoc comments for public methods
- Use type hints wherever possible
- Follow Laravel naming conventions

## 🔄 Migration Guidelines

### Migrating Legacy Tests

If you need to update existing legacy tests, follow this pattern:

1. **Update namespace and imports:**
```php
<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
```

2. **Convert describe/it to class methods:**
```php
// Old (Pest describe/it)
describe('Feature', function () {
    it('does something', function () {
        expect(true)->toBeTrue();
    });
});

// New (PHPUnit class methods)
class FeatureTest extends UnitTestSuite
{
    /** @test */
    public function it_does_something()
    {
        expect(true)->toBeTrue();
    }
}
```

3. **Update setup/teardown:**
```php
protected function setUp(): void
{
    $this->setTestRequirements([...]);
    parent::setUp();
    // Your setup code
}

protected function tearDown(): void
{
    // Your cleanup code
    parent::tearDown();
}
```

## 🐛 Bug Reports

When reporting bugs, please include:

1. **Environment details** (PHP version, Laravel version, package version)
2. **Steps to reproduce** the issue
3. **Expected behavior** vs **actual behavior**
4. **Code samples** demonstrating the issue
5. **Test case** that reproduces the bug (if possible)

## ✨ Feature Requests

For new features:

1. **Check existing issues** to avoid duplicates
2. **Describe the use case** and why it's needed
3. **Provide examples** of how the feature would be used
4. **Consider backward compatibility** implications

## 📋 Pull Request Process

1. **Fork the repository** and create a feature branch
2. **Write tests** for your changes using the appropriate test suite
3. **Ensure all tests pass** (`composer test`)
4. **Run code quality checks** (`composer format && composer analyse`)
5. **Update documentation** if needed
6. **Submit a pull request** with a clear description

### Pull Request Checklist

- [ ] Tests added/updated for new functionality
- [ ] All tests pass
- [ ] Code follows project standards
- [ ] Documentation updated (if applicable)
- [ ] No breaking changes (or clearly documented)
- [ ] Performance impact considered

## 🏗️ Architecture Guidelines

### Firebase Integration

- Use the `FirestoreMock` for testing
- Implement proper error handling for Firebase operations
- Follow the existing patterns for model relationships
- Consider caching implications for new features

### Performance Considerations

- Use appropriate test suites for performance testing
- Consider memory usage in large dataset operations
- Implement efficient querying patterns
- Profile performance-critical code paths

## 📞 Getting Help

- **Documentation**: Check the [docs/](docs/) directory
- **Issues**: Search existing GitHub issues
- **Discussions**: Use GitHub Discussions for questions
- **Testing**: Refer to [docs/11-testing.md](docs/11-testing.md)

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to JTD Firebase Models! 🔥
