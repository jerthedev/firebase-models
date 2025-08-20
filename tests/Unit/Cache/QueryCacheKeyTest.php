<?php

use JTD\FirebaseModels\Cache\QueryCacheKey;

describe('QueryCacheKey', function () {
    describe('Basic Key Generation', function () {
        it('generates consistent keys for identical queries', function () {
            $queryData = [
                'wheres' => [['field' => 'status', 'operator' => '=', 'value' => 'active']],
                'orders' => [['field' => 'created_at', 'direction' => 'desc']],
                'limit' => 10,
            ];

            $key1 = QueryCacheKey::generate('users', $queryData);
            $key2 = QueryCacheKey::generate('users', $queryData);

            expect($key1)->toBe($key2);
            expect($key1)->toMatch('/^firestore_query:users:[a-f0-9]{64}$/');
        });

        it('generates different keys for different collections', function () {
            $queryData = ['limit' => 10];

            $key1 = QueryCacheKey::generate('users', $queryData);
            $key2 = QueryCacheKey::generate('posts', $queryData);

            expect($key1)->not->toBe($key2);
        });

        it('generates different keys for different query data', function () {
            $queryData1 = ['limit' => 10];
            $queryData2 = ['limit' => 20];

            $key1 = QueryCacheKey::generate('users', $queryData1);
            $key2 = QueryCacheKey::generate('users', $queryData2);

            expect($key1)->not->toBe($key2);
        });
    });

    describe('Document Key Generation', function () {
        it('generates document cache keys', function () {
            $key = QueryCacheKey::generateForDocument('users', 'user-123');

            expect($key)->toBe('firestore_doc:users:user-123');
        });

        it('generates different keys for different documents', function () {
            $key1 = QueryCacheKey::generateForDocument('users', 'user-123');
            $key2 = QueryCacheKey::generateForDocument('users', 'user-456');

            expect($key1)->not->toBe($key2);
        });
    });

    describe('Count Key Generation', function () {
        it('generates count cache keys', function () {
            $queryData = ['wheres' => [['field' => 'active', 'operator' => '=', 'value' => true]]];
            $key = QueryCacheKey::generateForCount('users', $queryData);

            expect($key)->toMatch('/^firestore_count:users:[a-f0-9]{64}$/');
        });

        it('generates consistent count keys for identical queries', function () {
            $queryData = ['limit' => 100];

            $key1 = QueryCacheKey::generateForCount('users', $queryData);
            $key2 = QueryCacheKey::generateForCount('users', $queryData);

            expect($key1)->toBe($key2);
        });
    });

    describe('Exists Key Generation', function () {
        it('generates exists cache keys', function () {
            $queryData = ['wheres' => [['field' => 'email', 'operator' => '=', 'value' => 'test@example.com']]];
            $key = QueryCacheKey::generateForExists('users', $queryData);

            expect($key)->toMatch('/^firestore_exists:users:[a-f0-9]{64}$/');
        });
    });

    describe('Batch Key Generation', function () {
        it('generates batch cache keys', function () {
            $documentPaths = [
                'users/user-1',
                'users/user-2',
                'posts/post-1',
            ];

            $key = QueryCacheKey::generateForBatch($documentPaths);

            expect($key)->toMatch('/^firestore_batch:[a-f0-9]{64}$/');
        });

        it('generates consistent keys regardless of path order', function () {
            $paths1 = ['users/user-1', 'users/user-2', 'posts/post-1'];
            $paths2 = ['posts/post-1', 'users/user-2', 'users/user-1'];

            $key1 = QueryCacheKey::generateForBatch($paths1);
            $key2 = QueryCacheKey::generateForBatch($paths2);

            expect($key1)->toBe($key2);
        });
    });

    describe('Query Data Normalization', function () {
        it('normalizes nested arrays consistently', function () {
            $queryData1 = [
                'wheres' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
                'orders' => [['field' => 'name', 'direction' => 'asc']],
            ];

            $queryData2 = [
                'orders' => [['field' => 'name', 'direction' => 'asc']],
                'wheres' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
            ];

            $key1 = QueryCacheKey::generate('users', $queryData1);
            $key2 = QueryCacheKey::generate('users', $queryData2);

            // Keys should be the same after normalization (same structure, different key order)
            expect($key1)->toBe($key2);
        });

        it('handles objects in query data', function () {
            $queryData = [
                'wheres' => [
                    ['field' => 'metadata', 'operator' => '=', 'value' => (object) ['key' => 'value']],
                ],
            ];

            $key = QueryCacheKey::generate('users', $queryData);

            expect($key)->toBeString();
            expect($key)->toMatch('/^firestore_query:users:[a-f0-9]{64}$/');
        });
    });

    describe('Key Validation', function () {
        it('validates query keys correctly', function () {
            $validKeys = [
                'firestore_query:users:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
                'firestore_query:posts/comments:abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
                'firestore_doc:users:user-123',
                'firestore_doc:posts/comments:comment-456',
                'firestore_count:users:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
                'firestore_exists:posts:abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
                'firestore_batch:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            ];

            foreach ($validKeys as $key) {
                expect(QueryCacheKey::isValid($key))->toBeTrue();
            }
        });

        it('rejects invalid keys', function () {
            $invalidKeys = [
                'invalid_key',
                'firestore_query:users:invalid_hash',
                'firestore_doc:users',
                'firestore_count:users:short',
                'random_string',
                '',
            ];

            foreach ($invalidKeys as $key) {
                expect(QueryCacheKey::isValid($key))->toBeFalse();
            }
        });
    });

    describe('Collection Extraction', function () {
        it('extracts collection from query keys', function () {
            $key = 'firestore_query:users:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
            $collection = QueryCacheKey::extractCollection($key);

            expect($collection)->toBe('users');
        });

        it('extracts collection from document keys', function () {
            $key = 'firestore_doc:posts/comments:comment-123';
            $collection = QueryCacheKey::extractCollection($key);

            expect($collection)->toBe('posts/comments');
        });

        it('returns null for invalid keys', function () {
            $collection = QueryCacheKey::extractCollection('invalid_key');

            expect($collection)->toBeNull();
        });

        it('returns null for batch keys', function () {
            $key = 'firestore_batch:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
            $collection = QueryCacheKey::extractCollection($key);

            expect($collection)->toBeNull();
        });
    });

    describe('Collection Prefix', function () {
        it('generates collection prefix patterns', function () {
            $prefix = QueryCacheKey::getCollectionPrefix('users');

            expect($prefix)->toBe('firestore_*:users:*');
        });

        it('handles nested collection paths', function () {
            $prefix = QueryCacheKey::getCollectionPrefix('posts/comments');

            expect($prefix)->toBe('firestore_*:posts/comments:*');
        });
    });

    describe('Edge Cases', function () {
        it('handles empty query data', function () {
            $key = QueryCacheKey::generate('users', []);

            expect($key)->toBeString();
            expect($key)->toMatch('/^firestore_query:users:[a-f0-9]{64}$/');
        });

        it('handles null values in query data', function () {
            $queryData = [
                'limit' => null,
                'offset' => null,
                'wheres' => [],
            ];

            $key = QueryCacheKey::generate('users', $queryData);

            expect($key)->toBeString();
            expect($key)->toMatch('/^firestore_query:users:[a-f0-9]{64}$/');
        });

        it('handles special characters in collection names', function () {
            $key = QueryCacheKey::generate('users-test_collection', []);

            expect($key)->toBeString();
            expect($key)->toMatch('/^firestore_query:users-test_collection:[a-f0-9]{64}$/');
        });

        it('handles very large query data', function () {
            $largeQueryData = [
                'wheres' => array_fill(0, 100, ['field' => 'test', 'operator' => '=', 'value' => 'value']),
                'orders' => array_fill(0, 50, ['field' => 'field', 'direction' => 'asc']),
                'metadata' => str_repeat('a', 10000),
            ];

            $key = QueryCacheKey::generate('users', $largeQueryData);

            expect($key)->toBeString();
            expect($key)->toMatch('/^firestore_query:users:[a-f0-9]{64}$/');
        });
    });
});
