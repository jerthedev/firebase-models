<?php

// Create stub classes for missing Google Cloud Firestore classes
// This file should be loaded before any tests that use Firestore mocking

namespace Google\Cloud\Firestore {
    if (!class_exists('Google\Cloud\Firestore\FirestoreClient')) {
        class FirestoreClient
        {
            public function collection(string $name)
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function document(string $path)
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function batch()
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function runTransaction(callable $updateFunction, array $options = [])
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function bulkWriter(array $options = [])
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function projectId()
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }

            public function databaseId()
            {
                // This is just a stub - should not be called directly
                throw new \Exception('Stub FirestoreClient should not be used directly. Use mocks instead.');
            }
        }
    }

    if (!class_exists('Google\Cloud\Firestore\CollectionReference')) {
        class CollectionReference
        {
            public function document(?string $documentId = null)
            {
                throw new \Exception('Stub CollectionReference should not be used directly. Use mocks instead.');
            }

            public function add(array $fields)
            {
                throw new \Exception('Stub CollectionReference should not be used directly. Use mocks instead.');
            }

            public function documents(array $options = [])
            {
                throw new \Exception('Stub CollectionReference should not be used directly. Use mocks instead.');
            }
        }
    }

    if (!class_exists('Google\Cloud\Firestore\DocumentReference')) {
        class DocumentReference
        {
            public function snapshot(array $options = [])
            {
                throw new \Exception('Stub DocumentReference should not be used directly. Use mocks instead.');
            }

            public function set(array $fields, array $options = [])
            {
                throw new \Exception('Stub DocumentReference should not be used directly. Use mocks instead.');
            }

            public function update(array $fields, array $options = [])
            {
                throw new \Exception('Stub DocumentReference should not be used directly. Use mocks instead.');
            }

            public function delete(array $options = [])
            {
                throw new \Exception('Stub DocumentReference should not be used directly. Use mocks instead.');
            }
        }
    }

    if (!class_exists('Google\Cloud\Firestore\Query')) {
        class Query
        {
            public function where(string $field, string $operator, $value)
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }

            public function orderBy(string $field, string $direction = 'ASC')
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }

            public function limit(int $limit)
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }

            public function offset(int $offset)
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }

            public function documents(array $options = [])
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }

            public function get(array $options = [])
            {
                throw new \Exception('Stub Query should not be used directly. Use mocks instead.');
            }
        }
    }

    if (!class_exists('Google\Cloud\Firestore\QuerySnapshot')) {
        class QuerySnapshot implements \Countable, \IteratorAggregate
        {
            public function size(): int
            {
                throw new \Exception('Stub QuerySnapshot should not be used directly. Use mocks instead.');
            }

            public function isEmpty(): bool
            {
                throw new \Exception('Stub QuerySnapshot should not be used directly. Use mocks instead.');
            }

            public function getIterator(): \Iterator
            {
                throw new \Exception('Stub QuerySnapshot should not be used directly. Use mocks instead.');
            }

            public function rows(): array
            {
                throw new \Exception('Stub QuerySnapshot should not be used directly. Use mocks instead.');
            }

            public function count(): int
            {
                throw new \Exception('Stub QuerySnapshot should not be used directly. Use mocks instead.');
            }
        }
    }

    if (!class_exists('Google\Cloud\Firestore\DocumentSnapshot')) {
        class DocumentSnapshot
        {
            public function exists(): bool
            {
                throw new \Exception('Stub DocumentSnapshot should not be used directly. Use mocks instead.');
            }

            public function id(): string
            {
                throw new \Exception('Stub DocumentSnapshot should not be used directly. Use mocks instead.');
            }

            public function data(): ?array
            {
                throw new \Exception('Stub DocumentSnapshot should not be used directly. Use mocks instead.');
            }

            public function get(string $field)
            {
                throw new \Exception('Stub DocumentSnapshot should not be used directly. Use mocks instead.');
            }

            public function reference()
            {
                throw new \Exception('Stub DocumentSnapshot should not be used directly. Use mocks instead.');
            }
        }
    }
}

namespace Kreait\Firebase\Contract {
    if (!interface_exists('Firestore')) {
        interface Firestore
        {
            public function database();
        }
    }
}
