<?php

namespace Gopimosali\GlobalLogger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

/**
 * Monolog Handler that bridges to GlobalLogger
 *
 * This allows GlobalLogger to work seamlessly with Laravel's logging system
 */
class MonologHandler extends AbstractProcessingHandler
{
    public function __construct(
        protected GlobalLogger $globalLogger,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Write the log record to GlobalLogger
     */
    protected function write(LogRecord $record): void
    {
        $level = strtolower($record->level->getName());
        $message = $record->message;
        $context = $record->context;

        // Convert deprecation warnings to info level
        // Deprecations are identified by:
        // 1. Level: INFO (converted from WARNING)
        // 2. Message: contains "Deprecated"
        if ($level === 'warning' && (
            str_contains($message, 'Deprecated') ||
            str_contains($message, 'deprecated') ||
            str_contains(strtolower($message), 'deprecat') ||
            (isset($context['exception']) && str_contains($context['exception'], 'Deprecated'))
        )) {
            $level = 'info';
        }

        // Forward to GlobalLogger
        $this->globalLogger->log($level, $message, $context);
    }
}
