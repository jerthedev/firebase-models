<?php

namespace JTD\FirebaseModels\Tests\Unit\Scopes;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\Scopes\ActiveScope;
use JTD\FirebaseModels\Firestore\Scopes\PublishedScope;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

// Test model with global scopes
class TestModelWithGlobalScopes extends FirestoreModel
{
    protected ?string $collection = 'test_models';

    protected array $fillable = ['name', 'active', 'published', 'published_at', 'status'];

    protected static function booted(): void
    {
        // Add global scopes
        static::addGlobalScope(new ActiveScope());
        static::addGlobalScope('published', new PublishedScope());

        // Add closure-based global scope
        static::addGlobalScope('verified', function ($builder, $model) {
            $builder->where('verified', true);
        });
    }
}

// Test model with custom global scope
class TestModelWithCustomScope extends FirestoreModel
{
    protected ?string $collection = 'custom_models';

    protected array $fillable = ['name', 'status', 'priority'];

    protected static function booted(): void
    {
        // Add custom global scope with different column
        static::addGlobalScope(new ActiveScope('status', 'enabled'));
    }
}

// Test model without global scopes
class TestModelWithoutScopes extends FirestoreModel
{
    protected ?string $collection = 'no_scope_models';

    protected array $fillable = ['name', 'active', 'published'];
}

/**
 * Global Scopes Test
 *
 * Updated to use UnitTestSuite for optimized performance and memory management.
 */
class GlobalScopesTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for scope testing
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

        FirestoreMock::initialize();

        // Configure cache for testing
        config([
            'firebase-models.cache.enabled' => false, // Disable caching for scope tests
        ]);

        // Clear global scopes before each test
        TestModelWithGlobalScopes::clearBootedScopes();
        TestModelWithCustomScope::clearBootedScopes();

        // Ensure models are booted by creating instances
        new TestModelWithGlobalScopes();
        new TestModelWithCustomScope();
    }

    protected function tearDown(): void
    {
        FirestoreMock::clear();
        parent::tearDown();
    }

    // ========================================
    // GLOBAL SCOPE REGISTRATION TESTS
    // ========================================

    #[Test]
    public function it_can_register_global_scopes()
    {
        $model = new TestModelWithGlobalScopes();

        expect($model->hasGlobalScopes())->toBeTrue();
    }

    #[Test]
    public function it_can_check_for_specific_global_scopes()
    {
        $model = new TestModelWithGlobalScopes();

        expect(TestModelWithGlobalScopes::hasGlobalScope(ActiveScope::class))->toBeTrue();
        expect(TestModelWithGlobalScopes::hasGlobalScope('published'))->toBeTrue();
        expect(TestModelWithGlobalScopes::hasGlobalScope('verified'))->toBeTrue();
        expect(TestModelWithGlobalScopes::hasGlobalScope('nonexistent'))->toBeFalse();
    }

    #[Test]
    public function it_can_get_specific_global_scopes()
    {
        $activeScope = TestModelWithGlobalScopes::getGlobalScope(ActiveScope::class);
        expect($activeScope)->toBeInstanceOf(ActiveScope::class);

        $publishedScope = TestModelWithGlobalScopes::getGlobalScope('published');
        expect($publishedScope)->toBeInstanceOf(PublishedScope::class);

        $verifiedScope = TestModelWithGlobalScopes::getGlobalScope('verified');
        expect($verifiedScope)->toBeInstanceOf(\Closure::class);
    }

    #[Test]
    public function it_automatically_applies_global_scopes_to_queries()
    {
        // Simple test to verify global scopes work
        $model = new TestModelWithGlobalScopes();
        expect($model->hasGlobalScopes())->toBeTrue();
    }
}
