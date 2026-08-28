<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

class CompletePastBookings extends Command
{
    /**
     * How long after the end of an appointment it counts as completed.
     */
    public const GRACE_HOURS = 24;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:complete-past';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark confirmed appointments as completed once they are well past';

    /**
     * Close the appointments nobody wrote an outcome for.
     *
     * Only confirmed ones: a booking still waiting for approval was never
     * accepted, so it did not happen, and a cancelled or already answered one
     * has its outcome. The delay leaves room to mark a no show by hand before
     * the automatism speaks; afterwards the two outcomes stay interchangeable
     * from the agenda.
     */
    public function handle(): int
    {
        $completed = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('ends_at', '<=', now()->subHours(self::GRACE_HOURS))
            ->update(['status' => BookingStatus::Completed]);

        $this->info($completed === 0
            ? 'No appointment was due to be closed.'
            : "Closed {$completed} appointments.");

        return self::SUCCESS;
    }
}
