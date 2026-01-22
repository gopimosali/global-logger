<?php

namespace Gopimosali\GlobalLogger\Exceptions;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected GlobalLogger $globalLogger;

    public function register(): void
    {
        $this->globalLogger = app(GlobalLogger::class);

        $this->reportable(function (Throwable $e) {
            if (config('globallogger.exceptions.auto_log', true)) {
                $this->logException($e);
            }
        });
    }

    protected function logException(Throwable $e): void
    {
        $context = [
            // Core exception data
            'exception_class' => get_class($e),
            'exception_hash' => $this->generateExceptionHash($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),

            // Request context
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),

            // User context
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,

            // Environment
            'environment' => app()->environment(),
            'server_hostname' => gethostname(),

            // Memory & performance
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),

            // Type identifier for log-visualizer
            'log_type' => 'exception',
        ];

        // Add stack trace
        if (config('globallogger.exceptions.include_trace', true)) {
            $context['stack_trace'] = $e->getTraceAsString();
            $context['stack_trace_parsed'] = $this->parseStackTrace($e);
        }

        // Add request data (sanitized)
        if (config('globallogger.exceptions.include_request_data', false)) {
            $context['request_data'] = $this->sanitizeRequestData(request()->except([
                'password',
                'password_confirmation',
                'token',
                'secret',
                'api_key',
            ]));
        }

        // Add session data (sanitized)
        if (config('globallogger.exceptions.include_session', false) && request()->hasSession()) {
            $context['session_id'] = session()->getId();
        }

        $this->globalLogger->error($e->getMessage(), $context);
    }

    /**
     * Generate a unique hash for grouping similar exceptions
     */
    protected function generateExceptionHash(Throwable $e): string
    {
        return hash('xxh3', implode('|', [
            get_class($e),
            $e->getFile(),
            $e->getLine(),
        ]));
    }

    /**
     * Parse stack trace into structured array
     *
     * @return array<int, array{file: string, line: int, class: string|null, function: string, args: array}>
     */
    protected function parseStackTrace(Throwable $e): array
    {
        $frames = [];
        $trace = $e->getTrace();

        foreach ($trace as $index => $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? 'unknown',
                'args' => $this->sanitizeArgs($frame['args'] ?? []),
            ];

            // Limit frames to prevent excessive data
            if ($index >= 20) {
                break;
            }
        }

        return $frames;
    }

    /**
     * Sanitize function arguments to prevent sensitive data leakage
     *
     * @param  array<int, mixed>  $args
     * @return array<int, mixed>
     */
    protected function sanitizeArgs(array $args): array
    {
        return array_map(function ($arg) {
            if (is_object($arg)) {
                return get_class($arg);
            }

            if (is_array($arg)) {
                return '[array(' . count($arg) . ')]';
            }

            if (is_string($arg) && strlen($arg) > 100) {
                return substr($arg, 0, 100) . '...';
            }

            return $arg;
        }, array_slice($args, 0, 5)); // Limit to 5 args
    }

    /**
     * Sanitize request data
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeRequestData(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value) && strlen($value) > 500) {
                return substr($value, 0, 500) . '...';
            }

            return $value;
        }, $data);
    }
}
