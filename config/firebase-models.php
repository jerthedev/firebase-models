<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Project Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Firebase project credentials and settings. You can either
    | provide the path to your service account JSON file or set the project ID
    | directly. The package will use these settings to connect to Firebase.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'credentials' => env('FIREBASE_CREDENTIALS'),

    /*
    |--------------------------------------------------------------------------
    | Operating Mode
    |--------------------------------------------------------------------------
    |
    | Choose between 'cloud' and 'sync' modes:
    | - cloud: All operations go directly to Firestore
    | - sync: Mirror data between Firestore and local database
    |
    */

    'mode' => env('FIREBASE_MODE', 'cloud'),

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching to improve performance and reduce Firestore read costs.
    | You can enable request-level caching and optionally use a persistent
    | cache store like Redis or Memcached.
    |
    */

    'cache' => [
        'enabled' => env('FIREBASE_CACHE_ENABLED', true),

        'request_enabled' => env('FIREBASE_CACHE_REQUEST_ENABLED', true),

        'persistent_enabled' => env('FIREBASE_CACHE_PERSISTENT_ENABLED', true),

        'store' => env('FIREBASE_CACHE_STORE', 'redis'),

        'ttl' => env('FIREBASE_CACHE_TTL', 300), // 5 minutes

        'prefix' => env('FIREBASE_CACHE_PREFIX', 'firebase_models'),

        'auto_promote' => env('FIREBASE_CACHE_AUTO_PROMOTE', true),

        'max_items' => env('FIREBASE_CACHE_MAX_ITEMS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Mode Configuration
    |--------------------------------------------------------------------------
    |
    | When using sync mode, configure how data synchronization should work
    | between Firestore and your local database.
    |
    */

    'sync' => [
        'strategy' => env('FIREBASE_SYNC_STRATEGY', 'one_way'),

        'conflict_policy' => env('FIREBASE_SYNC_CONFLICT_POLICY', 'last_write_wins'),

        'batch_size' => env('FIREBASE_SYNC_BATCH_SIZE', 100),

        'read_strategy' => env('FIREBASE_SYNC_READ_STRATEGY', 'local_first'),

        'write_strategy' => env('FIREBASE_SYNC_WRITE_STRATEGY', 'both'),

        'timeout' => env('FIREBASE_SYNC_TIMEOUT', 300),

        'retry_attempts' => env('FIREBASE_SYNC_RETRY_ATTEMPTS', 3),

        'retry_delay' => env('FIREBASE_SYNC_RETRY_DELAY', 1000),

        'schedule' => [
            'enabled' => env('FIREBASE_SYNC_SCHEDULE_ENABLED', false),
            'frequency' => env('FIREBASE_SYNC_FREQUENCY', 'hourly'),
            'collections' => env('FIREBASE_SYNC_COLLECTIONS', ''),
        ],

        'mappings' => [
            // Example mapping configuration
            // 'posts' => [
            //     'table' => 'posts',
            //     'columns' => [
            //         'title' => 'title',
            //         'content' => 'body',
            //         'author_id' => 'user_id',
            //     ],
            // ],
        ],

        'auto_create_tables' => env('FIREBASE_SYNC_AUTO_CREATE_TABLES', false),

        'validate_schema' => env('FIREBASE_SYNC_VALIDATE_SCHEMA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Firebase Authentication integration with Laravel's auth system.
    |
    */

    'auth' => [
        'guard' => env('FIREBASE_AUTH_GUARD', 'firebase'),
        
        'provider' => env('FIREBASE_AUTH_PROVIDER', 'firebase_users'),
        
        'user_model' => env('FIREBASE_USER_MODEL', 'App\\Models\\User'),
        
        'token_cache_ttl' => env('FIREBASE_TOKEN_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default query behavior and limits.
    |
    */

    'query' => [
        'default_limit' => env('FIREBASE_QUERY_DEFAULT_LIMIT', 25),
        
        'max_limit' => env('FIREBASE_QUERY_MAX_LIMIT', 1000),
        
        'timeout' => env('FIREBASE_QUERY_TIMEOUT', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for Firebase operations and debugging.
    |
    */

    'logging' => [
        'enabled' => env('FIREBASE_LOGGING_ENABLED', false),
        
        'channel' => env('FIREBASE_LOG_CHANNEL', 'stack'),
        
        'level' => env('FIREBASE_LOG_LEVEL', 'info'),
    ],
];
