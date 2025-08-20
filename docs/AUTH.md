# Authentication Integration

This package integrates Firebase Auth into Laravel’s Auth system via a custom guard and user provider.

## Overview
- FirebaseAuthenticatable: Base user model class for Firebase-backed users
- Guard: 'firebase' guard verifies ID tokens and resolves the authenticated user
- Provider: 'firebase' user provider loads user profiles and hydrates model instances
- Modes: Works in cloud or sync mode

## Setup

1) Install & Configure
- Install the package and publish config (see CONFIGURATION.md)
- Ensure FIREBASE_CREDENTIALS and FIREBASE_PROJECT_ID are set

2) Configure Auth (config/auth.php)

Add a guard:

- 'guards' => [
  'firebase' => [
    'driver' => 'firebase',
    'provider' => 'firebase_users',
    'hash' => false,
  ],
],

Add a provider:

- 'providers' => [
  'firebase_users' => [
    'driver' => 'firebase',
    'model' => App\\Models\\User::class, # extends FirebaseAuthenticatable
  ],
],

Optionally set defaults:

- 'defaults' => [
  'guard' => 'firebase',
  'passwords' => 'users',
],

3) User Model

- Create App\\Models\\User that extends JTD\\FirebaseModels\\Auth\\FirebaseAuthenticatable
- Add any profile fields/claims mapping you require

## Token Verification Flow
- Client obtains Firebase ID token (signInWith... in frontend)
- For API requests: send Authorization: Bearer <ID_TOKEN>
- The firebase guard:
  - Extracts token from header/cookie
  - Verifies signature and expiration via kreait Auth
  - Loads user record/claims
  - Hydrates FirebaseAuthenticatable model (cloud or sync depending on config)
  - Optionally syncs to local DB (if sync mode enabled)

## Middleware
- Use 'auth:firebase' on routes to require authentication
- For APIs, pair with 'throttle' and other standard middleware
- Example:

Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/me', fn() => Auth::user());
});

## Sessions vs Stateless
- Stateless APIs: prefer bearer tokens per request
- Web sessions: after verifying an ID token, you may establish a Laravel session if desired. Ensure token refresh/invalidations are handled (revoke refresh tokens via Firebase if necessary)

## Logout & Revocation
- To log out on the server side for session-based flows, invalidate the Laravel session
- For token-based flows, instruct client to signOut and discard tokens
- Admin-initiated revocation can be done via Firebase Admin SDK; the guard can optionally check token revocation timestamps

## Roles & Permissions
- Map custom claims to roles/abilities
- Use Laravel Gates/Policies based on those claims or synced database roles

## Error Handling
- Return 401 on invalid/expired tokens
- Provide structured error responses; avoid leaking sensitive details

## Testing
- Use FirebaseMock to simulate token verification and user records
- Feature tests can use the 'firebase' guard with mocked tokens/claims

