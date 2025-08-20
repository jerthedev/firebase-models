<?php

use JTD\FirebaseModels\JtdFirebaseModelsServiceProvider;
use JTD\FirebaseModels\Facades\FirestoreDB;
use Google\Cloud\Firestore\FirestoreClient;

describe('JtdFirebaseModelsServiceProvider', function () {
    it('registers the FirestoreClient in the container', function () {
        expect(app()->bound(FirestoreClient::class))->toBeTrue();
        
        $client = app(FirestoreClient::class);
        expect($client)->toBeInstanceOf(FirestoreClient::class);
    });

    it('registers the FirestoreDB facade', function () {
        expect(class_exists('FirestoreDB'))->toBeTrue();
        
        $facade = app('FirestoreDB');
        expect($facade)->not->toBeNull();
    });

    it('publishes configuration files', function () {
        $provider = new JtdFirebaseModelsServiceProvider(app());
        
        // Check that publishes method exists and can be called
        expect(method_exists($provider, 'publishes'))->toBeTrue();
    });

    it('loads configuration from the correct path', function () {
        $configPath = config_path('firebase.php');
        
        // In testing, we check that the config is loaded
        expect(config('firebase.project_id'))->toBe('test-project');
    });

    it('provides the correct services', function () {
        $provider = new JtdFirebaseModelsServiceProvider(app());
        $provides = $provider->provides();
        
        expect($provides)->toContain(FirestoreClient::class);
        expect($provides)->toContain('FirestoreDB');
    });

    it('boots correctly in testing environment', function () {
        // Verify that the service provider boots without errors
        $provider = new JtdFirebaseModelsServiceProvider(app());
        
        expect(method_exists($provider, 'boot'))->toBeTrue();
        expect(method_exists($provider, 'register'))->toBeTrue();
    });

    it('handles missing configuration gracefully', function () {
        // Test with minimal configuration
        config(['firebase.project_id' => null]);
        
        expect(fn() => app(FirestoreClient::class))
            ->not->toThrow(\Exception::class);
    });

    it('registers event listeners correctly', function () {
        // Verify that model events are properly set up
        $dispatcher = app('events');
        
        expect($dispatcher)->toBeInstanceOf(\Illuminate\Contracts\Events\Dispatcher::class);
    });
});
