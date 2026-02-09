<?php

namespace Gopimosali\GlobalLogger\Listeners;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Str;

/**
 * Cache Event Listener
 *
 * Logs all cache events automatically
 */
class CacheEventListener
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Check if we're in log-visualizer context (should not log)
     */
    protected function isLogVisualizerContext(): bool
    {
        if (!app()->bound('request')) {
            return false;
        }

        $request = app('request');
        $routePrefix = config('log-visualizer.dashboard.route', 'logs');

        // Check if current request is to log-visualizer routes
        if ($request->is($routePrefix . '/*') || $request->is($routePrefix)) {
            return true;
        }

        // Check if it's a Livewire request from log-visualizer (check referer)
        if ($request->is('livewire/*')) {
            $referer = $request->header('referer');
            if ($referer) {
                $path = parse_url($referer, PHP_URL_PATH);
                if ($path && Str::startsWith($path, '/' . $routePrefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if cache key is from GlobalLogger internal operations
     * CRITICAL: Prevents infinite loop when circuit breaker uses cache
     */
    protected function isGlobalLoggerInternalKey(string $key): bool
    {
        return Str::startsWith($key, 'globallogger:')
            || Str::startsWith($key, 'laravel_cache:globallogger:');
    }

    /**
     * Check if cache key should be ignored based on configured patterns.
     * Supports exact matches and wildcard prefix matching (e.g., "illuminate:queue:paused:*").
     */
    protected function isIgnoredKey(string $key): bool
    {
        $ignoredKeys = config('globallogger.features.cache.ignored_keys', []);

        foreach ($ignoredKeys as $pattern) {
            if (Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle cache hit event
     */
    public function handleCacheHit(CacheHit $event): void
    {
        if (!config('globallogger.features.cache.enabled', true)) {
            return;
        }

        // CRITICAL: Prevent infinite loop from circuit breaker cache operations
        if ($this->isGlobalLoggerInternalKey($event->key)) {
            return;
        }

        // Skip ignored cache keys (framework-internal noise)
        if ($this->isIgnoredKey($event->key)) {
            return;
        }

        // Skip logging cache operations from log-visualizer
        if ($this->isLogVisualizerContext()) {
            return;
        }

        // Only log if detailed logging is enabled (can be noisy)
        if (!config('globallogger.features.cache.log_hits', false)) {
            return;
        }

        $this->logger->debug('Cache hit', [
            'log_type' => 'cache',
            'cache_operation' => 'get',
            'cache_result' => 'hit',
            'cache_key' => $event->key,
            'cache_store' => $event->storeName ?? config('cache.default'),
            'cache_tags' => $event->tags ?? [],
        ]);
    }

    /**
     * Handle cache missed event
     */
    public function handleCacheMissed(CacheMissed $event): void
    {
        if (!config('globallogger.features.cache.enabled', true)) {
            return;
        }

        // CRITICAL: Prevent infinite loop from circuit breaker cache operations
        if ($this->isGlobalLoggerInternalKey($event->key)) {
            return;
        }

        // Skip ignored cache keys (framework-internal noise)
        if ($this->isIgnoredKey($event->key)) {
            return;
        }

        // Skip logging cache operations from log-visualizer
        if ($this->isLogVisualizerContext()) {
            return;
        }

        // Log misses at info level (more important than hits)
        if (!config('globallogger.features.cache.log_misses', true)) {
            return;
        }

        $this->logger->info('Cache miss', [
            'log_type' => 'cache',
            'cache_operation' => 'get',
            'cache_result' => 'miss',
            'cache_key' => $event->key,
            'cache_store' => $event->storeName ?? config('cache.default'),
            'cache_tags' => $event->tags ?? [],
        ]);
    }

    /**
     * Handle key written event
     */
    public function handleKeyWritten(KeyWritten $event): void
    {
        if (!config('globallogger.features.cache.enabled', true)) {
            return;
        }

        // CRITICAL: Prevent infinite loop from circuit breaker cache operations
        if ($this->isGlobalLoggerInternalKey($event->key)) {
            return;
        }

        // Skip ignored cache keys (framework-internal noise)
        if ($this->isIgnoredKey($event->key)) {
            return;
        }

        // Skip logging cache operations from log-visualizer
        if ($this->isLogVisualizerContext()) {
            return;
        }

        if (!config('globallogger.features.cache.log_writes', false)) {
            return;
        }

        $this->logger->debug('Cache key written', [
            'log_type' => 'cache',
            'cache_operation' => 'set',
            'cache_result' => 'written',
            'cache_key' => $event->key,
            'cache_store' => $event->storeName ?? config('cache.default'),
            'cache_tags' => $event->tags ?? [],
            'cache_ttl' => $event->seconds ?? null,
        ]);
    }

    /**
     * Handle key forgotten event
     */
    public function handleKeyForgotten(KeyForgotten $event): void
    {
        if (!config('globallogger.features.cache.enabled', true)) {
            return;
        }

        // CRITICAL: Prevent infinite loop from circuit breaker cache operations
        if ($this->isGlobalLoggerInternalKey($event->key)) {
            return;
        }

        // Skip ignored cache keys (framework-internal noise)
        if ($this->isIgnoredKey($event->key)) {
            return;
        }

        // Skip logging cache operations from log-visualizer
        if ($this->isLogVisualizerContext()) {
            return;
        }

        if (!config('globallogger.features.cache.log_deletions', false)) {
            return;
        }

        $this->logger->debug('Cache key forgotten', [
            'log_type' => 'cache',
            'cache_operation' => 'forget',
            'cache_result' => 'deleted',
            'cache_key' => $event->key,
            'cache_store' => $event->storeName ?? config('cache.default'),
            'cache_tags' => $event->tags ?? [],
        ]);
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe($events): array
    {
        return [
            CacheHit::class => 'handleCacheHit',
            CacheMissed::class => 'handleCacheMissed',
            KeyWritten::class => 'handleKeyWritten',
            KeyForgotten::class => 'handleKeyForgotten',
        ];
    }
}
