<?php

namespace Gopimosali\GlobalLogger\Tracing;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

class AutoTracer
{
    protected array $activeTraces = [];

    public function __construct(
        protected GlobalLogger $logger,
        protected array $config = []
    ) {}

    public function register(): void
    {
        if ($this->config['http'] ?? true) {
            $this->registerHttpTracing();
        }

        if ($this->config['database'] ?? true) {
            $this->registerDatabaseTracing();
        }

        if ($this->config['queue'] ?? true) {
            $this->registerQueueTracing();
        }

        if ($this->config['mail'] ?? true) {
            $this->registerMailTracing();
        }

        if ($this->config['cache'] ?? false) {
            $this->registerCacheTracing();
        }
    }

    protected function registerHttpTracing(): void
    {
        Event::listen(RequestSending::class, function (RequestSending $event) {
            $traceId = $this->logger->startTrace('http.request', [
                'method' => $event->request->method(),
                'url' => $event->request->url(),
            ]);

            $this->activeTraces['http_'.spl_object_id($event->request)] = $traceId;
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $key = 'http_'.spl_object_id($event->request);

            if (isset($this->activeTraces[$key])) {
                $this->logger->endTrace($this->activeTraces[$key], [
                    'status_code' => $event->response->status(),
                    'duration_ms' => $event->response->transferStats?->getTransferTime() * 1000,
                ]);

                unset($this->activeTraces[$key]);
            }
        });
    }

    protected function registerDatabaseTracing(): void
    {
        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            // Prevent infinite loops - exclude queries to logging/monitoring tables
            if ($this->shouldExcludeQuery($event->sql)) {
                return;
            }

            $minDuration = $this->config['min_duration_ms'] ?? 10;

            if ($event->time >= $minDuration) {
                $traceId = $this->logger->startTrace('database.query', [
                    'connection' => $event->connectionName,
                    'sql' => $event->sql,
                    'bindings' => $event->bindings,
                ]);

                // Immediately end the trace since this is a completed event
                $this->logger->endTrace($traceId, [
                    'duration_ms' => $event->time,
                ]);
            }
        });
    }

    protected function shouldExcludeQuery(string $sql): bool
    {
        // Get excluded tables from config
        $excludedTables = $this->config['excluded_tables'] ?? [
            'global_logs',              // GlobalLogger's own table
            'telescope_entries',        // Laravel Telescope
            'telescope_entries_tags',
            'telescope_monitoring',
            'jobs',                     // Queue jobs table
            'failed_jobs',
            'pulse_entries',            // Laravel Pulse
            'pulse_aggregates',
            'pulse_values',
            'sessions',                 // Session table (noisy)
            'cache',                    // Cache table (noisy)
            'cache_locks',
        ];

        $sqlLower = strtolower($sql);

        foreach ($excludedTables as $table) {
            // Check for any operation on excluded tables (INSERT, UPDATE, DELETE, SELECT, TRUNCATE)
            if (preg_match('/\b(into|from|update|join|table)\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i', $sqlLower)) {
                return true;
            }
        }

        return false;
    }

    protected function registerQueueTracing(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $traceId = $this->logger->startTrace('queue.job', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
            ]);

            $this->activeTraces['job_'.$event->job->getJobId()] = $traceId;
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) {
            $key = 'job_'.$event->job->getJobId();

            if (isset($this->activeTraces[$key])) {
                $this->logger->endTrace($this->activeTraces[$key], [
                    'success' => true,
                ]);

                unset($this->activeTraces[$key]);
            }
        });
    }

    protected function registerMailTracing(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $traceId = $this->logger->startTrace('mail.send', [
                'to' => collect($event->message->getTo())->keys()->implode(', '),
                'subject' => $event->message->getSubject(),
            ]);

            $this->activeTraces['mail_'.spl_object_id($event->message)] = $traceId;
        });

        Event::listen(MessageSent::class, function (MessageSent $event) {
            $key = 'mail_'.spl_object_id($event->message);

            if (isset($this->activeTraces[$key])) {
                $this->logger->endTrace($this->activeTraces[$key], [
                    'success' => true,
                ]);

                unset($this->activeTraces[$key]);
            }
        });
    }

    protected function registerCacheTracing(): void
    {
        Event::listen('cache:*', function ($event, $data) {
            if ($event === 'cache:hit' || $event === 'cache:missed') {
                $traceId = $this->logger->startTrace("cache.{$event}", [
                    'key' => $data[0] ?? 'unknown',
                ]);

                $this->logger->endTrace($traceId);
            }
        });
    }
}
