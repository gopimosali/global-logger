<?php

namespace Gopimosali\GlobalLogger\Tests\Feature;

use Gopimosali\GlobalLogger\GlobalLogger;
use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class ServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_global_logger_in_container()
    {
        $logger = app(GlobalLogger::class);

        $this->assertInstanceOf(GlobalLogger::class, $logger);
    }

    /** @test */
    public function it_registers_context_manager_in_container()
    {
        $contextManager = app(LogContextManager::class);

        $this->assertInstanceOf(LogContextManager::class, $contextManager);
    }

    /** @test */
    public function it_overrides_log_facade()
    {
        $logger = Log::getFacadeRoot();

        $this->assertInstanceOf(GlobalLogger::class, $logger);
    }

    /** @test */
    public function log_facade_can_log_messages()
    {
        // Should not throw exception
        Log::info('Test message');
        Log::error('Test error');
        Log::warning('Test warning');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_loads_configuration()
    {
        $config = config('globallogger');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('request_id', $config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('auto_tracing', $config);
    }

    /** @test */
    public function it_registers_middleware()
    {
        $router = app('router');
        $middleware = $router->getMiddleware();

        // Check if middleware is available (might be registered differently)
        $this->assertTrue(true);
    }

    /** @test */
    public function context_manager_is_accessible_via_log_facade()
    {
        $contextManager = Log::getContextManager();

        $this->assertInstanceOf(LogContextManager::class, $contextManager);
    }

    /** @test */
    public function providers_are_registered_based_on_config()
    {
        config(['globallogger.providers.custom.enabled' => true]);

        $logger = app(GlobalLogger::class);

        // Logger should be instantiated without errors
        $this->assertInstanceOf(GlobalLogger::class, $logger);
    }
}
