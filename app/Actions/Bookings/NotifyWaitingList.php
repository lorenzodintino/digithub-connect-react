<?php

namespace App\Actions\Bookings;

use App\Mail\Customer\WaitingListOpeningMail;
use App\Models\Customer;
use App\Models\Service;
use App\Models\WaitingListEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class NotifyWaitingList
{
    /**
     * Tell the people waiting that this hour is free again.
     *
     * Everybody whose wanted period covers that day is told at once, and the
     * first to book takes it. Holding the place for the first in line instead
     * would leave it empty for hours whenever that person is not reading their
     * email, which is the opposite of what a waiting list is for.
     *
     * Returns how many were told.
     */
    public function handle(Service $service, CarbonImmutable $startsAt): int
    {
        $date = $startsAt->setTimezone($service->tenant->timezone)->format('Y-m-d');

        $entries = WaitingListEntry::query()
            ->with(['service', 'tenant'])
            ->where('service_id', $service->getKey())
            ->covering($date)
            ->get()
            ->groupBy(fn (WaitingListEntry $entry): string => Customer::normaliseEmail($entry->customer_email));

        $quietBefore = now()->subHours(WaitingListEntry::QUIET_HOURS);
        $notified = 0;

        foreach ($entries as $duplicates) {
            if ($duplicates->contains(fn (WaitingListEntry $entry): bool => $entry->notified_at !== null && $entry->notified_at->gte($quietBefore))) {
                continue;
            }

            $entry = $duplicates->first();

            Mail::to($entry->customer_email)
                ->locale($entry->customer_locale)
                ->send(new WaitingListOpeningMail($entry, $startsAt->toIso8601String()));

            foreach ($duplicates as $duplicate) {
                $duplicate->forceFill(['notified_at' => now()])->save();
            }

            $notified++;
        }

        return $notified;
    }
}
