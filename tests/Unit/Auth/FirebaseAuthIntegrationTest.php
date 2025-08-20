<?php

use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use Illuminate\Http\Request;
use Illuminate\Contracts\Hashing\Hasher;
use Mockery as m;

describe('Firebase Auth Integration', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();

        // Mock Firebase Auth
        $this->firebaseAuth = m::mock(\Kreait\Firebase\Contract\Auth::class);

        // Mock Hasher
        $this->hasher = m::mock(Hasher::class);

        // Create provider instance
        $this->provider = new FirebaseUserProvider(
            $this->firebaseAuth,
            User::class,
            $this->hasher
        );

        // Mock Request
        $this->request = m::mock(Request::class);

        // Create guard instance
        $this->guard = new FirebaseGuard(
            $this->provider,
            $this->request,
            $this->firebaseAuth
        );
    });

    afterEach(function () {
        m::close();
    });

    describe('Complete Authentication Flow', function () {
        it('can authenticate user with valid Firebase token', function () {
            // Mock Firebase token and claims
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('get')->with('email')->andReturn('test@example.com');
            $mockClaims->shouldReceive('get')->with('email_verified', false)->andReturn(true);
            $mockClaims->shouldReceive('get')->with('name')->andReturn('Test User');
            $mockClaims->shouldReceive('get')->with('picture')->andReturn(null);
            $mockClaims->shouldReceive('get')->with('phone_number')->andReturn(null);
            $mockClaims->shouldReceive('all')->andReturn([
                'sub' => 'test-uid-123',
                'email' => 'test@example.com',
                'email_verified' => true,
                'name' => 'Test User',
                'admin' => true,
                'role' => 'user'
            ]);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            // Mock Firebase Auth
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            // Mock Firebase UserRecord
            $mockUserRecord = m::mock(\Kreait\Firebase\Auth\UserRecord::class);
            $mockUserRecord->uid = 'test-uid-123';
            $mockUserRecord->email = 'test@example.com';
            $mockUserRecord->displayName = 'Test User';
            $mockUserRecord->emailVerified = true;
            $mockUserRecord->photoUrl = null;
            $mockUserRecord->phoneNumber = null;
            $mockUserRecord->customClaims = [];
            $mockUserRecord->providerData = [];
            $mockUserRecord->metadata = m::mock();
            $mockUserRecord->metadata->lastSignInTime = null;
            $mockUserRecord->metadata->creationTime = null;

            $this->firebaseAuth->shouldReceive('getUser')
                ->with('test-uid-123')
                ->andReturn($mockUserRecord);

            // Mock request to return the token
            $this->request->shouldReceive('query')->with('token')->andReturn('valid-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            // Get authenticated user
            $user = $this->guard->user();

            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe('test-uid-123');
            expect($user->email)->toBe('test@example.com');
            expect($user->name)->toBe('Test User');

            // Check guard state
            expect($this->guard->check())->toBeTrue();
            expect($this->guard->guest())->toBeFalse();
            expect($this->guard->id())->toBe('test-uid-123');
        });

        it('can attempt authentication with credentials', function () {
            // Create test user
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'auth@example.com',
                'displayName' => 'Auth User'
            ]);
            
            // Create test token
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // Attempt authentication
            $result = $this->guard->attempt(['token' => $token]);
            
            expect($result)->toBeTrue();
            expect($this->guard->check())->toBeTrue();
            
            $user = $this->guard->user();
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('auth@example.com');
        });

        it('fails authentication with invalid token', function () {
            // Mock request with invalid token
            $this->request->shouldReceive('query')->with('token')->andReturn('invalid-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user)->toBeNull();
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->guest())->toBeTrue();
        });

        it('can validate credentials', function () {
            // Create test user and token
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // Validate credentials
            $result = $this->guard->validate(['token' => $token]);
            
            expect($result)->toBeTrue();
        });

        it('fails validation with invalid credentials', function () {
            $result = $this->guard->validate(['token' => 'invalid-token']);
            
            expect($result)->toBeFalse();
        });

        it('can logout user', function () {
            // Create and authenticate user
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->guard->attempt(['token' => $token]);
            expect($this->guard->check())->toBeTrue();
            
            // Logout
            $this->guard->logout();
            
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->user())->toBeNull();
        });
    });

    describe('User Provider Integration', function () {
        it('can retrieve user by ID', function () {
            // Create test user
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'provider@example.com',
                'displayName' => 'Provider User'
            ]);
            
            $user = $this->provider->retrieveById($userData['uid']);
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('provider@example.com');
            expect($user->name)->toBe('Provider User');
        });

        it('returns null for non-existent user', function () {
            $user = $this->provider->retrieveById('non-existent-uid');
            
            expect($user)->toBeNull();
        });

        it('can retrieve user by credentials', function () {
            // Create test user and token
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'creds@example.com'
            ]);
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $user = $this->provider->retrieveByCredentials(['token' => $token]);
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('creds@example.com');
        });

        it('can validate user credentials', function () {
            // Create test user and token
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // Get user
            $user = $this->provider->retrieveById($userData['uid']);
            
            // Validate credentials
            $result = $this->provider->validateCredentials($user, ['token' => $token]);
            
            expect($result)->toBeTrue();
        });

        it('fails validation with mismatched credentials', function () {
            // Create two users
            $userData1 = FirebaseAuthMock::createTestUser();
            $userData2 = FirebaseAuthMock::createTestUser();
            
            // Get user 1 but create token for user 2
            $user = $this->provider->retrieveById($userData1['uid']);
            $token = FirebaseAuthMock::createTestToken($userData2['uid']);
            
            $result = $this->provider->validateCredentials($user, ['token' => $token]);
            
            expect($result)->toBeFalse();
        });
    });

    describe('Token Sources', function () {
        it('can get token from Authorization header', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn("Bearer {$token}");
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
        });

        it('can get token from query parameter', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->request->shouldReceive('query')->with('token')->andReturn($token);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
        });

        it('can get token from input parameter', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn($token);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
        });

        it('can get token from cookie', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn($token);
            
            $user = $this->guard->user();
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe($userData['uid']);
        });
    });

    describe('Custom Claims', function () {
        it('can access custom claims from token', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid'], [
                'admin' => true,
                'role' => 'manager',
                'permissions' => ['read', 'write', 'delete']
            ]);
            
            $this->request->shouldReceive('query')->with('token')->andReturn($token);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user)->toBeInstanceOf(User::class);
            
            // Check if user has Firebase token with custom claims
            $firebaseToken = $user->getFirebaseToken();
            expect($firebaseToken)->not->toBeNull();
            
            // The custom claims should be available through the token
            $claims = $firebaseToken->claims()->all();
            expect($claims['admin'])->toBeTrue();
            expect($claims['role'])->toBe('manager');
            expect($claims['permissions'])->toBe(['read', 'write', 'delete']);
        });
    });
});
