<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Collection;

// Test model for testing purposes
class TestPost extends FirestoreModel
{
    protected ?string $collection = 'posts';
    
    protected $fillable = [
        'title', 'content', 'published', 'author_id', 'views'
    ];
    
    protected $casts = [
        'published' => 'boolean',
        'views' => 'integer',
        'published_at' => 'datetime',
    ];
}

describe('FirestoreQueryBuilder', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        
        // Set up test data
        $this->mockFirestoreQuery('posts', [
            ['id' => '1', 'title' => 'First Post', 'published' => true, 'views' => 100],
            ['id' => '2', 'title' => 'Second Post', 'published' => false, 'views' => 50],
            ['id' => '3', 'title' => 'Third Post', 'published' => true, 'views' => 200],
        ]);
    });

    describe('Basic Queries', function () {
        it('can get all records', function () {
            $posts = TestPost::all();
            
            expect($posts)->toBeInstanceOf(Collection::class);
            expect($posts)->toHaveCount(3);
            expect($posts->first())->toBeFirestoreModel();
        });

        it('can find a record by ID', function () {
            $this->mockFirestoreGet('posts', '1', [
                'id' => '1', 
                'title' => 'First Post', 
                'published' => true
            ]);
            
            $post = TestPost::find('1');
            
            expect($post)->toBeFirestoreModel();
            expect($post->id)->toBe('1');
            expect($post->title)->toBe('First Post');
        });

        it('returns null when record not found', function () {
            $this->mockFirestoreGet('posts', 'nonexistent', null);
            
            $post = TestPost::find('nonexistent');
            
            expect($post)->toBeNull();
        });

        it('can find or fail', function () {
            $this->mockFirestoreGet('posts', '1', [
                'id' => '1', 
                'title' => 'First Post'
            ]);
            
            $post = TestPost::findOrFail('1');
            expect($post)->toBeFirestoreModel();
            
            $this->mockFirestoreGet('posts', 'nonexistent', null);
            
            expect(fn() => TestPost::findOrFail('nonexistent'))
                ->toThrow(\Illuminate\Database\RecordNotFoundException::class);
        });

        it('can get first record', function () {
            $post = TestPost::first();
            
            expect($post)->toBeFirestoreModel();
            expect($post->title)->toBe('First Post');
        });
    });

    describe('Where Clauses', function () {
        it('can filter with where clause', function () {
            $posts = TestPost::where('published', true)->get();
            
            expect($posts)->toHaveCount(2);
            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'published', 'operator' => '==', 'value' => true]
            ]);
        });

        it('can chain multiple where clauses', function () {
            $posts = TestPost::where('published', true)
                ->where('views', '>', 150)
                ->get();
            
            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'published', 'operator' => '==', 'value' => true],
                ['field' => 'views', 'operator' => '>', 'value' => 150]
            ]);
        });

        it('can use whereIn clause', function () {
            $posts = TestPost::whereIn('id', ['1', '3'])->get();
            
            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'id', 'operator' => 'in', 'value' => ['1', '3']]
            ]);
        });

        it('can use whereNotIn clause', function () {
            $posts = TestPost::whereNotIn('id', ['2'])->get();
            
            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'id', 'operator' => 'not-in', 'value' => ['2']]
            ]);
        });
    });

    describe('Ordering', function () {
        it('can order by field ascending', function () {
            $posts = TestPost::orderBy('title')->get();
            
            $this->assertFirestoreQueryExecuted('posts');
        });

        it('can order by field descending', function () {
            $posts = TestPost::orderBy('views', 'desc')->get();
            
            $this->assertFirestoreQueryExecuted('posts');
        });

        it('can chain multiple order clauses', function () {
            $posts = TestPost::orderBy('published')
                ->orderBy('views', 'desc')
                ->get();
            
            $this->assertFirestoreQueryExecuted('posts');
        });
    });

    describe('Limiting and Pagination', function () {
        it('can limit results', function () {
            $posts = TestPost::limit(2)->get();
            
            expect($posts)->toHaveCount(2);
        });

        it('can take results (alias for limit)', function () {
            $posts = TestPost::take(1)->get();
            
            expect($posts)->toHaveCount(1);
        });

        it('can paginate results', function () {
            $paginator = TestPost::paginate(2);
            
            expect($paginator)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            expect($paginator->items())->toHaveCount(2);
        });

        it('can simple paginate results', function () {
            $paginator = TestPost::simplePaginate(2);
            
            expect($paginator)->toBeInstanceOf(\Illuminate\Pagination\Paginator::class);
            expect($paginator->items())->toHaveCount(2);
        });
    });

    describe('Aggregates', function () {
        it('can count records', function () {
            $count = TestPost::count();
            
            expect($count)->toBe(3);
        });

        it('can count with conditions', function () {
            $count = TestPost::where('published', true)->count();
            
            expect($count)->toBe(2);
        });

        it('can check if records exist', function () {
            $exists = TestPost::where('published', true)->exists();
            
            expect($exists)->toBeTrue();
            
            $notExists = TestPost::where('title', 'Nonexistent')->exists();
            
            expect($notExists)->toBeFalse();
        });
    });

    describe('Model Creation', function () {
        it('can create a new model', function () {
            $this->mockFirestoreCreate('posts', '4');
            
            $post = TestPost::create([
                'title' => 'New Post',
                'content' => 'Content here',
                'published' => false
            ]);
            
            expect($post)->toBeFirestoreModel();
            expect($post)->toExistInFirestore();
            expect($post)->toBeRecentlyCreated();
            expect($post->title)->toBe('New Post');
            
            $this->assertFirestoreOperationCalled('create', 'posts');
        });

        it('can use firstOrCreate', function () {
            // First call - should create
            $this->mockFirestoreCreate('posts', '4');
            
            $post1 = TestPost::firstOrCreate(
                ['title' => 'Unique Post'],
                ['content' => 'Content', 'published' => true]
            );
            
            expect($post1)->toBeRecentlyCreated();
            
            // Second call - should find existing
            $this->mockFirestoreGet('posts', '4', [
                'id' => '4',
                'title' => 'Unique Post',
                'content' => 'Content',
                'published' => true
            ]);
            
            $post2 = TestPost::firstOrCreate(
                ['title' => 'Unique Post'],
                ['content' => 'Different Content']
            );
            
            expect($post2->wasRecentlyCreated)->toBeFalse();
        });

        it('can use updateOrCreate', function () {
            $this->mockFirestoreCreate('posts', '4');
            
            $post = TestPost::updateOrCreate(
                ['title' => 'Update Post'],
                ['content' => 'Updated Content', 'published' => true]
            );
            
            expect($post)->toBeFirestoreModel();
            expect($post->title)->toBe('Update Post');
            expect($post->content)->toBe('Updated Content');
        });
    });

    describe('Model Updates', function () {
        it('can update multiple records', function () {
            $this->mockFirestoreUpdate('posts', '1');
            $this->mockFirestoreUpdate('posts', '3');
            
            $updated = TestPost::where('published', true)
                ->update(['views' => 999]);
            
            expect($updated)->toBe(2);
            $this->assertFirestoreOperationCalled('update', 'posts');
        });

        it('can increment field values', function () {
            $this->mockFirestoreUpdate('posts', '1');
            
            $updated = TestPost::where('id', '1')
                ->increment('views', 10);
            
            expect($updated)->toBe(1);
        });

        it('can decrement field values', function () {
            $this->mockFirestoreUpdate('posts', '1');
            
            $updated = TestPost::where('id', '1')
                ->decrement('views', 5);
            
            expect($updated)->toBe(1);
        });
    });

    describe('Model Deletion', function () {
        it('can delete records', function () {
            $this->mockFirestoreDelete('posts', '1');
            $this->mockFirestoreDelete('posts', '3');
            
            $deleted = TestPost::where('published', true)->delete();
            
            expect($deleted)->toBe(2);
            $this->assertFirestoreOperationCalled('delete', 'posts');
        });
    });

    describe('Chunking', function () {
        it('can chunk results', function () {
            $chunks = [];
            
            TestPost::chunk(2, function ($posts, $page) use (&$chunks) {
                $chunks[] = ['page' => $page, 'count' => $posts->count()];
                return true;
            });
            
            expect($chunks)->toHaveCount(2);
            expect($chunks[0]['count'])->toBe(2);
            expect($chunks[1]['count'])->toBe(1);
        });

        it('can iterate with each', function () {
            $titles = [];
            
            TestPost::each(function ($post) use (&$titles) {
                $titles[] = $post->title;
                return true;
            }, 2);
            
            expect($titles)->toHaveCount(3);
            expect($titles)->toContain('First Post');
        });

        it('can create lazy collection', function () {
            $lazy = TestPost::lazy(2);
            
            expect($lazy)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);
            
            $titles = $lazy->pluck('title')->toArray();
            expect($titles)->toHaveCount(3);
        });
    });

    describe('Query Scoping', function () {
        it('can use whereKey for primary key queries', function () {
            $this->mockFirestoreGet('posts', '1', [
                'id' => '1', 
                'title' => 'First Post'
            ]);
            
            $post = TestPost::whereKey('1')->first();
            
            expect($post)->toBeFirestoreModel();
            expect($post->id)->toBe('1');
        });

        it('can use whereKeyNot for excluding primary keys', function () {
            $posts = TestPost::whereKeyNot('1')->get();
            
            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'id', 'operator' => '!=', 'value' => '1']
            ]);
        });
    });
});
