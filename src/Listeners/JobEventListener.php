<?php

namespace Gopimosali\GlobalLogger\Listeners;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;

/**
 * Job Event Listener
 *
 * Logs all job/queue events automatically
 */
class JobEventListener
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Handle job queued event
     */
    public function handleJobQueued(JobQueued $event): void
    {
        if (!config('globallogger.features.jobs.enabled', true)) {
            return;
        }

        $this->logger->info('Job queued', [
            'log_type' => 'job',
            'job_status' => 'queued',
            'job_class' => $event->job::class ?? 'Unknown',
            'queue' => $event->queue ?? 'default',
            'connection' => $event->connectionName ?? config('queue.default'),
            'payload' => config('globallogger.features.jobs.include_payload', false) ? $event->payload : null,
        ]);
    }

    /**
     * Handle job processing event
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        if (!config('globallogger.features.jobs.enabled', true)) {
            return;
        }

        $this->logger->info('Job processing started', [
            'log_type' => 'job',
            'job_status' => 'processing',
            'job_class' => $event->job->resolveName(),
            'job_id' => $event->job->getJobId(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
        ]);
    }

    /**
     * Handle job processed event
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        if (!config('globallogger.features.jobs.enabled', true)) {
            return;
        }

        // Calculate duration if available
        $duration = null;
        if (method_exists($event->job, 'hasFailed') && !$event->job->hasFailed()) {
            // Duration calculation would require storing start time
            // This is a simplified version
        }

        $this->logger->info('Job completed successfully', [
            'log_type' => 'job',
            'job_status' => 'completed',
            'job_class' => $event->job->resolveName(),
            'job_id' => $event->job->getJobId(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
            'duration_ms' => $duration,
        ]);
    }

    /**
     * Handle job failed event
     */
    public function handleJobFailed(JobFailed $event): void
    {
        if (!config('globallogger.features.jobs.enabled', true)) {
            return;
        }

        $this->logger->error('Job failed', [
            'log_type' => 'job',
            'job_status' => 'failed',
            'job_class' => $event->job->resolveName(),
            'job_id' => $event->job->getJobId(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
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
            JobQueued::class => 'handleJobQueued',
            JobProcessing::class => 'handleJobProcessing',
            JobProcessed::class => 'handleJobProcessed',
            JobFailed::class => 'handleJobFailed',
        ];
    }
}
