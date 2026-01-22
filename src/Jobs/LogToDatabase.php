<?php

namespace Gopimosali\GlobalLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * LogToDatabase Job - Async database logging
 *
 * Processes log entries asynchronously to prevent blocking the request
 */
class LogToDatabase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Number of seconds before timeout
     */
    public int $timeout = 30;

    /**
     * Create a new job instance
     *
     * @param  string  $level  Log level
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Log context
     * @param  array<string, mixed>  $config  Provider configuration
     */
    public function __construct(
        protected string $level,
        protected string $message,
        protected array $context,
        protected array $config
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $contextJson = json_encode($this->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            DB::connection($this->config['connection'])
                ->table($this->config['table'])
                ->insert([
                    'request_id' => $this->context['request_id'] ?? null,
                    'level' => $this->level,
                    'message' => $this->message,
                    'context' => $contextJson,
                    'environment' => $this->context['environment'] ?? config('app.env'),
                    'application' => $this->context['application'] ?? config('app.name'),
                    'created_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // Log failure without causing infinite loop
            error_log("LogToDatabase job failed: {$e->getMessage()}");
            throw $e; // Re-throw to trigger retry
        }
    }
}
