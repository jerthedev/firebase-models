<?php

use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use Mockery as m;

// Test user model for advanced testing
class AdvancedTestUser extends \JTD\FirebaseModels\Auth\FirebaseAuthenticatable
{
    protected ?string $collection = 'advanced_users';
    
    protected array $fillable = [
        'uid', 'email', 'name', 'role', 'department', 'permissions',
        'profile_data', 'settings', 'last_login', 'status'
    ];
    
    protected array $casts = [
        'permissions' => 'array',
        'profile_data' => 'array',
        'settings' => 'array',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
}

describe('FirebaseAuthenticatable Advanced Features', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        FirebaseAuthMock::initialize();
    });

    afterEach(function () {
        // Reset is handled by clearFirestoreMocks in next test
    });

    describe('Token Hydration', function () {
        it('can hydrate from Firebase ID token', function () {
            // Create test user and token
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'hydrate@example.com',
                'displayName' => 'Hydrate User',
                'emailVerified' => true
            ]);
            
            $token = FirebaseAuthMock::createTestToken($userData['uid'], [
                'role' => 'admin',
                'department' => 'engineering'
            ]);
            
            // Create user model and hydrate from token
            $user = new AdvancedTestUser();
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            $user->hydrateFromFirebaseToken($verifiedToken);
            
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('hydrate@example.com');
            expect($user->name)->toBe('Hydrate User');
            expect($user->hasVerifiedEmail())->toBeTrue();
            
            // Check custom claims
            $claims = $user->getFirebaseToken()->claims()->all();
            expect($claims['role'])->toBe('admin');
            expect($claims['department'])->toBe('engineering');
        });

        it('can hydrate from Firebase UserRecord', function () {
            // Create test user
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'record@example.com',
                'displayName' => 'Record User',
                'photoURL' => 'https://example.com/photo.jpg',
                'phoneNumber' => '+1234567890'
            ]);
            
            $userRecord = FirebaseAuthMock::getInstance()->getUser($userData['uid']);
            
            // Create user model and hydrate from UserRecord
            $user = new AdvancedTestUser();
            $user->setFirebaseUserRecord($userRecord);
            
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('record@example.com');
            expect($user->name)->toBe('Record User');
            expect($user->photo_url)->toBe('https://example.com/photo.jpg');
            expect($user->phone_number)->toBe('+1234567890');
        });

        it('can handle missing optional fields gracefully', function () {
            // Create minimal user data
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'minimal@example.com'
                // No displayName, photoURL, etc.
            ]);
            
            $userRecord = FirebaseAuthMock::getInstance()->getUser($userData['uid']);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseUserRecord($userRecord);
            
            expect($user->uid)->toBe($userData['uid']);
            expect($user->email)->toBe('minimal@example.com');
            expect($user->name)->toBeNull();
            expect($user->photo_url)->toBeNull();
            expect($user->phone_number)->toBeNull();
        });
    });

    describe('Authentication Interface', function () {
        it('implements Laravel Authenticatable interface correctly', function () {
            $user = new AdvancedTestUser([
                'uid' => 'test-uid-123',
                'email' => 'auth@example.com',
                'name' => 'Auth User'
            ]);
            
            // Test Authenticatable interface methods
            expect($user->getAuthIdentifierName())->toBe('uid');
            expect($user->getAuthIdentifier())->toBe('test-uid-123');

            // Firebase doesn't use passwords - should throw exception
            expect(function () use ($user) {
                $user->getAuthPassword();
            })->toThrow(\BadMethodCallException::class);

            expect($user->getRememberToken())->toBeNull(); // Firebase doesn't use remember tokens
            
            // Test remember token methods (should be no-ops)
            $user->setRememberToken('some-token');
            expect($user->getRememberToken())->toBeNull();
            expect($user->getRememberTokenName())->toBeNull(); // Firebase doesn't use remember tokens
        });

        it('can check email verification status', function () {
            $user = new AdvancedTestUser();
            
            // Initially not verified
            expect($user->hasVerifiedEmail())->toBeFalse();
            
            // Set verification timestamp
            $user->email_verified_at = now();
            expect($user->hasVerifiedEmail())->toBeTrue();
            
            // Test with null timestamp
            $user->email_verified_at = null;
            expect($user->hasVerifiedEmail())->toBeFalse();
        });

        it('can mark email as verified', function () {
            $user = new AdvancedTestUser();
            
            expect($user->hasVerifiedEmail())->toBeFalse();
            
            $user->markEmailAsVerified();
            
            expect($user->hasVerifiedEmail())->toBeTrue();
            expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Firebase Token Management', function () {
        it('can store and retrieve Firebase token', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseToken($verifiedToken);
            
            $retrievedToken = $user->getFirebaseToken();
            expect($retrievedToken)->toBe($verifiedToken);
            
            // Test token claims access
            $claims = $retrievedToken->claims()->all();
            expect($claims['sub'])->toBe($userData['uid']);
        });

        it('can clear Firebase token', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseToken($verifiedToken);
            
            expect($user->getFirebaseToken())->not->toBeNull();
            
            $user->clearFirebaseToken();
            
            expect($user->getFirebaseToken())->toBeNull();
        });

        it('can check if user has Firebase token', function () {
            $user = new AdvancedTestUser();
            
            expect($user->hasFirebaseToken())->toBeFalse();
            
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            
            $user->setFirebaseToken($verifiedToken);
            
            expect($user->hasFirebaseToken())->toBeTrue();
        });
    });

    describe('Custom Claims Handling', function () {
        it('can access custom claims from token', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid'], [
                'admin' => true,
                'role' => 'manager',
                'permissions' => ['read', 'write'],
                'department' => 'engineering'
            ]);
            
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseToken($verifiedToken);
            
            $claims = $user->getCustomClaims();
            expect($claims['admin'])->toBeTrue();
            expect($claims['role'])->toBe('manager');
            expect($claims['permissions'])->toBe(['read', 'write']);
            expect($claims['department'])->toBe('engineering');
        });

        it('can check specific custom claims', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid'], [
                'admin' => true,
                'role' => 'user'
            ]);
            
            $verifiedToken = FirebaseAuthMock::getInstance()->verifyIdToken($token);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseToken($verifiedToken);
            
            expect($user->hasCustomClaim('admin'))->toBeTrue();
            expect($user->hasCustomClaim('role'))->toBeTrue();
            expect($user->hasCustomClaim('nonexistent'))->toBeFalse();
            
            expect($user->getCustomClaim('admin'))->toBeTrue();
            expect($user->getCustomClaim('role'))->toBe('user');
            expect($user->getCustomClaim('nonexistent'))->toBeNull();
            expect($user->getCustomClaim('nonexistent', 'default'))->toBe('default');
        });

        it('returns empty array when no token is set', function () {
            $user = new AdvancedTestUser();
            
            expect($user->getCustomClaims())->toBe([]);
            expect($user->hasCustomClaim('admin'))->toBeFalse();
            expect($user->getCustomClaim('role'))->toBeNull();
        });
    });

    describe('Provider Data Handling', function () {
        it('can access provider data from UserRecord', function () {
            // Create user with provider data
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'provider@example.com',
                'providerData' => [
                    [
                        'providerId' => 'google.com',
                        'uid' => 'google-uid-123',
                        'email' => 'provider@gmail.com',
                        'displayName' => 'Google User'
                    ],
                    [
                        'providerId' => 'facebook.com',
                        'uid' => 'facebook-uid-456',
                        'email' => 'provider@facebook.com'
                    ]
                ]
            ]);
            
            $userRecord = FirebaseAuthMock::getInstance()->getUser($userData['uid']);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseUserRecord($userRecord);
            
            $providerData = $user->getProviderData();
            expect($providerData)->toBeArray();
            expect(count($providerData))->toBe(2);
            
            // Check Google provider
            $googleProvider = collect($providerData)->firstWhere('providerId', 'google.com');
            expect($googleProvider['uid'])->toBe('google-uid-123');
            expect($googleProvider['email'])->toBe('provider@gmail.com');
            
            // Check Facebook provider
            $facebookProvider = collect($providerData)->firstWhere('providerId', 'facebook.com');
            expect($facebookProvider['uid'])->toBe('facebook-uid-456');
        });

        it('can check if user has specific provider', function () {
            $userData = FirebaseAuthMock::createTestUser([
                'providerData' => [
                    ['providerId' => 'google.com', 'uid' => 'google-123'],
                    ['providerId' => 'github.com', 'uid' => 'github-456']
                ]
            ]);
            
            $userRecord = FirebaseAuthMock::getInstance()->getUser($userData['uid']);
            
            $user = new AdvancedTestUser();
            $user->setFirebaseUserRecord($userRecord);
            
            expect($user->hasProvider('google.com'))->toBeTrue();
            expect($user->hasProvider('github.com'))->toBeTrue();
            expect($user->hasProvider('facebook.com'))->toBeFalse();
            expect($user->hasProvider('twitter.com'))->toBeFalse();
        });
    });

    describe('Serialization', function () {
        it('can serialize to array', function () {
            $user = new AdvancedTestUser([
                'uid' => 'test-uid',
                'email' => 'serialize@example.com',
                'name' => 'Serialize User',
                'role' => 'admin',
                'permissions' => ['read', 'write']
            ]);
            
            $array = $user->toArray();
            
            expect($array['uid'])->toBe('test-uid');
            expect($array['email'])->toBe('serialize@example.com');
            expect($array['name'])->toBe('Serialize User');
            expect($array['role'])->toBe('admin');
            expect($array['permissions'])->toBe(['read', 'write']);
            
            // Firebase token should be hidden by default
            expect($array)->not->toHaveKey('firebase_token');
        });

        it('can serialize to JSON', function () {
            $user = new AdvancedTestUser([
                'uid' => 'json-uid',
                'email' => 'json@example.com',
                'name' => 'JSON User'
            ]);
            
            $json = $user->toJson();
            $decoded = json_decode($json, true);
            
            expect($decoded['uid'])->toBe('json-uid');
            expect($decoded['email'])->toBe('json@example.com');
            expect($decoded['name'])->toBe('JSON User');
        });
    });

    describe('Model Casting', function () {
        it('can cast attributes correctly', function () {
            $user = new AdvancedTestUser([
                'permissions' => ['read', 'write', 'delete'],
                'profile_data' => ['age' => 30, 'city' => 'New York'],
                'last_login' => '2023-01-01 12:00:00'
            ]);
            
            expect($user->permissions)->toBeArray();
            expect($user->permissions)->toBe(['read', 'write', 'delete']);
            
            expect($user->profile_data)->toBeArray();
            expect($user->profile_data['age'])->toBe(30);
            
            expect($user->last_login)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });
});
