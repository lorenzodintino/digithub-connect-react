<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    /**
     * How long a line stays in the register.
     *
     * Long enough to answer "who cancelled that appointment in March", short
     * enough that the register does not become an archive of its own.
     */
    public const KEEP_DAYS = 365;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop the register lines older than a year';

    /**
     * Forget what nobody is going to look up any more.
     */
    public function handle(): int
    {
        $dropped = ActivityLog::query()
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();

        $this->info($dropped === 0
            ? 'No register line was old enough to drop.'
            : "Dropped {$dropped} register lines.");

        return self::SUCCESS;
    }
}
