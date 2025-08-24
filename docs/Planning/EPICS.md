# Epics

1) Foundation & Packaging
- Composer package scaffolding, PSR-4 autoloading, Service Provider
- Publishable config (firebase-models.php), env wiring for kreait
- Bindings for Firestore/Auth clients; FirestoreDB facade
- CI setup, coding standards, static analysis, release automation

2) FirestoreModel Core (MVP)
- Abstract model base with attributes, fillable/guarded, casts, timestamps-like behavior
- Basic CRUD (find/get/create/update/delete)
- Query builder: where/orderBy/limit/cursor pagination
- Events (creating/created/updating/updated/deleting/deleted/saved/retrieved)

3) Eloquent Compatibility Enhancements
- Accessors/Mutators (getXAttribute/setXAttribute)
- Global/local scopes
- Soft delete semantics (deleted_at)
- Relationship patterns (belongsTo-like, hasMany-like via refs/ids)
- Collections and pagination helpers

4) Firebase Auth Integration
- FirebaseAuthenticatable model
- Custom Auth guard & user provider
- Token verification, session/cookie support
- Middleware, guards config, logout/revocation

5) Caching Layer
- Request-scoped cache
- Optional persistent cache (Redis/Memcached)
- Cache keys & invalidation on writes
- Configurable cache TTL and strategies

6) Sync Mode (Firestore <-> Local DB)
- SyncManager and strategies (one-way, bi-directional)
- Conflict resolution policies
- Schema guidance for mirrored tables
- Artisan commands for sync; scheduling

7) Transactions & Batch Operations
- Firestore transactions abstraction
- Batch writes; retries and error surfaces
- Idempotency guidance

8) Tooling & DX
- Artisan generators (scaffold model, auth user)
- Example snippets in host app
- Helpful exceptions and error messages

9) Documentation
- Installation & configuration
- Model usage guide (CRUD, queries, relations)
- Auth setup and usage
- Caching and Sync recipes
- Limitations & performance tips

10) Testing & Quality
- FirebaseMock for Firestore/Auth
- 100% PHP unit test coverage target
- Limited integration tests with real Firebase project
- Example Playwright flows for demo app (optional)

11) Security & Observability
- IAM/service account setup and advice
- Logging, metrics, and error handling
- Guidance for token revocation, passwordless flows

12) Performance & Cost Optimization
- Read minimization strategies
- Efficient query patterns and indexes
- Cache tuning and sync batching

13) Roadmap Extensions (Post-MVP)
- Realtime listeners/streaming support in PHP where feasible
- Advanced relationship helpers
- Multi-tenant support
- Laravel Scout-like search integration (optional)

