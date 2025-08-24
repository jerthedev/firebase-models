<?php

namespace JTD\FirebaseModels;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Google\Cloud\Firestore\FirestoreClient;
use JTD\FirebaseModels\Firestore\FirestoreDatabase;
use JTD\FirebaseModels\Auth\FirebaseGuard;
use JTD\FirebaseModels\Auth\FirebaseUserProvider;
use JTD\FirebaseModels\Auth\Middleware\FirebaseAuth;
use JTD\FirebaseModels\Auth\Middleware\VerifyFirebaseToken;
use JTD\FirebaseModels\Auth\Middleware\EnsureEmailIsVerified;
use JTD\FirebaseModels\Cache\Middleware\ClearRequestCache;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Sync\SyncManager;
use JTD\FirebaseModels\Console\Commands\FirebaseSyncCommand;
use JTD\FirebaseModels\Console\Commands\ScheduledSyncCommand;
use JTD\FirebaseModels\Console\Commands\SyncStatusCommand;
use JTD\FirebaseModels\Console\Commands\MakeSyncModelCommand;
use JTD\FirebaseModels\Console\Commands\FirestoreDebugCommand;
use JTD\FirebaseModels\Console\Commands\FirestoreOptimizeCommand;
use JTD\FirebaseModels\Console\Commands\MakeFirestoreModelCommand;

class JtdFirebaseModelsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/firebase-models.php',
            'firebase-models'
        );

        $this->registerFirebaseClients();
        $this->registerFacades();
        $this->registerAuthComponents();
        $this->registerSyncComponents();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/firebase-models.php' => config_path('firebase-models.php'),
        ], 'firebase-models-config');

        $this->bootAuthGuard();
        $this->registerMiddleware();
        $this->configureCaching();
        $this->bootEventDispatcher();
        $this->registerCommands();
        $this->validateConfiguration();
    }

    /**
     * Register Firebase SDK clients.
     */
    protected function registerFirebaseClients(): void
    {
        $this->app->singleton('firebase.factory', function ($app) {
            $config = $app['config']['firebase-models'];

            $factory = new Factory();

            // Set up service account credentials
            $credentials = $config['credentials'] ?? env('FIREBASE_CREDENTIALS');
            if (!empty($credentials)) {
                $factory = $factory->withServiceAccount($credentials);
            }

            // Set up project ID
            $projectId = $config['project_id'] ?? env('FIREBASE_PROJECT_ID');
            if (!empty($projectId)) {
                $factory = $factory->withProjectId($projectId);
            }

            return $factory;
        });

        $this->app->singleton(Firestore::class, function ($app) {
            return $app['firebase.factory']->createFirestore();
        });

        $this->app->singleton(FirebaseAuthContract::class, function ($app) {
            return $app['firebase.factory']->createAuth();
        });

        // Register the Google Cloud FirestoreClient
        $this->app->singleton(FirestoreClient::class, function ($app) {
            return $app[Firestore::class]->database();
        });

        // Register cache manager
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager();
        });

        // Maintain backward compatibility aliases
        $this->app->alias(Firestore::class, 'firebase.firestore');
        $this->app->alias(FirebaseAuthContract::class, 'firebase.auth');
    }

    /**
     * Register facade bindings.
     */
    protected function registerFacades(): void
    {
        $this->app->singleton('firestore.db', function ($app) {
            return new FirestoreDatabase($app[Firestore::class]);
        });
    }

    /**
     * Register authentication components.
     */
    protected function registerAuthComponents(): void
    {
        // Register the Firebase user provider
        Auth::provider('firebase', function ($app, array $config) {
            return new FirebaseUserProvider(
                $app[FirebaseAuthContract::class],
                $config['model'],
                $app['hash']
            );
        });
    }

    /**
     * Register sync components.
     */
    protected function registerSyncComponents(): void
    {
        // Register the SyncManager
        $this->app->singleton(SyncManager::class, function ($app) {
            $config = $app['config']['firebase-models.sync'] ?? [];
            return new SyncManager($config);
        });

        // Register sync manager alias
        $this->app->alias(SyncManager::class, 'firebase.sync');
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FirebaseSyncCommand::class,
                ScheduledSyncCommand::class,
                SyncStatusCommand::class,
                MakeSyncModelCommand::class,
                FirestoreDebugCommand::class,
                FirestoreOptimizeCommand::class,
                MakeFirestoreModelCommand::class,
            ]);
        }
    }

    /**
     * Boot the authentication guard.
     */
    protected function bootAuthGuard(): void
    {
        // Register the Firebase guard
        Auth::extend('firebase', function ($app, $name, array $config) {
            return new FirebaseGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $app[FirebaseAuthContract::class],
                $config['input_key'] ?? 'token',
                $config['header_key'] ?? 'Authorization',
                $config['cookie_key'] ?? 'firebase_token'
            );
        });
    }

    /**
     * Register Firebase authentication middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        // Register middleware aliases
        $router->aliasMiddleware('firebase.auth', FirebaseAuth::class);
        $router->aliasMiddleware('firebase.token', VerifyFirebaseToken::class);
        $router->aliasMiddleware('firebase.verified', EnsureEmailIsVerified::class);
        $router->aliasMiddleware('firebase.cache.clear', ClearRequestCache::class);
    }

    /**
     * Configure caching system.
     */
    protected function configureCaching(): void
    {
        // Configure request cache settings
        $maxItems = config('firebase-models.cache.max_items', 1000);
        RequestCache::setMaxItems($maxItems);

        // Configure persistent cache settings
        $store = config('firebase-models.cache.store');
        $ttl = config('firebase-models.cache.ttl', 3600);
        $prefix = config('firebase-models.cache.prefix', 'firestore');

        PersistentCache::setDefaultStore($store);
        PersistentCache::setDefaultTtl($ttl);
        PersistentCache::setKeyPrefix($prefix);

        // Get global cache enabled setting
        $globalEnabled = config('firebase-models.cache.enabled', true);

        // Determine individual cache component states
        // If global cache is disabled, disable both components regardless of individual settings
        $requestEnabled = $globalEnabled && config('firebase-models.cache.request_enabled', true);
        $persistentEnabled = $globalEnabled && config('firebase-models.cache.persistent_enabled', true);

        // Configure cache manager with proper enabled states
        CacheManager::configure([
            'request_cache_enabled' => $requestEnabled,
            'persistent_cache_enabled' => $persistentEnabled,
            'default_ttl' => $ttl,
            'default_store' => $store,
            'auto_promote' => config('firebase-models.cache.auto_promote', true),
        ]);

        // Enable/disable individual cache components
        if ($requestEnabled) {
            RequestCache::enable();
        } else {
            RequestCache::disable();
        }

        if ($persistentEnabled) {
            PersistentCache::enable();
        } else {
            PersistentCache::disable();
        }

        // Automatically add cache clearing middleware to web routes
        if (config('firebase-models.cache.auto_clear', true)) {
            $this->app['router']->pushMiddlewareToGroup('web', ClearRequestCache::class);
        }
    }

    /**
     * Boot the event dispatcher for FirestoreModel.
     */
    protected function bootEventDispatcher(): void
    {
        \JTD\FirebaseModels\Firestore\FirestoreModel::setEventDispatcher(
            $this->app['events']
        );
    }

    /**
     * Validate the package configuration.
     */
    protected function validateConfiguration(): void
    {
        $config = $this->app['config']['firebase-models'];

        // Skip validation in testing environment or mock mode
        if ($this->app->environment('testing') || ($config['mock_mode'] ?? false)) {
            return;
        }

        // Check for project ID
        $projectId = $config['project_id'] ?? env('FIREBASE_PROJECT_ID');
        if (empty($projectId)) {
            throw new \InvalidArgumentException(
                'Firebase project ID must be configured. Set FIREBASE_PROJECT_ID in your .env file or configure it in firebase-models.php'
            );
        }

        // Check for credentials
        $credentials = $config['credentials'] ?? env('FIREBASE_CREDENTIALS');
        if (empty($credentials)) {
            throw new \InvalidArgumentException(
                'Firebase credentials must be configured. Set FIREBASE_CREDENTIALS in your .env file or configure it in firebase-models.php'
            );
        }

        // Validate credentials file exists if it's a file path
        if (is_string($credentials) && !is_array($credentials) && !file_exists($credentials)) {
            throw new \InvalidArgumentException(
                "Firebase credentials file not found at: {$credentials}"
            );
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'firebase.factory',
            Firestore::class,
            FirebaseAuthContract::class,
            FirestoreClient::class,
            'FirestoreDB',
            'firebase.firestore', // backward compatibility
            'firebase.auth', // backward compatibility
            'firestore.db',
            SyncManager::class,
            'firebase.sync',
        ];
    }
}
