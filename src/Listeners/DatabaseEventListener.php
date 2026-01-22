<?php

namespace Gopimosali\GlobalLogger\Listeners;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Database Event Listener
 *
 * Logs database query events automatically
 */
class DatabaseEventListener
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Handle query executed event
     */
    public function handleQueryExecuted(QueryExecuted $event): void
    {
        if (!config('globallogger.features.database.enabled', true)) {
            return;
        }

        $durationMs = $event->time;
        $slowQueryThreshold = config('globallogger.features.database.slow_query_threshold_ms', 100);

        // Only log slow queries by default
        if ($durationMs < $slowQueryThreshold && !config('globallogger.features.database.log_all_queries', false)) {
            return;
        }

        $level = $durationMs >= $slowQueryThreshold ? 'warning' : 'debug';

        $context = [
            'log_type' => 'query',
            'query' => $event->sql,
            'bindings' => config('globallogger.features.database.include_bindings', false) ? $event->bindings : null,
            'duration_ms' => $durationMs,
            'connection' => $event->connectionName,
            'is_slow' => $durationMs >= $slowQueryThreshold,
        ];

        $message = sprintf(
            'Query executed in %.2fms on %s connection',
            $durationMs,
            $event->connectionName
        );

        $this->logger->log($level, $message, $context);
    }

    /**
     * Log database connection metrics periodically
     * This should be called by a scheduled task or middleware
     */
    public function logConnectionMetrics(): void
    {
        if (!config('globallogger.features.database.enabled', true)) {
            return;
        }

        if (!config('globallogger.features.database.log_connection_metrics', false)) {
            return;
        }

        $connections = config('database.connections');
        $metrics = [];

        foreach ($connections as $name => $config) {
            try {
                // Test connection
                DB::connection($name)->getPdo();
                $metrics[$name] = [
                    'status' => 'connected',
                    'driver' => $config['driver'] ?? 'unknown',
                ];
            } catch (\Exception $e) {
                $metrics[$name] = [
                    'status' => 'failed',
                    'driver' => $config['driver'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->logger->info('Database connection metrics', [
            'log_type' => 'database',
            'connections' => $metrics,
        ]);
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe($events): array
    {
        return [
            QueryExecuted::class => 'handleQueryExecuted',
        ];
    }
}
