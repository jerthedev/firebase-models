<?php

namespace JTD\FirebaseModels\Tests\Helpers;

use Mockery;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Auth\CreateRequest;
use Kreait\Firebase\Auth\UpdateRequest;

/**
 * FirebaseAuthMock provides comprehensive mocking capabilities for Firebase Auth operations
 * during testing, allowing tests to run without requiring actual Firebase connections.
 */
class FirebaseAuthMock
{
    protected static ?self $instance = null;
    protected array $users = [];
    protected array $tokens = [];
    protected array $operations = [];
    protected ?Auth $mockAuth = null;

    public static function initialize(): void
    {
        static::$instance = new static();
        static::$instance->setupMocks();
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::initialize();
        }

        return static::$instance;
    }

    protected function setupMocks(): void
    {
        // Mock the Firebase Auth contract
        $this->mockAuth = Mockery::mock(Auth::class);

        // Bind the mock auth to the container
        app()->instance(Auth::class, $this->mockAuth);
        app()->instance('firebase.auth', $this->mockAuth);

        // Set up default mock behaviors
        $this->setupDefaultMockBehaviors();
    }

    protected function setupDefaultMockBehaviors(): void
    {
        // Mock createUser() method
        $this->mockAuth->shouldReceive('createUser')
            ->andReturnUsing(function ($request) {
                return $this->createUser($request);
            });

        // Mock getUser() method
        $this->mockAuth->shouldReceive('getUser')
            ->andReturnUsing(function ($uid) {
                return $this->getUser($uid);
            });

        // Mock getUserByEmail() method
        $this->mockAuth->shouldReceive('getUserByEmail')
            ->andReturnUsing(function ($email) {
                return $this->getUserByEmail($email);
            });

        // Mock updateUser() method
        $this->mockAuth->shouldReceive('updateUser')
            ->andReturnUsing(function ($uid, $request) {
                return $this->updateUser($uid, $request);
            });

        // Mock deleteUser() method
        $this->mockAuth->shouldReceive('deleteUser')
            ->andReturnUsing(function ($uid) {
                return $this->deleteUser($uid);
            });

        // Mock verifyIdToken() method
        $this->mockAuth->shouldReceive('verifyIdToken')
            ->andReturnUsing(function ($token) {
                return $this->verifyIdToken($token);
            });

        // Mock createCustomToken() method
        $this->mockAuth->shouldReceive('createCustomToken')
            ->andReturnUsing(function ($uid, $claims = []) {
                return $this->createCustomToken($uid, $claims);
            });

        // Mock listUsers() method
        $this->mockAuth->shouldReceive('listUsers')
            ->andReturnUsing(function ($maxResults = 1000, $pageToken = null) {
                return $this->listUsers($maxResults, $pageToken);
            });

        // Mock setCustomUserClaims() method
        $this->mockAuth->shouldReceive('setCustomUserClaims')
            ->andReturnUsing(function ($uid, $claims) {
                return $this->setCustomUserClaims($uid, $claims);
            });
    }

    protected function createUser($request): UserRecord
    {
        $uid = $this->generateUserId();
        $userData = [
            'uid' => $uid,
            'email' => $request instanceof CreateRequest ? $request->email() : ($request['email'] ?? null),
            'emailVerified' => false,
            'displayName' => $request instanceof CreateRequest ? $request->displayName() : ($request['displayName'] ?? null),
            'photoURL' => $request instanceof CreateRequest ? $request->photoUrl() : ($request['photoURL'] ?? null),
            'disabled' => false,
            'metadata' => [
                'creationTime' => now()->toISOString(),
                'lastSignInTime' => null,
            ],
            'customClaims' => [],
            'providerData' => [],
        ];

        $this->users[$uid] = $userData;
        $this->recordOperation('createUser', $uid);

        return $this->createMockUserRecord($userData);
    }

    protected function getUser(string $uid): UserRecord
    {
        if (!isset($this->users[$uid])) {
            throw new \Kreait\Firebase\Exception\Auth\UserNotFound("User with UID '{$uid}' not found");
        }

        $this->recordOperation('getUser', $uid);
        return $this->createMockUserRecord($this->users[$uid]);
    }

    protected function getUserByEmail(string $email): UserRecord
    {
        foreach ($this->users as $userData) {
            if ($userData['email'] === $email) {
                $this->recordOperation('getUserByEmail', $userData['uid']);
                return $this->createMockUserRecord($userData);
            }
        }

        throw new \Kreait\Firebase\Exception\Auth\UserNotFound("User with email '{$email}' not found");
    }

    protected function updateUser(string $uid, $request): UserRecord
    {
        if (!isset($this->users[$uid])) {
            throw new \Kreait\Firebase\Exception\Auth\UserNotFound("User with UID '{$uid}' not found");
        }

        $updates = $request instanceof UpdateRequest ? [
            'email' => $request->email(),
            'displayName' => $request->displayName(),
            'photoURL' => $request->photoUrl(),
            'disabled' => $request->isDisabled(),
        ] : $request;

        $this->users[$uid] = array_merge($this->users[$uid], array_filter($updates, fn($v) => $v !== null));
        $this->recordOperation('updateUser', $uid);

        return $this->createMockUserRecord($this->users[$uid]);
    }

    protected function deleteUser(string $uid): void
    {
        if (!isset($this->users[$uid])) {
            throw new \Kreait\Firebase\Exception\Auth\UserNotFound("User with UID '{$uid}' not found");
        }

        unset($this->users[$uid]);
        $this->recordOperation('deleteUser', $uid);
    }

    protected function verifyIdToken(string $token): array
    {
        if (!isset($this->tokens[$token])) {
            throw new \Kreait\Firebase\Exception\Auth\InvalidIdToken("Invalid ID token");
        }

        $tokenData = $this->tokens[$token];
        
        // Check if token is expired
        if ($tokenData['exp'] < time()) {
            throw new \Kreait\Firebase\Exception\Auth\ExpiredIdToken("ID token has expired");
        }

        $this->recordOperation('verifyIdToken', $tokenData['uid']);
        return $tokenData;
    }

    protected function createCustomToken(string $uid, array $claims = []): string
    {
        $token = 'mock_custom_token_' . uniqid() . '_' . $uid;
        
        $this->tokens[$token] = [
            'uid' => $uid,
            'iss' => 'firebase-adminsdk-test@test-project.iam.gserviceaccount.com',
            'sub' => $uid,
            'aud' => 'test-project',
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour
            'claims' => $claims,
        ];

        $this->recordOperation('createCustomToken', $uid);
        return $token;
    }

    protected function listUsers(int $maxResults = 1000, ?string $pageToken = null): array
    {
        $users = array_values($this->users);
        $start = $pageToken ? (int) $pageToken : 0;
        $end = min($start + $maxResults, count($users));
        
        $pageUsers = array_slice($users, $start, $maxResults);
        $userRecords = array_map([$this, 'createMockUserRecord'], $pageUsers);

        $this->recordOperation('listUsers', null);

        return [
            'users' => $userRecords,
            'pageToken' => $end < count($users) ? (string) $end : null,
        ];
    }

    protected function setCustomUserClaims(string $uid, array $claims): void
    {
        if (!isset($this->users[$uid])) {
            throw new \Kreait\Firebase\Exception\Auth\UserNotFound("User with UID '{$uid}' not found");
        }

        $this->users[$uid]['customClaims'] = $claims;
        $this->recordOperation('setCustomUserClaims', $uid);
    }

    protected function createMockUserRecord(array $userData): UserRecord
    {
        $mockUserRecord = Mockery::mock(UserRecord::class);

        $mockUserRecord->shouldReceive('uid')->andReturn($userData['uid']);
        $mockUserRecord->shouldReceive('email')->andReturn($userData['email']);
        $mockUserRecord->shouldReceive('emailVerified')->andReturn($userData['emailVerified']);
        $mockUserRecord->shouldReceive('displayName')->andReturn($userData['displayName']);
        $mockUserRecord->shouldReceive('photoUrl')->andReturn($userData['photoURL']);
        $mockUserRecord->shouldReceive('disabled')->andReturn($userData['disabled']);
        $mockUserRecord->shouldReceive('metadata')->andReturn($userData['metadata']);
        $mockUserRecord->shouldReceive('customClaims')->andReturn($userData['customClaims']);
        $mockUserRecord->shouldReceive('providerData')->andReturn($userData['providerData']);

        return $mockUserRecord;
    }

    protected function generateUserId(): string
    {
        return 'mock_user_' . uniqid() . '_' . random_int(1000, 9999);
    }

    protected function recordOperation(string $operation, ?string $uid): void
    {
        $this->operations[] = [
            'operation' => $operation,
            'uid' => $uid,
            'timestamp' => microtime(true),
        ];
    }

    // Public API methods for testing

    public static function createTestUser(array $userData = []): array
    {
        $instance = static::getInstance();
        $uid = $instance->generateUserId();
        
        $defaultData = [
            'uid' => $uid,
            'email' => 'test@example.com',
            'emailVerified' => true,
            'displayName' => 'Test User',
            'photoURL' => null,
            'disabled' => false,
            'metadata' => [
                'creationTime' => now()->toISOString(),
                'lastSignInTime' => now()->toISOString(),
            ],
            'customClaims' => [],
            'providerData' => [],
        ];

        $userData = array_merge($defaultData, $userData);
        $instance->users[$uid] = $userData;

        return $userData;
    }

    public static function createTestToken(string $uid, array $claims = []): string
    {
        $instance = static::getInstance();
        return $instance->createCustomToken($uid, $claims);
    }

    public static function assertOperationCalled(string $operation, ?string $uid = null): void
    {
        $instance = static::getInstance();
        
        $found = false;
        foreach ($instance->operations as $op) {
            if ($op['operation'] === $operation && 
                ($uid === null || $op['uid'] === $uid)) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $message = "Expected Firebase Auth operation '{$operation}'";
            if ($uid) {
                $message .= " for user '{$uid}'";
            }
            $message .= " was not called.";
            
            throw new \PHPUnit\Framework\AssertionFailedError($message);
        }
    }

    public static function clear(): void
    {
        $instance = static::getInstance();
        $instance->users = [];
        $instance->tokens = [];
        $instance->operations = [];
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getOperations(): array
    {
        return $this->operations;
    }
}
