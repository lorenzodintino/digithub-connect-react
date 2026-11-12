<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Support\Search;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WaitingListController extends Controller
{
    /**
     * The people waiting for a place, the soonest window first.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $search = $this->filter($request, 'q');

        /** @var User $user */
        $user = $request->user();

        $entries = WaitingListEntry::query()
            ->forTenant($tenant)
            ->with('service')
            ->when($search !== '', fn ($query) => Search::contains($query, ['customer_name', 'customer_email', 'customer_phone'], $search))
            ->orderBy('wanted_from')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WaitingListEntry $entry) => [
                'uid' => $entry->uid,
                'name' => $entry->customer_name,
                'email' => $entry->customer_email,
                'phone' => $entry->customer_phone,
                'service' => $entry->service->name,
                'period' => $entry->wanted_from->translatedFormat('d M').' – '.$entry->wanted_to->translatedFormat('d M Y'),
                'notified' => $entry->notified_at?->setTimezone($user->timezone)->translatedFormat('d M, H:i'),
            ]);

        return Inertia::render('app/waiting-list', [
            'entries' => $entries,
            'filters' => ['q' => $search],
        ]);
    }

    /**
     * Take somebody off the list on their behalf.
     */
    public function destroy(Request $request, WaitingListEntry $entry): RedirectResponse
    {
        $tenant = $this->tenant($request);

        abort_unless($entry->tenant_id === $tenant->id, 404);

        $entry->delete();

        Toast::success(__('Removed from the waiting list.'));

        return back();
    }

    /**
     * Only the manager runs the waiting list of the portal.
     */
    private function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        abort_if($tenant === null, 404);
        Gate::authorize('update', $tenant);

        return $tenant;
    }
}
