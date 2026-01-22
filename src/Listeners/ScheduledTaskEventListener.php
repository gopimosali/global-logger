<?php

namespace Gopimosali\GlobalLogger\Listeners;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

/**
 * Scheduled Task Event Listener
 *
 * Logs all scheduled task (cron) events automatically
 */
class ScheduledTaskEventListener
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Handle scheduled task starting event
     */
    public function handleTaskStarting(ScheduledTaskStarting $event): void
    {
        if (!config('globallogger.features.scheduler.enabled', true)) {
            return;
        }

        $this->logger->info('Scheduled task starting', [
            'log_type' => 'scheduled_task',
            'task_status' => 'starting',
            'task_command' => $event->task->command,
            'task_description' => $event->task->description,
            'task_expression' => $event->task->expression,
            'task_timezone' => $event->task->timezone ?? config('app.timezone'),
        ]);
    }

    /**
     * Handle scheduled task finished event
     */
    public function handleTaskFinished(ScheduledTaskFinished $event): void
    {
        if (!config('globallogger.features.scheduler.enabled', true)) {
            return;
        }

        $runtime = $event->runtime ?? 0;

        $this->logger->info('Scheduled task completed', [
            'log_type' => 'scheduled_task',
            'task_status' => 'completed',
            'task_command' => $event->task->command,
            'task_description' => $event->task->description,
            'task_expression' => $event->task->expression,
            'duration_ms' => round($runtime * 1000, 2),
            'exit_code' => $event->task->exitCode ?? 0,
        ]);
    }

    /**
     * Handle scheduled task failed event
     */
    public function handleTaskFailed(ScheduledTaskFailed $event): void
    {
        if (!config('globallogger.features.scheduler.enabled', true)) {
            return;
        }

        $this->logger->error('Scheduled task failed', [
            'log_type' => 'scheduled_task',
            'task_status' => 'failed',
            'task_command' => $event->task->command,
            'task_description' => $event->task->description,
            'task_expression' => $event->task->expression,
            'exception_class' => get_class($event->exception),
            'exception_message' => $event->exception->getMessage(),
            'exception_file' => $event->exception->getFile(),
            'exception_line' => $event->exception->getLine(),
        ]);
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe($events): array
    {
        return [
            ScheduledTaskStarting::class => 'handleTaskStarting',
            ScheduledTaskFinished::class => 'handleTaskFinished',
            ScheduledTaskFailed::class => 'handleTaskFailed',
        ];
    }
}
