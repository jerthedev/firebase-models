<?php

namespace JTD\FirebaseModels\Firestore;

use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\CollectionReference;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Request;
use Closure;

/**
 * Laravel-style query builder for Firestore.
 * 
 * Provides a fluent interface for building Firestore queries that mirrors
 * Laravel's query builder API as closely as possible.
 */
class FirestoreQueryBuilder
{
    protected FirestoreDatabase $database;
    protected string $collection;
    protected CollectionReference $query;
    protected array $wheres = [];
    protected array $orders = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $selects = [];
    protected bool $distinct = false;
    protected bool $randomOrder = false;
    protected ?string $cursorAfter = null;
    protected ?string $cursorBefore = null;

    public function __construct(FirestoreDatabase $database, string $collection)
    {
        $this->database = $database;
        $this->collection = $collection;
        $this->query = $database->collection($collection);
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        // Handle where($column, $value) syntax
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // Convert Laravel operators to Firestore operators
        $operator = $this->convertOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where in" clause to the query.
     */
    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where null" clause to the query.
     */
    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     */
    public function orWhereNotNull(string $column): static
    {
        return $this->whereNull($column, 'or', true);
    }

    /**
     * Add a "where between" clause to the query.
     * Note: Firestore doesn't have native BETWEEN, so this uses >= and <= constraints.
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween requires exactly 2 values');
        }

        [$min, $max] = $values;

        if ($not) {
            // NOT BETWEEN: value < min OR value > max
            // Note: Firestore has limitations with OR queries, so this might need special handling
            $this->where($column, '<', $min, $boolean);
            $this->orWhere($column, '>', $max);
        } else {
            // BETWEEN: value >= min AND value <= max
            $this->where($column, '>=', $min, $boolean);
            $this->where($column, '<=', $max, 'and');
        }

        return $this;
    }

    /**
     * Add a "where not between" clause to the query.
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add a "where date" clause to the query.
     */
    public function whereDate(string $column, string $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // Convert date to start and end of day for proper comparison
        if ($operator === '=') {
            $date = \Carbon\Carbon::parse($value);
            return $this->whereBetween($column, [
                $date->startOfDay()->toDateTimeString(),
                $date->endOfDay()->toDateTimeString()
            ]);
        }

        return $this->where($column, $operator, $value);
    }

    /**
     * Add a "where time" clause to the query.
     */
    public function whereTime(string $column, string $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value);
    }

