<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\BookingNotifier;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind customers about appointments that are coming up';

    /**
     * Send the reminders that are due.
     *
     * Deliberately not driven by a time window. Asking for "bookings starting
     * between now+H and now+H+15min" would silently skip anything that fell
     * between two runs — a missed run, a slow queue, a restart. Instead it asks
     * for everything already inside the reminder window and not yet reminded,
     * which is self-healing: a late run simply catches up.
     */
    public function handle(BookingNotifier $notifier): int
    {
        $sent = 0;

        $portals = Tenant::query()
            ->active()
            ->whereNotNull('reminder_hours_before')
            ->where('reminder_hours_before', '>', 0)
            ->cursor();

        foreach ($portals as $portal) {
            $sent += $this->remindPortal($portal, $notifier);
        }

        $this->info($sent === 0 ? 'No reminder was due.' : "Sent {$sent} reminders.");

        return self::SUCCESS;
    }

    /**
     * Send the reminders due for a single portal.
     */
    private function remindPortal(Tenant $portal, BookingNotifier $notifier): int
    {
        $hours = $portal->reminder_hours_before;

        $bookings = Booking::query()
            ->where('tenant_id', $portal->getKey())
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours($hours))
            ->with(['service', 'operator', 'tenant'])
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            // Somebody booking inside the reminder window does not need to be
            // reminded of what they just did.
            if ($booking->created_at->greaterThan($booking->starts_at->subHours($hours))) {
                $booking->update(['reminder_sent_at' => now()]);

                continue;
            }

            $notifier->reminder($booking);

            $booking->update(['reminder_sent_at' => now()]);

            $sent++;
        }

        return $sent;
    }
}
