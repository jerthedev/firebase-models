<?php

// Create stub classes for missing Google Cloud Firestore classes
// This file should be loaded before any tests that use Firestore mocking

namespace Google\Cloud\Firestore {
    if (!class_exists('FirestoreClient')) {
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
        }
    }

    if (!class_exists('CollectionReference')) {
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

    if (!class_exists('DocumentReference')) {
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
}

namespace Kreait\Firebase\Contract {
    if (!interface_exists('Firestore')) {
        interface Firestore
        {
            public function database();
        }
    }
}
