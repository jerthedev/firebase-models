# Architecture

Package: jerthedev/firebase-models
Namespace: JTD\\FirebaseModels
Local path: packages/jerthedev/firebase-models
Dependency: kreait/firebase-php

## High-level Overview
The package provides an Eloquent-like developer experience for Firebase services:
- Firestore-backed models via a base `FirestoreModel` class
- A `FirestoreDB` facade wrapping Firestore operations
- Firebase Auth integration with a `FirebaseAuthenticatable` user model and a custom Auth guard/provider
- Two operating modes: Cloud (direct Firestore) and Sync (mirror to local DB), plus an optional caching layer

## Core Components
- FirestoreModel (abstract)
  - Behaves similarly to `Illuminate\Database\Eloquent\Model` where feasible
  - Attributes, casts, fillable/guarded, timestamps-like handling, events, scopes
  - Query-like builder methods translated to Firestore
  - Relationships mapping (document-collection patterns) where practical
- FirestoreDB Facade
  - Laravel-style facade exposing Firestore database operations using kreait SDK
  - Transactions/batch writes abstraction
  - Helpers for collection/doc references, queries, snapshots
- FirebaseAuthenticatable (abstract)
  - Authenticatable user model backed by Firebase Auth
  - Works with custom guard/provider to support `Auth::user()` flows
  - Supports Cloud/Sync modes
- Auth Guard / User Provider
  - Token verification via Firebase (ID tokens)
  - Fetches user claims/profile; maps to model instance
  - Optional sync to local DB (on login / scheduled)
- Modes
  - Cloud: all reads/writes hit Firestore; optional caching to cut read cost/latency
  - Sync: reconcile data between Firestore and a local relational DB; reads may serve from local; writes reconcile to Firestore and local
- Caching Layer
  - Configurable: request cache, optional persistent cache (e.g., Redis)
  - Cache invalidation hooks tied to writes and Firestore listeners (if used)

## Package Layout (proposed)
- src/
  - Contracts/
  - Exceptions/
  - Facades/
    - FirestoreDB.php
  - Auth/
    - FirebaseGuard.php
    - FirebaseUserProvider.php
    - FirebaseAuthenticatable.php
  - Firestore/
    - FirestoreModel.php
    - QueryBuilder.php
    - Repository.php (optional)
  - Cache/
  - Sync/
    - SyncManager.php
    - Strategies/
  - Support/
    - Helpers.php
  - JtdFirebaseModelsServiceProvider.php
- config/
  - firebase-models.php (mode, cache, sync, kreait config references)
- docs/
- tests/
  - Unit/
  - Integration/
  - Fixtures/
  - Mocks/
    - FirebaseMock/ (Firestore + Auth emulation)
- stubs/ (for artisan generators, if any)

## Configuration & Bootstrapping
- Publishable config: `firebase-models.php`
  - mode: cloud|sync
  - caching: enabled, store, ttl
  - sync: strategy, conflict policy, schedules
  - credentials: defer to kreait/firebase-php config/ENV (project id, service account)
- Service provider registers:
  - Kreait SDK clients (Firestore, Auth) bindings
  - Facades and singleton repositories
  - Auth guard/provider via `config/auth.php` augmentation instructions

## Query & Model Features
- Attributes and Casting: mirror Eloquent casts where possible (string/int/bool/array/date); Firestore-native types supported
- Scopes: local/global scopes support translated to Firestore constraints
- Events: creating/created/updating/updated/deleting/deleted/saved/retrieved where applicable
- Pagination: cursor-based pagination mapped to Firestore (limit/order/startAt)
- Soft Deletes: emulation via a `deleted_at` field or state flag when needed
- Relationships: documented patterns (refs/ids) with convenience APIs (belongsTo-like, hasMany-like)
- Transactions/Batch Writes: facade APIs mapped to Firestore atomic operations

## Auth Integration
- Custom Guard
  - Extract/verify Firebase ID token from Authorization header or session/cookie
  - Load user claims; hydrate `FirebaseAuthenticatable` model
  - Optional: sync user to local DB; attach roles/permissions if using Laravel’s authorization
- Middleware examples and config wiring
- Logout/token revocation support via Firebase Admin SDK features where available

## Sync Mode
- SyncManager
  - One-way or bi-directional strategies (source of truth: Firestore)
  - Conflict resolution policies (last-write-wins, vector clocks, timestamps + version fields)
  - Batch sync artisan commands; per-request on-demand sync hooks
- Local schema guidance for mirrored tables

## Caching Strategy
- Request cache via container-scoped store
- Optional persistent cache for doc/collection snapshots keyed by paths and query fingerprints
- Invalidation on writes; optional subscription hooks

## Dependencies
- kreait/firebase-php (Firestore, Auth)
- illuminate/* (support, container, auth, contracts, collections)
- Optional: cache store (redis/memcached) for persistent caching

## Testing Strategy
- Goal: 100% PHP Unit test coverage
- FirebaseMock
  - Emulates Firestore document/collection APIs: queries, ordering, limits, transactions (simplified), snapshots
  - Emulates Firebase Auth: token verification, user records, claims
  - Deterministic and fast for unit tests
- Integration Tests (limited)
  - Small suite against a real Firebase project to validate SDK wiring and critical behaviors
- Test Organization
  - tests/Unit: models, facades, guard/provider, query builder, caching, sync logic
  - tests/Integration: real SDK calls behind feature flags/ENV

## Tooling & Dev Experience
- Artisan commands
  - firebase:sync (one-off, scheduled)
  - firebase:cache:warm / :clear
  - firebase:scaffold model/auth-user (optional)
- Example app snippets in the host Laravel project for manual testing

## Documentation
- Installation and configuration
- Model and query usage guides
- Auth setup (guard/provider) and middleware
- Sync mode and caching recipes
- Limitations and performance tips

## Security & Observability
- IAM/Service account scope guidance
- Token verification and revocation guidance
- Logging strategy for SDK errors; optional Laravel Telescope integration

## Release & Compatibility
- Semantic versioning; Laravel version matrix
- Nova/Nebula compatibility notes if relevant later

