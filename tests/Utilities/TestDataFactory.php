<?php

namespace JTD\FirebaseModels\Tests\Utilities;

/**
 * TestDataFactory provides standardized test data generation
 * for consistent testing across different test suites.
 */
class TestDataFactory
{
    /**
     * Create a user test data.
     */
    public static function createUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 'user_' . uniqid(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified' => true,
            'role' => 'user',
            'status' => 'active',
            'profile' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'avatar' => 'https://example.com/avatar.jpg',
                'bio' => 'Test user biography',
            ],
            'preferences' => [
                'theme' => 'light',
                'notifications' => true,
                'language' => 'en',
            ],
            'metadata' => [
                'source' => 'test',
                'created_by' => 'system',
                'tags' => ['test', 'user'],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create a post test data.
     */
    public static function createPost(array $overrides = []): array
    {
        return array_merge([
            'id' => 'post_' . uniqid(),
            'title' => 'Test Post Title',
            'content' => 'This is test post content with some meaningful text for testing purposes.',
            'excerpt' => 'This is a test post excerpt.',
            'status' => 'published',
            'author_id' => 'user_' . uniqid(),
            'category' => 'general',
            'tags' => ['test', 'post', 'content'],
            'featured' => false,
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
            'metadata' => [
                'source' => 'test',
                'format' => 'markdown',
                'reading_time' => 2,
            ],
            'seo' => [
                'meta_title' => 'Test Post Meta Title',
                'meta_description' => 'Test post meta description for SEO.',
                'keywords' => ['test', 'post', 'seo'],
            ],
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create a comment test data.
     */
    public static function createComment(array $overrides = []): array
    {
        return array_merge([
            'id' => 'comment_' . uniqid(),
            'content' => 'This is a test comment with meaningful content.',
            'author_id' => 'user_' . uniqid(),
            'post_id' => 'post_' . uniqid(),
            'parent_id' => null,
            'status' => 'approved',
            'like_count' => 0,
            'reply_count' => 0,
            'metadata' => [
                'source' => 'test',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test User Agent',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create a category test data.
     */
    public static function createCategory(array $overrides = []): array
    {
        return array_merge([
            'id' => 'category_' . uniqid(),
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'This is a test category description.',
            'parent_id' => null,
            'sort_order' => 0,
            'post_count' => 0,
            'is_active' => true,
            'metadata' => [
                'source' => 'test',
                'color' => '#007cba',
                'icon' => 'category-icon',
            ],
            'seo' => [
                'meta_title' => 'Test Category Meta Title',
                'meta_description' => 'Test category meta description.',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create a product test data.
     */
    public static function createProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => 'product_' . uniqid(),
            'name' => 'Test Product',
            'description' => 'This is a test product with detailed description.',
            'sku' => 'TEST-' . strtoupper(uniqid()),
            'price' => 99.99,
            'sale_price' => null,
            'currency' => 'USD',
            'stock_quantity' => 100,
            'stock_status' => 'in_stock',
            'category_id' => 'category_' . uniqid(),
            'brand' => 'Test Brand',
            'weight' => 1.5,
            'dimensions' => [
                'length' => 10,
                'width' => 8,
                'height' => 6,
            ],
            'images' => [
                'https://example.com/product1.jpg',
                'https://example.com/product2.jpg',
            ],
            'attributes' => [
                'color' => 'blue',
                'size' => 'medium',
                'material' => 'cotton',
            ],
            'is_active' => true,
            'is_featured' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create an order test data.
     */
    public static function createOrder(array $overrides = []): array
    {
        return array_merge([
            'id' => 'order_' . uniqid(),
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => 'user_' . uniqid(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 199.98,
            'tax_amount' => 19.98,
            'shipping_amount' => 10.00,
            'discount_amount' => 0.00,
            'currency' => 'USD',
            'items' => [
                [
                    'product_id' => 'product_' . uniqid(),
                    'quantity' => 2,
                    'price' => 99.99,
                    'total' => 199.98,
                ],
            ],
            'shipping_address' => [
                'name' => 'Test Customer',
                'address_line_1' => '123 Test Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'Test City',
                'state' => 'Test State',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'billing_address' => [
                'name' => 'Test Customer',
                'address_line_1' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'Test State',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'notes' => 'Test order notes',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Create multiple instances of a data type.
     */
    public static function createMultiple(string $type, int $count, array $baseOverrides = []): array
    {
        $items = [];
        $method = 'create' . ucfirst($type);
        
        if (!method_exists(self::class, $method)) {
            throw new \InvalidArgumentException("Unknown data type: {$type}");
        }
        
        for ($i = 0; $i < $count; $i++) {
            $overrides = array_merge($baseOverrides, [
                'sequence' => $i,
                'batch_id' => 'batch_' . uniqid(),
            ]);
            
            $items[] = self::$method($overrides);
        }
        
        return $items;
    }

    /**
     * Create related data sets.
     */
    public static function createRelatedDataSet(): array
    {
        // Create users
        $users = self::createMultiple('user', 3);
        
        // Create categories
        $categories = self::createMultiple('category', 2);
        
        // Create posts with relationships
        $posts = [];
        foreach ($users as $user) {
            foreach ($categories as $category) {
                $posts[] = self::createPost([
                    'author_id' => $user['id'],
                    'category' => $category['id'],
                ]);
            }
        }
        
        // Create comments with relationships
        $comments = [];
        foreach ($posts as $post) {
            foreach ($users as $user) {
                $comments[] = self::createComment([
                    'post_id' => $post['id'],
                    'author_id' => $user['id'],
                ]);
            }
        }
        
        return [
            'users' => $users,
            'categories' => $categories,
            'posts' => $posts,
            'comments' => $comments,
        ];
    }

    /**
     * Create hierarchical data (categories with subcategories).
     */
    public static function createHierarchicalCategories(int $depth = 3, int $childrenPerLevel = 2): array
    {
        $categories = [];
        
        // Create root categories
        for ($i = 0; $i < $childrenPerLevel; $i++) {
            $rootCategory = self::createCategory([
                'name' => "Root Category {$i}",
                'level' => 0,
                'parent_id' => null,
            ]);
            $categories[] = $rootCategory;
            
            // Create subcategories recursively
            $subcategories = self::createSubcategories($rootCategory['id'], $depth - 1, $childrenPerLevel, 1);
            $categories = array_merge($categories, $subcategories);
        }
        
        return $categories;
    }

    /**
     * Create subcategories recursively.
     */
    private static function createSubcategories(string $parentId, int $remainingDepth, int $childrenPerLevel, int $currentLevel): array
    {
        if ($remainingDepth <= 0) {
            return [];
        }
        
        $subcategories = [];
        
        for ($i = 0; $i < $childrenPerLevel; $i++) {
            $subcategory = self::createCategory([
                'name' => "Category Level {$currentLevel} - {$i}",
                'parent_id' => $parentId,
                'level' => $currentLevel,
            ]);
            $subcategories[] = $subcategory;
            
            // Create children recursively
            $children = self::createSubcategories($subcategory['id'], $remainingDepth - 1, $childrenPerLevel, $currentLevel + 1);
            $subcategories = array_merge($subcategories, $children);
        }
        
        return $subcategories;
    }
}