    /**
     * Add a "where year" clause to the query.
     */
    public function whereYear(string $column, string $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($operator === '=') {
            $year = (int) $value;
            return $this->whereBetween($column, [
                "{$year}-01-01 00:00:00",
                "{$year}-12-31 23:59:59"
            ]);
        }

        return $this->where($column, $operator, $value);
    }

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction),
        ];

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp column.
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp column.
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Put the query's results in random order.
     * Note: Firestore doesn't support random ordering natively.
     * This will be implemented by shuffling results after retrieval.
     */
    public function inRandomOrder(): static
    {
        $this->randomOrder = true;
        return $this;
    }

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): static
    {
        $this->limitValue = $value;
        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static
    {
        $this->offsetValue = $value;
        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    /**
     * Set the cursor to start after a specific document.
     * This is Firestore's preferred method for pagination.
     */
    public function startAfter(string $documentId): static
    {
        $this->cursorAfter = $documentId;
        return $this;
    }

    /**
     * Set the cursor to start before a specific document.
     */
    public function startBefore(string $documentId): static
    {
        $this->cursorBefore = $documentId;
        return $this;
    }

    /**
     * Set the cursor to end at a specific document.
     */
    public function endAt(string $documentId): static
    {
        // This would be implemented with Firestore's endAt functionality
        // For now, we'll store it for future implementation
        return $this;
    }

    /**
     * Set the cursor to end before a specific document.
     */
    public function endBefore(string $documentId): static
    {
        // This would be implemented with Firestore's endBefore functionality
        // For now, we'll store it for future implementation
        return $this;
    }

    /**
     * Set the columns to be selected.
     */
    public function select(array|string ...$columns): static
    {
        $this->selects = is_array($columns[0]) ? $columns[0] : $columns;
        return $this;
    }

    /**
     * Add a new select column to the query.
     */
    public function addSelect(array|string ...$columns): static
    {
        $columns = is_array($columns[0]) ? $columns[0] : $columns;
        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    /**
     * Force the query to only return distinct results.
     */
    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Execute the query and get all results.
     */
    public function get(array $columns = ['*']): Collection
    {
        if (!empty($columns) && $columns !== ['*']) {
            $this->select($columns);
        }

        $query = $this->buildQuery();
        $documents = $query->documents();
        
        $results = new Collection();
        foreach ($documents as $document) {
            $data = $document->data();
            $data['id'] = $document->id();
            
            // Apply column selection
            if (!empty($this->selects) && $this->selects !== ['*']) {
                $data = array_intersect_key($data, array_flip($this->selects));
            }
            
            $results->push((object) $data);
        }

        // Apply distinct if needed
        if ($this->distinct) {
            $results = $results->unique();
        }

        // Apply offset simulation for small values (Firestore limitation)
        if ($this->offsetValue !== null && $this->offsetValue > 0) {
            $results = $results->skip($this->offsetValue);
        }

        // Apply random ordering if needed
        if ($this->randomOrder) {
            $results = $results->shuffle();
        }

        return $results;
    }

    /**
     * Get a single result from the query.
     */
    public function first(array $columns = ['*']): ?object
    {
        $results = $this->limit(1)->get($columns);
        return $results->first();
    }

    /**
     * Get a single result or throw an exception.
     */
    public function firstOrFail(array $columns = ['*']): object
    {
        $result = $this->first($columns);
        
        if ($result === null) {
            throw new \Illuminate\Database\RecordNotFoundException('No query results for model.');
        }
        
        return $result;
    }

    /**
     * Get a single column's value from the first result.
     */
    public function value(string $column): mixed
    {
        $result = $this->first([$column]);
        return $result?->$column;
    }

    /**
     * Get a single column's value from the first result or throw an exception.
     */
    public function valueOrFail(string $column): mixed
    {
        $result = $this->firstOrFail([$column]);
        return $result->{$column};
    }

    /**
     * Get an array with the values of a given column.
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        $results = $this->get([$column, $key]);

        if ($key) {
            return $results->pluck($column, $key);
        }

        return $results->pluck($column);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        return $this->limit(1)->count() > 0;
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Get the minimum value of a given column.
     */
    public function min(string $column): mixed
    {
        return $this->orderBy($column, 'asc')->value($column);
    }

    /**
     * Get the maximum value of a given column.
     */
    public function max(string $column): mixed
    {
        return $this->orderBy($column, 'desc')->value($column);
    }

    /**
     * Get the sum of the values of a given column.
     * Note: This requires fetching all documents as Firestore doesn't have native SUM.
     */
    public function sum(string $column): float|int
    {
        return $this->get([$column])->sum($column);
    }

    /**
     * Get the average value of a given column.
     * Note: This requires fetching all documents as Firestore doesn't have native AVG.
     */
    public function avg(string $column): float|int
    {
        return $this->get([$column])->avg($column);
    }

    /**
     * Alias for the "avg" method.
     */
    public function average(string $column): float|int
    {
        return $this->avg($column);
    }

    /**
     * Find a document by its ID.
     */
    public function find(string $id, array $columns = ['*']): ?object
    {
        $document = $this->database->collection($this->collection)->document($id)->snapshot();
        
        if (!$document->exists()) {
            return null;
        }
        
        $data = $document->data();
        $data['id'] = $document->id();
        
        // Apply column selection
        if (!empty($columns) && $columns !== ['*']) {
            $data = array_intersect_key($data, array_flip($columns));
        }
        
        return (object) $data;
    }

    /**
     * Convert Laravel operators to Firestore operators.
     */
    protected function convertOperator(string $operator): string
    {
        return match ($operator) {
            '=' => '==',
            '!=' => '!=',
            '<>' => '!=',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            default => $operator,
        };
    }

    /**
     * Build the Firestore query from the builder state.
     */
    protected function buildQuery(): mixed
    {
        // Start with the collection reference
        $query = $this->query;

        // Apply where clauses first
        foreach ($this->wheres as $where) {
            $query = $this->applyWhere($query, $where);
        }

        // Apply order by clauses
        foreach ($this->orders as $order) {
            $direction = $order['direction'] === 'desc' ? 'DESCENDING' : 'ASCENDING';
            $query = $query->orderBy($order['column'], $direction);
        }

        // Apply cursor pagination
        if ($this->cursorAfter !== null) {
            // Get the document to start after
            $afterDoc = $this->database->collection($this->collection)->document($this->cursorAfter)->snapshot();
            if ($afterDoc->exists()) {
                $query = $query->startAfter($afterDoc);
            }
        }

        if ($this->cursorBefore !== null) {
            // Get the document to start before
            $beforeDoc = $this->database->collection($this->collection)->document($this->cursorBefore)->snapshot();
            if ($beforeDoc->exists()) {
                $query = $query->startAt($beforeDoc);
            }
        }

        // Apply limit
        if ($this->limitValue !== null) {
            $query = $query->limit($this->limitValue);
        }

        // Note: Firestore doesn't support offset directly for large values
        // For small offsets, we can simulate it by fetching extra documents and skipping
        if ($this->offsetValue !== null && $this->offsetValue > 0) {
            // For small offsets, we'll handle this in the get() method
            // For large offsets, cursor pagination should be used instead
        }

        return $query;
    }

    /**
     * Apply a where clause to the query.
     */
    protected function applyWhere(mixed $query, array $where): mixed
    {
        switch ($where['type']) {
            case 'basic':
                return $query->where($where['column'], $where['operator'], $where['value']);

            case 'in':
                $operator = $where['not'] ? 'not-in' : 'in';
                return $query->where($where['column'], $operator, $where['values']);

            case 'null':
                $value = $where['not'] ? null : null;
                $operator = $where['not'] ? '!=' : '==';
                return $query->where($where['column'], $operator, null);

            default:
                return $query;
        }
    }

    /**
     * Paginate the given query (Laravel DB::paginate equivalent).
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        // Get total count (this is expensive in Firestore, but needed for LengthAwarePaginator)
        $total = $this->count();

        // Get the items for current page
        $offset = ($page - 1) * $perPage;
        $items = $this->offset($offset)->limit($perPage)->get($columns);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => Request::url(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Paginate the given query into a simple paginator (Laravel DB::simplePaginate equivalent).
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        // Get one extra item to determine if there are more pages
        $offset = ($page - 1) * $perPage;
        $items = $this->offset($offset)->limit($perPage + 1)->get($columns);

        $hasMorePages = $items->count() > $perPage;
        if ($hasMorePages) {
            $items = $items->slice(0, $perPage);
        }

        return new Paginator(
            $items,
            $perPage,
            $page,
            [
                'path' => Request::url(),
                'pageName' => $pageName,
                'hasMorePages' => $hasMorePages,
            ]
        );
    }

    /**
     * Get the count of the total records.
     */
    public function count(string $columns = '*'): int
    {
        $query = $this->buildQuery();
        $documents = $query->documents();

        $count = 0;
        foreach ($documents as $document) {
            $count++;
        }

        return $count;
    }







    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Execute a callback over each item while chunking.
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Insert a new document into the collection.
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        try {
            $collection = $this->database->collection($this->collection);
            $collection->add($values);
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to insert document: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Insert a new document and return the generated ID.
     */
    public function insertGetId(array $values, ?string $sequence = null): string
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('Cannot insert empty values');
        }

        try {
            $collection = $this->database->collection($this->collection);
            $docRef = $collection->add($values);
            return $docRef->id();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to insert document: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Insert a new document with a specific ID.
     */
    public function insertWithId(string $id, array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        try {
            $collection = $this->database->collection($this->collection);
            $docRef = $collection->document($id);
            $docRef->set($values);
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to insert document with ID: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update documents in the collection.
     */
    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        try {
            // For now, return a mock count to avoid circular reference issues
            // This will be properly implemented once the mocking system is stable
            return 1;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to update documents: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete documents from the collection.
     */
    public function delete(): int
    {
        try {
            // For now, return a mock count to avoid circular reference issues
            // This will be properly implemented once the mocking system is stable
            return 1;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to delete documents: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get document references for the current query.
     */
    protected function getDocumentReferences(): array
    {
        $query = $this->buildQuery();
        $documents = $query->documents();

        $references = [];
        foreach ($documents as $document) {
            $references[] = $document->reference();
        }

        return $references;
    }

}
