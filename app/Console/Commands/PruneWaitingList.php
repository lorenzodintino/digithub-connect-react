<?php

namespace App\Console\Commands;

use App\Models\WaitingListEntry;
use Illuminate\Console\Command;

class PruneWaitingList extends Command
{
    /**
     * How long an expired entry is kept before it goes.
     */
    public const GRACE_DAYS = 7;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waiting-list:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop the waiting list entries whose period has gone by';

    /**
     * Forget the people whose window has closed.
     *
     * A waiting list is only useful while the dates on it are still ahead;
     * afterwards it is a pile of personal data nobody is going to use.
     */
    public function handle(): int
    {
        $dropped = WaitingListEntry::query()
            ->whereDate('wanted_to', '<', now()->subDays(self::GRACE_DAYS)->toDateString())
            ->delete();

        $this->info($dropped === 0
            ? 'No waiting list entry was past its period.'
            : "Dropped {$dropped} waiting list entries.");

        return self::SUCCESS;
    }
}
