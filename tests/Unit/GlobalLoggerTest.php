<?php

namespace Gopimosali\GlobalLogger\Tests\Unit;

use Gopimosali\GlobalLogger\GlobalLogger;
use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Tests\TestCase;
use Mockery;
use Psr\Log\LogLevel;

class GlobalLoggerTest extends TestCase
{
    protected GlobalLogger $logger;
    protected LogContextManager $contextManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextManager = new LogContextManager([
            'request_id' => ['version' => 7],
        ]);
        $this->logger = new GlobalLogger($this->contextManager);
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(GlobalLogger::class, $this->logger);
    }

    /** @test */
    public function it_returns_context_manager()
    {
        $manager = $this->logger->getContextManager();

        $this->assertInstanceOf(LogContextManager::class, $manager);
        $this->assertSame($this->contextManager, $manager);
    }

    /** @test */
    public function it_implements_psr3_logger_interface()
    {
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $this->logger);
    }

    /** @test */
    public function it_can_add_providers()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);

        $this->logger->addProvider($provider);

        // Provider should be added successfully (no exception thrown)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_log_info_messages()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Test message', Mockery::type('array'));

        $this->logger->addProvider($provider);
        $this->logger->info('Test message');
    }

    /** @test */
    public function it_can_log_error_messages()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::ERROR, 'Error message', Mockery::type('array'));

        $this->logger->addProvider($provider);
        $this->logger->error('Error message');
    }

    /** @test */
    public function it_can_log_warning_messages()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::WARNING, 'Warning message', Mockery::type('array'));

        $this->logger->addProvider($provider);
        $this->logger->warning('Warning message');
    }

    /** @test */
    public function it_can_log_debug_messages()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'Debug message', Mockery::type('array'));

        $this->logger->addProvider($provider);
        $this->logger->debug('Debug message');
    }

    /** @test */
    public function it_enriches_logs_with_context()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Test message', Mockery::on(function ($context) {
                return isset($context['request_id'])
                    && isset($context['timestamp'])
                    && isset($context['environment'])
                    && isset($context['application']);
            }));

        $this->logger->addProvider($provider);
        $this->logger->info('Test message');
    }

    /** @test */
    public function it_merges_provided_context_with_automatic_context()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Test message', Mockery::on(function ($context) {
                return isset($context['request_id'])
                    && isset($context['user_id'])
                    && $context['user_id'] === 123;
            }));

        $this->logger->addProvider($provider);
        $this->logger->info('Test message', ['user_id' => 123]);
    }

    /** @test */
    public function it_can_start_a_trace()
    {
        $traceId = $this->logger->startTrace('test.operation', ['key' => 'value']);

        $this->assertIsString($traceId);
        $this->assertNotEmpty($traceId);
    }

    /** @test */
    public function it_can_end_a_trace()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')->once();

        $this->logger->addProvider($provider);

        $traceId = $this->logger->startTrace('test.operation');
        usleep(10000); // 10ms
        $this->logger->endTrace($traceId);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_trace_with_duration()
    {
        $provider = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, Mockery::type('string'), Mockery::on(function ($context) {
                return isset($context['duration_ms'])
                    && $context['duration_ms'] >= 10
                    && isset($context['type'])
                    && $context['type'] === 'trace';
            }));

        $this->logger->addProvider($provider);

        $traceId = $this->logger->startTrace('test.operation');
        usleep(10000); // 10ms
        $this->logger->endTrace($traceId);
    }

    /** @test */
    public function it_handles_multiple_providers()
    {
        $provider1 = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider1->shouldReceive('log')->once();

        $provider2 = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider2->shouldReceive('log')->once();

        $this->logger->addProvider($provider1);
        $this->logger->addProvider($provider2);

        $this->logger->info('Test message');
    }

    /** @test */
    public function it_continues_logging_if_one_provider_fails()
    {
        $provider1 = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider1->shouldReceive('log')->once()->andThrow(new \Exception('Provider 1 failed'));

        $provider2 = Mockery::mock(\Gopimosali\GlobalLogger\Contracts\LogProviderInterface::class);
        $provider2->shouldReceive('log')->once();

        $this->logger->addProvider($provider1);
        $this->logger->addProvider($provider2);

        // Should not throw exception even though provider1 fails
        $this->logger->info('Test message');

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
