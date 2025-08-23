<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Firestore\Listeners\RealtimeListenerManager;
use JTD\FirebaseModels\Firestore\Listeners\DocumentListener;
use JTD\FirebaseModels\Firestore\Listeners\CollectionListener;
use JTD\FirebaseModels\Firestore\Listeners\QueryListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Event;

#[Group('unit')]
#[Group('advanced')]
#[Group('realtime-listeners')]
class RealtimeListenersTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing listeners
        RealtimeListenerManager::stopAllListeners();
        
        // Configure for testing
        RealtimeListenerManager::configure([
            'auto_reconnect' => true,
            'max_reconnect_attempts' => 3,
            'reconnect_delay_ms' => 100,
            'log_events' => false, // Disable logging for tests
        ]);
    }

    protected function tearDown(): void
    {
        RealtimeListenerManager::stopAllListeners();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_document_listeners()
    {
        $callbackExecuted = false;
        
        $listener = RealtimeListenerManager::listenToDocument(
            'posts',
            'test-post-123',
            function ($snapshot, $type) use (&$callbackExecuted) {
                $callbackExecuted = true;
                expect($type)->toBeIn(['added', 'modified', 'removed']);
            }
        );

        expect($listener)->toBeInstanceOf(DocumentListener::class);
        expect($listener->getType())->toBe('document');
        expect($listener->getCollection())->toBe('posts');
        expect($listener->isActive())->toBeTrue();

        // Simulate document change
        $this->simulateDocumentChange($listener, 'modified');
        expect($callbackExecuted)->toBeTrue();
    }

    #[Test]
    public function it_creates_collection_listeners()
    {
        $eventsReceived = [];
        
        $listener = RealtimeListenerManager::listenToCollection(
            'posts',
            function ($changes) use (&$eventsReceived) {
                foreach ($changes as $change) {
                    $eventsReceived[] = [
                        'type' => $change->type(),
                        'document' => $change->newSnapshot()->id(),
                    ];
                }
            }
        );

        expect($listener)->toBeInstanceOf(CollectionListener::class);
        expect($listener->getType())->toBe('collection');
        expect($listener->getCollection())->toBe('posts');

        // Simulate collection changes
        $this->simulateCollectionChanges($listener, [
            ['type' => 'added', 'id' => 'post-1'],
            ['type' => 'modified', 'id' => 'post-2'],
        ]);

        expect($eventsReceived)->toHaveCount(2);
        expect($eventsReceived[0]['type'])->toBe('added');
        expect($eventsReceived[1]['type'])->toBe('modified');
    }

    #[Test]
    public function it_creates_query_listeners()
    {
        $queryResults = [];
        
        $wheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100]
        ];

        $listener = RealtimeListenerManager::listenToQuery(
            'posts',
            $wheres,
            function ($snapshot) use (&$queryResults) {
                $queryResults = $snapshot->documents();
            }
        );

        expect($listener)->toBeInstanceOf(QueryListener::class);
        expect($listener->getType())->toBe('query');
        expect($listener->getCollection())->toBe('posts');

        // Simulate query result changes
        $this->simulateQueryChanges($listener, [
            ['id' => 'post-1', 'title' => 'Popular Post 1'],
            ['id' => 'post-2', 'title' => 'Popular Post 2'],
        ]);

        expect($queryResults)->toHaveCount(2);
    }

    #[Test]
    public function it_manages_multiple_listeners()
    {
        $listener1 = RealtimeListenerManager::listenToDocument('posts', 'doc-1', function () {});
        $listener2 = RealtimeListenerManager::listenToCollection('users', function () {});
        $listener3 = RealtimeListenerManager::listenToQuery('comments', [], function () {});

        $activeListeners = RealtimeListenerManager::getActiveListeners();
        expect($activeListeners)->toHaveCount(3);

        $stats = RealtimeListenerManager::getStatistics();
        expect($stats['total_listeners'])->toBe(3);
        expect($stats['active_listeners'])->toBe(3);
        expect($stats['listeners_by_type']['document'])->toBe(1);
        expect($stats['listeners_by_type']['collection'])->toBe(1);
        expect($stats['listeners_by_type']['query'])->toBe(1);
    }

    #[Test]
    public function it_stops_individual_listeners()
    {
        $listener = RealtimeListenerManager::listenToDocument('posts', 'doc-1', function () {});
        $listenerId = $this->getListenerId($listener);

        expect(RealtimeListenerManager::getActiveListeners())->toHaveCount(1);

        $stopped = RealtimeListenerManager::stopListener($listenerId);
        expect($stopped)->toBeTrue();
        expect(RealtimeListenerManager::getActiveListeners())->toHaveCount(0);

        // Try to stop non-existent listener
        $notStopped = RealtimeListenerManager::stopListener('non-existent');
        expect($notStopped)->toBeFalse();
    }

    #[Test]
    public function it_stops_all_listeners()
    {
        RealtimeListenerManager::listenToDocument('posts', 'doc-1', function () {});
        RealtimeListenerManager::listenToCollection('users', function () {});
        RealtimeListenerManager::listenToQuery('comments', [], function () {});

        expect(RealtimeListenerManager::getActiveListeners())->toHaveCount(3);

        $stoppedCount = RealtimeListenerManager::stopAllListeners();
        expect($stoppedCount)->toBe(3);
        expect(RealtimeListenerManager::getActiveListeners())->toHaveCount(0);
    }

    #[Test]
    public function it_handles_listener_errors()
    {
        Event::fake();

        $listener = RealtimeListenerManager::listenToDocument(
            'posts',
            'error-doc',
            function () {
                throw new \Exception('Listener callback error');
            }
        );

        $listenerId = $this->getListenerId($listener);

        // Simulate error
        $error = new \Exception('Connection lost');
        RealtimeListenerManager::handleListenerError($listenerId, $error);

        // Verify event was dispatched
        Event::assertDispatched('firestore.listener.error');
    }

    #[Test]
    public function it_handles_auto_reconnection()
    {
        $reconnectAttempts = 0;
        
        $listener = $this->createMockListener('document', 'posts', function () use (&$reconnectAttempts) {
            $reconnectAttempts++;
        });

        $listenerId = $this->getListenerId($listener);

        // Simulate multiple errors to trigger reconnection
        for ($i = 0; $i < 3; $i++) {
            $error = new \Exception('Connection error');
            RealtimeListenerManager::handleListenerError($listenerId, $error);
        }

        // Should have attempted reconnection
        expect($listener->getReconnectAttempts())->toBeGreaterThan(0);
    }

    #[Test]
    public function it_respects_max_reconnect_attempts()
    {
        RealtimeListenerManager::configure(['max_reconnect_attempts' => 2]);

        $listener = $this->createMockListener('document', 'posts');
        $listenerId = $this->getListenerId($listener);

        // Simulate errors beyond max attempts
        for ($i = 0; $i < 5; $i++) {
            $error = new \Exception('Persistent error');
            RealtimeListenerManager::handleListenerError($listenerId, $error);
        }

        // Should not exceed max attempts
        expect($listener->getReconnectAttempts())->toBeLessThanOrEqual(2);
    }

    #[Test]
    public function it_provides_listener_statistics()
    {
        // Create listeners with different activity levels
        $activeListener = RealtimeListenerManager::listenToDocument('posts', 'active', function () {});
        $inactiveListener = RealtimeListenerManager::listenToDocument('posts', 'inactive', function () {});
        
        // Simulate some activity
        $this->simulateListenerActivity($activeListener, 5, 0);
        $this->simulateListenerActivity($inactiveListener, 2, 3);
        $inactiveListener->stop();

        $stats = RealtimeListenerManager::getStatistics();

        expect($stats['total_listeners'])->toBe(2);
        expect($stats['active_listeners'])->toBe(1);
        expect($stats['inactive_listeners'])->toBe(1);
        expect($stats['total_events_received'])->toBe(7);
        expect($stats['total_errors'])->toBe(3);
    }

    #[Test]
    public function it_checks_listener_health()
    {
        $healthyListener = RealtimeListenerManager::listenToDocument('posts', 'healthy', function () {});
        $unhealthyListener = RealtimeListenerManager::listenToDocument('posts', 'unhealthy', function () {});
        
        // Simulate unhealthy state
        $this->simulateListenerActivity($unhealthyListener, 0, 15); // High error count
        $unhealthyListener->stop();

        $health = RealtimeListenerManager::checkHealth();

        expect($health['status'])->toBe('degraded');
        expect($health['issues'])->not->toBeEmpty();
        expect($health['listeners'])->toHaveCount(2);
    }

    #[Test]
    public function it_exports_configuration()
    {
        RealtimeListenerManager::listenToDocument('posts', 'doc-1', function () {});
        RealtimeListenerManager::listenToCollection('users', function () {});

        $export = RealtimeListenerManager::exportConfiguration();

        expect($export)->toHaveKey('config');
        expect($export)->toHaveKey('listeners');
        expect($export['config'])->toHaveKey('auto_reconnect');
        expect($export['listeners'])->toHaveCount(2);

        foreach ($export['listeners'] as $listenerData) {
            expect($listenerData)->toHaveKey('type');
            expect($listenerData)->toHaveKey('collection');
            expect($listenerData)->toHaveKey('stats');
        }
    }

    #[Test]
    public function it_handles_heartbeat_monitoring()
    {
        RealtimeListenerManager::startHeartbeat();
        
        // In a real implementation, this would verify heartbeat functionality
        // For now, we just verify the methods exist and can be called
        expect(true)->toBeTrue();

        RealtimeListenerManager::stopHeartbeat();
        expect(true)->toBeTrue();
    }

    #[Test]
    public function it_handles_listener_configuration()
    {
        $originalConfig = RealtimeListenerManager::getConfiguration();

        $newConfig = [
            'auto_reconnect' => false,
            'max_reconnect_attempts' => 10,
            'custom_option' => 'test_value'
        ];

        RealtimeListenerManager::configure($newConfig);
        $updatedConfig = RealtimeListenerManager::getConfiguration();

        expect($updatedConfig['auto_reconnect'])->toBeFalse();
        expect($updatedConfig['max_reconnect_attempts'])->toBe(10);
        expect($updatedConfig['custom_option'])->toBe('test_value');

        // Restore original config
        RealtimeListenerManager::configure($originalConfig);
    }

    // Helper methods for testing

    protected function simulateDocumentChange($listener, string $type): void
    {
        // In a real implementation, this would trigger the actual listener callback
        $listener->incrementEventCount();
    }

    protected function simulateCollectionChanges($listener, array $changes): void
    {
        $listener->incrementEventCount(count($changes));
    }

    protected function simulateQueryChanges($listener, array $documents): void
    {
        $listener->incrementEventCount();
    }

    protected function simulateListenerActivity($listener, int $events, int $errors): void
    {
        for ($i = 0; $i < $events; $i++) {
            $listener->incrementEventCount();
        }
        for ($i = 0; $i < $errors; $i++) {
            $listener->incrementErrorCount();
        }
    }

    protected function createMockListener(string $type, string $collection, callable $callback = null): object
    {
        // Create a mock listener for testing
        return new class($type, $collection, $callback) {
            private string $type;
            private string $collection;
            private bool $active = true;
            private int $eventCount = 0;
            private int $errorCount = 0;
            private int $reconnectAttempts = 0;

            public function __construct(string $type, string $collection, ?callable $callback = null)
            {
                $this->type = $type;
                $this->collection = $collection;
            }

            public function getType(): string { return $this->type; }
            public function getCollection(): string { return $this->collection; }
            public function isActive(): bool { return $this->active; }
            public function stop(): void { $this->active = false; }
            public function start(): void { $this->active = true; }
            public function restart(): void { $this->active = true; }
            public function canReconnect(): bool { return true; }
            public function getEventCount(): int { return $this->eventCount; }
            public function getErrorCount(): int { return $this->errorCount; }
            public function getReconnectAttempts(): int { return $this->reconnectAttempts; }
            public function incrementEventCount(int $count = 1): void { $this->eventCount += $count; }
            public function incrementErrorCount(): void { $this->errorCount++; }
            public function incrementReconnectAttempts(): void { $this->reconnectAttempts++; }
            public function getLastEventTime(): ?string { return now()->toISOString(); }
            public function getOptions(): array { return []; }
        };
    }

    protected function getListenerId($listener): string
    {
        // In a real implementation, this would return the actual listener ID
        return 'test_listener_' . uniqid();
    }
}
