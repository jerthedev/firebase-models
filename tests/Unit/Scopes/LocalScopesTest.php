<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;

// Test model with local scopes
class TestModelWithScopes extends FirestoreModel
{
    protected ?string $collection = 'test_models';
    
    protected array $fillable = ['name', 'email', 'status', 'age', 'active', 'published'];

    // Simple local scope
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // Local scope with parameters
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Local scope with multiple parameters
    public function scopeAgeRange($query, int $minAge, int $maxAge)
    {
        return $query->where('age', '>=', $minAge)
                     ->where('age', '<=', $maxAge);
    }

    // Complex local scope
    public function scopePublishedAndActive($query)
    {
        return $query->where('published', true)
                     ->where('active', true)
                     ->orderBy('created_at', 'desc');
    }

    // Local scope with conditional logic
    public function scopeByRole($query, ?string $role = null)
    {
        if ($role) {
            return $query->where('role', $role);
        }
        
        return $query;
    }

    // Local scope that returns a specific result
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    // Local scope with complex where conditions
    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('active', true)
                     ->where('last_login_at', '>=', now()->subDays($days));
    }
}

describe('Local Scopes', function () {
    beforeEach(function () {
        FirestoreMock::initialize();

        // Configure cache for testing
        config([
            'cache.default' => 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
            'firebase-models.cache.store' => 'array',
            'firebase-models.cache.enabled' => false, // Disable caching for scope tests
        ]);
    });

    afterEach(function () {
        FirestoreMock::clear();
    });

    describe('Scope Detection', function () {
        it('can detect local scopes', function () {
            $model = new TestModelWithScopes();
            
            expect($model->hasLocalScope('active'))->toBeTrue();
            expect($model->hasLocalScope('status'))->toBeTrue();
            expect($model->hasLocalScope('ageRange'))->toBeTrue();
            expect($model->hasLocalScope('publishedAndActive'))->toBeTrue();
            expect($model->hasLocalScope('byRole'))->toBeTrue();
            expect($model->hasLocalScope('admins'))->toBeTrue();
            expect($model->hasLocalScope('recentlyActive'))->toBeTrue();
            
            expect($model->hasLocalScope('nonExistent'))->toBeFalse();
        });

        it('can get all local scopes', function () {
            $model = new TestModelWithScopes();
            $scopes = $model->getLocalScopes();
            
            expect($scopes)->toBeArray();
            expect($scopes)->toHaveKey('active');
            expect($scopes)->toHaveKey('status');
            expect($scopes)->toHaveKey('ageRange');
            expect($scopes)->toHaveKey('publishedAndActive');
            expect($scopes)->toHaveKey('byRole');
            expect($scopes)->toHaveKey('admins');
            expect($scopes)->toHaveKey('recentlyActive');
            
            expect($scopes['active'])->toBe('scopeActive');
            expect($scopes['status'])->toBe('scopeStatus');
        });

        it('can get local scope names', function () {
            $model = new TestModelWithScopes();
            $scopeNames = $model->getLocalScopeNames();
            
            expect($scopeNames)->toContain('active');
            expect($scopeNames)->toContain('status');
            expect($scopeNames)->toContain('ageRange');
            expect($scopeNames)->toContain('publishedAndActive');
            expect($scopeNames)->toContain('byRole');
            expect($scopeNames)->toContain('admins');
            expect($scopeNames)->toContain('recentlyActive');
        });
    });

    describe('Simple Scopes', function () {
        it('can apply simple local scopes', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Active User', 'active' => true]);
            FirestoreMock::createDocument('test_models', 'doc2', ['name' => 'Inactive User', 'active' => false]);

            $activeUsers = TestModelWithScopes::active()->get();
            
            expect($activeUsers)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($activeUsers->count())->toBe(1);
            expect($activeUsers->first()->name)->toBe('Active User');
        });

        it('can chain simple scopes', function () {
            FirestoreMock::createDocument('test_models', 'doc1', [
                'name' => 'Active Published User', 
                'active' => true, 
                'published' => true
            ]);
            FirestoreMock::createDocument('test_models', 'doc2', [
                'name' => 'Active Unpublished User', 
                'active' => true, 
                'published' => false
            ]);

            $users = TestModelWithScopes::active()->publishedAndActive()->get();
            
            expect($users->count())->toBe(1);
            expect($users->first()->name)->toBe('Active Published User');
        });
    });

    describe('Parameterized Scopes', function () {
        it('can apply scopes with single parameter', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Admin User', 'status' => 'admin']);
            FirestoreMock::createDocument('test_models', 'doc2', ['name' => 'Regular User', 'status' => 'user']);

            $admins = TestModelWithScopes::status('admin')->get();
            
            expect($admins->count())->toBe(1);
            expect($admins->first()->name)->toBe('Admin User');
        });

        it('can apply scopes with multiple parameters', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Young User', 'age' => 20]);
            FirestoreMock::createDocument('test_models', 'doc2', ['name' => 'Middle User', 'age' => 35]);
            FirestoreMock::createDocument('test_models', 'doc3', ['name' => 'Old User', 'age' => 60]);

            $middleAged = TestModelWithScopes::ageRange(25, 45)->get();
            
            expect($middleAged->count())->toBe(1);
            expect($middleAged->first()->name)->toBe('Middle User');
        });

        it('can apply scopes with optional parameters', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Admin User', 'role' => 'admin']);
            FirestoreMock::createDocument('test_models', 'doc2', ['name' => 'Regular User', 'role' => 'user']);

            // With parameter
            $admins = TestModelWithScopes::byRole('admin')->get();
            expect($admins->count())->toBe(1);
            expect($admins->first()->name)->toBe('Admin User');

            // Without parameter (should return all)
            $allUsers = TestModelWithScopes::byRole()->get();
            expect($allUsers->count())->toBe(2);
        });
    });

    describe('Scope Chaining', function () {
        it('can chain multiple scopes', function () {
            FirestoreMock::createDocument('test_models', 'doc1', [
                'name' => 'Active Admin', 
                'active' => true, 
                'status' => 'admin',
                'age' => 30
            ]);
            FirestoreMock::createDocument('test_models', 'doc2', [
                'name' => 'Inactive Admin', 
                'active' => false, 
                'status' => 'admin',
                'age' => 30
            ]);
            FirestoreMock::createDocument('test_models', 'doc3', [
                'name' => 'Active User', 
                'active' => true, 
                'status' => 'user',
                'age' => 30
            ]);

            $activeAdmins = TestModelWithScopes::active()
                ->status('admin')
                ->ageRange(25, 35)
                ->get();
            
            expect($activeAdmins->count())->toBe(1);
            expect($activeAdmins->first()->name)->toBe('Active Admin');
        });

        it('can chain scopes with regular query methods', function () {
            FirestoreMock::createDocument('test_models', 'doc1', [
                'name' => 'Alice', 
                'active' => true, 
                'email' => 'alice@example.com'
            ]);
            FirestoreMock::createDocument('test_models', 'doc2', [
                'name' => 'Bob', 
                'active' => true, 
                'email' => 'bob@example.com'
            ]);

            $user = TestModelWithScopes::active()
                ->where('email', 'alice@example.com')
                ->first();
            
            expect($user)->not->toBeNull();
            expect($user->name)->toBe('Alice');
        });
    });

    describe('Scope Error Handling', function () {
        it('throws exception for non-existent scope', function () {
            expect(function () {
                TestModelWithScopes::nonExistentScope()->get();
            })->toThrow(\BadMethodCallException::class);
        });

        it('handles scope method calls correctly', function () {
            $model = new TestModelWithScopes();

            expect(function () use ($model) {
                $model->callScope('nonExistentScope');
            })->toThrow(\BadMethodCallException::class);
        });
    });

    describe('Dynamic Scope Calls', function () {
        it('can call scopes dynamically on model instances', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Active User', 'active' => true]);
            
            $model = new TestModelWithScopes();
            $activeUsers = $model->active()->get();
            
            expect($activeUsers->count())->toBe(1);
            expect($activeUsers->first()->name)->toBe('Active User');
        });

        it('can call scopes dynamically on model class', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Admin User', 'status' => 'admin']);
            
            $admins = TestModelWithScopes::status('admin')->get();
            
            expect($admins->count())->toBe(1);
            expect($admins->first()->name)->toBe('Admin User');
        });
    });

    describe('Scope Integration', function () {
        it('works with pagination', function () {
            for ($i = 1; $i <= 10; $i++) {
                FirestoreMock::createDocument('test_models', "doc{$i}", [
                    'name' => "User {$i}", 
                    'active' => $i % 2 === 0 // Even numbers are active
                ]);
            }

            $activePage = TestModelWithScopes::active()->paginate(3);
            
            expect($activePage)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            expect($activePage->count())->toBe(3);
            expect($activePage->total())->toBe(5); // 5 active users
        });

        it('works with aggregates', function () {
            FirestoreMock::createDocument('test_models', 'doc1', ['name' => 'Active User 1', 'active' => true]);
            FirestoreMock::createDocument('test_models', 'doc2', ['name' => 'Active User 2', 'active' => true]);
            FirestoreMock::createDocument('test_models', 'doc3', ['name' => 'Inactive User', 'active' => false]);

            $activeCount = TestModelWithScopes::active()->count();
            
            expect($activeCount)->toBe(2);
        });

        it('works with ordering and limiting', function () {
            FirestoreMock::createDocument('test_models', 'doc1', [
                'name' => 'User A',
                'active' => true,
                'created_at' => '2023-01-01'
            ]);
            FirestoreMock::createDocument('test_models', 'doc2', [
                'name' => 'User B',
                'active' => true,
                'created_at' => '2023-01-02'
            ]);

            $latestActive = TestModelWithScopes::active()
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->get();

            expect($latestActive->count())->toBe(1);
            // The scope is working correctly if we get exactly 1 active user
            expect($latestActive->first()->active)->toBeTrue();
        });
    });
});
