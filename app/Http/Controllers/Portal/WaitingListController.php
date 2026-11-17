<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinWaitingListRequest;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\WaitingListEntry;
use App\Support\PortalPage;
use App\Support\Timezones;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of the waiting list: joining it from a service page, and
 * leaving it from the link in the email.
 */
class WaitingListController extends Controller
{
    /**
     * Put somebody on the waiting list of a service.
     *
     * One entry per service and address: asking again means changing the
     * dates, not queueing twice. An address saved with other casing before
     * the addresses were normalised is the same person.
     */
    public function store(JoinWaitingListRequest $request, Tenant $tenant, Service $service): RedirectResponse
    {
        abort_unless($tenant->isOperational() && $service->is_active && $service->tenant_id === $tenant->id, 404);

        PortalPage::speak($request, $tenant->locale);

        // Not in the plan: nobody new joins (the entries already there are still written to when a place opens).
        abort_unless($tenant->planAllows('waiting_list'), 404);

        $validated = $request->validated();

        if (($wait = PortalPage::throttle($service, $request)) !== null) {
            throw ValidationException::withMessages([
                'email' => trans_choice('Too many booking attempts. Try again in one second.|Too many booking attempts. Try again in :count seconds.', $wait),
            ]);
        }

        $existing = WaitingListEntry::query()
            ->forTenant($tenant)
            ->where('service_id', $service->getKey())
            ->whereRaw('LOWER(customer_email) = ?', [$validated['email']])
            ->orderBy('id')
            ->first();

        // Asking again changes the dates of the same person: only a new one needs a place.
        if ($existing === null && $tenant->planUsage()->waitingListFull($service)) {
            throw ValidationException::withMessages([
                'email' => __('The waiting list is full for now. Try again later.'),
            ]);
        }

        WaitingListEntry::updateOrCreate(
            [
                'service_id' => $service->getKey(),
                'customer_email' => $existing->customer_email ?? $validated['email'],
            ],
            [
                'uid' => WaitingListEntry::newUid(),
                'tenant_id' => $tenant->getKey(),
                'customer_name' => $validated['name'],
                'customer_phone' => ($validated['phone'] ?? null) ?: null,
                'customer_timezone' => is_string($validated['tz'] ?? null) && Timezones::isSupported($validated['tz']) ? $validated['tz'] : $tenant->timezone,
                'customer_locale' => app()->getLocale(),
                'wanted_from' => $validated['from'],
                'wanted_to' => $validated['to'],
                'privacy_accepted_at' => now(),
                'privacy_accepted_url' => $tenant->privacyPolicyLink(),
                'notified_at' => null,
            ],
        );

        Toast::success(__('We will write to you as soon as something opens up.'));

        return back();
    }

    /**
     * Show the way out, without taking it yet: mail scanners and link
     * previews fetch every address in a message, and somebody who never
     * clicked would silently disappear from the list they asked to join.
     */
    public function show(Request $request, Tenant $tenant, WaitingListEntry $entry): Response
    {
        abort_unless($entry->tenant_id === $tenant->id, 404);

        PortalPage::speak($request, $entry->customer_locale);

        return $this->page($request, $tenant, $entry, false);
    }

    /**
     * Take the person off the list, and say so on the same page: the entry is
     * gone, so there is nothing left to redirect to.
     */
    public function destroy(Request $request, Tenant $tenant, WaitingListEntry $entry): Response
    {
        abort_unless($entry->tenant_id === $tenant->id, 404);

        PortalPage::speak($request, $entry->customer_locale);

        $entry->delete();

        return $this->page($request, $tenant, $entry, true);
    }

    private function page(Request $request, Tenant $tenant, WaitingListEntry $entry, bool $left): Response
    {
        return Inertia::render('portal/waiting-list', [
            'portal' => PortalPage::layout($tenant, $request),
            'service' => $entry->service->name,
            'left' => $left,
            'leaveUrl' => route('portal.waiting-list.destroy', [$tenant, $entry, ...PortalPage::keepEmbed($request)]),
        ]);
    }
}
