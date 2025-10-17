<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;

class DatadogProvider implements LogProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function log(string $level, string $message, array $context): void
    {
        // Send to Datadog Logs
        $this->sendToDatadogLogs($level, $message, $context);

        // Send trace to Datadog APM if it's a trace type log
        if (($context['type'] ?? '') === 'trace' && ($this->config['apm_enabled'] ?? true)) {
            $this->sendToDatadogAPM($message, $context);
        }
    }

    protected function sendToDatadogLogs(string $level, string $message, array $context): void
    {
        try {
            $payload = [
                'ddsource' => 'php',
                'ddtags' => "env:{$context['environment']},service:{$this->config['service']}",
                'hostname' => gethostname(),
                'service' => $this->config['service'],
                'status' => $this->mapLogLevel($level),
                'message' => $message,
                'context' => $context,
                'timestamp' => time() * 1000,
            ];

            $ch = curl_init("https://{$this->config['host']}/api/v2/logs");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    "DD-API-KEY: {$this->config['api_key']}",
                ],
                CURLOPT_POSTFIELDS => json_encode([$payload]),
            ]);

            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('Datadog logging failed: '.$e->getMessage());
        }
    }

    protected function sendToDatadogAPM(string $message, array $context): void
    {
        try {
            // Convert request_id to Datadog trace ID format
            $requestId = $context['request_id'] ?? '';
            $datadogTraceId = $this->convertToDatadogTraceId($requestId);

            $spanId = $this->generateSpanId();
            $startTime = ($context['metadata']['start_time'] ?? microtime(true)) * 1e9; // nanoseconds
            $duration = ($context['duration_ms'] ?? 0) * 1e6; // convert ms to nanoseconds

            $span = [
                'trace_id' => $datadogTraceId,
                'span_id' => $spanId,
                'parent_id' => 0,
                'name' => $context['metadata']['name'] ?? 'unknown',
                'resource' => $message,
                'service' => $this->config['service'],
                'start' => (int) $startTime,
                'duration' => (int) $duration,
                'meta' => array_merge($context['metadata'] ?? [], [
                    'request_id' => $requestId, // Original request_id preserved
                    'original_request_id' => $requestId,
                ]),
                'metrics' => [
                    '_sampling_priority_v1' => 1,
                ],
            ];

            // Send to Datadog APM Agent
            $ch = curl_init("http://{$this->config['apm_host']}:{$this->config['apm_port']}/v0.4/traces");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/msgpack',
                    'Datadog-Meta-Tracer-Version: 1.0',
                ],
                CURLOPT_POSTFIELDS => msgpack_pack([[$span]]),
            ]);

            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('Datadog APM tracing failed: '.$e->getMessage());
        }
    }

    protected function convertToDatadogTraceId(string $requestId): string
    {
        $uuid = str_replace('-', '', $requestId);
        $traceId = substr($uuid, 0, 16);

        return (string) hexdec($traceId);
    }

    protected function generateSpanId(): string
    {
        return (string) mt_rand(1, PHP_INT_MAX);
    }

    protected function mapLogLevel(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical', 'error' => 'error',
            'warning' => 'warn',
            'notice', 'info' => 'info',
            'debug' => 'debug',
            default => 'info',
        };
    }
}
