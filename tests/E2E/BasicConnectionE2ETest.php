<?php

namespace JTD\FirebaseModels\Tests\E2E;

use JTD\FirebaseModels\Tests\E2E\BaseE2ETestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Basic connection test for E2E infrastructure.
 * Tests fundamental Firebase connectivity and operations.
 */
#[Group('e2e')]
#[Group('connection')]
class BasicConnectionE2ETest extends BaseE2ETestCase
{
    #[Test]
    public function it_can_connect_to_firebase(): void
    {
        // Test Firebase Factory creation
        $firestore = $this->getFirestore();
        $auth = $this->getFirebaseAuth();

        $this->assertNotNull($firestore);
        $this->assertNotNull($auth);
        
        // Test database reference
        $database = $firestore->database();
        $this->assertNotNull($database);
    }

    #[Test]
    public function it_can_perform_basic_firestore_operations(): void
    {
        $testCollection = $this->getTestCollection('basic_connection_test');
        
        // Create test data
        $testData = [
            'name' => 'E2E Test Document',
            'created_at' => new \DateTime(),
            'active' => true,
            'metadata' => [
                'test_type' => 'basic_connection',
                'environment' => 'e2e'
            ]
        ];
        
        // Test document creation
        $docRef = $this->getFirestore()->database()
            ->collection($testCollection)
            ->add($testData);
        
        $this->assertNotNull($docRef);
        $this->assertNotEmpty($docRef->id());
        
        // Test document read
        $snapshot = $docRef->snapshot();
        $this->assertTrue($snapshot->exists());
        
        $retrievedData = $snapshot->data();
        $this->assertEquals('E2E Test Document', $retrievedData['name']);
        $this->assertTrue($retrievedData['active']);
        $this->assertEquals('basic_connection', $retrievedData['metadata']['test_type']);
        
        // Test document update (using correct API format)
        $docRef->update([
            ['path' => 'updated_at', 'value' => new \DateTime()],
            ['path' => 'active', 'value' => false]
        ]);
        
        $updatedSnapshot = $docRef->snapshot();
        $updatedData = $updatedSnapshot->data();
        $this->assertFalse($updatedData['active']);
        $this->assertArrayHasKey('updated_at', $updatedData);
        
        // Test document deletion
        $docRef->delete();
        
        $deletedSnapshot = $docRef->snapshot();
        $this->assertFalse($deletedSnapshot->exists());
    }

    #[Test]
    public function it_can_perform_basic_queries(): void
    {
        $testCollection = $this->getTestCollection('basic_query_test');
        $collection = $this->getFirestore()->database()->collection($testCollection);
        
        // Create multiple test documents
        $docRefs = [];
        for ($i = 1; $i <= 3; $i++) {
            $docRefs[] = $collection->add([
                'name' => "Test Document {$i}",
                'priority' => $i,
                'category' => 'test',
                'created_at' => new \DateTime()
            ]);
        }
        
        // Test simple query
        $results = $collection->where('category', '=', 'test')->documents();
        $count = 0;
        foreach ($results as $doc) {
            $count++;
            $data = $doc->data();
            $this->assertEquals('test', $data['category']);
        }
        $this->assertEquals(3, $count);
        
        // Test query with ordering (Spark-friendly: single field ordering)
        try {
            $orderedResults = $collection
                ->where('category', '=', 'test')
                ->orderBy('priority', 'DESC')
                ->documents();

            $priorities = [];
            foreach ($orderedResults as $doc) {
                $priorities[] = $doc->data()['priority'];
            }
            $this->assertEquals([3, 2, 1], $priorities);

        } catch (\Google\Cloud\Core\Exception\FailedPreconditionException $e) {
            // If index is required, provide helpful message but don't fail the test
            if (strpos($e->getMessage(), 'requires an index') !== false) {
                $this->markTestSkipped(
                    "Complex query requires Firestore index. This is expected for new Firebase projects.\n" .
                    "The basic E2E connectivity is working correctly.\n" .
                    "To enable complex queries, create the index using the URL in the error message."
                );
            } else {
                throw $e;
            }
        }
        
        // Clean up
        foreach ($docRefs as $docRef) {
            $docRef->delete();
        }
    }
}
