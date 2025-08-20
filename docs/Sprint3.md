# Sprint 3 (2 weeks)

Scope: Sync mode, transactions/batch ops, relationship patterns.
Velocity guideline: 4 pts = 1 day; tasks 0.5–6 pts (max 8).

## Week 1

| Task | Description | Points |
| --- | --- | ---: |
| SyncManager foundation | One-way sync (Firestore -> local), basic schema guidance | 6 |
| Conflict policies | Last-write-wins, timestamp/version handling | 4 |
| Artisan commands | firebase:sync run and schedule support | 3 |

## Week 2

| Task | Description | Points |
| --- | --- | ---: |
| Transactions abstraction | Firestore transactions mapped; retries | 4 |
| Batch writes | Helper APIs for batch operations | 3 |
| Relationship helpers v1 | belongsTo-like, hasMany-like helpers | 5 |
| Docs: Sync & transactions | Configuration, usage, conflict resolution | 2 |

## Weekly Summaries
- Week 1: Deliver core sync with conflict handling and operational commands.
- Week 2: Add transactions/batch APIs and first relationship helpers.

