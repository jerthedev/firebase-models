<?php

namespace JTD\FirebaseModels\Facades;

use Illuminate\Support\Facades\Facade;
use JTD\FirebaseModels\Firestore\FirestoreDatabase;

/**
 * Laravel DB Facade Compatible Methods
 * @method static \JTD\FirebaseModels\Firestore\FirestoreQueryBuilder table(string $collection)
 * @method static \Illuminate\Support\Collection select(string $collection, array $constraints = [])
 * @method static bool insert(string $collection, array $data)
 * @method static string insertGetId(string $collection, array $data)
 * @method static mixed transaction(callable $callback, int $attempts = 1)
 * @method static void listen(\Closure $callback)
 * @method static void whenQueryingForLongerThan(int $threshold, \Closure $callback)
 * @method static string getName()
 *
 * Direct Firestore Methods
 * @method static \Google\Cloud\Firestore\FirestoreClient getClient()
 * @method static \JTD\FirebaseModels\Firestore\FirestoreQueryBuilder collection(string $path)
 * @method static \Google\Cloud\Firestore\CollectionReference collectionReference(string $path)
 * @method static \Google\Cloud\Firestore\DocumentReference document(string $path)
 * @method static \Google\Cloud\Firestore\DocumentReference doc(string $path)
 * @method static \Google\Cloud\Firestore\DocumentReference add(string $collection, array $data)
 * @method static void set(string $path, array $data, array $options = [])
 * @method static void update(string $path, array $data, ?array $precondition = null)
 * @method static void delete(string $path, ?array $precondition = null)
 * @method static ?\Google\Cloud\Firestore\DocumentSnapshot get(string $path)
 * @method static bool exists(string $path)
 * @method static mixed runTransaction(callable $updateFunction, array $options = [])
 * @method static \Google\Cloud\Firestore\WriteBatch batch()
 * @method static \Google\Cloud\Firestore\Query query(string $collection)
 * @method static \Google\Cloud\Firestore\Query collectionGroup(string $collectionId)
 * @method static \Google\Cloud\Firestore\QuerySnapshot getCollection(string $path)
 * @method static \Google\Cloud\Firestore\Query where(string $collection, string $field, string $operator, mixed $value)
 * @method static \Google\Cloud\Firestore\Query orderBy(string $collection, string $field, string $direction = 'ASC')
 * @method static \Google\Cloud\Firestore\Query limit(string $collection, int $limit)
 * @method static string getProjectId()
 * @method static string getDatabaseId()
 *
 * @see \JTD\FirebaseModels\Firestore\FirestoreDatabase
 */
class FirestoreDB extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'firestore.db';
    }
}
