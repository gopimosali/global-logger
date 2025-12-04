<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Gopimosali\GlobalLogger\Jobs\LogToDatabase;

/**
 * QueuedDatabaseProvider - Async queue-based database logging
 *
 * Sends logs to a queue for async processing, preventing database
 * operations from blocking the request.
 */
class QueuedDatabaseProvider implements LogProviderInterface
{
    public function __construct(
        protected array $config
    ) {}

    public function log(string $level, string $message, array $context): void
    {
        $asyncConfig = config('globallogger.performance.async', []);

        if (! ($asyncConfig['enabled'] ?? false)) {
            // Fallback to synchronous logging
            $provider = new DatabaseProvider($this->config);
            $provider->log($level, $message, $context);

            return;
        }

        // Dispatch to queue
        $job = new LogToDatabase(
            $level,
            $message,
            $context,
            $this->config
        );

        if ($queue = $asyncConfig['queue'] ?? null) {
            $job->onQueue($queue);
        }

        if ($connection = $asyncConfig['connection'] ?? null) {
            $job->onConnection($connection);
        }

        dispatch($job);
    }
}
