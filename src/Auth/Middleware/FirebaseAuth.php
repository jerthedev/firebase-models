<?php

namespace JTD\FirebaseModels\Auth\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Firebase Authentication Middleware.
 *
 * This middleware extends Laravel's built-in Authenticate middleware to provide
 * Firebase-specific authentication handling while maintaining full compatibility
 * with Laravel's auth system.
 */
class FirebaseAuth extends Authenticate
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't redirect - let the exception be thrown
        if ($request->expectsJson()) {
            return null;
        }

        // For web requests, redirect to login page
        return route('login');
    }

    /**
     * Handle an unauthenticated user.
     */
    protected function unauthenticated($request, array $guards)
    {
        // Check if this is a Firebase guard request
        $isFirebaseGuard = in_array('firebase', $guards) ||
                          (empty($guards) && config('auth.defaults.guard') === 'firebase');

        if ($isFirebaseGuard && $request->expectsJson()) {
            // For Firebase API requests, provide specific error message
            throw new AuthenticationException(
                'Firebase authentication required. Please provide a valid Firebase ID token.',
                $guards,
                $this->redirectTo($request)
            );
        }

        // Fall back to parent behavior for other cases
        parent::unauthenticated($request, $guards);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $user = $this->auth->guard($guard)->user();

                // Set the authenticated user for the request
                $this->auth->shouldUse($guard);

                return $user;
            }
        }

        $this->unauthenticated($request, $guards);
    }
}
