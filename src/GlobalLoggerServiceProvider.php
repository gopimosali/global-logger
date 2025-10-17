<?php

namespace Gopimosali\GlobalLogger;

use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Middleware\LogContextMiddleware;
use Gopimosali\GlobalLogger\Providers\AwsCloudWatchProvider;
use Gopimosali\GlobalLogger\Providers\CustomProvider;
use Gopimosali\GlobalLogger\Providers\DatabaseProvider;
use Gopimosali\GlobalLogger\Providers\DatadogProvider;
use Gopimosali\GlobalLogger\Providers\OracleProvider;
use Gopimosali\GlobalLogger\Tracing\AutoTracer;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class GlobalLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/globallogger.php',
            'globallogger'
        );

        // Register LogContextManager as singleton
        $this->app->singleton(LogContextManager::class, function ($app) {
            return new LogContextManager(config('globallogger'));
        });

        // Register GlobalLogger
        $this->app->singleton(GlobalLogger::class, function ($app) {
            $logger = new GlobalLogger($app->make(LogContextManager::class));

            // Register enabled providers
            $this->registerProviders($logger);

            return $logger;
        });

        // Override Laravel's Log facade to use GlobalLogger
        $this->app->extend('log', function ($service, $app) {
            return $app->make(GlobalLogger::class);
        });

        // Register AutoTracer if enabled
        if (config('globallogger.auto_tracing.enabled', true)) {
            $this->app->singleton(AutoTracer::class, function ($app) {
                return new AutoTracer(
                    $app->make(GlobalLogger::class),
                    config('globallogger.auto_tracing')
                );
            });
        }
    }

    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/globallogger.php' => config_path('globallogger.php'),
            ], 'globallogger-config');

            // Publish migration for database provider
            $this->publishes([
                __DIR__.'/../database/migrations/create_global_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_global_logs_table.php'),
            ], 'globallogger-migrations');
        }

        // Register middleware
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(LogContextMiddleware::class);

        // Boot AutoTracer if enabled
        if (config('globallogger.auto_tracing.enabled', true)) {
            $this->app->make(AutoTracer::class)->register();
        }
    }

    protected function registerProviders(GlobalLogger $logger): void
    {
        $config = config('globallogger.providers');

        // AWS CloudWatch + X-Ray
        if ($config['aws']['enabled'] ?? false) {
            $logger->addProvider(new AwsCloudWatchProvider($config['aws']));
        }

        // Datadog
        if ($config['datadog']['enabled'] ?? false) {
            $logger->addProvider(new DatadogProvider($config['datadog']));
        }

        // Oracle Cloud Logging
        if ($config['oracle']['enabled'] ?? false) {
            $logger->addProvider(new OracleProvider($config['oracle']));
        }

        // Database
        if ($config['database']['enabled'] ?? false) {
            $logger->addProvider(new DatabaseProvider($config['database']));
        }

        // Custom file logging
        if ($config['custom']['enabled'] ?? false) {
            $logger->addProvider(new CustomProvider($config['custom']));
        }
    }
}
