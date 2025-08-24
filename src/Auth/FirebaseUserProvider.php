<?php

namespace JTD\FirebaseModels\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\Auth\InvalidIdToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

/**
 * Firebase User Provider for Laravel Authentication.
 * 
 * This provider integrates Firebase Authentication with Laravel's Auth system,
 * allowing Firebase users to be retrieved and authenticated using Laravel's
 * standard UserProvider interface.
 */
class FirebaseUserProvider implements UserProvider
{
    /**
     * The Firebase Auth instance.
     */
    protected FirebaseAuth $firebaseAuth;

    /**
     * The Eloquent user model.
     */
    protected string $model;

    /**
     * The hasher implementation (not used for Firebase auth).
     */
    protected Hasher $hasher;

    /**
     * Create a new Firebase user provider.
     */
    public function __construct(FirebaseAuth $firebaseAuth, string $model, Hasher $hasher)
    {
        $this->firebaseAuth = $firebaseAuth;
        $this->model = $model;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        try {
            // Get user from Firebase Auth
            $firebaseUser = $this->firebaseAuth->getUser($identifier);
            
            // Create or update local user model
            $user = $this->createModel();
            $user->setFirebaseUserRecord($firebaseUser);
            
            // Try to load additional data from Firestore if the model supports it
            if (method_exists($user, 'find')) {
                $existingUser = $user->find($identifier);
                if ($existingUser) {
                    // Merge Firebase data with existing Firestore data
                    $existingUser->setFirebaseUserRecord($firebaseUser);
                    return $existingUser;
                }
            }
            
            return $user;
        } catch (UserNotFound $e) {
            return null;
        }
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Firebase doesn't use remember tokens, so we just retrieve by ID
        return $this->retrieveById($identifier);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Firebase doesn't use remember tokens, so this is a no-op
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['token'])) {
            return null;
        }

        try {
            // Verify the Firebase ID token
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($credentials['token']);
            $uid = $verifiedIdToken->claims()->get('sub');
            
            // Try to get existing user first
            $user = $this->retrieveById($uid);
            
            if ($user) {
                // Set the token on the existing user
                if ($user instanceof FirebaseAuthenticatable) {
                    $user->setFirebaseToken($verifiedIdToken);
                }
                return $user;
            }
            
            // If user doesn't exist, create a new one from the token
            $user = $this->createModel();
            
            if ($user instanceof FirebaseAuthenticatable) {
                $user->hydrateFromFirebaseToken($verifiedIdToken);
                
                // Try to get additional user data from Firebase Auth
                try {
                    $firebaseUser = $this->firebaseAuth->getUser($uid);
                    $user->setFirebaseUserRecord($firebaseUser);
                } catch (UserNotFound $e) {
                    // User might be new, continue with token data only
                }
            }
            
            return $user;
        } catch (InvalidIdToken | RevokedIdToken $e) {
            return null;
        }
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (empty($credentials['token'])) {
            return false;
        }

        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($credentials['token']);
            $tokenUid = $verifiedIdToken->claims()->get('sub');
            
            // Check if the token UID matches the user's UID
            return $user->getAuthIdentifier() === $tokenUid;
        } catch (InvalidIdToken | RevokedIdToken | FailedToVerifyToken $e) {
            return false;
        }
    }

    /**
     * Rehash the user's password if required and supported.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Firebase handles password hashing, so this is a no-op
    }

    /**
     * Create a new instance of the model.
     */
    public function createModel(): FirebaseAuthenticatable
    {
        $class = '\\' . ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the name of the Eloquent user model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model.
     */
    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the Firebase Auth instance.
     */
    public function getFirebaseAuth(): FirebaseAuth
    {
        return $this->firebaseAuth;
    }

    /**
     * Get the hasher implementation.
     */
    public function getHasher(): Hasher
    {
        return $this->hasher;
    }

    /**
     * Create a user in Firebase Auth and optionally in Firestore.
     */
    public function createUser(array $userData): ?Authenticatable
    {
        try {
            // Create user in Firebase Auth
            $userProperties = [];
            
            if (isset($userData['email'])) {
                $userProperties['email'] = $userData['email'];
            }
            
            if (isset($userData['name'])) {
                $userProperties['displayName'] = $userData['name'];
            }
            
            if (isset($userData['photo_url'])) {
                $userProperties['photoUrl'] = $userData['photo_url'];
            }
            
            if (isset($userData['phone_number'])) {
                $userProperties['phoneNumber'] = $userData['phone_number'];
            }
            
            if (isset($userData['email_verified'])) {
                $userProperties['emailVerified'] = $userData['email_verified'];
            }
            
            if (isset($userData['password'])) {
                $userProperties['password'] = $userData['password'];
            }

            $firebaseUser = $this->firebaseAuth->createUser($userProperties);
            
            // Create local user model
            $user = $this->createModel();
            $user->setFirebaseUserRecord($firebaseUser);
            
            // Save additional data to Firestore if supported
            if (method_exists($user, 'save')) {
                // Fill any additional attributes
                $additionalData = array_diff_key($userData, array_flip([
                    'email', 'name', 'photo_url', 'phone_number', 'email_verified', 'password'
                ]));
                
                if (!empty($additionalData)) {
                    $user->fill($additionalData);
                }
                
                $user->save();
            }
            
            return $user;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Update a user in Firebase Auth and optionally in Firestore.
     */
    public function updateUser(Authenticatable $user, array $userData): bool
    {
        try {
            $uid = $user->getAuthIdentifier();
            
            // Update Firebase Auth user
            $userProperties = [];
            
            if (isset($userData['email'])) {
                $userProperties['email'] = $userData['email'];
            }
            
            if (isset($userData['name'])) {
                $userProperties['displayName'] = $userData['name'];
            }
            
            if (isset($userData['photo_url'])) {
                $userProperties['photoUrl'] = $userData['photo_url'];
            }
            
            if (isset($userData['phone_number'])) {
                $userProperties['phoneNumber'] = $userData['phone_number'];
            }
            
            if (isset($userData['email_verified'])) {
                $userProperties['emailVerified'] = $userData['email_verified'];
            }
            
            if (!empty($userProperties)) {
                $this->firebaseAuth->updateUser($uid, $userProperties);
            }
            
            // Update local user model if supported
            if (method_exists($user, 'update')) {
                $user->update($userData);
            }
            
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Delete a user from Firebase Auth and optionally from Firestore.
     */
    public function deleteUser(Authenticatable $user): bool
    {
        try {
            $uid = $user->getAuthIdentifier();
            
            // Delete from Firebase Auth
            $this->firebaseAuth->deleteUser($uid);
            
            // Delete from Firestore if supported
            if (method_exists($user, 'delete')) {
                $user->delete();
            }
            
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }
}
