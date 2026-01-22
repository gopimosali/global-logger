<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class CustomProvider implements LogProviderInterface
{
    protected Logger $logger;

    public function __construct(
        protected array $config
    ) {
        $this->logger = new Logger('globallogger');
        $this->logger->pushHandler(
            new RotatingFileHandler(
                $config['path'],
                $config['max_files'] ?? 14,
                Logger::DEBUG
            )
        );
    }

    public function log(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($this->mapLogLevel($level), $message, $context);
        } catch (\Throwable $e) {
            error_log('Custom file logging failed: '.$e->getMessage());
        }
    }

    protected function mapLogLevel(string $level): int
    {
        return match ($level) {
            'emergency' => Logger::EMERGENCY,
            'alert' => Logger::ALERT,
            'critical' => Logger::CRITICAL,
            'error' => Logger::ERROR,
            'warning' => Logger::WARNING,
            'notice' => Logger::NOTICE,
            'info' => Logger::INFO,
            'debug' => Logger::DEBUG,
            default => Logger::INFO,
        };
    }
}
