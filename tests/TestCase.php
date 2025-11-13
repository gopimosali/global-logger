<?php

namespace Gopimosali\GlobalLogger\Tests;

use Gopimosali\GlobalLogger\GlobalLoggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
    }

    /**
     * Get package providers
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            GlobalLoggerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default config
        $app['config']->set('globallogger.providers.custom.enabled', true);
        $app['config']->set('globallogger.providers.custom.path', storage_path('logs/test.log'));

        $app['config']->set('globallogger.providers.aws.enabled', false);
        $app['config']->set('globallogger.providers.datadog.enabled', false);
        $app['config']->set('globallogger.providers.oracle.enabled', false);
        $app['config']->set('globallogger.providers.database.enabled', false);

        $app['config']->set('globallogger.request_id.version', 7);
        $app['config']->set('globallogger.request_id.header', 'X-Request-ID');
        $app['config']->set('globallogger.request_id.include_in_response', true);

        $app['config']->set('globallogger.auto_tracing.enabled', false);
    }

    /**
     * Clean up the testing environment
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
