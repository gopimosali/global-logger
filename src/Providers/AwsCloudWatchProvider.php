<?php

namespace Gopimosali\GlobalLogger\Providers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\XRay\XRayClient;
use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;

class AwsCloudWatchProvider implements LogProviderInterface
{
    protected CloudWatchLogsClient $cloudWatchClient;

    protected ?XRayClient $xrayClient = null;

    protected string $sequenceToken = '';

    public function __construct(
        protected array $config
    ) {
        // Initialize CloudWatch client
        $this->cloudWatchClient = new CloudWatchLogsClient([
            'region' => $config['region'],
            'version' => 'latest',
        ]);

        // Initialize X-Ray client if enabled
        if ($config['xray_enabled'] ?? true) {
            $this->xrayClient = new XRayClient([
                'region' => $config['region'],
                'version' => 'latest',
            ]);
        }
    }

    public function log(string $level, string $message, array $context): void
    {
        // Send to CloudWatch Logs
        $this->sendToCloudWatch($level, $message, $context);

        // Send trace to X-Ray if it's a trace type log
        if (($context['type'] ?? '') === 'trace' && $this->xrayClient) {
            $this->sendToXRay($message, $context);
        }
    }

    protected function sendToCloudWatch(string $level, string $message, array $context): void
    {
        try {
            $logEvent = [
                'logGroupName' => $this->config['log_group'],
                'logStreamName' => $this->config['log_stream'],
                'logEvents' => [
                    [
                        'timestamp' => (int) (microtime(true) * 1000),
                        'message' => json_encode([
                            'level' => $level,
                            'message' => $message,
                            'context' => $context,
                        ]),
                    ],
                ],
            ];

            if (! empty($this->sequenceToken)) {
                $logEvent['sequenceToken'] = $this->sequenceToken;
            }

            $result = $this->cloudWatchClient->putLogEvents($logEvent);
            $this->sequenceToken = $result->get('nextSequenceToken') ?? '';
        } catch (\Throwable $e) {
            error_log('CloudWatch logging failed: '.$e->getMessage());
        }
    }

    protected function sendToXRay(string $message, array $context): void
    {
        try {
            // Convert request_id to X-Ray trace ID format
            $requestId = $context['request_id'] ?? '';
            $xrayTraceId = $this->convertToXRayTraceId($requestId);

            $startTime = ($context['metadata']['start_time'] ?? microtime(true));
            $endTime = $startTime + (($context['duration_ms'] ?? 0) / 1000);

            $segment = [
                'name' => $context['metadata']['name'] ?? 'unknown',
                'id' => $this->generateSegmentId(),
                'trace_id' => $xrayTraceId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'annotations' => [
                    'request_id' => $requestId, // Original request_id preserved
                ],
                'metadata' => [
                    'custom' => array_merge($context['metadata'] ?? [], [
                        'original_request_id' => $requestId,
                    ]),
                ],
            ];

            $this->xrayClient->putTraceSegments([
                'TraceSegmentDocuments' => [json_encode($segment)],
            ]);
        } catch (\Throwable $e) {
            error_log('X-Ray tracing failed: '.$e->getMessage());
        }
    }

    protected function convertToXRayTraceId(string $requestId): string
    {
        $uuid = str_replace('-', '', $requestId);
        $timestamp = hexdec(substr($uuid, 0, 8));
        $uniqueId = substr($uuid, 8, 24);

        return "1-{$timestamp}-{$uniqueId}";
    }

    protected function generateSegmentId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
