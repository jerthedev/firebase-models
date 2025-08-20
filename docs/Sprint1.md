# Sprint 1 (2 weeks)

Scope: Package foundation, FirestoreModel MVP, initial docs. Aggressive timeline with AI-assisted development.
Velocity guideline: 4 pts = 1 day; tasks 0.5–6 pts (max 8).

## Week 1

| Task | Description | Points |
| --- | --- | ---: |
| Package scaffolding | Composer.json, PSR-4, Service Provider, config publish, base directories | 3 |
| Kreait wiring | Bind Firestore/Auth clients; env/config; minimal bootstrap validation | 3 |
| FirestoreDB facade | Create facade + binding; thin wrapper to kreait Firestore | 2 |
| FirestoreModel base (attributes/casts) | Abstract model with attributes, fillable/guarded, casts, timestamps-like handling | 5 |
| Unit test harness | Pest/PHPUnit config, coverage reports, CI workflow | 2 |

## Week 2

| Task | Description | Points |
| --- | --- | ---: |
| FirestoreModel CRUD | create/find/get/update/delete; collection/doc resolution | 5 |
| Query builder v1 | where/orderBy/limit/cursor pagination mapping | 4 |
| Events v1 | creating/created/updating/updated/deleting/deleted/saved/retrieved | 2 |
| Docs v1 | Project overview, architecture, installation, model quickstart | 2 |
| FirebaseMock v1 | In-memory Firestore/Auth emulator for unit tests | 5 |

## Weekly Summaries
- Week 1: Establish package skeleton, SDK wiring, foundational model structure, and CI/testing baseline.
- Week 2: Deliver MVP model capabilities with events and docs; land first version of FirebaseMock.

