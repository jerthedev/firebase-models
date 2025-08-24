<?php

namespace JTD\FirebaseModels\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Verify Firebase Token Middleware.
 *
 * This middleware verifies Firebase ID tokens without requiring full authentication.
 * Useful for optional authentication or token validation scenarios.
 */
class VerifyFirebaseToken
{
    /**
     * The Firebase Auth instance.
     */
    protected FirebaseAuth $firebaseAuth;

    /**
     * Create a new middleware instance.
     */
    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $optional = null): SymfonyResponse
    {
        $token = $this->getTokenFromRequest($request);

        if ($token) {
            try {
                // Verify the Firebase ID token
                $verifiedIdToken = $this->firebaseAuth->verifyIdToken($token);

                // Add the verified token to the request for later use
                $request->attributes->set('firebase_token', $verifiedIdToken);
                $request->attributes->set('firebase_uid', $verifiedIdToken->claims()->get('sub'));

                // Add token claims to request attributes
                $claims = $verifiedIdToken->claims()->all();
                $request->attributes->set('firebase_claims', $claims);
            } catch (\Throwable $e) {
                // If optional parameter is set, continue without authentication
                if ($optional === 'optional') {
                    return $next($request);
                }

                // Otherwise, return unauthorized response
                return response()->json([
                    'error' => 'Invalid Firebase token',
                    'message' => 'The provided Firebase ID token is invalid or expired.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        } else {
            // No token provided
            if ($optional === 'optional') {
                return $next($request);
            }

            return response()->json([
                'error' => 'Missing Firebase token',
                'message' => 'Firebase ID token is required. Please provide it in the Authorization header, query parameter, or request body.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    /**
     * Get the Firebase token from the request.
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        // Check Authorization header first (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check query parameter
        $token = $request->query('token');
        if ($token) {
            return $token;
        }

        // Check request input
        $token = $request->input('token');
        if ($token) {
            return $token;
        }

        // Check cookie
        $token = $request->cookie('firebase_token');
        if ($token) {
            return $token;
        }

        return null;
    }
}
