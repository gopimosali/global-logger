<?php

namespace Gopimosali\GlobalLogger;

use Illuminate\Log\LogManager;
use Monolog\Logger;

/**
 * GlobalLogManager - Laravel LogManager that integrates GlobalLogger
 *
 * This class extends Laravel's LogManager and adds a custom 'globallogger' driver
 * that automatically converts deprecations to info level.
 */
class GlobalLogManager extends LogManager
{
    /**
     * Create an instance of the GlobalLogger driver
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    protected function createGloballoggerDriver(array $config): Logger
    {
        $globalLogger = $this->app->make(GlobalLogger::class);
        $handler = new MonologHandler($globalLogger);

        return new Logger('globallogger', [$handler]);
    }
}
