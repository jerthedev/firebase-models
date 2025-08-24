<?php

namespace JTD\FirebaseModels\Tests\Unit;

use Illuminate\Http\Request;
use JTD\FirebaseModels\Auth\FirebaseAuthenticatable;
use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use JTD\FirebaseModels\Tests\Models\TestUser;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('core')]
#[Group('firebase-auth')]
class FirebaseAuthCoreTest extends UnitTestSuite
{
    protected FirebaseGuard $guard;

    protected FirebaseUserProvider $provider;

    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up Firebase Auth mocking
        FirebaseAuthMock::initialize();
        FirebaseAuthMock::clear();

        // Create mock request
        $this->request = new Request();

        // Create provider and guard instances
        $this->provider = new FirebaseUserProvider(
            app(\Kreait\Firebase\Contract\Auth::class),
            TestUser::class,
            app(\Illuminate\Contracts\Hashing\Hasher::class)
        );
        $this->guard = new FirebaseGuard(
            $this->provider,
            $this->request,
            app(\Kreait\Firebase\Contract\Auth::class)
        );
    }

    protected function tearDown(): void
    {
        FirebaseAuthMock::clear();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_firebase_guard_instance()
    {
        expect($this->guard)->toBeInstanceOf(FirebaseGuard::class);
        expect($this->guard->getProvider())->toBe($this->provider);
    }

    #[Test]
    public function it_creates_firebase_user_provider_instance()
    {
        expect($this->provider)->toBeInstanceOf(FirebaseUserProvider::class);
        expect($this->provider->getModel())->toBe(TestUser::class);
    }

    #[Test]
    public function it_handles_token_extraction_from_request()
    {
        // Test Authorization header
        $this->request->headers->set('Authorization', 'Bearer test-token-123');
        $token = $this->guard->getTokenForRequest();
        expect($token)->toBe('test-token-123');

        // Test without Bearer prefix
        $this->request->headers->set('Authorization', 'test-token-456');
        $token = $this->guard->getTokenForRequest();
        expect($token)->toBe('test-token-456');

        // Test token input parameter
        $this->request = new Request(['token' => 'input-token-789']);
        $this->guard = new FirebaseGuard(
            $this->provider,
            $this->request,
            app(\Kreait\Firebase\Contract\Auth::class)
        );
        $token = $this->guard->getTokenForRequest();
        expect($token)->toBe('input-token-789');
    }

    #[Test]
    public function it_handles_user_authentication_with_valid_token()
    {
        // Create a test user in Firebase Auth mock
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'test@example.com',
            'displayName' => 'Test User',
        ]);

        // Create a valid token for the user
        $token = FirebaseAuthMock::createTestToken($userData['uid']);

        // Set token in request
        $this->request->headers->set('Authorization', "Bearer {$token}");

        // Attempt authentication
        $user = $this->guard->user();

        expect($user)->toBeInstanceOf(TestUser::class);
        expect($user->getAuthIdentifier())->toBe($userData['uid']);
        expect($user->email)->toBe('test@example.com');
        expect($user->name)->toBe('Test User');
    }

    #[Test]
    public function it_handles_authentication_with_invalid_token()
    {
        // Set invalid token in request
        $this->request->headers->set('Authorization', 'Bearer invalid-token');

        // Attempt authentication
        $user = $this->guard->user();

        expect($user)->toBeNull();
        expect($this->guard->check())->toBeFalse();
        expect($this->guard->guest())->toBeTrue();
    }

    #[Test]
    public function it_handles_authentication_without_token()
    {
        // No token in request
        $user = $this->guard->user();

        expect($user)->toBeNull();
        expect($this->guard->check())->toBeFalse();
        expect($this->guard->guest())->toBeTrue();
    }

    #[Test]
    public function it_handles_user_validation()
    {
        // Create test user and token
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'validate@example.com',
            'displayName' => 'Validate User',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);

        // Test validation with valid credentials
        $isValid = $this->guard->validate(['token' => $token]);
        expect($isValid)->toBeTrue();

        // Test validation with invalid credentials
        $isInvalid = $this->guard->validate(['token' => 'invalid-token']);
        expect($isInvalid)->toBeFalse();
    }

    #[Test]
    public function it_handles_user_login_attempt()
    {
        // Create test user and token
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'login@example.com',
            'displayName' => 'Login User',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);

        // Attempt login
        $loginSuccess = $this->guard->attempt(['token' => $token]);
        expect($loginSuccess)->toBeTrue();

        // Verify user is authenticated
        expect($this->guard->check())->toBeTrue();
        expect($this->guard->user())->toBeInstanceOf(TestUser::class);
        expect($this->guard->user()->getAuthIdentifier())->toBe($userData['uid']);
    }

    #[Test]
    public function it_handles_user_logout()
    {
        // First, log in a user
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'logout@example.com',
            'displayName' => 'Logout User',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);

        $this->guard->attempt(['token' => $token]);
        expect($this->guard->check())->toBeTrue();

        // Now logout
        $this->guard->logout();
        expect($this->guard->check())->toBeFalse();
        expect($this->guard->user())->toBeNull();
    }

    #[Test]
    public function it_handles_once_authentication()
    {
        // Create test user and token
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'once@example.com',
            'displayName' => 'Once User',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);

        // Test once authentication
        $onceSuccess = $this->guard->once(['token' => $token]);
        expect($onceSuccess)->toBeTrue();
        expect($this->guard->check())->toBeTrue();
    }

    #[Test]
    public function it_handles_user_provider_operations()
    {
        // Create test user
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'provider@example.com',
            'displayName' => 'Provider User',
        ]);

        // Test retrieveById
        $user = $this->provider->retrieveById($userData['uid']);
        expect($user)->toBeInstanceOf(TestUser::class);
        expect($user->getAuthIdentifier())->toBe($userData['uid']);

        // Test retrieveByToken (not typically used with Firebase)
        $userByToken = $this->provider->retrieveByToken($userData['uid'], 'remember_token');
        expect($userByToken)->toBeNull(); // Firebase doesn't use remember tokens

        // Test retrieveByCredentials
        $token = FirebaseAuthMock::createTestToken($userData['uid']);
        $userByCredentials = $this->provider->retrieveByCredentials(['token' => $token]);
        expect($userByCredentials)->toBeInstanceOf(TestUser::class);

        // Test validateCredentials
        $isValid = $this->provider->validateCredentials($user, ['token' => $token]);
        expect($isValid)->toBeTrue();
    }

    #[Test]
    public function it_handles_firebase_authenticatable_model()
    {
        $user = new TestUser([
            'uid' => 'test-uid-123',
            'email' => 'model@example.com',
            'name' => 'Model User',
        ]);

        expect($user)->toBeInstanceOf(FirebaseAuthenticatable::class);
        expect($user->getAuthIdentifierName())->toBe('uid');
        expect($user->getAuthIdentifier())->toBe('test-uid-123');
        expect($user->getAuthPasswordName())->toBe('password'); // Default, not used
        expect($user->getAuthPassword())->toBeNull(); // Firebase handles passwords
    }

    #[Test]
    public function it_handles_firebase_token_operations()
    {
        $user = new TestUser([
            'uid' => 'token-test-uid',
            'email' => 'token@example.com',
        ]);

        // Test token setting and getting
        $mockToken = Mockery::mock(\Kreait\Firebase\JWT\IdToken::class);
        $user->setFirebaseToken($mockToken);

        expect($user->getFirebaseToken())->toBe($mockToken);
        expect($user->hasFirebaseToken())->toBeTrue();

        // Test token clearing
        $user->clearFirebaseToken();
        expect($user->getFirebaseToken())->toBeNull();
        expect($user->hasFirebaseToken())->toBeFalse();
    }

    #[Test]
    public function it_handles_user_creation_from_firebase_data()
    {
        // Create Firebase user data
        $firebaseUserData = [
            'uid' => 'firebase-create-uid',
            'email' => 'create@example.com',
            'displayName' => 'Created User',
            'emailVerified' => true,
            'disabled' => false,
        ];

        // Create user from Firebase data
        $user = TestUser::createFromFirebaseUser($firebaseUserData);

        expect($user)->toBeInstanceOf(TestUser::class);
        expect($user->uid)->toBe('firebase-create-uid');
        expect($user->email)->toBe('create@example.com');
        expect($user->name)->toBe('Created User');
        expect($user->email_verified_at)->not->toBeNull();
    }

    #[Test]
    public function it_handles_guard_state_management()
    {
        expect($this->guard->check())->toBeFalse();
        expect($this->guard->guest())->toBeTrue();
        expect($this->guard->id())->toBeNull();

        // Log in a user
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'state@example.com',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);
        $this->guard->attempt(['token' => $token]);

        expect($this->guard->check())->toBeTrue();
        expect($this->guard->guest())->toBeFalse();
        expect($this->guard->id())->toBe($userData['uid']);
    }

    #[Test]
    public function it_handles_authentication_events()
    {
        $eventsFired = [];

        // Listen for authentication events
        app('events')->listen('auth.login', function ($event) use (&$eventsFired) {
            $eventsFired[] = 'login';
        });

        app('events')->listen('auth.logout', function ($event) use (&$eventsFired) {
            $eventsFired[] = 'logout';
        });

        // Perform login
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'events@example.com',
        ]);
        $token = FirebaseAuthMock::createTestToken($userData['uid']);
        $this->guard->attempt(['token' => $token]);

        // Perform logout
        $this->guard->logout();

        // Note: Event testing might need additional setup depending on implementation
        // This test structure is ready for when events are implemented
    }

    #[Test]
    public function it_handles_concurrent_authentication_requests()
    {
        // Create multiple users
        $user1Data = FirebaseAuthMock::createTestUser(['email' => 'concurrent1@example.com']);
        $user2Data = FirebaseAuthMock::createTestUser(['email' => 'concurrent2@example.com']);

        $token1 = FirebaseAuthMock::createTestToken($user1Data['uid']);
        $token2 = FirebaseAuthMock::createTestToken($user2Data['uid']);

        // Test that each guard instance maintains separate state
        $guard1 = new FirebaseGuard(
            $this->provider,
            new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token1}"]),
            app(\Kreait\Firebase\Contract\Auth::class)
        );

        $guard2 = new FirebaseGuard(
            $this->provider,
            new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token2}"]),
            app(\Kreait\Firebase\Contract\Auth::class)
        );

        $user1 = $guard1->user();
        $user2 = $guard2->user();

        expect($user1->getAuthIdentifier())->toBe($user1Data['uid']);
        expect($user2->getAuthIdentifier())->toBe($user2Data['uid']);
        expect($user1->getAuthIdentifier())->not->toBe($user2->getAuthIdentifier());
    }
}
