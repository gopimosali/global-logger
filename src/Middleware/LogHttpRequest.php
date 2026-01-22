<?php

namespace Gopimosali\GlobalLogger\Middleware;

use Closure;
use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * HTTP Request Logging Middleware
 *
 * Logs all HTTP requests and responses
 */
class LogHttpRequest
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!config('globallogger.features.http.enabled', true)) {
            return $next($request);
        }

        // Skip logging for certain paths
        $excludedPaths = config('globallogger.features.http.excluded_paths', [
            'health',
            'horizon/*',
            'telescope/*',
            'logs/*',
        ]);

        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Skip Livewire requests from log-visualizer components
        if ($this->isLogVisualizerLivewireRequest($request)) {
            return $next($request);
        }

        $startTime = microtime(true);

        // Process request
        $response = $next($request);

        // Calculate duration
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Determine log level based on status code
        $statusCode = $response->status();
        $level = match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };

        $context = [
            'log_type' => 'request',
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'duration_ms' => round($durationMs, 2),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ];

        // Add query parameters if configured
        if (config('globallogger.features.http.include_query_params', false)) {
            $context['query_params'] = $request->query();
        }

        // Add request body if configured (be careful with sensitive data)
        if (config('globallogger.features.http.include_request_body', false)) {
            $context['request_body'] = $this->sanitizeData($request->except(['password', 'password_confirmation', 'token']));
        }

        // Add response size
        if (method_exists($response, 'getContent')) {
            $context['response_size_bytes'] = strlen($response->getContent());
        }

        // Mark slow requests
        $slowRequestThreshold = config('globallogger.features.http.slow_request_threshold_ms', 1000);
        if ($durationMs >= $slowRequestThreshold) {
            $context['is_slow'] = true;
            $level = 'warning';
        }

        $message = sprintf(
            '%s %s - %d (%dms)',
            $request->method(),
            $request->path(),
            $statusCode,
            round($durationMs)
        );

        $this->logger->log($level, $message, $context);

        return $response;
    }

    /**
     * Check if request is a Livewire request from log-visualizer component
     */
    protected function isLogVisualizerLivewireRequest(Request $request): bool
    {
        // Check if this is a Livewire request
        if (!$request->is('livewire/*')) {
            return false;
        }

        // Check referer header - if coming from /logs/* routes, it's log-visualizer
        $referer = $request->header('referer');
        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH);
            $routePrefix = config('log-visualizer.dashboard.route', 'logs');
            if ($path && Str::startsWith($path, '/' . $routePrefix)) {
                return true;
            }
        }

        // Check if the component name contains log-visualizer namespace
        $componentName = $request->input('components.0.snapshot')
            ?? $request->input('components.0.name')
            ?? $request->input('fingerprint.name')
            ?? '';

        if (Str::contains($componentName, 'gopimosali.log-visualizer')
            || Str::contains($componentName, 'Gopimosali\\LogVisualizer')) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize sensitive data from request
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = config('globallogger.features.http.sensitive_fields', [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'cvv',
        ]);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            } elseif (Str::contains(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}
