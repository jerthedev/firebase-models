<?php

namespace JTD\FirebaseModels\Tests\Models;

use Illuminate\Support\Carbon;
use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Test Post model for unit testing.
 */
class TestPost extends FirestoreModel
{
    protected ?string $collection = 'posts';

    protected array $fillable = [
        'id',
        'title',
        'content',
        'excerpt',
        'published',
        'featured',
        'views',
        'likes',
        'tags',
        'categories',
        'metadata',
        'author_id',
        'status',
        'published_at',
        'slug',
        'seo_title',
        'seo_description',
    ];

    protected array $casts = [
        'published' => 'boolean',
        'featured' => 'boolean',
        'views' => 'integer',
        'likes' => 'integer',
        'tags' => 'array',
        'categories' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $hidden = [
        'secret_field',
        'internal_notes',
    ];

    protected array $appends = [
        'computed_field',
        'formatted_title',
    ];

    /**
     * Set the collection name dynamically for testing.
     */
    public function setCollection(string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * Scope for published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    /**
     * Scope for featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope for posts with specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for posts by author.
     */
    public function scopeByAuthor($query, string $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope for posts with minimum views.
     */
    public function scopePopular($query, int $minViews = 100)
    {
        return $query->where('views', '>=', $minViews);
    }

    /**
     * Accessor for computed field (for testing appends).
     */
    public function getComputedFieldAttribute(): string
    {
        return 'computed_'.($this->id ?? 'unknown');
    }

    /**
     * Accessor for formatted title.
     */
    public function getFormattedTitleAttribute(): string
    {
        return ucwords($this->title ?? 'Untitled Post');
    }

    /**
     * Accessor for excerpt (auto-generated from content if not set).
     */
    public function getExcerptAttribute(): string
    {
        if (!empty($this->attributes['excerpt'])) {
            return $this->attributes['excerpt'];
        }

        $content = $this->content ?? '';

        return strlen($content) > 100 ? substr($content, 0, 100).'...' : $content;
    }

    /**
     * Mutator for title (always title case).
     */
    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = ucwords(strtolower($value));
    }

    /**
     * Mutator for slug (auto-generate from title if not provided).
     */
    public function setSlugAttribute($value): void
    {
        if (empty($value) && !empty($this->title)) {
            $value = strtolower(str_replace(' ', '-', $this->title));
        }
        $this->attributes['slug'] = $value;
    }

    /**
     * Check if post is published.
     */
    public function isPublished(): bool
    {
        return $this->published === true;
    }

    /**
     * Check if post is featured.
     */
    public function isFeatured(): bool
    {
        return $this->featured === true;
    }

    /**
     * Check if post has specific tag.
     */
    public function hasTag(string $tag): bool
    {
        $tags = $this->tags ?? [];

        return in_array($tag, $tags);
    }

    /**
     * Add tag to post.
     */
    public function addTag(string $tag): static
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
        }

        return $this;
    }

    /**
     * Remove tag from post.
     */
    public function removeTag(string $tag): static
    {
        $tags = $this->tags ?? [];
        $this->tags = array_values(array_filter($tags, fn ($t) => $t !== $tag));

        return $this;
    }

    /**
     * Increment view count.
     */
    public function incrementViews(int $count = 1): static
    {
        $this->views = ($this->views ?? 0) + $count;

        return $this;
    }

    /**
     * Increment like count.
     */
    public function incrementLikes(int $count = 1): static
    {
        $this->likes = ($this->likes ?? 0) + $count;

        return $this;
    }

    /**
     * Get reading time estimate in minutes.
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count($this->content ?? '');

        return max(1, ceil($wordCount / 200)); // Assume 200 words per minute
    }

    /**
     * Check if post is recent (within last 7 days).
     */
    public function isRecent(): bool
    {
        if (!$this->created_at) {
            return false;
        }

        return $this->created_at->isAfter(Carbon::now()->subDays(7));
    }

    /**
     * Get post summary for API responses.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'published' => $this->published,
            'views' => $this->views ?? 0,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Scope for recent posts.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope for posts with content.
     */
    public function scopeWithContent($query)
    {
        return $query->whereNotNull('content');
    }
}
