<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockV2;
use JTD\FirebaseModels\Tests\Helpers\FieldTransforms;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use JTD\FirebaseModels\Facades\FirestoreDB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
#[Group('mock')]
#[Group('firebase-mock-v2')]
class FirebaseMockV2Test extends UnitTestSuite
{
    protected FirestoreMockV2 $mockV2;

    protected function setUp(): void
    {
        // Configure test requirements for FirebaseMock v2 testing
        $this->setTestRequirements([
            'document_count' => 1000,
            'memory_constraint' => false,
            'needs_full_mockery' => true,
        ]);

        parent::setUp();

        // Clear the default mock and initialize FirestoreMockV2
        \JTD\FirebaseModels\Tests\Helpers\FirestoreMock::clear();
        FirestoreMockV2::initialize();
        $this->mockV2 = FirestoreMockV2::getInstance();
    }

    protected function tearDown(): void
    {
        FirestoreMockV2::clear();
        parent::tearDown();
    }

    #[Test]
    public function it_supports_advanced_query_operators()
    {
        // Setup test data
        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'title' => 'Post 1',
                'tags' => ['php', 'laravel', 'firebase'],
                'categories' => ['web', 'backend'],
                'status' => 'published'
            ]),
            TestDataFactory::createPost([
                'id' => '2', 
                'title' => 'Post 2',
                'tags' => ['javascript', 'react', 'frontend'],
                'categories' => ['web', 'frontend'],
                'status' => 'draft'
            ]),
            TestDataFactory::createPost([
                'id' => '3',
                'title' => 'Post 3', 
                'tags' => ['python', 'django'],
                'categories' => ['backend'],
                'status' => 'published'
            ])
        ];

        foreach ($testData as $post) {
            $this->mockV2->storeDocument('posts', $post['id'], $post);
        }

        $collection = FirestoreDB::collection('posts');

        // Test array-contains-any
        $query1 = $collection->where('tags', 'array-contains-any', ['php', 'javascript']);
        $results1 = $query1->documents();
        expect($results1)->toHaveCount(2);

        // Test not-in operator
        $query2 = $collection->where('status', 'not-in', ['archived', 'deleted']);
        $results2 = $query2->documents();
        expect($results2)->toHaveCount(3);

        // Test in operator with array
        $query3 = $collection->where('status', 'in', ['published', 'featured']);
        $results3 = $query3->documents();
        expect($results3)->toHaveCount(2);
    }

    #[Test]
    public function it_validates_compound_indexes()
    {
        // Enable strict index validation
        $this->mockV2->enableStrictIndexValidation();

        // Add a compound index
        $this->mockV2->addCompoundIndex('posts', [
            ['field' => 'status', 'order' => 'ASCENDING'],
            ['field' => 'created_at', 'order' => 'DESCENDING']
        ]);

        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'status' => 'published',
                'created_at' => '2024-01-01',
                'title' => 'Post 1'
            ])
        ];

        $this->mockV2->storeDocument('posts', '1', $testData[0]);
        $collection = FirestoreDB::collection('posts');

        // This query should work (has matching index)
        $validQuery = $collection
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'DESC');
        
        $results = $validQuery->documents();
        expect($results)->toHaveCount(1);

        // This query should fail (no matching index)
        $this->expectException(\Google\Cloud\Core\Exception\FailedPreconditionException::class);
        $this->expectExceptionMessage('The query requires an index');
        
        $invalidQuery = $collection
            ->where('status', '=', 'published')
            ->where('title', '=', 'Post 1');
        
        $invalidQuery->documents();
    }

    #[Test]
    public function it_supports_field_transforms()
    {
        $collection = FirestoreDB::collection('posts');
        $docRef = $collection->document('post-1');

        // Test server timestamp
        $docRef->set([
            'title' => 'Test Post',
            'created_at' => FieldTransforms::serverTimestamp(),
            'views' => 0,
            'tags' => ['initial']
        ]);

        // Test increment
        $docRef->update([
            'views' => FieldTransforms::increment(5),
            'likes' => FieldTransforms::increment(1)
        ]);

        // Test array operations
        $docRef->update([
            'tags' => FieldTransforms::arrayUnion(['php', 'laravel']),
            'removed_tags' => FieldTransforms::arrayRemove(['old_tag'])
        ]);

        // Verify transforms were applied
        $snapshot = $docRef->snapshot();
        $data = $snapshot->data();

        expect($data['created_at'])->toBeInstanceOf(\DateTime::class);
        expect($data['views'])->toBe(5);
        expect($data['likes'])->toBe(1);
        expect($data['tags'])->toContain('php');
        expect($data['tags'])->toContain('laravel');
        expect($data['tags'])->toContain('initial');
    }

    #[Test]
    public function it_supports_nested_field_queries()
    {
        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'title' => 'Post 1',
                'metadata' => [
                    'author' => ['name' => 'John Doe', 'role' => 'admin'],
                    'stats' => ['views' => 100, 'likes' => 50]
                ]
            ]),
            TestDataFactory::createPost([
                'id' => '2',
                'title' => 'Post 2', 
                'metadata' => [
                    'author' => ['name' => 'Jane Smith', 'role' => 'editor'],
                    'stats' => ['views' => 200, 'likes' => 75]
                ]
            ])
        ];

        foreach ($testData as $post) {
            $this->mockV2->storeDocument('posts', $post['id'], $post);
        }

        $collection = FirestoreDB::collection('posts');

        // Test nested field query
        $query = $collection->where('metadata.author.role', '=', 'admin');
        $results = $query->documents();
        
        expect($results)->toHaveCount(1);
        expect($results[0]->data()['title'])->toBe('Post 1');

        // Test nested numeric comparison
        $query2 = $collection->where('metadata.stats.views', '>', 150);
        $results2 = $query2->documents();
        
        expect($results2)->toHaveCount(1);
        expect($results2[0]->data()['title'])->toBe('Post 2');
    }

    #[Test]
    public function it_supports_complex_ordering()
    {
        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'title' => 'Post A',
                'priority' => 1,
                'created_at' => '2024-01-01'
            ]),
            TestDataFactory::createPost([
                'id' => '2',
                'title' => 'Post B',
                'priority' => 2,
                'created_at' => '2024-01-02'
            ]),
            TestDataFactory::createPost([
                'id' => '3',
                'title' => 'Post C',
                'priority' => 1,
                'created_at' => '2024-01-03'
            ])
        ];

        foreach ($testData as $post) {
            $this->mockV2->storeDocument('posts', $post['id'], $post);
        }

        $collection = FirestoreDB::collection('posts');

        // Test multiple field ordering
        $query = $collection
            ->orderBy('priority', 'ASC')
            ->orderBy('created_at', 'DESC');
        
        $results = $query->documents();
        
        expect($results)->toHaveCount(3);
        expect($results[0]->data()['title'])->toBe('Post C'); // priority 1, latest date
        expect($results[1]->data()['title'])->toBe('Post A'); // priority 1, earlier date
        expect($results[2]->data()['title'])->toBe('Post B'); // priority 2
    }

    #[Test]
    public function it_handles_null_values_in_queries()
    {
        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'title' => 'Post 1',
                'description' => 'Has description',
                'featured_image' => null
            ]),
            TestDataFactory::createPost([
                'id' => '2',
                'title' => 'Post 2',
                'description' => null,
                'featured_image' => 'image.jpg'
            ])
        ];

        foreach ($testData as $post) {
            $this->mockV2->storeDocument('posts', $post['id'], $post);
        }

        $collection = FirestoreDB::collection('posts');

        // Test querying for null values
        $query1 = $collection->where('description', '=', null);
        $results1 = $query1->documents();
        expect($results1)->toHaveCount(1);
        expect($results1[0]->data()['title'])->toBe('Post 2');

        // Test querying for non-null values
        $query2 = $collection->where('featured_image', '!=', null);
        $results2 = $query2->documents();
        expect($results2)->toHaveCount(1);
        expect($results2[0]->data()['title'])->toBe('Post 2');
    }

    #[Test]
    public function it_provides_helpful_index_error_messages()
    {
        $this->mockV2->enableStrictIndexValidation();

        $testData = [
            TestDataFactory::createPost([
                'id' => '1',
                'status' => 'published',
                'category' => 'tech',
                'created_at' => '2024-01-01'
            ])
        ];

        $this->mockV2->storeDocument('posts', '1', $testData[0]);
        $collection = FirestoreDB::collection('posts');

        try {
            $collection
                ->where('status', '=', 'published')
                ->where('category', '=', 'tech')
                ->documents();
            
            $this->fail('Expected FailedPreconditionException was not thrown');
        } catch (\Google\Cloud\Core\Exception\FailedPreconditionException $e) {
            expect($e->getMessage())->toContain('The query requires an index');
            expect($e->getMessage())->toContain('console.firebase.google.com');
            expect($e->getMessage())->toContain('create_composite');
        }
    }

    #[Test]
    public function it_optimizes_performance_for_large_datasets()
    {
        $this->enableMemoryMonitoring();

        // Create large dataset
        $largeDataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataset[] = TestDataFactory::createPost([
                'id' => "post-{$i}",
                'title' => "Post {$i}",
                'status' => $i % 3 === 0 ? 'published' : 'draft',
                'views' => rand(1, 1000),
                'created_at' => "2024-01-" . str_pad($i % 30 + 1, 2, '0', STR_PAD_LEFT)
            ]);
        }

        // Store documents
        foreach ($largeDataset as $post) {
            $this->mockV2->storeDocument('posts', $post['id'], $post);
        }

        // Test query performance
        $executionTime = $this->benchmark(function () {
            $collection = FirestoreDB::collection('posts');
            $query = $collection
                ->where('status', '=', 'published')
                ->orderBy('views', 'DESC')
                ->limit(10);
            
            return $query->documents();
        });

        expect($executionTime)->toBeLessThan(1.0); // Should be reasonably fast even with 1000 docs
        
        // Verify memory usage is reasonable
        $memoryUsage = memory_get_usage(true);
        expect($memoryUsage)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB for 1000 documents
    }
}
