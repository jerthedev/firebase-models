# Sprint 2 (2 weeks)

Scope: Auth integration, caching layer, Eloquent compatibility features.
Velocity guideline: 4 pts = 1 day; tasks 0.5–6 pts (max 8).

## Week 1

| Task | Description | Points |
| --- | --- | ---: |
| FirebaseAuthenticatable | Base user model, hydrations, claims mapping | 5 |
| Auth guard/provider | Token verification, user resolution, session/cookie support | 5 |
| Middleware and config wiring | Guard registration, config/auth.php instructions | 2 |
| Unit tests for auth | Guard/provider + model tests with FirebaseMock | 3 |

## Week 2

| Task | Description | Points |
| --- | --- | ---: |
| Caching layer (request) | Request-scoped cache for Firestore reads | 3 |
| Persistent cache (optional) | Redis/Memcached integration with invalidation | 4 |
| Eloquent compat: accessors/mutators | getXAttribute/setXAttribute semantics | 3 |
| Eloquent compat: scopes | Local/global scopes support and tests | 3 |
| Docs: Auth & caching | How-to guides for setup and usage | 2 |

## Weekly Summaries
- Week 1: Ship Firebase-based authentication fully integrated with Laravel’s Auth.
- Week 2: Introduce caching and add key Eloquent-like features.

