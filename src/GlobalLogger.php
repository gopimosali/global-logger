<?php

namespace Gopimosali\GlobalLogger;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * GlobalLogger - PSR-3 compliant logger with multi-provider support
 *
 * Main logging class that implements PSR-3 LoggerInterface and provides:
 * - Automatic request_id correlation across all logs
 * - Multi-provider support (AWS, Datadog, Oracle, Database, Files)
 * - Optional performance tracing with startTrace()/endTrace()
 * - Zero code changes required (drop-in replacement for Laravel's Log)
 */
class GlobalLogger implements LoggerInterface
{
    /**
     * Array of registered log providers
     *
     * @var array<LogProviderInterface>
     */
    protected array $providers = [];

    /**
     * Log context manager handles request_id generation and context enrichment
     */
    protected LogContextManager $contextManager;

    /**
     * Active traces being tracked for performance measurement
     *
     * @var array<string, array{id: string, name: string, request_id: string, start_time: float, metadata: array}>
     */
    protected array $activeTraces = [];

    /**
     * Create a new GlobalLogger instance
     *
     * @param  LogContextManager  $contextManager  Context manager for request_id and enrichment
     */
    public function __construct(LogContextManager $contextManager)
    {
        $this->contextManager = $contextManager;
    }

    /**
     * Add a log provider to send logs to
     *
     * Providers are called in parallel when logging. If one fails, others continue.
     *
     * @param  LogProviderInterface  $provider  The provider to add (AWS, Datadog, Oracle, etc.)
     */
    public function addProvider(LogProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Return a specific channel (for Laravel Log compatibility)
     *
     * Since GlobalLogger doesn't use channels, this returns $this for method chaining.
     * This allows GlobalLogger to be a drop-in replacement for Laravel's Log facade.
     *
     * @param  string  $channel  Channel name (ignored)
     * @return static
     */
    public function channel(string $channel): static
    {
        // GlobalLogger doesn't use channels, but this allows for Laravel Log compatibility
        // Future: Could implement multiple logger instances per channel if needed
        return $this;
    }

    /**
     * Start a trace for performance tracking
     *
     * @param  string  $name  Trace name (e.g., 'database.query', 'http.request')
     * @param  array  $metadata  Additional trace metadata
     * @return string Trace ID
     */
    public function startTrace(string $name, array $metadata = []): string
    {
        $traceId = $this->generateTraceId();
        $requestId = $this->contextManager->getRequestId();

        $this->activeTraces[$traceId] = [
            'id' => $traceId,
            'name' => $name,
            'request_id' => $requestId,
            'start_time' => microtime(true),
            'metadata' => $metadata,
        ];

        return $traceId;
    }

    /**
     * End a trace and log the duration
     *
     * @param  string  $traceId  Trace ID returned from startTrace()
     * @param  array  $additionalMetadata  Additional metadata to include
     */
    public function endTrace(string $traceId, array $additionalMetadata = []): void
    {
        if (! isset($this->activeTraces[$traceId])) {
            return;
        }

        $trace = $this->activeTraces[$traceId];
        $endTime = microtime(true);
        $duration = ($endTime - $trace['start_time']) * 1000; // Convert to milliseconds

        unset($this->activeTraces[$traceId]);

        $context = array_merge($trace['metadata'], $additionalMetadata, [
            'trace_id' => $traceId,
            'duration_ms' => round($duration, 2),
            'type' => 'trace',
        ]);

        $this->info("Trace completed: {$trace['name']}", $context);
    }

    /**
     * System is unusable (PSR-3 emergency level)
     *
     * Examples: System down, database unavailable, critical failure
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately (PSR-3 alert level)
     *
     * Examples: Website down, database unavailable, critical error
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions (PSR-3 critical level)
     *
     * Examples: Application component unavailable, unexpected exception
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action (PSR-3 error level)
     *
     * Examples: Exceptions, database errors, failed operations
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors (PSR-3 warning level)
     *
     * Examples: Deprecated API usage, poor use of API, undesirable things
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events (PSR-3 notice level)
     *
     * Examples: Normal but significant condition, important events
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events (PSR-3 info level)
     *
     * Examples: User logs in, SQL logs, application flow
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information (PSR-3 debug level)
     *
     * Examples: Variable dumps, execution flow, detailed diagnostics
     *
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log with an arbitrary level
     *
     * Enriches context with request_id and sends to all registered providers.
     * If a provider fails, it fails silently to prevent cascading failures.
     *
     * @param  mixed  $level  Log level (use PSR-3 LogLevel constants)
     * @param  string|\Stringable  $message  The log message
     * @param  array  $context  Additional context data
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $enrichedContext = $this->contextManager->getContext();
        $enrichedContext = array_merge($enrichedContext, $context);

        foreach ($this->providers as $provider) {
            try {
                $provider->log($level, (string) $message, $enrichedContext);
            } catch (\Throwable $e) {
                // Silently fail individual providers to prevent cascading failures
                error_log('GlobalLogger provider failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Generate a unique trace ID for performance tracking
     *
     * Uses uniqid() with prefix and more_entropy for uniqueness
     *
     * @return string Unique trace identifier
     */
    protected function generateTraceId(): string
    {
        return uniqid('trace_', true);
    }

    /**
     * Get the context manager instance
     *
     * Useful for accessing request_id or adding custom context
     *
     * @return LogContextManager The context manager instance
     */
    public function getContextManager(): LogContextManager
    {
        return $this->contextManager;
    }
}
