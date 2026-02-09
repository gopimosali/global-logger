<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Request ID Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how request IDs are generated and managed. The request_id is
    | the primary correlation identifier used across all logs and traces.
    | It's automatically generated once per request and included in all logs.
    |
    */
    'request_id' => [
        // UUID version to use (7 recommended for time-sortability)
        'version' => 7,

        // Header name to check for existing request ID (from load balancers, etc.)
        'header' => 'X-Request-ID',

        // Include request_id in response headers
        'include_in_response' => env('GLOBALLOG_INCLUDE_REQUEST_ID_IN_RESPONSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | Enable automatic tracing for common Laravel operations. When enabled,
    | the package automatically creates traces for HTTP requests, database
    | queries, queue jobs, emails, and cache operations without requiring
    | manual trace wrapping in your code.
    |
    */
    'auto_tracing' => [
        // Master switch for automatic tracing
        'enabled' => env('GLOBALLOG_AUTO_TRACING_ENABLED', true),

        // Trace HTTP requests made via Laravel's Http facade
        'http' => env('GLOBALLOG_AUTO_TRACE_HTTP', true),

        // Trace database queries (Eloquent & Query Builder)
        'database' => env('GLOBALLOG_AUTO_TRACE_DATABASE', true),

        // Trace queue job dispatches
        'queue' => env('GLOBALLOG_AUTO_TRACE_QUEUE', true),

        // Trace email sending
        'mail' => env('GLOBALLOG_AUTO_TRACE_MAIL', true),

        // Trace cache operations (may be noisy)
        'cache' => env('GLOBALLOG_AUTO_TRACE_CACHE', false),

        // Minimum duration (ms) to log a trace (prevents noise from fast operations)
        'min_duration_ms' => env('GLOBALLOG_AUTO_TRACE_MIN_DURATION', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Providers
    |--------------------------------------------------------------------------
    |
    | Configure which logging providers are enabled. Each provider sends logs
    | to different destinations. You can enable multiple providers simultaneously.
    |
    */
    'providers' => [
        'aws' => [
            'enabled' => env('GLOBALLOG_AWS_ENABLED', false),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'log_group' => env('GLOBALLOG_AWS_LOG_GROUP', '/aws/laravel'),
            'log_stream' => env('GLOBALLOG_AWS_LOG_STREAM', 'application'),
            'xray_enabled' => env('GLOBALLOG_XRAY_ENABLED', true),
            'xray_daemon' => env('XRAY_DAEMON_ADDRESS', '127.0.0.1:2000'),
        ],

        'datadog' => [
            'enabled' => env('GLOBALLOG_DATADOG_ENABLED', false),
            'api_key' => env('DATADOG_API_KEY'),
            'host' => env('DATADOG_HOST', 'http-intake.logs.datadoghq.com'),
            'service' => env('DATADOG_SERVICE', env('APP_NAME', 'laravel')),
            'apm_enabled' => env('DATADOG_APM_ENABLED', true),
            'apm_host' => env('DD_AGENT_HOST', 'localhost'),
            'apm_port' => env('DD_TRACE_AGENT_PORT', 8126),
        ],

        'oracle' => [
            'enabled' => env('GLOBALLOG_ORACLE_ENABLED', false),
            'endpoint' => env('ORACLE_LOGGING_ENDPOINT'),
            'log_id' => env('ORACLE_LOG_ID'),
            'compartment_id' => env('ORACLE_COMPARTMENT_ID'),
            'tenancy_id' => env('ORACLE_TENANCY_ID'),
            'user_id' => env('ORACLE_USER_ID'),
            'fingerprint' => env('ORACLE_KEY_FINGERPRINT'),
            'private_key_path' => env('ORACLE_PRIVATE_KEY_PATH'),
        ],

        'database' => [
            'enabled' => env('GLOBALLOG_DATABASE_ENABLED', false),
            'connection' => env('GLOBALLOG_DB_CONNECTION', config('database.default')),
            'table' => env('GLOBALLOG_DB_TABLE', 'global_logs'),
        ],

        'custom' => [
            'enabled' => env('GLOBALLOG_CUSTOM_ENABLED', true),  // Default provider - no setup required!
            'path' => env('GLOBALLOG_CUSTOM_PATH', storage_path('logs/globallogger.log')),
            'max_files' => env('GLOBALLOG_CUSTOM_MAX_FILES', 14),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Handling
    |--------------------------------------------------------------------------
    |
    | Configure automatic exception logging behavior.
    |
    */
    'exceptions' => [
        // Automatically log all exceptions
        'auto_log' => env('GLOBALLOG_AUTO_LOG_EXCEPTIONS', true),

        // Include stack traces in exception logs
        'include_trace' => env('GLOBALLOG_EXCEPTION_INCLUDE_TRACE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy & GDPR Compliance
    |--------------------------------------------------------------------------
    |
    | Configure PII (Personally Identifiable Information) redaction to comply
    | with GDPR, CCPA, and other privacy regulations.
    |
    */
    'privacy' => [
        // Enable automatic PII redaction
        'redact_pii' => env('GLOBALLOG_REDACT_PII', true),

        // Fields to automatically redact (replaced with '[REDACTED]')
        'redact_fields' => [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'cvc',
            'ssn',
            'social_security',
            'auth_token',
            'bearer_token',
            'private_key',
        ],

        // Log user ID (set to false if your jurisdiction requires it)
        'log_user_id' => env('GLOBALLOG_LOG_USER_ID', true),

        // Log IP addresses
        'log_ip_address' => env('GLOBALLOG_LOG_IP_ADDRESS', true),

        // Log user agent
        'log_user_agent' => env('GLOBALLOG_LOG_USER_AGENT', true),

        // Anonymize IP addresses (replace last octet with 0)
        'anonymize_ip' => env('GLOBALLOG_ANONYMIZE_IP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure batching, sampling, and async logging for high-traffic apps.
    |
    */
    'performance' => [
        // Batch logging configuration
        'batch' => [
            'enabled' => env('GLOBALLOG_BATCH_ENABLED', true),
            'size' => env('GLOBALLOG_BATCH_SIZE', 100),
            'flush_interval_seconds' => env('GLOBALLOG_BATCH_FLUSH_INTERVAL', 5),
        ],

        // Async queue-based logging
        'async' => [
            'enabled' => env('GLOBALLOG_ASYNC_ENABLED', false),
            'queue' => env('GLOBALLOG_ASYNC_QUEUE', 'logs'),
            'connection' => env('GLOBALLOG_ASYNC_CONNECTION', null),
        ],

        // Log sampling (reduces volume for high-traffic apps)
        'sampling' => [
            'enabled' => env('GLOBALLOG_SAMPLING_ENABLED', false),
            'rate' => env('GLOBALLOG_SAMPLING_RATE', 0.1), // 10%
            'levels' => ['debug', 'info'], // Only sample these levels
            'exclude_errors' => true, // Never sample errors/warnings
        ],

        // Circuit breaker pattern (stops calling failing providers)
        'circuit_breaker' => [
            'enabled' => env('GLOBALLOG_CIRCUIT_BREAKER_ENABLED', true),
            'failure_threshold' => env('GLOBALLOG_CIRCUIT_BREAKER_THRESHOLD', 5),
            'timeout_seconds' => env('GLOBALLOG_CIRCUIT_BREAKER_TIMEOUT', 60),
            'half_open_requests' => env('GLOBALLOG_CIRCUIT_BREAKER_HALF_OPEN', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Log Level Filtering
    |--------------------------------------------------------------------------
    |
    | Configure minimum log levels for each provider. This allows you to send
    | different log levels to different destinations (e.g., only errors to AWS).
    |
    */
    'provider_levels' => [
        'aws' => env('GLOBALLOG_AWS_MIN_LEVEL', 'debug'),
        'datadog' => env('GLOBALLOG_DATADOG_MIN_LEVEL', 'debug'),
        'oracle' => env('GLOBALLOG_ORACLE_MIN_LEVEL', 'debug'),
        'database' => env('GLOBALLOG_DATABASE_MIN_LEVEL', 'info'),
        'custom' => env('GLOBALLOG_CUSTOM_MIN_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of old logs from the database.
    |
    */
    'retention' => [
        // Automatically delete logs older than X days
        'enabled' => env('GLOBALLOG_RETENTION_ENABLED', true),
        'days' => env('GLOBALLOG_RETENTION_DAYS', 30),

        // Run cleanup during deployment (via artisan command)
        'cleanup_on_deploy' => env('GLOBALLOG_CLEANUP_ON_DEPLOY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Feature Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure automatic logging for Laravel features. These listeners
    | automatically log events from various Laravel subsystems for monitoring
    | and visualization in the log-visualizer dashboard.
    |
    */
    'features' => [
        // Jobs & Queues Monitoring
        'jobs' => [
            'enabled' => env('GLOBALLOG_JOBS_ENABLED', true),
            'include_payload' => env('GLOBALLOG_JOBS_INCLUDE_PAYLOAD', false),
        ],

        // Cache Operations Monitoring
        'cache' => [
            'enabled' => env('GLOBALLOG_CACHE_ENABLED', true),
            'log_hits' => env('GLOBALLOG_CACHE_LOG_HITS', false), // Can be noisy
            'log_misses' => env('GLOBALLOG_CACHE_LOG_MISSES', true),
            'log_writes' => env('GLOBALLOG_CACHE_LOG_WRITES', false),
            'log_deletions' => env('GLOBALLOG_CACHE_LOG_DELETIONS', false),

            // Cache keys to ignore (supports wildcard matching with *)
            // These are framework-internal keys that produce noise
            'ignored_keys' => [
                'illuminate:queue:restart',
                'illuminate:queue:paused:*',
                'livewire-checksum-failures:*',
            ],
        ],

        // Database Query Monitoring
        'database' => [
            'enabled' => env('GLOBALLOG_DATABASE_MONITORING_ENABLED', true),
            'log_all_queries' => env('GLOBALLOG_DATABASE_LOG_ALL_QUERIES', false),
            'slow_query_threshold_ms' => env('GLOBALLOG_SLOW_QUERY_THRESHOLD', 100),
            'include_bindings' => env('GLOBALLOG_DATABASE_INCLUDE_BINDINGS', false),
            'log_connection_metrics' => env('GLOBALLOG_DATABASE_LOG_CONNECTIONS', false),

            // SQL patterns to ignore (supports wildcard matching with *)
            // These queries will never be logged, even if slow
            'ignored_queries' => [
                // 'select 1',  // Example: health checks
            ],

            // Tables to ignore - queries touching these tables won't be logged
            'ignored_tables' => [
                // 'telescope_entries',  // Example: Telescope internals
            ],
        ],

        // HTTP Request/Response Monitoring
        'http' => [
            'enabled' => env('GLOBALLOG_HTTP_ENABLED', true),
            'slow_request_threshold_ms' => env('GLOBALLOG_SLOW_REQUEST_THRESHOLD', 1000),
            'include_query_params' => env('GLOBALLOG_HTTP_INCLUDE_QUERY', false),
            'include_request_body' => env('GLOBALLOG_HTTP_INCLUDE_BODY', false),
            'excluded_paths' => [
                'health',
                'horizon/*',
                'telescope/*',
                'logs/*',
            ],
            'sensitive_fields' => [
                'password',
                'password_confirmation',
                'token',
                'api_key',
                'secret',
                'credit_card',
                'cvv',
            ],
        ],

        // Scheduled Tasks Monitoring
        'scheduler' => [
            'enabled' => env('GLOBALLOG_SCHEDULER_ENABLED', true),
        ],

        // Mail Monitoring
        'mail' => [
            'enabled' => env('GLOBALLOG_MAIL_ENABLED', true),
            'log_sending' => env('GLOBALLOG_MAIL_LOG_SENDING', false),
        ],
    ],
];
