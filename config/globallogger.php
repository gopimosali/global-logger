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
            'enabled' => env('GLOBALLOG_CUSTOM_ENABLED', false),
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
];
