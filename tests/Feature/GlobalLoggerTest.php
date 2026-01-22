<?php

namespace Gopimosali\GlobalLogger\Tests\Feature;

use Gopimosali\GlobalLogger\GlobalLogger;
use Gopimosali\GlobalLogger\Utils\CircuitBreaker;
use Gopimosali\GlobalLogger\Utils\PrivacyHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;

class GlobalLoggerEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable database provider for testing
        config(['globallogger.providers.database.enabled' => true]);
        config(['globallogger.providers.database.table' => 'global_logs']);
    }

    /** @test */
    public function it_redacts_sensitive_fields_from_context(): void
    {
        config(['globallogger.privacy.redact_pii' => true]);

        $context = [
            'user' => 'john',
            'password' => 'secret123',
            'email' => 'john@example.com',
            'api_key' => 'sk_test_123456',
        ];

        $redacted = PrivacyHelper::redactContext($context);

        $this->assertEquals('john', $redacted['user']);
        $this->assertEquals('[REDACTED]', $redacted['password']);
        $this->assertEquals('john@example.com', $redacted['email']);
        $this->assertEquals('[REDACTED]', $redacted['api_key']);
    }

    /** @test */
    public function it_anonymizes_ipv4_addresses(): void
    {
        config(['globallogger.privacy.anonymize_ip' => true]);

        $ip = '192.168.1.100';
        $anonymized = PrivacyHelper::anonymizeIp($ip);

        $this->assertEquals('192.168.1.0', $anonymized);
    }

    /** @test */
    public function it_does_not_anonymize_ip_when_disabled(): void
    {
        config(['globallogger.privacy.anonymize_ip' => false]);

        $ip = '192.168.1.100';
        $anonymized = PrivacyHelper::anonymizeIp($ip);

        $this->assertEquals('192.168.1.100', $anonymized);
    }

    /** @test */
    public function circuit_breaker_opens_after_threshold_failures(): void
    {
        config(['globallogger.performance.circuit_breaker.enabled' => true]);
        config(['globallogger.performance.circuit_breaker.failure_threshold' => 3]);

        $breaker = new CircuitBreaker('test_provider');

        // Should be closed initially
        $this->assertTrue($breaker->canProceed());

        // Record failures
        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertTrue($breaker->canProceed()); // Still closed

        $breaker->recordFailure(); // Third failure
        $this->assertFalse($breaker->canProceed()); // Now open
    }

    /** @test */
    public function circuit_breaker_transitions_to_half_open_after_timeout(): void
    {
        config(['globallogger.performance.circuit_breaker.enabled' => true]);
        config(['globallogger.performance.circuit_breaker.failure_threshold' => 1]);
        config(['globallogger.performance.circuit_breaker.timeout_seconds' => 0]);

        $breaker = new CircuitBreaker('test_provider');

        // Open the circuit
        $breaker->recordFailure();
        $this->assertFalse($breaker->canProceed());

        // Wait for timeout (mocked by setting timeout to 0)
        sleep(1);

        // Should allow half-open request
        $this->assertTrue($breaker->canProceed());
    }

    /** @test */
    public function it_logs_structured_metrics(): void
    {
        Log::metric('api.response_time', 150.5, ['endpoint' => '/api/users']);

        $this->assertDatabaseHas('global_logs', [
            'level' => 'info',
        ]);

        $log = DB::table('global_logs')->latest('id')->first();
        $context = json_decode($log->context, true);

        $this->assertEquals('metric', $context['log_type']);
        $this->assertEquals('api.response_time', $context['metric_name']);
        $this->assertEquals(150.5, $context['metric_value']);
        $this->assertEquals('/api/users', $context['tags']['endpoint']);
    }

    /** @test */
    public function it_logs_structured_audit_events(): void
    {
        Log::audit('user.login', ['user_id' => 123, 'ip' => '192.168.1.1']);

        $this->assertDatabaseHas('global_logs', [
            'level' => 'info',
        ]);

        $log = DB::table('global_logs')->latest('id')->first();
        $context = json_decode($log->context, true);

        $this->assertEquals('audit', $context['log_type']);
        $this->assertEquals('user.login', $context['action']);
        $this->assertEquals(123, $context['user_id']);
    }

    /** @test */
    public function it_logs_structured_security_events(): void
    {
        Log::security('failed_login_attempt', ['username' => 'admin', 'attempts' => 5]);

        $this->assertDatabaseHas('global_logs', [
            'level' => 'warning',
        ]);

        $log = DB::table('global_logs')->latest('id')->first();
        $context = json_decode($log->context, true);

        $this->assertEquals('security', $context['log_type']);
        $this->assertEquals('failed_login_attempt', $context['event']);
        $this->assertEquals(5, $context['attempts']);
    }

    /** @test */
    public function it_includes_request_id_in_all_logs(): void
    {
        $response = $this->get('/');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($requestId);

        // Check that all logs have the same request_id
        $logs = DB::table('global_logs')
            ->where('request_id', $requestId)
            ->get();

        $this->assertGreaterThan(0, $logs->count());
    }

    /** @test */
    public function batch_logging_accumulates_logs_before_flushing(): void
    {
        config(['globallogger.performance.batch.enabled' => true]);
        config(['globallogger.performance.batch.size' => 5]);

        // Log 3 times (below batch size)
        Log::info('Test 1');
        Log::info('Test 2');
        Log::info('Test 3');

        // Logs might not be in DB yet (batched)
        // But after batch size is reached, they should flush
        Log::info('Test 4');
        Log::info('Test 5'); // This should trigger flush

        // Give it a moment to flush
        usleep(100000); // 100ms

        // All 5 should be in database now
        $count = DB::table('global_logs')
            ->where('message', 'LIKE', 'Test%')
            ->count();

        $this->assertGreaterThanOrEqual(5, $count);
    }

    /** @test */
    public function privacy_helper_masks_strings_correctly(): void
    {
        $masked = PrivacyHelper::maskString('1234567890', 4);

        $this->assertEquals('1234**7890', $masked);
    }

    /** @test */
    public function privacy_helper_sanitizes_request_data(): void
    {
        config(['globallogger.privacy.log_user_id' => false]);
        config(['globallogger.privacy.log_ip_address' => true]);
        config(['globallogger.privacy.anonymize_ip' => true]);

        $data = [
            'user_id' => 123,
            'ip' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0',
        ];

        $sanitized = PrivacyHelper::sanitizeRequestData($data);

        $this->assertEquals('[REDACTED]', $sanitized['user_id']);
        $this->assertEquals('192.168.1.0', $sanitized['ip']);
    }

    /** @test */
    public function prune_command_deletes_old_logs(): void
    {
        config(['globallogger.retention.enabled' => true]);
        config(['globallogger.retention.days' => 30]);

        // Create old log
        DB::table('global_logs')->insert([
            'level' => 'info',
            'message' => 'Old log',
            'context' => '{}',
            'created_at' => now()->subDays(35),
        ]);

        // Create recent log
        DB::table('global_logs')->insert([
            'level' => 'info',
            'message' => 'Recent log',
            'context' => '{}',
            'created_at' => now(),
        ]);

        $this->artisan('globallogger:prune --days=30')
            ->expectsConfirmation('Are you sure you want to delete 1 log entries?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('global_logs', [
            'message' => 'Old log',
        ]);

        $this->assertDatabaseHas('global_logs', [
            'message' => 'Recent log',
        ]);
    }

    /** @test */
    public function prune_command_dry_run_does_not_delete(): void
    {
        config(['globallogger.retention.enabled' => true]);

        DB::table('global_logs')->insert([
            'level' => 'info',
            'message' => 'Old log',
            'context' => '{}',
            'created_at' => now()->subDays(35),
        ]);

        $this->artisan('globallogger:prune --days=30 --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseHas('global_logs', [
            'message' => 'Old log',
        ]);
    }

    protected function tearDown(): void
    {
        // Clear circuit breaker cache
        Cache::flush();

        parent::tearDown();
    }
}
