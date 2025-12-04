<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;

class DatabaseProvider implements LogProviderInterface
{
    /**
     * Buffer for batch logging
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $buffer = [];

    /**
     * Last flush timestamp
     */
    protected float $lastFlush;

    public function __construct(
        protected array $config
    ) {
        $this->lastFlush = microtime(true);
    }

    public function log(string $level, string $message, array $context): void
    {
        $batchEnabled = config('globallogger.performance.batch.enabled', true);

        if ($batchEnabled) {
            $this->addToBuffer($level, $message, $context);
            $this->flushIfNeeded();
        } else {
            $this->insertSingle($level, $message, $context);
        }
    }

    /**
     * Add log entry to buffer
     */
    protected function addToBuffer(string $level, string $message, array $context): void
    {
        try {
            $this->buffer[] = [
                'request_id' => $context['request_id'] ?? null,
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'environment' => $context['environment'] ?? config('app.env'),
                'application' => $context['application'] ?? config('app.name'),
                'created_at' => now(),
            ];
        } catch (\JsonException $e) {
            // Log JSON encoding failure without causing infinite loop
            $this->logError('JSON encoding failed: '.$e->getMessage());
        }
    }

    /**
     * Insert a single log entry immediately
     */
    protected function insertSingle(string $level, string $message, array $context): void
    {
        try {
            $contextJson = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            DB::connection($this->config['connection'])
                ->table($this->config['table'])
                ->insert([
                    'request_id' => $context['request_id'] ?? null,
                    'level' => $level,
                    'message' => $message,
                    'context' => $contextJson,
                    'environment' => $context['environment'] ?? config('app.env'),
                    'application' => $context['application'] ?? config('app.name'),
                    'created_at' => now(),
                ]);
        } catch (\JsonException $e) {
            $this->logError('JSON encoding failed: '.$e->getMessage());
        } catch (\Throwable $e) {
            $this->logError('Database logging failed: '.$e->getMessage());
        }
    }

    /**
     * Flush buffer if needed (size or time threshold)
     */
    protected function flushIfNeeded(): void
    {
        $batchSize = config('globallogger.performance.batch.size', 100);
        $flushInterval = config('globallogger.performance.batch.flush_interval_seconds', 5);

        $timeSinceLastFlush = microtime(true) - $this->lastFlush;

        if (count($this->buffer) >= $batchSize || $timeSinceLastFlush >= $flushInterval) {
            $this->flush();
        }
    }

    /**
     * Flush all buffered logs to database
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            DB::connection($this->config['connection'])
                ->table($this->config['table'])
                ->insert($this->buffer);

            $this->buffer = [];
            $this->lastFlush = microtime(true);
        } catch (\Throwable $e) {
            $this->logError('Database batch insert failed: '.$e->getMessage());
            $this->buffer = []; // Clear buffer to prevent memory issues
        }
    }

    /**
     * Log errors to Laravel's default log without causing infinite loops
     */
    protected function logError(string $message): void
    {
        try {
            // Use Laravel's file channel directly to avoid recursion
            LaravelLog::channel('single')->error($message);
        } catch (\Throwable $e) {
            // Last resort - use error_log
            error_log("GlobalLogger DatabaseProvider: {$message}");
        }
    }

    /**
     * Flush buffer on destruction
     */
    public function __destruct()
    {
        $this->flush();
    }
}
