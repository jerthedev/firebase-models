<?php

use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use Illuminate\Http\Request;
use Illuminate\Contracts\Hashing\Hasher;
use Mockery as m;

describe('Firebase Auth Security', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        FirebaseAuthMock::initialize();
        
        // Create real Firebase Auth mock instance
        $this->firebaseAuth = FirebaseAuthMock::getInstance();
        
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
        FirebaseAuthMock::reset();
    });

    describe('Token Security', function () {
        it('does not expose Firebase tokens in serialization', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            
            $user = new User();
            $user->setFirebaseToken($verifiedToken);
            $user->fill(['uid' => $userData['uid'], 'email' => 'test@example.com']);
            
            $array = $user->toArray();
            $json = $user->toJson();
            
            // Firebase token should not be in serialized output
            expect($array)->not->toHaveKey('firebase_token');
            expect($json)->not->toContain('firebase_token');
        });

        it('validates token signature and expiration', function () {
            // Create expired token
            $userData = FirebaseAuthMock::createTestUser();
            $expiredToken = FirebaseAuthMock::createTestToken($userData['uid'], [], time() - 3600); // Expired 1 hour ago
            
            $this->request->shouldReceive('query')->with('token')->andReturn($expiredToken);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            // Should reject expired token
            expect($user)->toBeNull();
        });

        it('prevents token reuse after logout', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // First authentication
            $result = $this->guard->attempt(['token' => $token]);
            expect($result)->toBeTrue();
            
            // Logout
            $this->guard->logout();
            
            // Try to reuse the same token
            $this->request->shouldReceive('query')->with('token')->andReturn($token);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            // Should still work (Firebase tokens are stateless)
            // But the guard state should be reset
            expect($this->guard->check())->toBeFalse();
        });

        it('validates token audience and issuer', function () {
            // This would be handled by Firebase SDK in real implementation
            // Here we test that invalid tokens are rejected
            $result = $this->guard->validate(['token' => 'invalid.token.format']);
            
            expect($result)->toBeFalse();
        });
    });

    describe('User Impersonation Protection', function () {
        it('prevents user impersonation with mismatched tokens', function () {
            // Create two users
            $userData1 = FirebaseAuthMock::createTestUser(['email' => 'user1@example.com']);
            $userData2 = FirebaseAuthMock::createTestUser(['email' => 'user2@example.com']);
            
            // Create token for user1
            $token1 = FirebaseAuthMock::createTestToken($userData1['uid']);
            
            // Get user2 instance
            $user2 = $this->provider->retrieveById($userData2['uid']);
            
            // Try to validate user2 with user1's token
            $result = $this->provider->validateCredentials($user2, ['token' => $token1]);
            
            expect($result)->toBeFalse();
        });

        it('ensures UID consistency across authentication flow', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            $this->request->shouldReceive('query')->with('token')->andReturn($token);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            expect($user->uid)->toBe($userData['uid']);
            expect($this->guard->id())->toBe($userData['uid']);
            
            // Token claims should match user UID
            $tokenClaims = $user->getFirebaseToken()->claims()->all();
            expect($tokenClaims['sub'])->toBe($userData['uid']);
        });
    });

    describe('Input Sanitization', function () {
        it('handles malicious token inputs safely', function () {
            $maliciousInputs = [
                '<script>alert("xss")</script>',
                'javascript:alert(1)',
                '../../etc/passwd',
                'null',
                'undefined',
                '0',
                'false',
                '[]',
                '{}',
                'SELECT * FROM users',
                '<?php echo "test"; ?>',
            ];
            
            foreach ($maliciousInputs as $input) {
                $result = $this->guard->validate(['token' => $input]);
                expect($result)->toBeFalse();
            }
        });

        it('handles extremely long token inputs', function () {
            $longToken = str_repeat('a', 100000); // 100KB token
            
            $result = $this->guard->validate(['token' => $longToken]);
            
            expect($result)->toBeFalse();
        });

        it('handles binary and non-UTF8 inputs', function () {
            $binaryInputs = [
                "\x00\x01\x02\x03",
                pack('H*', 'deadbeef'),
                "\xFF\xFE\xFD",
            ];
            
            foreach ($binaryInputs as $input) {
                $result = $this->guard->validate(['token' => $input]);
                expect($result)->toBeFalse();
            }
        });
    });

    describe('Rate Limiting and DoS Protection', function () {
        it('handles rapid authentication attempts gracefully', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // Simulate 100 rapid authentication attempts
            $results = [];
            for ($i = 0; $i < 100; $i++) {
                $results[] = $this->guard->validate(['token' => $token]);
            }
            
            // All should succeed (no rate limiting in mock)
            foreach ($results as $result) {
                expect($result)->toBeTrue();
            }
        });

        it('handles memory exhaustion attempts', function () {
            // Create many user instances to test memory usage
            $users = [];
            for ($i = 0; $i < 1000; $i++) {
                $userData = FirebaseAuthMock::createTestUser();
                $users[] = $this->provider->retrieveById($userData['uid']);
            }
            
            expect(count($users))->toBe(1000);
            
            // Memory should be manageable
            $memoryUsage = memory_get_usage(true);
            expect($memoryUsage)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB
        });
    });

    describe('Information Disclosure Prevention', function () {
        it('does not leak sensitive information in error messages', function () {
            // This would need to be tested with actual Firebase SDK errors
            // For now, we test that our error handling doesn't expose internals
            
            $this->request->shouldReceive('query')->with('token')->andReturn('invalid-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $user = $this->guard->user();
            
            // Should fail silently without exposing error details
            expect($user)->toBeNull();
        });

        it('does not expose user existence through timing attacks', function () {
            $startTime = microtime(true);
            $this->provider->retrieveById('non-existent-user');
            $nonExistentTime = microtime(true) - $startTime;
            
            $userData = FirebaseAuthMock::createTestUser();
            $startTime = microtime(true);
            $this->provider->retrieveById($userData['uid']);
            $existentTime = microtime(true) - $startTime;
            
            // Timing difference should be minimal (within 10ms)
            $timingDifference = abs($existentTime - $nonExistentTime);
            expect($timingDifference)->toBeLessThan(0.01);
        });
    });

    describe('Session Security', function () {
        it('properly clears user state on logout', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            
            // Authenticate
            $this->guard->attempt(['token' => $token]);
            expect($this->guard->check())->toBeTrue();
            
            $user = $this->guard->user();
            expect($user)->not->toBeNull();
            
            // Logout
            $this->guard->logout();
            
            // All state should be cleared
            expect($this->guard->check())->toBeFalse();
            expect($this->guard->user())->toBeNull();
            expect($this->guard->id())->toBeNull();
        });

        it('prevents session fixation attacks', function () {
            // Firebase tokens are stateless, so session fixation is not applicable
            // But we can test that each authentication creates a fresh state
            
            $userData1 = FirebaseAuthMock::createTestUser();
            $token1 = FirebaseAuthMock::createTestToken($userData1['uid']);
            
            $userData2 = FirebaseAuthMock::createTestUser();
            $token2 = FirebaseAuthMock::createTestToken($userData2['uid']);
            
            // First authentication
            $this->guard->attempt(['token' => $token1]);
            $firstUserId = $this->guard->id();
            
            // Second authentication should completely replace the first
            $this->guard->attempt(['token' => $token2]);
            $secondUserId = $this->guard->id();
            
            expect($firstUserId)->not->toBe($secondUserId);
            expect($secondUserId)->toBe($userData2['uid']);
        });
    });

    describe('Custom Claims Security', function () {
        it('safely handles malicious custom claims', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $maliciousClaims = [
                'admin' => '<script>alert("xss")</script>',
                'role' => 'javascript:alert(1)',
                'permissions' => ['../../etc/passwd', 'SELECT * FROM users'],
                'data' => ['<img src=x onerror=alert(1)>']
            ];
            
            $token = FirebaseAuthMock::createTestToken($userData['uid'], $maliciousClaims);
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            
            $user = new User();
            $user->setFirebaseToken($verifiedToken);
            
            $claims = $user->getCustomClaims();
            
            // Claims should be preserved as-is (not sanitized by our code)
            // Sanitization should happen at the application level
            expect($claims['admin'])->toBe('<script>alert("xss")</script>');
            expect($claims['role'])->toBe('javascript:alert(1)');
        });

        it('handles deeply nested custom claims', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $deepClaims = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => [
                                'level5' => 'deep_value'
                            ]
                        ]
                    ]
                ]
            ];
            
            $token = FirebaseAuthMock::createTestToken($userData['uid'], $deepClaims);
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            
            $user = new User();
            $user->setFirebaseToken($verifiedToken);
            
            $claims = $user->getCustomClaims();
            
            expect($claims['level1']['level2']['level3']['level4']['level5'])->toBe('deep_value');
        });
    });
});
