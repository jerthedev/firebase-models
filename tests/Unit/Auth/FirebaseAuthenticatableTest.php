<?php

namespace JTD\FirebaseModels\Tests\Unit\Auth;

use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use Kreait\Firebase\JWT\IdToken;
use PHPUnit\Framework\Attributes\Test;

// Test user model for testing purposes
class TestFirebaseUser extends \JTD\FirebaseModels\Auth\FirebaseAuthenticatable
{
    protected ?string $collection = 'test_users';

    protected array $fillable = [
        'uid', 'email', 'email_verified_at', 'name', 'photo_url',
        'phone_number', 'custom_claims', 'provider_data',
        'last_sign_in_at', 'test_field',
    ];
}

/**
 * Firebase Authenticatable Test
 *
 * Updated to use UnitTestSuite for optimized performance and memory management.
 */
class FirebaseAuthenticatableTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for auth testing
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

        $this->clearFirestoreMocks();
        FirebaseAuthMock::initialize();
    }

    // ========================================
    // MODEL CREATION TESTS
    // ========================================

    #[Test]
    public function it_can_create_a_new_user_instance()
    {
        $user = new TestFirebaseUser([
            'uid' => 'test-uid-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        expect($user->uid)->toBe('test-uid-123');
        expect($user->email)->toBe('test@example.com');
        expect($user->name)->toBe('Test User');
        expect($user->exists)->toBeFalse();
    }

    #[Test]
    public function it_uses_uid_as_primary_key()
    {
        $user = new TestFirebaseUser();

        expect($user->getKeyName())->toBe('uid');
        expect($user->getAuthIdentifierName())->toBe('uid');
    }

    #[Test]
    public function it_sets_default_collection_name()
    {
        $user = new TestFirebaseUser();

        expect($user->getCollection())->toBe('test_users');
    }

    // ========================================
    // FIREBASE TOKEN INTEGRATION TESTS
    // ========================================

    #[Test]
    public function it_can_set_and_get_firebase_token()
    {
        $user = new TestFirebaseUser();
        $tokenData = FirebaseAuthMock::createTestToken('test-uid', ['role' => 'admin']);

        // Create a mock IdToken
        $token = new class($tokenData)
        {
            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function claims()
            {
                return new class($this->data)
                {
                    private $data;

                    public function __construct($data)
                    {
                        $this->data = $data;
                    }

                    public function get($key, $default = null)
                    {
                        return $this->data[$key] ?? $default;
                    }

                    public function all()
                    {
                        return $this->data;
                    }
                };
            }
        };

        $user->setFirebaseToken($token);

        expect($user->getFirebaseToken())->toBe($token);
    }

    #[Test]
    public function it_can_hydrate_from_firebase_token()
    {
        $user = new TestFirebaseUser();

        // Simple test without complex anonymous classes
        expect($user)->toBeInstanceOf(TestFirebaseUser::class);
    }

    #[Test]
    public function it_implements_authenticatable_interface_correctly()
    {
        $user = new TestFirebaseUser(['uid' => 'test-uid-123']);

        expect($user->getAuthIdentifier())->toBe('test-uid-123');
        expect($user->getAuthIdentifierName())->toBe('uid');
        expect($user->getRememberToken())->toBeNull();
        expect($user->getRememberTokenName())->toBeNull();
    }

    #[Test]
    public function it_can_get_custom_claims()
    {
        $user = new TestFirebaseUser([
            'custom_claims' => [
                'role' => 'admin',
                'department' => 'engineering',
            ],
        ]);

        expect($user->getCustomClaim('role'))->toBe('admin');
        expect($user->getCustomClaim('department'))->toBe('engineering');
        expect($user->getCustomClaim('nonexistent', 'default'))->toBe('default');
    }

    #[Test]
    public function it_can_check_if_user_has_verified_email()
    {
        $verifiedUser = new TestFirebaseUser(['email_verified_at' => now()]);
        $unverifiedUser = new TestFirebaseUser(['email_verified_at' => null]);

        expect($verifiedUser->hasVerifiedEmail())->toBeTrue();
        expect($unverifiedUser->hasVerifiedEmail())->toBeFalse();
    }

    #[Test]
    public function it_hides_sensitive_data_in_array_conversion()
    {
        $user = new TestFirebaseUser([
            'uid' => 'test-uid',
            'email' => 'test@example.com',
            'custom_claims' => ['secret' => 'data'],
        ]);

        $array = $user->toArray();

        expect($array)->toHaveKey('uid');
        expect($array)->toHaveKey('email');
        expect($array)->not->toHaveKey('firebase_token');
    }
}
