<?php

namespace JTD\FirebaseModels\Firestore\Listeners;

use Exception;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Real-time Listener Manager for Firestore.
 *
 * Manages document, collection, and query listeners with automatic
 * reconnection, error handling, and Laravel event integration.
 */
class RealtimeListenerManager
{
    protected static array $listeners = [];

    protected static array $config = [
        'auto_reconnect' => true,
        'max_reconnect_attempts' => 5,
        'reconnect_delay_ms' => 1000,
        'heartbeat_interval_ms' => 30000,
        'log_events' => true,
    ];

    /**
     * Listen to document changes.
     */
    public static function listenToDocument(
        string $collection,
        string $documentId,
        callable $callback,
        array $options = []
    ): DocumentListener {
        $listener = new DocumentListener($collection, $documentId, $callback, $options);
        $listenerId = static::generateListenerId('document', $collection, $documentId);

        static::$listeners[$listenerId] = $listener;
        $listener->start();

        if (static::$config['log_events']) {
            Log::info('Started document listener', [
                'collection' => $collection,
                'document' => $documentId,
                'listener_id' => $listenerId,
            ]);
        }

        return $listener;
    }

    /**
     * Listen to collection changes.
     */
    public static function listenToCollection(
        string $collection,
        callable $callback,
        array $options = []
    ): CollectionListener {
        $listener = new CollectionListener($collection, $callback, $options);
        $listenerId = static::generateListenerId('collection', $collection);

        static::$listeners[$listenerId] = $listener;
        $listener->start();

        if (static::$config['log_events']) {
            Log::info('Started collection listener', [
                'collection' => $collection,
                'listener_id' => $listenerId,
            ]);
        }

        return $listener;
    }

    /**
     * Listen to query changes.
     */
    public static function listenToQuery(
        string $collection,
        array $wheres,
        callable $callback,
        array $options = []
    ): QueryListener {
        $listener = new QueryListener($collection, $wheres, $callback, $options);
        $listenerId = static::generateListenerId('query', $collection, md5(serialize($wheres)));

        static::$listeners[$listenerId] = $listener;
        $listener->start();

        if (static::$config['log_events']) {
            Log::info('Started query listener', [
                'collection' => $collection,
                'wheres' => $wheres,
                'listener_id' => $listenerId,
            ]);
        }

        return $listener;
    }

    /**
     * Stop a specific listener.
     */
    public static function stopListener(string $listenerId): bool
    {
        if (!isset(static::$listeners[$listenerId])) {
            return false;
        }

        $listener = static::$listeners[$listenerId];
        $listener->stop();
        unset(static::$listeners[$listenerId]);

        if (static::$config['log_events']) {
            Log::info('Stopped listener', ['listener_id' => $listenerId]);
        }

        return true;
    }

