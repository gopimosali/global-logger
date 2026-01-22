<?php

namespace Gopimosali\GlobalLogger;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Utils\CircuitBreaker;
use Gopimosali\GlobalLogger\Utils\PrivacyHelper;
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
 * - PII redaction for GDPR compliance
 * - Log sampling for high-traffic apps
 * - Circuit breaker for failing providers
 * - Provider-level log filtering
 */
class GlobalLogger implements LoggerInterface
{
    /**
     * Array of registered log providers with their names
     *
     * @var array<string, LogProviderInterface>
     */
    protected array $providers = [];

    /**
     * Circuit breakers for each provider
     *
     * @var array<string, CircuitBreaker>
     */
    protected array $circuitBreakers = [];

    /**
     * PSR-3 log level priority mapping
     *
     * @var array<string, int>
     */
    protected array $logLevels = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

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
    public function __construct(
        protected LogContextManager $contextManager
    ) {}

    /**
 * Register a custom driver creator Closure.
 * This makes GlobalLogger compatible with Laravel's Log facade
 *
 * @param  string  $driver
 * @param  \Closure  $callback
 * @return $this
 */
public function extend(string $driver, \Closure $callback): static
{
    // GlobalLogger doesn't use Laravel's driver system
    // This method exists for compatibility only
    return $this;
}

/**
 * Get a log channel instance (Laravel Log compatibility)
 *
 * @param  string|null  $channel
 * @return $this
 */
public function channel(?string $channel = null): static
{
    // GlobalLogger doesn't use channels like Laravel Log
    // This method exists for compatibility only
    // All logs go through the registered providers
    return $this;
}

    /**
     * Add a log provider to send logs to
     *
     * Providers are called in parallel when logging. If one fails, others continue.
     *
     * @param  string  $name  Provider name (aws, datadog, database, etc.)
     * @param  LogProviderInterface  $provider  The provider to add (AWS, Datadog, Oracle, etc.)
     */
    public function addProvider(string $name, LogProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
        $this->circuitBreakers[$name] = new CircuitBreaker("provider_{$name}");
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
        // Apply sampling if enabled
        if ($this->shouldSample($level)) {
            return;
        }

        // Get enriched context with request_id and automatic context
        $enrichedContext = $this->contextManager->getContext();
        $enrichedContext = array_merge($enrichedContext, $context);

        // Apply PII redaction
        $enrichedContext = PrivacyHelper::redactContext($enrichedContext);

        // Send to all providers
        foreach ($this->providers as $name => $provider) {
            $this->logToProvider($name, $provider, $level, (string) $message, $enrichedContext);
        }
    }

    /**
     * Send log to a specific provider with circuit breaker and level filtering
     */
    protected function logToProvider(string $name, LogProviderInterface $provider, string $level, string $message, array $context): void
    {
        // Check provider-level log filtering
        if (! $this->shouldLogToProvider($name, $level)) {
            return;
        }

        // Check circuit breaker
        $circuitBreaker = $this->circuitBreakers[$name] ?? null;
        if ($circuitBreaker && ! $circuitBreaker->canProceed()) {
            return;
        }

        try {
            $provider->log($level, $message, $context);

            // Record success for circuit breaker
            if ($circuitBreaker) {
                $circuitBreaker->recordSuccess();
            }
        } catch (\Throwable $e) {
            // Record failure for circuit breaker
            if ($circuitBreaker) {
                $circuitBreaker->recordFailure();
            }

            // Log error without causing infinite loop
            error_log("GlobalLogger provider '{$name}' failed: {$e->getMessage()}");
        }
    }

    /**
     * Check if log should be sampled (skipped)
     */
    protected function shouldSample(string $level): bool
    {
        if (! config('globallogger.performance.sampling.enabled', false)) {
            return false;
        }

        // Never sample errors if configured
        if (config('globallogger.performance.sampling.exclude_errors', true)) {
            if (in_array($level, [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
                return false;
            }
        }

        // Only sample configured levels
        $sampleLevels = config('globallogger.performance.sampling.levels', ['debug', 'info']);
        if (! in_array($level, $sampleLevels)) {
            return false;
        }

        // Apply sampling rate
        $rate = config('globallogger.performance.sampling.rate', 0.1);

        return mt_rand() / mt_getrandmax() > $rate;
    }

    /**
     * Check if log should be sent to specific provider based on level filtering
     */
    protected function shouldLogToProvider(string $name, string $level): bool
    {
        $minLevel = config("globallogger.provider_levels.{$name}", 'debug');

        $currentPriority = $this->logLevels[$level] ?? 0;
        $minPriority = $this->logLevels[$minLevel] ?? 0;

        return $currentPriority >= $minPriority;
    }

    /**
     * Log a metric (structured logging)
     *
     * @param  string  $name  Metric name
     * @param  float|int  $value  Metric value
     * @param  array<string, mixed>  $tags  Additional tags
     */
    public function metric(string $name, float|int $value, array $tags = []): void
    {
        $this->info("Metric: {$name}", [
            'log_type' => 'metric',
            'metric_name' => $name,
            'metric_value' => $value,
            'tags' => $tags,
        ]);
    }

    /**
     * Log an audit event (structured logging)
     *
     * @param  string  $action  Action performed
     * @param  array<string, mixed>  $data  Audit data
     */
    public function audit(string $action, array $data = []): void
    {
        $this->info("Audit: {$action}", array_merge([
            'log_type' => 'audit',
            'action' => $action,
        ], $data));
    }

    /**
     * Log a security event (structured logging)
     *
     * @param  string  $event  Security event type
     * @param  array<string, mixed>  $data  Event data
     */
    public function security(string $event, array $data = []): void
    {
        $this->warning("Security: {$event}", array_merge([
            'log_type' => 'security',
            'event' => $event,
        ], $data));
    }

    /**
     * Log a performance metric
     *
     * @param  string  $operation  Operation name
     * @param  float  $durationMs  Duration in milliseconds
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function performance(string $operation, float $durationMs, array $metadata = []): void
    {
        $this->info("Performance: {$operation}", array_merge([
            'log_type' => 'performance',
            'operation' => $operation,
            'duration_ms' => $durationMs,
        ], $metadata));
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
