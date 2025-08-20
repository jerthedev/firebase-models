# Configuration

This package uses kreait/firebase-php for Firestore and Firebase Auth. Configure via env and a publishable config file.

## Requirements
- PHP and Laravel versions per package composer constraints
- Service account credentials for Firebase (JSON)
- Firestore enabled; Authentication configured in Firebase console

## Installation (high level)
- Require the package via Composer (to be published on Packagist as jerthedev/firebase-models)
- Laravel auto-discovery should register the service provider; if not, add it manually

## Environment Variables
Set these in your .env (or equivalent secret storage):

- FIREBASE_CREDENTIALS=storage/app/firebase-service-account.json
- FIREBASE_PROJECT_ID=your-project-id
- FIREBASE_DEFAULT_TTL=300
- FIREBASE_MODE=cloud   # or sync
- FIREBASE_CACHE_STORE=redis   # optional

Note: You can also rely on kreait’s default env vars; we’ll read from either the package config or kreait’s config.

## Config Publishing
After installing the package, publish the config file:

- php artisan vendor:publish --tag=firebase-models-config

This creates config/firebase-models.php with keys like:

- mode: cloud|sync
- cache: enabled, store, ttl
- sync: strategy, conflict policy, schedules
- credentials: path or JSON content reference, project id

## Service Provider
The package’s service provider (JtdFirebaseModelsServiceProvider) will:
- Bind Kreait Firestore and Auth clients
- Register the FirestoreDB facade binding
- Merge default config

If auto-discovery is disabled, add to config/app.php providers:
- JTD\FirebaseModels\JtdFirebaseModelsServiceProvider::class

## Auth Guard and Provider
Add a guard and provider in config/auth.php (example):

- 'guards' => [
  'firebase' => [
    'driver' => 'firebase',
    'provider' => 'firebase_users',
    'hash' => false,
  ],
],

- 'providers' => [
  'firebase_users' => [
    'driver' => 'firebase',
    'model' => App\\Models\\User::class, # extends FirebaseAuthenticatable
  ],
],

Update default guard if desired:
- 'defaults' => ['guard' => 'firebase', 'passwords' => 'users']

## Credentials
Place your service account JSON securely (outside web root). Point FIREBASE_CREDENTIALS to it. The provider will load credentials and initialize kreait clients.

## Middleware
For stateless APIs, use bearer tokens; add middleware to extract ID token (Authorization: Bearer <token>) and rely on the guard to verify via Firebase. For session-based flows, configure token exchange or server-side session after verification.

## Caching
Configure request cache and optional persistent cache store via config/firebase-models.php. Use redis or memcached stores for best performance. Invalidation occurs on writes.

## Sync Mode
When FIREBASE_MODE=sync, configure sync.strategy and conflict policy. Provide local schema mappings and schedule artisan sync commands (see docs).
