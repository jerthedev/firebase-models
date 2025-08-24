<?php

namespace JTD\FirebaseModels\Auth\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Email Is Verified Middleware (Firebase-compatible).
 *
 * This middleware ensures that the authenticated user has verified their email address.
 * It works with both Laravel's built-in email verification and Firebase email verification.
 */
class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        $user = $request->user();

        if (!$user) {
            // No authenticated user
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Authentication required.',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        // Check if user implements MustVerifyEmail interface
        if ($user instanceof MustVerifyEmail) {
            // Use Laravel's built-in email verification check
            if (!$user->hasVerifiedEmail()) {
                return $this->handleUnverifiedEmail($request, $redirectToRoute);
            }
        } else {
            // For Firebase users, check email verification
            if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
                return $this->handleUnverifiedEmail($request, $redirectToRoute);
            }

            // Fallback: check email_verified_at attribute
            if (!$user->email_verified_at) {
                return $this->handleUnverifiedEmail($request, $redirectToRoute);
            }
        }

        return $next($request);
    }

    /**
     * Handle unverified email response.
     */
    protected function handleUnverifiedEmail(Request $request, ?string $redirectToRoute): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Email not verified',
                'message' => 'Your email address is not verified. Please verify your email address to continue.',
            ], 403);
        }

        // Redirect to email verification page
        $route = $redirectToRoute ?: 'verification.notice';

        if (route($route)) {
            return redirect()->route($route);
        }

        // Fallback if verification route doesn't exist
        throw new AuthorizationException('Your email address is not verified.');
    }
}
