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
        $this->bootEventDispatcher();
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
        // Auth guard and provider will be registered here
        // This will be implemented in the Auth task
    }

    /**
     * Boot the authentication guard.
     */
    protected function bootAuthGuard(): void
    {
        // Guard registration will be implemented in the Auth task
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
        ];
    }
}
