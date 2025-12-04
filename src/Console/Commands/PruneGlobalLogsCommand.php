<?php

namespace Gopimosali\GlobalLogger\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PruneGlobalLogsCommand - Clean up old log entries
 *
 * Removes log entries older than the configured retention period
 * to prevent the logs table from growing indefinitely.
 */
class PruneGlobalLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'globallogger:prune
                            {--days= : Number of days to retain logs (overrides config)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Delete old log entries from the global_logs table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('globallogger.retention.enabled', true)) {
            $this->warn('Log retention is disabled in config. Enable it to use this command.');

            return self::FAILURE;
        }

        // Get retention period
        $days = $this->option('days') ?? config('globallogger.retention.days', 30);
        $isDryRun = $this->option('dry-run');

        $this->info("Pruning logs older than {$days} days...");

        // Get database config
        $connection = config('globallogger.providers.database.connection', config('database.default'));
        $table = config('globallogger.providers.database.table', 'global_logs');

        // Calculate cutoff date
        $cutoffDate = now()->subDays((int) $days);

        // Count logs to be deleted
        $count = DB::connection($connection)
            ->table($table)
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($count === 0) {
            $this->info('No logs to prune.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} log entries to delete.");

        if ($isDryRun) {
            $this->warn('[DRY RUN] No logs were actually deleted.');

            return self::SUCCESS;
        }

        // Delete old logs
        if (! $this->confirm("Are you sure you want to delete {$count} log entries?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = DB::connection($connection)
            ->table($table)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Successfully deleted {$deleted} log entries.");

        return self::SUCCESS;
    }
}
