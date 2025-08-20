<?php

use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\User;
use Illuminate\Contracts\Hashing\Hasher;
use Mockery as m;

describe('FirebaseUserProvider', function () {
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
    });

    afterEach(function () {
        m::close();
    });

    describe('User Retrieval', function () {
        it('can retrieve user by ID', function () {
            $mockFirebaseUser = m::mock(\Kreait\Firebase\Auth\UserRecord::class);
            $mockFirebaseUser->uid = 'test-uid-123';
            $mockFirebaseUser->email = 'test@example.com';
            $mockFirebaseUser->displayName = 'Test User';
            $mockFirebaseUser->emailVerified = true;
            $mockFirebaseUser->photoUrl = null;
            $mockFirebaseUser->phoneNumber = null;
            $mockFirebaseUser->customClaims = [];
            $mockFirebaseUser->providerData = [];
            $mockFirebaseUser->metadata = m::mock();
            $mockFirebaseUser->metadata->lastSignInTime = null;
            $mockFirebaseUser->metadata->creationTime = null;

            $this->firebaseAuth->shouldReceive('getUser')
                ->with('test-uid-123')
                ->andReturn($mockFirebaseUser);

            $user = $this->provider->retrieveById('test-uid-123');

            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe('test-uid-123');
            expect($user->email)->toBe('test@example.com');
            expect($user->name)->toBe('Test User');
        });

        it('returns null for non-existent user', function () {
            $this->firebaseAuth->shouldReceive('getUser')
                ->with('non-existent-uid')
                ->andThrow(new \Kreait\Firebase\Exception\Auth\UserNotFound());

            $user = $this->provider->retrieveById('non-existent-uid');

            expect($user)->toBeNull();
        });

        it('can retrieve user by token', function () {
            // For Firebase, retrieveByToken just calls retrieveById
            $mockFirebaseUser = m::mock(\Kreait\Firebase\Auth\UserRecord::class);
            $mockFirebaseUser->uid = 'test-uid-123';
            $mockFirebaseUser->email = 'test@example.com';
            $mockFirebaseUser->displayName = 'Test User';
            $mockFirebaseUser->emailVerified = true;
            $mockFirebaseUser->photoUrl = null;
            $mockFirebaseUser->phoneNumber = null;
            $mockFirebaseUser->customClaims = [];
            $mockFirebaseUser->providerData = [];
            $mockFirebaseUser->metadata = m::mock();
            $mockFirebaseUser->metadata->lastSignInTime = null;
            $mockFirebaseUser->metadata->creationTime = null;

            $this->firebaseAuth->shouldReceive('getUser')
                ->with('test-uid-123')
                ->andReturn($mockFirebaseUser);

            $user = $this->provider->retrieveByToken('test-uid-123', 'some-token');

            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe('test-uid-123');
        });
    });

    describe('Credential-based Retrieval', function () {
        it('can retrieve user by credentials with valid token', function () {
            $mockToken = m::mock(\Kreait\Firebase\JWT\IdToken::class);
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
                'iss' => 'firebase',
                'aud' => 'project-id',
                'iat' => time(),
                'exp' => time() + 3600,
            ]);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            // Mock that user doesn't exist yet
            $this->firebaseAuth->shouldReceive('getUser')
                ->with('test-uid-123')
                ->andThrow(new \Kreait\Firebase\Exception\Auth\UserNotFound());

            $user = $this->provider->retrieveByCredentials(['token' => 'valid-token']);

            expect($user)->toBeInstanceOf(User::class);
            expect($user->uid)->toBe('test-uid-123');
            expect($user->email)->toBe('test@example.com');
            expect($user->name)->toBe('Test User');
        });

        it('returns null for invalid token', function () {
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Kreait\Firebase\Exception\Auth\InvalidIdToken());

            $user = $this->provider->retrieveByCredentials(['token' => 'invalid-token']);

            expect($user)->toBeNull();
        });

        it('returns null when no token provided', function () {
            $user = $this->provider->retrieveByCredentials([]);

            expect($user)->toBeNull();
        });
    });

    describe('Credential Validation', function () {
        it('validates credentials with matching token UID', function () {
            $mockUser = m::mock(User::class);
            $mockUser->shouldReceive('getAuthIdentifier')->andReturn('test-uid-123');

            $mockToken = m::mock(\Kreait\Firebase\JWT\IdToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            $result = $this->provider->validateCredentials($mockUser, ['token' => 'valid-token']);

            expect($result)->toBeTrue();
        });

        it('fails validation with mismatched token UID', function () {
            $mockUser = m::mock(User::class);
            $mockUser->shouldReceive('getAuthIdentifier')->andReturn('test-uid-123');

            $mockToken = m::mock(\Kreait\Firebase\JWT\IdToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('different-uid');
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            $result = $this->provider->validateCredentials($mockUser, ['token' => 'valid-token']);

            expect($result)->toBeFalse();
        });

        it('fails validation with invalid token', function () {
            $mockUser = m::mock(User::class);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Kreait\Firebase\Exception\Auth\InvalidIdToken());

            $result = $this->provider->validateCredentials($mockUser, ['token' => 'invalid-token']);

            expect($result)->toBeFalse();
        });

        it('fails validation with no token', function () {
            $mockUser = m::mock(User::class);

            $result = $this->provider->validateCredentials($mockUser, []);

            expect($result)->toBeFalse();
        });
    });

    describe('Model Management', function () {
        it('can create model instance', function () {
            $user = $this->provider->createModel();

            expect($user)->toBeInstanceOf(User::class);
        });

        it('can get and set model class', function () {
            expect($this->provider->getModel())->toBe(User::class);

            $result = $this->provider->setModel('App\\Models\\CustomUser');

            expect($result)->toBe($this->provider);
            expect($this->provider->getModel())->toBe('App\\Models\\CustomUser');
        });

        it('can get Firebase Auth instance', function () {
            expect($this->provider->getFirebaseAuth())->toBe($this->firebaseAuth);
        });

        it('can get hasher instance', function () {
            expect($this->provider->getHasher())->toBe($this->hasher);
        });
    });

    describe('Remember Token Methods', function () {
        it('handles remember token methods gracefully', function () {
            $mockUser = m::mock(User::class);

            // These should not throw exceptions
            $this->provider->updateRememberToken($mockUser, 'some-token');
            
            // Test passes if no exception is thrown
            expect(true)->toBeTrue();
        });
    });

    describe('Password Rehashing', function () {
        it('handles password rehashing gracefully', function () {
            $mockUser = m::mock(User::class);

            // This should not throw exceptions (Firebase handles passwords)
            $this->provider->rehashPasswordIfRequired($mockUser, ['token' => 'some-token']);
            
            // Test passes if no exception is thrown
            expect(true)->toBeTrue();
        });
    });
});
