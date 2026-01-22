<?php

namespace Gopimosali\GlobalLogger\LogContext;

use Ramsey\Uuid\Uuid;

/**
 * LogContextManager - Manages request correlation and context enrichment
 *
 * Responsibilities:
 * - Generate and maintain a single request_id per HTTP request
 * - Enrich all logs with automatic context (timestamp, environment, etc.)
 * - Convert request_id to provider-specific formats (X-Ray, Datadog)
 * - Allow adding custom context that persists throughout the request
 *
 * The request_id is the primary correlation identifier used across all logs
 * and traces to track a complete request journey through the system.
 */
class LogContextManager
{
    /**
     * Custom context data added during the request
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * The request ID for the current request (generated once per request)
     */
    protected ?string $requestId = null;

    /**
     * Package configuration array
     */
    protected array $config;

    /**
     * Create a new LogContextManager instance
     *
     * @param  array  $config  Package configuration from config/globallogger.php
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get or generate the request ID
     *
     * The request_id is generated once per request and reused for all logs.
     * Uses UUID v7 by default (time-sortable) or UUID v4 if configured.
     *
     * @return string UUID format (e.g., "550e8400-e29b-41d4-a716-446655440000")
     */
    public function getRequestId(): string
    {
        if ($this->requestId === null) {
            $this->requestId = $this->generateRequestId();
        }

        return $this->requestId;
    }

    /**
     * Set the request ID manually
     *
     * Useful when receiving a request_id from an upstream service (e.g., load balancer)
     * via the X-Request-ID header to maintain correlation across services.
     *
     * @param  string  $requestId  The request ID to use (should be a valid UUID)
     */
    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /**
     * Get all context including automatic enrichment
     *
     * Returns the complete context that will be included in all logs:
     * - request_id: Unique identifier for this request
     * - timestamp: ISO 8601 formatted current time
     * - environment: Application environment (production, staging, etc.)
     * - application: Application name from config
     * - Plus any custom context added via addContext()
     *
     * @return array<string, mixed> Complete context array
     */
    public function getContext(): array
    {
        return array_merge([
            'request_id' => $this->getRequestId(),
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'application' => config('app.name'),
        ], $this->context);
    }

    /**
     * Add custom context data
     *
     * Custom context is merged with automatic context and included in all
     * subsequent logs during this request. Useful for adding user_id, tenant_id,
     * feature flags, or any other request-specific data.
     *
     * Example:
     * ```php
     * $contextManager->addContext([
     *     'user_id' => 123,
     *     'tenant_id' => 'tenant-abc',
     *     'feature_flag' => 'new_checkout'
     * ]);
     * ```
     *
     * @param  array<string, mixed>  $context  Context data to add
     */
    public function addContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Clear custom context (keeps request_id and automatic context)
     *
     * Removes all custom context added via addContext(), but preserves
     * the request_id and automatic context enrichment.
     */
    public function clearContext(): void
    {
        $this->context = [];
    }

    /**
     * Convert request_id to AWS X-Ray trace ID format
     *
     * X-Ray uses a specific format: 1-{timestamp}-{24-hex-chars}
     * This method converts a standard UUID to X-Ray format while
     * preserving the original UUID in trace annotations.
     *
     * Format breakdown:
     * - Version: Always "1"
     * - Timestamp: First 8 hex chars of UUID as decimal
     * - Unique ID: Next 24 hex chars of UUID
     *
     * Example:
     * UUID: 550e8400-e29b-41d4-a716-446655440000
     * X-Ray: 1-1427846144-550e8400e29b41d4a716
     *
     * @param  string|null  $requestId  Request ID to convert (uses current if null)
     * @return string X-Ray formatted trace ID
     */
    public function toXRayTraceId(?string $requestId = null): string
    {
        $requestId = $requestId ?? $this->getRequestId();

        // Extract UUID bytes
        $uuid = str_replace('-', '', $requestId);

        // Get first 8 hex chars for timestamp (4 bytes)
        $timestamp = hexdec(substr($uuid, 0, 8));

        // Get next 24 hex chars for unique ID
        $uniqueId = substr($uuid, 8, 24);

        return "1-{$timestamp}-{$uniqueId}";
    }

    /**
     * Convert request_id to Datadog trace ID format
     *
     * Datadog uses a 64-bit unsigned integer for trace IDs.
     * This method converts a standard UUID to Datadog format while
     * preserving the original UUID in span tags.
     *
     * Example:
     * UUID: 550e8400-e29b-41d4-a716-446655440000
     * Datadog: 6145998120563704832
     *
     * @param  string|null  $requestId  Request ID to convert (uses current if null)
     * @return string Datadog formatted trace ID (64-bit integer as string)
     */
    public function toDatadogTraceId(?string $requestId = null): string
    {
        $requestId = $requestId ?? $this->getRequestId();

        // Convert UUID to numeric ID (use first 16 hex chars = 64 bits)
        $uuid = str_replace('-', '', $requestId);
        $traceId = substr($uuid, 0, 16);

        return (string) hexdec($traceId);
    }

    /**
     * Generate a new request ID using configured UUID version
     *
     * Supports:
     * - UUID v7 (default): Time-sortable, recommended for most use cases
     * - UUID v4: Random, used if v7 is not available
     *
     * Configure version in config/globallogger.php:
     * ```php
     * 'request_id' => [
     *     'version' => 7, // or 4
     * ]
     * ```
     *
     * @return string Generated UUID (36 characters with dashes)
     */
    protected function generateRequestId(): string
    {
        $version = $this->config['request_id']['version'] ?? 7;

        return match ($version) {
            4 => Uuid::uuid4()->toString(),
            7 => Uuid::uuid7()->toString(),
            default => Uuid::uuid4()->toString(),
        };
    }
}
