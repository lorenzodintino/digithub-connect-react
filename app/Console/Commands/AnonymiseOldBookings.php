<?php

namespace App\Console\Commands;

use App\Actions\Bookings\AnonymiseCustomer;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AnonymiseOldBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:anonymise-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erase the customer data of bookings older than each portal retention';

    /**
     * Apply the retention every portal chose for itself.
     *
     * A portal without `data_retention_days` keeps its bookings untouched: how
     * long customer data is kept is the portal's decision, never ours.
     */
    public function handle(AnonymiseCustomer $anonymiser): int
    {
        $scrubbed = 0;

        $portals = Tenant::query()
            ->whereNotNull('data_retention_days')
            ->where('data_retention_days', '>', 0)
            ->cursor();

        foreach ($portals as $portal) {
            $cutoff = now()->subDays($portal->data_retention_days);

            Booking::query()
                ->forTenant($portal)
                ->whereNull('anonymised_at')
                ->where('ends_at', '<', $cutoff)
                ->cursor()
                ->each(function (Booking $booking) use ($anonymiser, &$scrubbed): void {
                    $anonymiser->scrub($booking);
                    $scrubbed++;
                });
        }

        $this->info($scrubbed === 0
            ? 'No booking was past its retention.'
            : "Erased the customer data of {$scrubbed} bookings.");

        return self::SUCCESS;
    }
}
