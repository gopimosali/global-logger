<?php

namespace Gopimosali\GlobalLogger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void emergency(string|\Stringable $message, array $context = [])
 * @method static void alert(string|\Stringable $message, array $context = [])
 * @method static void critical(string|\Stringable $message, array $context = [])
 * @method static void error(string|\Stringable $message, array $context = [])
 * @method static void warning(string|\Stringable $message, array $context = [])
 * @method static void notice(string|\Stringable $message, array $context = [])
 * @method static void info(string|\Stringable $message, array $context = [])
 * @method static void debug(string|\Stringable $message, array $context = [])
 * @method static void log(string $level, string|\Stringable $message, array $context = [])
 * @method static string startTrace(string $name, array $metadata = [])
 * @method static void endTrace(string $traceId, array $additionalMetadata = [])
 * @method static \Gopimosali\GlobalLogger\LogContext\LogContextManager getContextManager()
 *
 * @see \Gopimosali\GlobalLogger\GlobalLogger
 */
class GlobalLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gopimosali\GlobalLogger\GlobalLogger::class;
    }
}
