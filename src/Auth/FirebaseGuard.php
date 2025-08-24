<?php

namespace JTD\FirebaseModels\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Throwable;

/**
 * Firebase Authentication Guard for Laravel.
 *
 * This guard integrates Firebase ID token authentication with Laravel's Auth system,
 * providing seamless authentication using Firebase tokens while maintaining
 * Laravel's standard Auth interface.
 */
class FirebaseGuard implements Guard
{
    use GuardHelpers;

    /**
     * The request instance.
     */
    protected Request $request;

    /**
     * The Firebase Auth instance.
     */
    protected FirebaseAuth $firebaseAuth;

    /**
     * The name of the query parameter to check for the token.
     */
    protected string $inputKey;

    /**
     * The name of the header to check for the token.
     */
    protected string $headerKey;

    /**
     * The name of the cookie to check for the token.
     */
    protected string $cookieKey;

    /**
     * Indicates if the logout method has been called.
     */
    protected bool $loggedOut = false;

    /**
     * Create a new authentication guard.
     */
    public function __construct(
        UserProvider $provider,
        Request $request,
        FirebaseAuth $firebaseAuth,
        string $inputKey = 'token',
        string $headerKey = 'Authorization',
        string $cookieKey = 'firebase_token'
    ) {
        $this->provider = $provider;
        $this->request = $request;
        $this->firebaseAuth = $firebaseAuth;
        $this->inputKey = $inputKey;
        $this->headerKey = $headerKey;
        $this->cookieKey = $cookieKey;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        // If we have already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously inefficient.
        if (!is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $token = $this->getTokenForRequest();

        if (!empty($token)) {
            try {
                // Verify the Firebase ID token
                $verifiedIdToken = $this->firebaseAuth->verifyIdToken($token);

                // Get the user from the provider using the Firebase UID
                $user = $this->provider->retrieveById($verifiedIdToken->claims()->get('sub'));

                if ($user && $user instanceof FirebaseAuthenticatable) {
                    // Set the Firebase token on the user model
                    $user->setFirebaseToken($verifiedIdToken);
                }
            } catch (Throwable $e) {
                // Token is invalid or revoked, user remains null
                report($e);
            }
        }

        return $this->user = $user;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials['token'])) {
            return false;
        }

        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($credentials['token']);

            return !is_null($verifiedIdToken);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        if (empty($credentials['token'])) {
            return false;
        }

        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($credentials['token']);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Get or create the user
            $user = $this->provider->retrieveById($uid);

            if (!$user) {
                // If user doesn't exist, try to create from token
                $user = $this->provider->retrieveByCredentials(['token' => $credentials['token']]);
            }

            if ($user) {
                $this->setUser($user);

                if ($user instanceof FirebaseAuthenticatable) {
                    $user->setFirebaseToken($verifiedIdToken);
                }

                return true;
            }
        } catch (Throwable $e) {
            // Token is invalid, authentication failed
        }

        return false;
    }

    /**
     * Log a user into the application without sessions or cookies.
     */
    public function once(array $credentials = []): bool
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->provider->retrieveByCredentials($credentials));

            return true;
        }

        return false;
    }

    /**
     * Log the user out of the application.
     */
    public function logout(): void
    {
        $user = $this->user();

        // Clear the user from the guard
        $this->user = null;
        $this->loggedOut = true;

        // Note: Firebase token revocation would need to be handled client-side
        // or through Firebase Admin SDK if needed
    }

    /**
     * Get the token for the current request.
     */
    public function getTokenForRequest(): ?string
    {
        $token = $this->request->query($this->inputKey);

        if (is_null($token)) {
            $token = $this->request->input($this->inputKey);
        }

        if (is_null($token)) {
            $token = $this->getBearerTokenFromHeader();
        }

        if (is_null($token)) {
            $token = $this->request->cookie($this->cookieKey);
        }

        return $token;
    }

    /**
     * Get the bearer token from the request headers.
     */
    protected function getBearerTokenFromHeader(): ?string
    {
        $header = $this->request->header($this->headerKey);

        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);

            return $token === '' ? '' : $token;
        }

        return null;
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the current request instance.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get the Firebase Auth instance.
     */
    public function getFirebaseAuth(): FirebaseAuth
    {
        return $this->firebaseAuth;
    }

    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id(): mixed
    {
        if ($user = $this->user()) {
            return $user->getAuthIdentifier();
        }

        return null;
    }

    /**
     * Set the current user.
     */
    public function setUser(?Authenticatable $user): static
    {
        $this->user = $user;
        $this->loggedOut = $user === null;

        return $this;
    }

    /**
     * Determine if the guard has a user instance.
     */
    public function hasUser(): bool
    {
        return !is_null($this->user);
    }
}
