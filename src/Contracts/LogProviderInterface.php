<?php

namespace Gopimosali\GlobalLogger\Contracts;

interface LogProviderInterface
{
    /**
     * Log a message with the given level and context
     *
     * @param  string  $level  PSR-3 log level
     * @param  string  $message  Log message
     * @param  array  $context  Additional context data
     */
    public function log(string $level, string $message, array $context): void;
}