    /**
     * Stop all listeners.
     */
    public static function stopAllListeners(): int
    {
        $count = 0;
        foreach (static::$listeners as $listenerId => $listener) {
            $listener->stop();
            $count++;
        }

        static::$listeners = [];

        if (static::$config['log_events']) {
            Log::info('Stopped all listeners', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Get active listeners.
     */
    public static function getActiveListeners(): array
    {
        return array_filter(static::$listeners, function ($listener) {
            return $listener->isActive();
        });
    }

    /**
     * Get listener statistics.
     */
    public static function getStatistics(): array
    {
        $stats = [
            'total_listeners' => count(static::$listeners),
            'active_listeners' => count(static::getActiveListeners()),
            'inactive_listeners' => 0,
            'listeners_by_type' => [
                'document' => 0,
                'collection' => 0,
                'query' => 0,
            ],
            'total_events_received' => 0,
            'total_errors' => 0,
        ];

        foreach (static::$listeners as $listener) {
            if (!$listener->isActive()) {
                $stats['inactive_listeners']++;
            }

            $type = $listener->getType();
            if (isset($stats['listeners_by_type'][$type])) {
                $stats['listeners_by_type'][$type]++;
            }

            $stats['total_events_received'] += $listener->getEventCount();
            $stats['total_errors'] += $listener->getErrorCount();
        }

        return $stats;
    }

    /**
     * Configure the listener manager.
     */
    public static function configure(array $config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Get current configuration.
     */
    public static function getConfiguration(): array
    {
        return static::$config;
    }

    /**
     * Generate a unique listener ID.
     */
    protected static function generateListenerId(string $type, string ...$parts): string
    {
        return $type.'_'.implode('_', $parts).'_'.uniqid();
    }

    /**
     * Handle listener error.
     */
    public static function handleListenerError(string $listenerId, Exception $error): void
    {
        if (!isset(static::$listeners[$listenerId])) {
            return;
        }

        $listener = static::$listeners[$listenerId];

        Log::error('Listener error', [
            'listener_id' => $listenerId,
            'error' => $error->getMessage(),
            'type' => $listener->getType(),
        ]);

        // Fire Laravel event
        Event::dispatch('firestore.listener.error', [$listenerId, $error, $listener]);

        // Auto-reconnect if enabled
        if (static::$config['auto_reconnect'] && $listener->canReconnect()) {
            static::scheduleReconnect($listenerId);
        }
    }

    /**
     * Schedule listener reconnection.
     */
    protected static function scheduleReconnect(string $listenerId): void
    {
        if (!isset(static::$listeners[$listenerId])) {
            return;
        }

        $listener = static::$listeners[$listenerId];
        $attempts = $listener->getReconnectAttempts();

        if ($attempts >= static::$config['max_reconnect_attempts']) {
            Log::warning('Max reconnect attempts reached', [
                'listener_id' => $listenerId,
                'attempts' => $attempts,
            ]);

            return;
        }

        $delay = static::$config['reconnect_delay_ms'] * pow(2, $attempts); // Exponential backoff

        // In a real implementation, this would use a queue or timer
        // For now, we'll just log the reconnection attempt
        Log::info('Scheduling listener reconnection', [
            'listener_id' => $listenerId,
            'delay_ms' => $delay,
            'attempt' => $attempts + 1,
        ]);

        // Simulate reconnection
        $listener->incrementReconnectAttempts();
        $listener->restart();
    }

    /**
     * Start heartbeat monitoring.
     */
    public static function startHeartbeat(): void
    {
        // In a real implementation, this would start a background process
        // to monitor listener health and send heartbeat events
        Log::info('Started listener heartbeat monitoring', [
            'interval_ms' => static::$config['heartbeat_interval_ms'],
        ]);
    }

    /**
     * Stop heartbeat monitoring.
     */
    public static function stopHeartbeat(): void
    {
        Log::info('Stopped listener heartbeat monitoring');
    }

    /**
     * Check listener health.
     */
    public static function checkHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'listeners' => [],
        ];

        foreach (static::$listeners as $listenerId => $listener) {
            $listenerHealth = [
                'id' => $listenerId,
                'type' => $listener->getType(),
                'active' => $listener->isActive(),
                'last_event' => $listener->getLastEventTime(),
                'error_count' => $listener->getErrorCount(),
                'reconnect_attempts' => $listener->getReconnectAttempts(),
            ];

            // Check for issues
            if (!$listener->isActive()) {
                $health['issues'][] = "Listener {$listenerId} is inactive";
                $health['status'] = 'degraded';
            }

            if ($listener->getErrorCount() > 10) {
                $health['issues'][] = "Listener {$listenerId} has high error count";
                $health['status'] = 'degraded';
            }

            $health['listeners'][] = $listenerHealth;
        }

        return $health;
    }

    /**
     * Export listener configuration for debugging.
     */
    public static function exportConfiguration(): array
    {
        $export = [
            'config' => static::$config,
            'listeners' => [],
        ];

        foreach (static::$listeners as $listenerId => $listener) {
            $export['listeners'][$listenerId] = [
                'type' => $listener->getType(),
                'collection' => $listener->getCollection(),
                'active' => $listener->isActive(),
                'options' => $listener->getOptions(),
                'stats' => [
                    'event_count' => $listener->getEventCount(),
                    'error_count' => $listener->getErrorCount(),
                    'reconnect_attempts' => $listener->getReconnectAttempts(),
                    'last_event' => $listener->getLastEventTime(),
                ],
            ];
        }

        return $export;
    }
}
