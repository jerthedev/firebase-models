<?php

use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\JWT\IdToken;

// Test user model for testing purposes
class TestFirebaseUser extends \JTD\FirebaseModels\Auth\FirebaseAuthenticatable
{
    protected ?string $collection = 'test_users';
    
    protected array $fillable = [
        'uid', 'email', 'email_verified_at', 'name', 'photo_url',
        'phone_number', 'custom_claims', 'provider_data',
        'last_sign_in_at', 'test_field'
    ];
}

describe('FirebaseAuthenticatable', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        FirebaseAuthMock::initialize();
    });

    describe('Model Creation', function () {
        it('can create a new user instance', function () {
            $user = new TestFirebaseUser([
                'uid' => 'test-uid-123',
                'email' => 'test@example.com',
                'name' => 'Test User'
            ]);

            expect($user->uid)->toBe('test-uid-123');
            expect($user->email)->toBe('test@example.com');
            expect($user->name)->toBe('Test User');
            expect($user->exists)->toBeFalse();
        });

        it('uses uid as primary key', function () {
            $user = new TestFirebaseUser();
            
            expect($user->getKeyName())->toBe('uid');
            expect($user->getAuthIdentifierName())->toBe('uid');
        });

        it('sets default collection name', function () {
            $user = new TestFirebaseUser();
            
            expect($user->getCollection())->toBe('test_users');
        });
    });

    describe('Firebase Token Integration', function () {
        it('can set and get Firebase token', function () {
            $user = new TestFirebaseUser();
            $tokenData = FirebaseAuthMock::createTestToken('test-uid', ['role' => 'admin']);
            
            // Create a mock IdToken
            $token = new class($tokenData) {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function claims() {
                    return new class($this->data) {
                        private $data;
                        public function __construct($data) { $this->data = $data; }
                        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
                        public function all() { return $this->data; }
                    };
                }
            };
            
            $user->setFirebaseToken($token);
            
            expect($user->getFirebaseToken())->toBe($token);
        });

        it('can hydrate from Firebase token', function () {
            $user = new TestFirebaseUser();
            
            // Create mock token data
            $tokenData = [
                'sub' => 'test-uid-123',
                'email' => 'test@example.com',
                'email_verified' => true,
                'name' => 'Test User',
                'picture' => 'https://example.com/photo.jpg',
                'phone_number' => '+1234567890',
                'role' => 'admin',
                'permissions' => ['read', 'write']
            ];
            
            // Create a mock IdToken
            $token = new class($tokenData) {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function claims() {
                    return new class($this->data) {
                        private $data;
                        public function __construct($data) { $this->data = $data; }
                        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
                        public function all() { return $this->data; }
                    };
                }
            };
            
            $user->hydrateFromFirebaseToken($token);
            
            expect($user->uid)->toBe('test-uid-123');
            expect($user->email)->toBe('test@example.com');
            expect($user->email_verified_at)->not->toBeNull();
            expect($user->name)->toBe('Test User');
            expect($user->photo_url)->toBe('https://example.com/photo.jpg');
            expect($user->phone_number)->toBe('+1234567890');
            
            // Check custom claims
            $customClaims = $user->getAttribute('custom_claims');
            expect($customClaims['role'])->toBe('admin');
            expect($customClaims['permissions'])->toBe(['read', 'write']);
        });
    });

    describe('Authentication Interface', function () {
        it('implements Authenticatable interface correctly', function () {
            $user = new TestFirebaseUser(['uid' => 'test-uid-123']);
            
            expect($user->getAuthIdentifier())->toBe('test-uid-123');
            expect($user->getAuthIdentifierName())->toBe('uid');
            expect($user->getRememberToken())->toBeNull();
            expect($user->getRememberTokenName())->toBeNull();
        });

        it('throws exception for password methods', function () {
            $user = new TestFirebaseUser();
            
            expect(fn() => $user->getAuthPassword())
                ->toThrow(\BadMethodCallException::class);
        });

        it('handles remember token methods gracefully', function () {
            $user = new TestFirebaseUser();
            
            // These should not throw exceptions
            $user->setRememberToken('some-token');
            expect($user->getRememberToken())->toBeNull();
        });
    });

    describe('Custom Claims and Roles', function () {
        it('can get custom claims', function () {
            $user = new TestFirebaseUser([
                'custom_claims' => [
                    'role' => 'admin',
                    'department' => 'engineering'
                ]
            ]);
            
            expect($user->getCustomClaim('role'))->toBe('admin');
            expect($user->getCustomClaim('department'))->toBe('engineering');
            expect($user->getCustomClaim('nonexistent', 'default'))->toBe('default');
        });

        it('can check if custom claims exist', function () {
            $user = new TestFirebaseUser([
                'custom_claims' => ['role' => 'admin']
            ]);
            
            expect($user->hasCustomClaim('role'))->toBeTrue();
            expect($user->hasCustomClaim('nonexistent'))->toBeFalse();
        });

        it('can get and check roles', function () {
            $user = new TestFirebaseUser([
                'custom_claims' => [
                    'roles' => ['admin', 'moderator']
                ]
            ]);
            
            expect($user->getRoles())->toBe(['admin', 'moderator']);
            expect($user->hasRole('admin'))->toBeTrue();
            expect($user->hasRole('user'))->toBeFalse();
        });

        it('can get and check permissions', function () {
            $user = new TestFirebaseUser([
                'custom_claims' => [
                    'permissions' => ['read', 'write', 'delete']
                ]
            ]);
            
            expect($user->getPermissions())->toBe(['read', 'write', 'delete']);
            expect($user->hasPermission('read'))->toBeTrue();
            expect($user->hasPermission('execute'))->toBeFalse();
        });
    });

    describe('User Status Methods', function () {
        it('can check if user has verified email', function () {
            $verifiedUser = new TestFirebaseUser(['email_verified_at' => now()]);
            $unverifiedUser = new TestFirebaseUser(['email_verified_at' => null]);

            expect($verifiedUser->hasVerifiedEmail())->toBeTrue();
            expect($unverifiedUser->hasVerifiedEmail())->toBeFalse();

            // Test deprecated method for backward compatibility
            expect($verifiedUser->isVerified())->toBeTrue();
            expect($unverifiedUser->isVerified())->toBeFalse();
        });

        it('can mark email as verified', function () {
            $user = new TestFirebaseUser(['email' => 'test@example.com']);

            expect($user->hasVerifiedEmail())->toBeFalse();

            $user->markEmailAsVerified();

            expect($user->hasVerifiedEmail())->toBeTrue();
            expect($user->email_verified_at)->not->toBeNull();
        });

        it('can get email for verification', function () {
            $user = new TestFirebaseUser(['email' => 'test@example.com']);

            expect($user->getEmailForVerification())->toBe('test@example.com');
        });
    });

    describe('Serialization', function () {
        it('hides sensitive data in array conversion', function () {
            $user = new TestFirebaseUser([
                'uid' => 'test-uid',
                'email' => 'test@example.com',
                'custom_claims' => ['secret' => 'data']
            ]);
            
            $array = $user->toArray();
            
            expect($array)->toHaveKey('uid');
            expect($array)->toHaveKey('email');
            expect($array)->not->toHaveKey('firebase_token');
        });
    });
});
