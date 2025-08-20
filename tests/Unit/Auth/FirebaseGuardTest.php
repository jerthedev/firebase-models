<?php

use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\User;
use Illuminate\Http\Request;
use Mockery as m;

describe('FirebaseGuard', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        
        // Mock Firebase Auth
        $this->firebaseAuth = m::mock(\Kreait\Firebase\Contract\Auth::class);
        
        // Mock User Provider
        $this->userProvider = m::mock(FirebaseUserProvider::class);
        
        // Mock Request
        $this->request = m::mock(Request::class);
        
        // Create guard instance
        $this->guard = new FirebaseGuard(
            $this->userProvider,
            $this->request,
            $this->firebaseAuth
        );
    });

    afterEach(function () {
        m::close();
    });



    describe('Token Retrieval', function () {
        it('can get token from Authorization header', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer test-token-123');
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            $token = $this->guard->getTokenForRequest();

            expect($token)->toBe('test-token-123');
        });

        it('can get token from query parameter', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn('query-token-123');

            $token = $this->guard->getTokenForRequest();

            expect($token)->toBe('query-token-123');
        });

        it('can get token from input parameter', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn('input-token-123');

            $token = $this->guard->getTokenForRequest();

            expect($token)->toBe('input-token-123');
        });

        it('can get token from cookie', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn('cookie-token-123');

            $token = $this->guard->getTokenForRequest();

            expect($token)->toBe('cookie-token-123');
        });

        it('returns null when no token found', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            $token = $this->guard->getTokenForRequest();

            expect($token)->toBeNull();
        });
    });

    describe('User Authentication', function () {
        it('can authenticate user with valid token', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            $mockUser = m::mock(User::class);
            $mockUser->shouldReceive('setFirebaseToken')->with($mockToken);

            $this->request->shouldReceive('query')->with('token')->andReturn('valid-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            $this->firebaseAuth->shouldReceive('verifyIdToken')->with('valid-token')->andReturn($mockToken);
            $this->userProvider->shouldReceive('retrieveById')->with('test-uid-123')->andReturn($mockUser);

            $user = $this->guard->user();

            expect($user)->toBe($mockUser);
        });

        it('returns null for invalid token', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn('invalid-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Exception('Invalid token'));

            $user = $this->guard->user();

            expect($user)->toBeNull();
        });

        it('returns null when no token provided', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            $user = $this->guard->user();

            expect($user)->toBeNull();
        });
    });

    describe('Validation', function () {
        it('validates credentials with valid token', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            $result = $this->guard->validate(['token' => 'valid-token']);

            expect($result)->toBeTrue();
        });

        it('fails validation with invalid token', function () {
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Exception('Invalid token'));

            $result = $this->guard->validate(['token' => 'invalid-token']);

            expect($result)->toBeFalse();
        });

        it('fails validation with no token', function () {
            $result = $this->guard->validate([]);

            expect($result)->toBeFalse();
        });
    });

    describe('Attempt Authentication', function () {
        it('can attempt authentication with valid credentials', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            // Use a real User instance instead of a mock to ensure instanceof works
            $mockUser = new User(['uid' => 'test-uid-123']);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            $this->userProvider->shouldReceive('retrieveById')
                ->with('test-uid-123')
                ->andReturn($mockUser);

            // If retrieveById returns null, the guard will call retrieveByCredentials
            $this->userProvider->shouldReceive('retrieveByCredentials')
                ->with(['token' => 'valid-token'])
                ->andReturn(null);

            $result = $this->guard->attempt(['token' => 'valid-token']);

            expect($result)->toBeTrue();
            // After successful attempt, the user should be set in the guard
            expect($this->guard->hasUser())->toBeTrue();
            expect($this->guard->user())->toBe($mockUser);
        });

        it('fails attempt with invalid credentials', function () {
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Exception('Invalid token'));

            $result = $this->guard->attempt(['token' => 'invalid-token']);

            expect($result)->toBeFalse();
            expect($this->guard->user())->toBeNull();
        });
    });

    describe('Guard State', function () {
        it('can check if user is authenticated', function () {
            // No user set - need to set up request expectations for check() call
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->guest())->toBeTrue();

            // Set a user
            $mockUser = m::mock(User::class);
            $this->guard->setUser($mockUser);

            expect($this->guard->check())->toBeTrue();
            expect($this->guard->guest())->toBeFalse();
        });

        it('can get user ID', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            expect($this->guard->id())->toBeNull();

            $mockUser = m::mock(User::class);
            $mockUser->shouldReceive('getAuthIdentifier')->andReturn('test-uid-123');
            $this->guard->setUser($mockUser);

            expect($this->guard->id())->toBe('test-uid-123');
        });

        it('can logout user', function () {
            $mockUser = m::mock(User::class);
            $this->guard->setUser($mockUser);

            expect($this->guard->check())->toBeTrue();

            $this->guard->logout();

            // Set up expectations for the check() and user() calls after logout
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->user())->toBeNull();
        });

        it('can check if guard has user', function () {
            expect($this->guard->hasUser())->toBeFalse();

            $mockUser = m::mock(User::class);
            $this->guard->setUser($mockUser);

            expect($this->guard->hasUser())->toBeTrue();
        });
    });

    describe('Request Management', function () {
        it('can set and get request', function () {
            $newRequest = m::mock(Request::class);
            
            $result = $this->guard->setRequest($newRequest);
            
            expect($result)->toBe($this->guard);
            expect($this->guard->getRequest())->toBe($newRequest);
        });

        it('can get Firebase Auth instance', function () {
            expect($this->guard->getFirebaseAuth())->toBe($this->firebaseAuth);
        });
    });
});
