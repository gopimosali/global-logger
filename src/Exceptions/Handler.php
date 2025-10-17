<?php

namespace Gopimosali\GlobalLogger\Exceptions;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected GlobalLogger $globalLogger;

    public function register(): void
    {
        $this->globalLogger = app(GlobalLogger::class);

        $this->reportable(function (Throwable $e) {
            if (config('globallogger.exceptions.auto_log', true)) {
                $this->logException($e);
            }
        });
    }

    protected function logException(Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
        ];

        if (config('globallogger.exceptions.include_trace', true)) {
            $context['trace'] = $e->getTraceAsString();
        }

        $this->globalLogger->error($e->getMessage(), $context);
    }
}
