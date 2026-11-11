<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tracking:prune
    {--days= : Delete points older than this many days (default: TRACKING_RETENTION_DAYS)}
    {--dry-run : Count the points that would be deleted without deleting them}')]
#[Description('Delete location points older than the retention period')]
class PruneLocationPoints extends Command
{
    public function handle(): int
    {
        $days = $this->option('days') ?? config('tracking.retention_days');

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('The retention period must be a whole number of days, 1 or more.');

            return self::FAILURE;
        }

        $days = (int) $days;
        $cutoff = now('UTC')->subDays($days)->format('Y-m-d H:i:s');

        if ($this->option('dry-run')) {
            $count = DB::table('location_points')->where('recorded_at', '<', $cutoff)->count();
            $this->components->info("Dry run: {$count} location point(s) recorded before {$cutoff} UTC would be deleted.");

            return self::SUCCESS;
        }

        // Delete in slices so a large backlog never holds one long lock.
        $deleted = 0;
        do {
            $slice = DB::delete(
                'DELETE FROM location_points WHERE id IN (SELECT id FROM location_points WHERE recorded_at < ? ORDER BY id LIMIT 10000)',
                [$cutoff],
            );
            $deleted += $slice;
        } while ($slice > 0);

        // A latest position older than the retention period is history too.
        $positions = DB::table('agent_positions')->where('recorded_at', '<', $cutoff)->delete();

        $this->components->info("Deleted {$deleted} location point(s) and {$positions} stale latest position(s) recorded before {$cutoff} UTC ({$days} day retention).");

        return self::SUCCESS;
    }
}
