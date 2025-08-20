# Artisan Commands

This package provides CLI commands to streamline development and operations.

## Scaffolding

make:firestore-model
- Description: Generate a new Firestore model class extending FirestoreModel
- Usage: php artisan make:firestore-model Post
- Options:
  - --collection=posts   Specify Firestore collection name (default: pluralized)
  - --timestamps         Include created_at/updated_at handling
  - --soft-deletes       Include deleted_at semantics
  - --force              Overwrite existing files
- Output: app/Models/Post.php (by default)

make:firebase-user
- Description: Generate a FirebaseAuthenticatable user model
- Usage: php artisan make:firebase-user User
- Options:
  - --sync               Prepare local DB syncing traits/stubs
  - --force              Overwrite
- Output: app/Models/User.php

## Firebase Setup & Ops

firebase:install
- Description: Publish config, check credentials, and validate connectivity
- Usage: php artisan firebase:install
- Options:
  - --credentials=path/to/service-account.json
  - --project=project-id
- Behavior: publishes config/firebase-models.php, tests kreait clients, prints status

firebase:sync
- Description: Run sync between Firestore and local database (if mode=sync)
- Usage: php artisan firebase:sync
- Options:
  - --once               Run a single pass
  - --collection=posts   Limit to a collection
  - --since=timestamp    Incremental sync since timestamp
  - --dry-run            Report changes without applying

firebase:cache:warm
- Description: Warm cache for common collections/queries
- Usage: php artisan firebase:cache:warm
- Options:
  - --collection=posts   Warm a specific collection
  - --config=preset      Use a preset from config

firebase:cache:clear
- Description: Clear package caches (request/persistent)
- Usage: php artisan firebase:cache:clear
- Options:
  - --collection=posts   Clear cache scope for a collection

## Notes
- Generators create PSR-4 namespaced classes following Laravel conventions
- Commands respect configuration in config/firebase-models.php
- Use --help on any command to view usage details

