<?php

use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use Illuminate\Http\Request;
use Illuminate\Contracts\Hashing\Hasher;
use Mockery as m;

describe('Firebase Auth Error Handling', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        
        // Mock Firebase Auth for error scenarios
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

    describe('Token Verification Errors', function () {
        it('handles expired tokens gracefully', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn('expired-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('expired-token')
                ->andThrow(new \Exception('Token expired'));
            
            $user = $this->guard->user();
            
            expect($user)->toBeNull();
            expect($this->guard->check())->toBeFalse();
        });

        it('handles malformed tokens gracefully', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn('malformed.token.here');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('malformed.token.here')
                ->andThrow(new \Exception('Malformed token'));
            
            $user = $this->guard->user();
            
            expect($user)->toBeNull();
        });

        it('handles revoked tokens gracefully', function () {
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('revoked-token')
                ->andThrow(new \Exception('Token revoked'));
            
            $result = $this->guard->validate(['token' => 'revoked-token']);
            
            expect($result)->toBeFalse();
        });

        it('handles network errors during token verification', function () {
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('network-error-token')
                ->andThrow(new \Exception('Network error'));
            
            $result = $this->guard->attempt(['token' => 'network-error-token']);
            
            expect($result)->toBeFalse();
        });
    });

    describe('User Retrieval Errors', function () {
        it('handles user not found in Firebase Auth', function () {
            $this->firebaseAuth->shouldReceive('getUser')
                ->with('non-existent-uid')
                ->andThrow(new \Exception('User not found'));
            
            $user = $this->provider->retrieveById('non-existent-uid');
            
            expect($user)->toBeNull();
        });

        it('handles Firebase Auth service unavailable', function () {
            $this->firebaseAuth->shouldReceive('getUser')
                ->with('service-down-uid')
                ->andThrow(new \Exception('Service unavailable'));
            
            $user = $this->provider->retrieveById('service-down-uid');
            
            expect($user)->toBeNull();
        });

        it('handles invalid UID format', function () {
            $this->firebaseAuth->shouldReceive('getUser')
                ->with('invalid-uid-format')
                ->andThrow(new \Exception('Invalid UID format'));
            
            $user = $this->provider->retrieveById('invalid-uid-format');
            
            expect($user)->toBeNull();
        });
    });

    describe('Token Claims Errors', function () {
        it('handles missing required claims', function () {
            // Mock token with missing 'sub' claim
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andReturn(null);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('missing-sub-token')
                ->andReturn($mockToken);
            
            $this->provider->shouldReceive('retrieveById')
                ->with(null)
                ->andReturn(null);
            
            $result = $this->guard->attempt(['token' => 'missing-sub-token']);
            
            expect($result)->toBeFalse();
        });

        it('handles corrupted token claims', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            $mockClaims->shouldReceive('get')->with('sub')->andThrow(new \Exception('Corrupted claims'));
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('corrupted-claims-token')
                ->andReturn($mockToken);
            
            $result = $this->guard->attempt(['token' => 'corrupted-claims-token']);
            
            expect($result)->toBeFalse();
        });
    });

    describe('Provider Credential Validation Errors', function () {
        it('handles empty credentials gracefully', function () {
            $user = $this->provider->retrieveByCredentials([]);
            
            expect($user)->toBeNull();
        });

        it('handles null credentials gracefully', function () {
            $user = $this->provider->retrieveByCredentials(['token' => null]);
            
            expect($user)->toBeNull();
        });

        it('handles credentials validation with invalid user', function () {
            $mockUser = m::mock(User::class);
            $mockUser->shouldReceive('getAuthIdentifier')->andReturn('test-uid');
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('different-user-token')
                ->andThrow(new \Exception('Invalid token'));
            
            $result = $this->provider->validateCredentials($mockUser, ['token' => 'different-user-token']);
            
            expect($result)->toBeFalse();
        });
    });

    describe('Model Instantiation Errors', function () {
        it('handles invalid model class gracefully', function () {
            $provider = new FirebaseUserProvider(
                $this->firebaseAuth,
                'NonExistentClass',
                $this->hasher
            );
            
            expect(function () {
                $provider->createModel();
            })->toThrow(\Error::class);
        });

        it('handles model without FirebaseAuthenticatable interface', function () {
            // Create a provider with a regular class instead of FirebaseAuthenticatable
            $provider = new FirebaseUserProvider(
                $this->firebaseAuth,
                \stdClass::class,
                $this->hasher
            );
            
            expect(function () {
                $provider->createModel();
            })->toThrow(\Error::class);
        });
    });

    describe('Request Token Extraction Errors', function () {
        it('handles malformed Authorization header', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Malformed header');
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $token = $this->guard->getTokenForRequest();
            
            expect($token)->toBeNull();
        });

        it('handles empty Authorization header', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('');
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $token = $this->guard->getTokenForRequest();
            
            expect($token)->toBeNull();
        });

        it('handles Bearer header without token', function () {
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer ');
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $token = $this->guard->getTokenForRequest();
            
            expect($token)->toBe('');
        });
    });

    describe('Concurrent Access Errors', function () {
        it('handles multiple simultaneous authentication attempts', function () {
            // Simulate multiple rapid authentication attempts
            $tokens = ['token1', 'token2', 'token3'];
            $results = [];
            
            foreach ($tokens as $token) {
                $this->firebaseAuth->shouldReceive('verifyIdToken')
                    ->with($token)
                    ->andThrow(new \Exception('Rate limited'));
                
                $results[] = $this->guard->validate(['token' => $token]);
            }
            
            // All should fail gracefully
            foreach ($results as $result) {
                expect($result)->toBeFalse();
            }
        });
    });

    describe('Memory and Resource Errors', function () {
        it('handles large token payloads gracefully', function () {
            // Simulate a very large token that might cause memory issues
            $largeToken = str_repeat('a', 10000);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with($largeToken)
                ->andThrow(new \Exception('Token too large'));
            
            $result = $this->guard->validate(['token' => $largeToken]);
            
            expect($result)->toBeFalse();
        });
    });

    describe('Configuration Errors', function () {
        it('handles missing Firebase configuration gracefully', function () {
            // This would typically be handled at the service provider level,
            // but we can test that the guard handles null Firebase Auth
            expect(function () {
                new FirebaseGuard(
                    $this->provider,
                    $this->request,
                    null // This would cause a type error in real usage
                );
            })->toThrow(\TypeError::class);
        });
    });

    describe('Edge Cases', function () {
        it('handles user logout when no user is authenticated', function () {
            // Should not throw an error
            $this->guard->logout();
            
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->user())->toBeNull();
        });

        it('handles multiple logout calls', function () {
            // Should not throw an error
            $this->guard->logout();
            $this->guard->logout();
            $this->guard->logout();
            
            expect($this->guard->check())->toBeFalse();
        });

        it('handles setting null user', function () {
            $this->guard->setUser(null);
            
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->user())->toBeNull();
        });
    });
});
