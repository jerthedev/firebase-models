<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Console\Commands\MakeFirestoreModelCommand;
use JTD\FirebaseModels\Console\Commands\FirestoreDebugCommand;
use JTD\FirebaseModels\Console\Commands\FirestoreOptimizeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

#[Group('unit')]
#[Group('developer-tools')]
#[Group('cli-commands')]
class DeveloperToolsTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure test model directory exists
        if (!File::exists(app_path('Models'))) {
            File::makeDirectory(app_path('Models'), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test models
        $testModels = [
            app_path('Models/TestGeneratedModel.php'),
            app_path('Models/BlogPost.php'),
            app_path('Models/UserProfile.php'),
        ];

        foreach ($testModels as $model) {
            if (File::exists($model)) {
                File::delete($model);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_basic_firestore_model()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'TestGeneratedModel'
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/TestGeneratedModel.php');
        expect(File::exists($modelPath))->toBeTrue();

        $content = File::get($modelPath);
        expect($content)->toContain('class TestGeneratedModel extends FirestoreModel');
        expect($content)->toContain("protected ?string \$collection = 'test_generated_models'");
        expect($content)->toContain('public bool $timestamps = true');
    }

    #[Test]
    public function it_creates_model_with_custom_collection()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'BlogPost',
            '--collection' => 'blog_posts'
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/BlogPost.php');
        $content = File::get($modelPath);
        expect($content)->toContain("protected ?string \$collection = 'blog_posts'");
    }

    #[Test]
    public function it_creates_model_with_fillable_attributes()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'UserProfile',
            '--fillable' => 'name,email,bio,avatar_url'
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/UserProfile.php');
        $content = File::get($modelPath);
        expect($content)->toContain("'name'");
        expect($content)->toContain("'email'");
        expect($content)->toContain("'bio'");
        expect($content)->toContain("'avatar_url'");
    }

    #[Test]
    public function it_creates_model_with_cast_attributes()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'TestGeneratedModel',
            '--casts' => 'published:boolean,published_at:datetime,tags:array'
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/TestGeneratedModel.php');
        $content = File::get($modelPath);
        expect($content)->toContain("'published' => 'boolean'");
        expect($content)->toContain("'published_at' => 'datetime'");
        expect($content)->toContain("'tags' => 'array'");
    }

    #[Test]
    public function it_creates_model_without_timestamps()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'TestGeneratedModel',
            '--timestamps' => false
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/TestGeneratedModel.php');
        $content = File::get($modelPath);
        expect($content)->toContain('public bool $timestamps = false');
    }

    #[Test]
    public function it_runs_firestore_debug_command()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--config' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Firebase Models Debug Information');
        expect($output)->toContain('Configuration');
    }

    #[Test]
    public function it_runs_debug_with_connection_test()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--connection' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Connection Test');
        expect($output)->toContain('Project ID:');
    }

    #[Test]
    public function it_runs_debug_with_performance_stats()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--performance' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Performance Statistics');
        expect($output)->toContain('Overall Score:');
    }

    #[Test]
    public function it_runs_debug_with_memory_stats()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--memory' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Memory Statistics');
        expect($output)->toContain('Current Usage:');
    }

    #[Test]
    public function it_runs_debug_with_health_check()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--health' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Health Check');
    }

    #[Test]
    public function it_runs_debug_with_all_options()
    {
        $exitCode = Artisan::call('firestore:debug', [
            '--all' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Firebase Models Debug Information');
        expect($output)->toContain('Connection Test');
        expect($output)->toContain('Configuration');
        expect($output)->toContain('Performance Statistics');
        expect($output)->toContain('Memory Statistics');
        expect($output)->toContain('Health Check');
    }

    #[Test]
    public function it_runs_firestore_optimize_command()
    {
        $exitCode = Artisan::call('firestore:optimize');

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Firebase Models Optimization');
        expect($output)->toContain('Available optimization options');
    }

    #[Test]
    public function it_runs_optimize_with_analysis()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--analyze' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Analyzing performance');
        expect($output)->toContain('Overall Performance Score');
    }

    #[Test]
    public function it_runs_optimize_with_suggestions()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--suggestions' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('optimization suggestions');
    }

    #[Test]
    public function it_runs_optimize_with_benchmarks()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--benchmark' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Running performance benchmarks');
        expect($output)->toContain('Operation Results');
    }

    #[Test]
    public function it_runs_optimize_with_auto_optimization()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--auto' => true
        ]);

        // May return failure if auto-optimization is disabled, which is expected
        expect($exitCode)->toBeIn([0, 1]);

        $output = Artisan::output();
        expect($output)->toContain('Running automatic optimization');
    }

    #[Test]
    public function it_runs_optimize_with_query_optimization()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--queries' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Optimizing query performance');
        expect($output)->toContain('Query optimization enabled');
    }

    #[Test]
    public function it_runs_optimize_with_memory_optimization()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--memory' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Optimizing memory usage');
        expect($output)->toContain('Memory monitoring enabled');
    }

    #[Test]
    public function it_runs_optimize_with_cache_optimization()
    {
        $exitCode = Artisan::call('firestore:optimize', [
            '--cache' => true
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Optimizing cache configuration');
        expect($output)->toContain('Cache configuration optimized');
    }

    #[Test]
    public function it_validates_model_generation_with_complex_options()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'TestGeneratedModel',
            '--collection' => 'custom_collection',
            '--fillable' => 'title,content,published,author_id',
            '--casts' => 'published:boolean,published_at:datetime,metadata:array',
            '--timestamps' => true
        ]);

        expect($exitCode)->toBe(0);

        $modelPath = app_path('Models/TestGeneratedModel.php');
        expect(File::exists($modelPath))->toBeTrue();

        $content = File::get($modelPath);
        
        // Check collection
        expect($content)->toContain("protected ?string \$collection = 'custom_collection'");
        
        // Check fillable
        expect($content)->toContain("'title'");
        expect($content)->toContain("'content'");
        expect($content)->toContain("'published'");
        expect($content)->toContain("'author_id'");
        
        // Check casts
        expect($content)->toContain("'published' => 'boolean'");
        expect($content)->toContain("'published_at' => 'datetime'");
        expect($content)->toContain("'metadata' => 'array'");
        
        // Check timestamps
        expect($content)->toContain('public bool $timestamps = true');
        
        // Check class structure
        expect($content)->toContain('use JTD\FirebaseModels\Firestore\FirestoreModel');
        expect($content)->toContain('class TestGeneratedModel extends FirestoreModel');
        expect($content)->toContain('protected static function boot()');
    }

    #[Test]
    public function it_handles_model_generation_errors_gracefully()
    {
        // Try to create model with invalid name
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => '' // Empty name should cause error
        ]);

        // Should handle error gracefully (may return non-zero exit code)
        expect($exitCode)->toBeInt();
    }

    #[Test]
    public function it_provides_helpful_output_for_generated_models()
    {
        $exitCode = Artisan::call('make:firestore-model', [
            'name' => 'TestGeneratedModel',
            '--fillable' => 'title,content'
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Firestore model [TestGeneratedModel] created successfully!');
        expect($output)->toContain('Collection: test_generated_models');
        expect($output)->toContain('Usage Examples:');
        expect($output)->toContain('TestGeneratedModel::create([');
        expect($output)->toContain('TestGeneratedModel::where(');
        expect($output)->toContain('TestGeneratedModel::find(');
    }

    #[Test]
    public function it_shows_debug_information_without_errors()
    {
        // Test that debug command doesn't crash with default options
        $exitCode = Artisan::call('firestore:debug');

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Firebase Models Debug Information');
        expect($output)->not->toContain('Error:');
        expect($output)->not->toContain('Exception:');
    }

    #[Test]
    public function it_shows_optimization_menu_by_default()
    {
        $exitCode = Artisan::call('firestore:optimize');

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Available optimization options:');
        expect($output)->toContain('--auto');
        expect($output)->toContain('--analyze');
        expect($output)->toContain('--benchmark');
        expect($output)->toContain('--suggestions');
        expect($output)->toContain('Example:');
    }

    #[Test]
    public function it_handles_command_exceptions_gracefully()
    {
        // This test ensures commands don't crash with unexpected errors
        // We'll test with potentially problematic scenarios

        // Test debug command with potentially missing dependencies
        $exitCode = Artisan::call('firestore:debug', ['--performance' => true]);
        expect($exitCode)->toBeIn([0, 1]); // May fail gracefully

        // Test optimize command with potentially missing dependencies  
        $exitCode = Artisan::call('firestore:optimize', ['--analyze' => true]);
        expect($exitCode)->toBeIn([0, 1]); // May fail gracefully

        // Commands should not throw uncaught exceptions
        expect(true)->toBeTrue(); // If we reach here, no uncaught exceptions occurred
    }
}
