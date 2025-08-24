# JTD Firebase Models – Project Overview

Package: jerthedev/firebase-models
Namespace: JTD\\FirebaseModels
Local path: packages/jerthedev/firebase-models

## Purpose
A standalone Laravel package that lets developers work with Firebase (Firestore + Firebase Auth) using a familiar, Eloquent-like model experience. It supports two operating modes:
- Cloud mode: Operate directly against Firestore (with optional caching).
- Sync mode: Keep a mirrored copy in a local database for faster queries and offline tolerance while treating Firebase as the source of truth.

## Key Goals
- Provide a base FirestoreModel class that feels like Laravel's Model: attributes, casts, accessors/mutators, scopes, query-like operations, events, etc.
- Expose a FirestoreDB facade wrapping kreait/firebase-php Firestore operations with a Laravel-ish API.
- First-class Firebase Auth integration:
  - A FirebaseAuthenticatable-style User model that plugs into Laravel Auth flows.
  - A custom guard/provider to authenticate against Firebase seamlessly.
- Minimize friction for Laravel developers: config, service providers, facades, and sensible conventions.
- High test coverage with fast, deterministic unit tests and a thin layer of integration tests against a real Firebase project.

## External Dependency
- kreait/firebase-php – the official PHP SDK used to connect to Google Cloud Firestore and Firebase Auth.

## Modes
- Cloud: CRUD and queries go to Firestore; add a configurable cache (per-request/in-memory and optionally persistent) to reduce read costs and latency.
- Sync: Background or on-demand sync between Firestore and a local relational DB. Reads can be served locally; writes update Firestore and reconcile locally.

## Developer Experience
- Models: `class Post extends FirestoreModel { ... }` used much like Eloquent (where possible).
- Auth: `class User extends FirebaseAuthenticatable { ... }` that supports cloud or sync mode and integrates with Laravel's Auth (guards, middleware).
- Facades & Providers: `FirestoreDB` facade and a package service provider auto-registering bindings/config.
- Configuration: Simple env-driven setup for credentials (via kreait), mode selection, cache settings, and sync strategy.

## Compatibility Targets
- Aim to support common Eloquent patterns (attributes, casts, fillable/guarded, timestamps, events, scopes, soft deletes-like semantics where feasible, relationships mapping) while acknowledging Firestore's document/collection model may differ.
- Laravel Auth compatibility (guards, providers, middleware) for typical `Auth::user()` flows using Firebase tokens.

## Documentation & Examples
- Clear install/config instructions, examples for defining models, querying, authentication, caching, and enabling sync.
- Recipes for common tasks (pagination, simple relationships, batch writes/transactions, realtime listeners where available).

## Testing Strategy
- 100% PHP unit test coverage target using a fast FirebaseMock that emulates Firestore/Auth behaviors.
- Limited integration tests against a real Firebase project to verify SDK integration and edge behaviors.

## Roadmap (High-level)
1) Foundation: skeleton package, service provider, config, facades, SDK wiring.
2) FirestoreModel MVP: CRUD, basic queries, casts, events.
3) Auth: guard/provider + FirebaseAuthenticatable User model.
4) Caching: request + optional persistent cache.
5) Sync mode: mirroring engine and conflict handling.
6) Eloquent-compat features: scopes, accessors/mutators, soft-delete semantics, relationships mapping.
7) Tooling & Docs: artisan commands, examples, full docs, and hardening.

## Conventions
- Mirrors conventions used in other JTD packages (admin-panel, cms-blog-system) while remaining installable into any Laravel app via Composer.
- Keep package code inside packages/jerthedev/firebase-models; the host Laravel app is a testbed only.

