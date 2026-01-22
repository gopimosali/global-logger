<?php

namespace Gopimosali\GlobalLogger\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GenerateExampleLogsCommand - Generate example logs for testing and demonstration
 *
 * Creates various types of logs to showcase all features of GlobalLogger:
 * - Standard PSR-3 log levels
 * - Structured logging (metrics, audit, security)
 * - HTTP request tracking
 * - Performance traces
 * - PII redaction examples
 * - Error scenarios
 */
class GenerateExampleLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'globallogger:generate-examples
                            {--count=50 : Number of example logs to generate}
                            {--type= : Specific log type (all, basic, structured, http, errors, performance)}
                            {--with-errors : Include error examples}';

    /**
     * The console command description.
     */
    protected $description = 'Generate example logs to demonstrate GlobalLogger features';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $type = $this->option('type') ?? 'all';
        $withErrors = $this->option('with-errors');

        $this->info("Generating {$count} example logs...");
        $this->newLine();

        $generated = 0;

        if ($type === 'all' || $type === 'basic') {
            $generated += $this->generateBasicLogs($count);
        }

        if ($type === 'all' || $type === 'structured') {
            $generated += $this->generateStructuredLogs($count);
        }

        if ($type === 'all' || $type === 'http') {
            $generated += $this->generateHttpLogs(min($count, 10));
        }

        if ($type === 'all' || $type === 'performance') {
            $generated += $this->generatePerformanceLogs($count);
        }

        if ($withErrors || $type === 'errors') {
            $generated += $this->generateErrorLogs(min($count, 20));
        }

        $this->newLine();
        $this->info("✓ Successfully generated {$generated} example logs!");
        $this->line("View them in your log-visualizer dashboard or database.");

        return self::SUCCESS;
    }

    /**
     * Generate basic PSR-3 log level examples
     */
    protected function generateBasicLogs(int $count): int
    {
        $this->line('📝 Generating basic log level examples...');

        $messages = [
            'debug' => [
                'Database query executed: SELECT * FROM users WHERE id = ?',
                'Cache miss for key: user_profile_123',
                'Session started for user',
                'Route matched: GET /api/users',
                'Configuration loaded from cache',
            ],
            'info' => [
                'User logged in successfully',
                'Email sent to user@example.com',
                'File uploaded: document.pdf',
                'Export completed: 1,500 records',
                'Background job processed successfully',
            ],
            'notice' => [
                'User changed password',
                'API rate limit threshold reached (80%)',
                'Deprecated method called: oldMethod()',
                'Cache warming initiated',
                'Scheduled maintenance window approaching',
            ],
            'warning' => [
                'Slow database query detected (250ms)',
                'Disk space running low (15% remaining)',
                'Failed to send notification to user',
                'External API timeout (retrying)',
                'Unusual login pattern detected',
            ],
        ];

        $generated = 0;
        $levels = ['debug', 'info', 'notice', 'warning'];

        for ($i = 0; $i < min($count, 20); $i++) {
            $level = $levels[array_rand($levels)];
            $message = $messages[$level][array_rand($messages[$level])];

            Log::$level($message, [
                'user_id' => rand(1, 1000),
                'ip' => $this->generateRandomIp(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $generated++;
        }

        $this->info("  ✓ Generated {$generated} basic logs");

        return $generated;
    }

    /**
     * Generate structured logging examples (metrics, audit, security)
     */
    protected function generateStructuredLogs(int $count): int
    {
        $this->line('📊 Generating structured log examples...');
        $generated = 0;

        // Metrics
        $metrics = [
            ['name' => 'api.response_time', 'value' => rand(50, 500), 'tags' => ['endpoint' => '/api/users', 'method' => 'GET']],
            ['name' => 'database.query_time', 'value' => rand(10, 200), 'tags' => ['table' => 'users', 'type' => 'SELECT']],
            ['name' => 'cache.hit_rate', 'value' => rand(70, 99), 'tags' => ['cache' => 'redis', 'region' => 'us-east-1']],
            ['name' => 'queue.processing_time', 'value' => rand(100, 3000), 'tags' => ['queue' => 'emails', 'job' => 'SendWelcomeEmail']],
            ['name' => 'sales.daily_revenue', 'value' => rand(10000, 50000), 'tags' => ['currency' => 'USD', 'region' => 'US']],
            ['name' => 'users.active_count', 'value' => rand(500, 5000), 'tags' => ['period' => 'daily']],
            ['name' => 'api.requests_per_minute', 'value' => rand(100, 1000), 'tags' => ['endpoint' => '/api/search']],
        ];

        for ($i = 0; $i < min($count / 3, 15); $i++) {
            $metric = $metrics[array_rand($metrics)];
            Log::metric($metric['name'], $metric['value'], $metric['tags']);
            $generated++;
        }

        // Audit events
        $auditEvents = [
            ['action' => 'user.login', 'data' => ['user_id' => rand(1, 1000), 'method' => 'oauth', 'provider' => 'google']],
            ['action' => 'user.logout', 'data' => ['user_id' => rand(1, 1000), 'duration_minutes' => rand(5, 120)]],
            ['action' => 'user.profile_updated', 'data' => ['user_id' => rand(1, 1000), 'fields' => ['email', 'phone']]],
            ['action' => 'order.created', 'data' => ['order_id' => rand(1000, 9999), 'amount' => rand(50, 500), 'currency' => 'USD']],
            ['action' => 'payment.processed', 'data' => ['transaction_id' => uniqid('txn_'), 'amount' => rand(50, 500)]],
            ['action' => 'admin.settings_changed', 'data' => ['admin_id' => rand(1, 10), 'setting' => 'email_notifications']],
            ['action' => 'file.deleted', 'data' => ['file_id' => rand(1, 1000), 'filename' => 'document.pdf']],
        ];

        for ($i = 0; $i < min($count / 3, 15); $i++) {
            $event = $auditEvents[array_rand($auditEvents)];
            Log::audit($event['action'], $event['data']);
            $generated++;
        }

        // Security events
        $securityEvents = [
            ['event' => 'failed_login_attempt', 'data' => ['username' => 'admin', 'attempts' => rand(1, 5), 'ip' => $this->generateRandomIp()]],
            ['event' => 'suspicious_activity', 'data' => ['user_id' => rand(1, 1000), 'reason' => 'Multiple requests from different IPs']],
            ['event' => 'password_reset_requested', 'data' => ['email' => 'user@example.com', 'ip' => $this->generateRandomIp()]],
            ['event' => 'api_key_generated', 'data' => ['user_id' => rand(1, 1000), 'scopes' => ['read', 'write']]],
            ['event' => 'unauthorized_access_attempt', 'data' => ['endpoint' => '/admin/users', 'ip' => $this->generateRandomIp()]],
            ['event' => 'account_locked', 'data' => ['user_id' => rand(1, 1000), 'reason' => 'Too many failed login attempts']],
        ];

        for ($i = 0; $i < min($count / 3, 10); $i++) {
            $event = $securityEvents[array_rand($securityEvents)];
            Log::security($event['event'], $event['data']);
            $generated++;
        }

        $this->info("  ✓ Generated {$generated} structured logs");

        return $generated;
    }

    /**
     * Generate HTTP request examples
     */
    protected function generateHttpLogs(int $count): int
    {
        $this->line('🌐 Generating HTTP request examples...');
        $generated = 0;

        $endpoints = [
            'https://jsonplaceholder.typicode.com/posts/1',
            'https://jsonplaceholder.typicode.com/users/1',
            'https://jsonplaceholder.typicode.com/todos/1',
        ];

        for ($i = 0; $i < min($count, 5); $i++) {
            try {
                $endpoint = $endpoints[array_rand($endpoints)];
                Http::timeout(5)->get($endpoint);
                $generated++;
            } catch (\Exception $e) {
                Log::warning('HTTP request failed', [
                    'endpoint' => $endpoint ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("  ✓ Generated {$generated} HTTP request logs");

        return $generated;
    }

    /**
     * Generate performance tracking examples
     */
    protected function generatePerformanceLogs(int $count): int
    {
        $this->line('⚡ Generating performance log examples...');
        $generated = 0;

        $operations = [
            ['operation' => 'database.complex_query', 'duration' => rand(50, 500), 'metadata' => ['tables' => ['users', 'orders', 'products']]],
            ['operation' => 'image.processing', 'duration' => rand(500, 2000), 'metadata' => ['size' => '1920x1080', 'format' => 'jpg']],
            ['operation' => 'pdf.generation', 'duration' => rand(1000, 3000), 'metadata' => ['pages' => rand(5, 50)]],
            ['operation' => 'api.third_party_call', 'duration' => rand(200, 1000), 'metadata' => ['provider' => 'stripe']],
            ['operation' => 'search.elasticsearch', 'duration' => rand(30, 200), 'metadata' => ['results' => rand(10, 1000)]],
            ['operation' => 'cache.rebuild', 'duration' => rand(100, 500), 'metadata' => ['keys' => rand(100, 1000)]],
        ];

        for ($i = 0; $i < min($count, 15); $i++) {
            $op = $operations[array_rand($operations)];
            Log::performance($op['operation'], (float) $op['duration'], $op['metadata']);
            $generated++;
        }

        $this->info("  ✓ Generated {$generated} performance logs");

        return $generated;
    }

    /**
     * Generate error examples (with PII redaction demo)
     */
    protected function generateErrorLogs(int $count): int
    {
        $this->line('❌ Generating error log examples...');
        $generated = 0;

        $errors = [
            [
                'level' => 'error',
                'message' => 'Payment processing failed',
                'context' => [
                    'user_id' => rand(1, 1000),
                    'order_id' => rand(1000, 9999),
                    'error_code' => 'PAYMENT_DECLINED',
                    'card_number' => '4532********1234', // Will be redacted if "card" is in sensitive fields
                ],
            ],
            [
                'level' => 'error',
                'message' => 'Database connection failed',
                'context' => [
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'error' => 'Connection timeout after 30s',
                ],
            ],
            [
                'level' => 'error',
                'message' => 'API authentication failed',
                'context' => [
                    'endpoint' => '/api/users',
                    'api_key' => 'sk_test_***REDACTED***', // Will be redacted
                    'error' => 'Invalid API key',
                ],
            ],
            [
                'level' => 'critical',
                'message' => 'Out of memory exception',
                'context' => [
                    'memory_used' => '512MB',
                    'memory_limit' => '256MB',
                    'file' => 'ImageProcessor.php',
                    'line' => 42,
                ],
            ],
            [
                'level' => 'error',
                'message' => 'File upload failed',
                'context' => [
                    'filename' => 'document.pdf',
                    'size' => '50MB',
                    'error' => 'File size exceeds limit',
                ],
            ],
            [
                'level' => 'critical',
                'message' => 'Redis connection lost',
                'context' => [
                    'host' => 'redis.example.com',
                    'port' => 6379,
                    'password' => '***REDACTED***', // Will be redacted
                ],
            ],
        ];

        for ($i = 0; $i < min($count, 10); $i++) {
            $error = $errors[array_rand($errors)];
            Log::{$error['level']}($error['message'], $error['context']);
            $generated++;
        }

        $this->info("  ✓ Generated {$generated} error logs");

        return $generated;
    }

    /**
     * Generate a random IP address for testing
     */
    protected function generateRandomIp(): string
    {
        return implode('.', [
            rand(1, 255),
            rand(0, 255),
            rand(0, 255),
            rand(1, 255),
        ]);
    }
}
