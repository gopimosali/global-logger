<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Illuminate\Support\Facades\DB;

class DatabaseProvider implements LogProviderInterface
{
    public function __construct(
        protected array $config
    ) {}

    public function log(string $level, string $message, array $context): void
    {
        try {
            DB::connection($this->config['connection'])
                ->table($this->config['table'])
                ->insert([
                    'request_id' => $context['request_id'] ?? null,
                    'level' => $level,
                    'message' => $message,
                    'context' => json_encode($context),
                    'environment' => $context['environment'] ?? config('app.env'),
                    'application' => $context['application'] ?? config('app.name'),
                    'created_at' => now(),
                ]);
        } catch (\Throwable $e) {
            error_log('Database logging failed: '.$e->getMessage());
        }
    }
}
