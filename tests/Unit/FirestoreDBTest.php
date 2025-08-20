<?php

use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\FirestoreQueryBuilder;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;

describe('FirestoreDB Facade', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    describe('Basic Operations', function () {
        it('can create a query builder for a collection', function () {
            $builder = FirestoreDB::table('users');
            
            expect($builder)->toBeInstanceOf(FirestoreQueryBuilder::class);
        });

        it('can access the underlying client', function () {
            $client = FirestoreDB::client();
            
            expect($client)->toBeInstanceOf(\Google\Cloud\Firestore\FirestoreClient::class);
        });

        it('can get a collection reference', function () {
            $collection = FirestoreDB::collection('users');
            
            expect($collection)->toBeInstanceOf(\Google\Cloud\Firestore\CollectionReference::class);
        });

        it('can get a document reference', function () {
            $document = FirestoreDB::document('users/123');
            
            expect($document)->toBeInstanceOf(\Google\Cloud\Firestore\DocumentReference::class);
        });
    });

    describe('Query Building', function () {
        it('can build where queries', function () {
            $builder = FirestoreDB::table('users')
                ->where('active', '==', true);
            
            expect($builder)->toBeInstanceOf(FirestoreQueryBuilder::class);
            expect($builder->wheres)->toHaveCount(1);
            expect($builder->wheres[0]['field'])->toBe('active');
            expect($builder->wheres[0]['operator'])->toBe('==');
            expect($builder->wheres[0]['value'])->toBe(true);
        });

        it('can build orderBy queries', function () {
            $builder = FirestoreDB::table('users')
                ->orderBy('created_at', 'desc');
            
            expect($builder->orders)->toHaveCount(1);
            expect($builder->orders[0]['field'])->toBe('created_at');
            expect($builder->orders[0]['direction'])->toBe('desc');
        });

        it('can build limit queries', function () {
            $builder = FirestoreDB::table('users')
                ->limit(10);
            
            expect($builder->limitValue)->toBe(10);
        });

        it('can chain query methods', function () {
            $builder = FirestoreDB::table('users')
                ->where('active', '==', true)
                ->orderBy('name')
                ->limit(5);
            
            expect($builder->wheres)->toHaveCount(1);
            expect($builder->orders)->toHaveCount(1);
            expect($builder->limitValue)->toBe(5);
        });
    });

    describe('Document Operations', function () {
        it('can create documents', function () {
            $this->mockFirestoreCreate('users', '123');
            
            $result = FirestoreDB::table('users')->insert([
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]);
            
            expect($result)->toBeTrue();
            $this->assertFirestoreOperationCalled('create', 'users');
        });

        it('can update documents', function () {
            $this->mockFirestoreUpdate('users', '123');
            
            $result = FirestoreDB::table('users')
                ->where('id', '==', '123')
                ->update(['name' => 'Jane Doe']);
            
            expect($result)->toBe(1);
            $this->assertFirestoreOperationCalled('update', 'users', '123');
        });

        it('can delete documents', function () {
            $this->mockFirestoreDelete('users', '123');
            
            $result = FirestoreDB::table('users')
                ->where('id', '==', '123')
                ->delete();
            
            expect($result)->toBe(1);
            $this->assertFirestoreOperationCalled('delete', 'users', '123');
        });
    });

    describe('Data Retrieval', function () {
        beforeEach(function () {
            $this->mockFirestoreQuery('users', [
                ['id' => '1', 'name' => 'John Doe', 'active' => true],
                ['id' => '2', 'name' => 'Jane Smith', 'active' => false],
                ['id' => '3', 'name' => 'Bob Johnson', 'active' => true],
            ]);
        });

        it('can get all documents', function () {
            $results = FirestoreDB::table('users')->get();
            
            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($results)->toHaveCount(3);
        });

        it('can get first document', function () {
            $result = FirestoreDB::table('users')->first();
            
            expect($result)->not->toBeNull();
            expect($result->name)->toBe('John Doe');
        });

        it('can count documents', function () {
            $count = FirestoreDB::table('users')->count();
            
            expect($count)->toBe(3);
        });

        it('can check if documents exist', function () {
            $exists = FirestoreDB::table('users')
                ->where('name', '==', 'John Doe')
                ->exists();
            
            expect($exists)->toBeTrue();
            
            $notExists = FirestoreDB::table('users')
                ->where('name', '==', 'Nonexistent')
                ->exists();
            
            expect($notExists)->toBeFalse();
        });
    });

    describe('Batch Operations', function () {
        it('can perform batch writes', function () {
            $batch = FirestoreDB::batch();
            
            expect($batch)->toBeInstanceOf(\Google\Cloud\Firestore\WriteBatch::class);
        });

        it('can run transactions', function () {
            $result = FirestoreDB::runTransaction(function ($transaction) {
                // Mock transaction operations
                return 'success';
            });
            
            expect($result)->toBe('success');
        });
    });

    describe('Error Handling', function () {
        it('handles invalid collection names gracefully', function () {
            expect(fn() => FirestoreDB::table(''))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('handles invalid document paths gracefully', function () {
            expect(fn() => FirestoreDB::document('invalid'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('Configuration', function () {
        it('uses correct project ID', function () {
            $projectId = FirestoreDB::getProjectId();
            
            expect($projectId)->toBe('test-project');
        });

        it('can check if in mock mode', function () {
            $isMocked = FirestoreDB::isMocked();
            
            expect($isMocked)->toBeTrue();
        });
    });
});
