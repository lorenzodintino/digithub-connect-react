<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * The register, read by the platform administrator and the portal manager.
     *
     * An operator has their own agenda; who did what across the business is
     * not theirs to read.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $seesEverything = $user->isAdmin();

        $filters = [
            'q' => $this->filter($request, 'q'),
            'group' => $this->filter($request, 'group', 'all'),
            'portal' => $seesEverything ? $this->filter($request, 'portal', 'all') : 'all',
            'actor' => $this->filter($request, 'actor', 'all'),
            'from' => $this->dateFilter($request, 'from'),
            'to' => $this->dateFilter($request, 'to'),
        ];

        $visible = fn (Builder $query) => $seesEverything ? $query : $query->where('tenant_id', $user->tenant_id);

        $entries = ActivityLog::query()
            ->with('tenant')
            ->tap($visible)
            ->when($filters['portal'] !== 'all', fn (Builder $query) => $query->where('tenant_id', $filters['portal']))
            ->when($filters['group'] !== 'all', fn (Builder $query) => $query->where('action', 'like', $filters['group'].'.%'))
            ->when($filters['actor'] !== 'all', fn (Builder $query) => $query->where('user_id', $filters['actor']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['q'] !== '', fn (Builder $query) => Search::contains($query, ['actor_name', 'action', 'subject_label'], $filters['q']))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ActivityLog $entry) => [
                'id' => $entry->id,
                'date' => $entry->created_at?->setTimezone($user->timezone)->translatedFormat('d M Y'),
                'time' => $entry->created_at?->setTimezone($user->timezone)->format('H:i'),
                'actor' => $entry->actor_name,
                'role' => $entry->actor_role,
                'action' => $this->actionLabel($entry->action),
                'subject' => $entry->subject_label,
                'portal' => $entry->tenant?->name,
                'context' => collect($entry->context ?? [])
                    ->map(fn ($value, $key) => $this->contextLine((string) $key, $value))
                    ->values()
                    ->all(),
                'ip' => $entry->ip_address,
            ]);

        return Inertia::render('shared/activity', [
            'entries' => $entries,
            'filters' => $filters,
            'seesEverything' => $seesEverything,
            // Read from what is actually in the register: a filter listing
            // kinds of event that never happened is noise.
            'groups' => ActivityLog::query()
                ->tap($visible)
                ->distinct()
                ->pluck('action')
                ->map(fn (string $action) => str($action)->before('.')->value())
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'actors' => User::query()
                ->whereKey(ActivityLog::query()->tap($visible)->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $person) => ['id' => (string) $person->id, 'name' => $person->name])
                ->all(),
            'portals' => $seesEverything
                ? Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant) => ['id' => (string) $tenant->id, 'name' => $tenant->name])->all()
                : [],
        ]);
    }

    /**
     * One reference of an entry, as «Label: value».
     *
     * The keys are English words chosen by whoever writes the entry, so they get
     * a label in the reader's language; one added later shows as it is.
     */
    private function contextLine(string $key, mixed $value): string
    {
        $label = match ($key) {
            'block_id' => __('Block id'),
            'bookings' => __('Bookings'),
            'by' => __('Made by'),
            'email' => __('Email'),
            'error' => __('Error'),
            'exempt' => __('Exempt from the license'),
            'failures' => __('Failures'),
            'fields' => __('Fields'),
            'files' => __('Files'),
            'from' => __('Changed from'),
            'impersonated_by' => __('Impersonated by'),
            'job' => __('Job'),
            'minutes' => __('Minutes'),
            'operator' => __('Operator'),
            'outcome' => __('Outcome'),
            'paid_until' => __('Paid until'),
            'portal_id' => __('Portal id'),
            'portals' => __('Portals'),
            'queue' => __('Queue'),
            'reactivated' => __('Reactivated'),
            'reason' => __('Reason'),
            'records' => __('Records'),
            'role' => __('Role'),
            'service' => __('Service'),
            'session' => __('Session'),
            'to' => __('Changed to'),
            'type' => __('Type'),
            'version' => __('Version'),
            default => $key,
        };

        $text = match (true) {
            is_array($value) => implode(', ', $value),
            is_bool($value) => $value ? __('Yes') : __('No'),
            default => (string) $value,
        };

        return $label.': '.$text;
    }

    /**
     * The wording of an action, with a fallback for the ones added later.
     */
    private function actionLabel(string $action): string
    {
        return match ($action) {
            'booking.created' => __('Booking created'),
            'booking.confirmed' => __('Booking confirmed'),
            'booking.cancelled' => __('Booking cancelled'),
            'booking.rescheduled' => __('Booking moved'),
            'booking.outcome' => __('Outcome recorded'),
            'customer.erased' => __('Customer data erased'),
            'service.created' => __('Service created'),
            'service.updated' => __('Service updated'),
            'service.deleted' => __('Service deleted'),
            'resource.created' => __('Resource created'),
            'resource.updated' => __('Resource updated'),
            'resource.deleted' => __('Resource deleted'),
            'resource.blocked' => __('Resource made unavailable'),
            'resource.unblocked' => __('Resource unavailability removed'),
            'staff.deleted' => __('Staff member deleted'),
            'account.deleted' => __('Account deleted'),
            'portal.updated' => __('Portal settings changed'),
            'portal.closed' => __('Portal closed for a period'),
            'portal.exported' => __('Portal data exported'),
            'portal.paid_until' => __('Paid period updated'),
            'portal.billing_updated' => __('Billing details updated'),
            'portal.license_exempt' => __('License exemption changed'),
            'portal.license_json_exported' => __('Customer data exported for the License Manager'),
            'portal.suspended' => __('Portal suspended for non-payment'),
            'portal.deleted' => __('Portal deleted'),
            'legal.published' => __('Legal document published'),
            'legal.accepted' => __('Legal document accepted'),
            'plan.created' => __('Plan created'),
            'plan.updated' => __('Plan updated'),
            'impersonation.started' => __('Impersonation started'),
            'impersonation.stopped' => __('Impersonation ended'),
            'impersonation.expired' => __('Impersonation expired'),
            'job.failed' => __('Job failed'),
            'job.reported' => __('Failures reported'),
            'auth.login' => __('Signed in'),
            'auth.logout' => __('Signed out'),
            'auth.failed' => __('Failed sign in'),
            default => $action,
        };
    }
}
