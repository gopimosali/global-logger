<?php

namespace Gopimosali\GlobalLogger;

use Illuminate\Log\LogManager;
use Monolog\Logger;

/**
 * GlobalLogManager - Laravel LogManager that integrates GlobalLogger
 *
 * This class extends Laravel's LogManager and adds a custom 'globallogger' driver
 * that automatically converts deprecations to info level.
 *
 * Also provides tracing methods (startTrace/endTrace) accessible via Log:: facade.
 */
class GlobalLogManager extends LogManager
{
    /**
     * Create an instance of the GlobalLogger driver
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    protected function createGloballoggerDriver(array $config): Logger
    {
        $globalLogger = $this->app->make(GlobalLogger::class);
        $handler = new MonologHandler($globalLogger);

        return new Logger('globallogger', [$handler]);
    }

    /**
     * Start a trace for performance tracking
     *
     * This method forwards to GlobalLogger's tracing system, allowing
     * Log::startTrace() to be used consistently with other Log methods.
     *
     * @param  string  $name  Trace name (e.g., 'database.query', 'http.request')
     * @param  array<string, mixed>  $metadata  Additional trace metadata
     * @return string Trace ID for use with endTrace()
     */
    public function startTrace(string $name, array $metadata = []): string
    {
        return $this->app->make(GlobalLogger::class)->startTrace($name, $metadata);
    }

    /**
     * End a trace and log the duration
     *
     * This method forwards to GlobalLogger's tracing system, allowing
     * Log::endTrace() to be used consistently with other Log methods.
     *
     * @param  string  $traceId  Trace ID returned from startTrace()
     * @param  array<string, mixed>  $additionalMetadata  Additional metadata to include in the trace log
     */
    public function endTrace(string $traceId, array $additionalMetadata = []): void
    {
        $this->app->make(GlobalLogger::class)->endTrace($traceId, $additionalMetadata);
    }
}
