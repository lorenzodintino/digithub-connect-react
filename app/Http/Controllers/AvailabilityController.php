<?php

namespace App\Http\Controllers;

use App\Actions\Availability\BlockTime;
use App\Actions\Availability\CloseThePortal;
use App\Actions\Availability\SaveWeeklySchedule;
use App\Actions\Availability\SetDateOverride;
use App\Exceptions\PeriodHasBookingsException;
use App\Http\Requests\WeeklyScheduleRequest;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\PortalClosure;
use App\Models\Tenant;
use App\Models\TimeBlock;
use App\Models\User;
use App\Support\Toast;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Weekly hours, date overrides and time blocks of a staff member — the
 * signed-in one, or for a manager any person of the portal (`?member=`) — and
 * the closures of the whole portal.
 */
class AvailabilityController extends Controller
{
    public function index(Request $request, SaveWeeklySchedule $saveWeeklySchedule): Response
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->member($user, $this->filter($request, 'member', (string) $user->id));
        $timezone = $member->tenant->timezone;

        $rules = $member->availabilityRules()->orderBy('starts_at')->get()->groupBy('weekday');

        $uncovered = $saveWeeklySchedule->uncoveredBookings($member);

        return Inertia::render('app/availability', [
            'member' => ['id' => $member->id, 'name' => $member->name, 'is_bookable' => $member->is_bookable],
            'members' => $user->isManager()
                ? $user->tenant->users()->orderByRaw("role = 'manager' desc")->orderBy('name')->get()
                    ->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name, 'is_manager' => $person->isManager()])->all()
                : [],
            'isManager' => $user->isManager(),
            'schedule' => collect(Week::ordered())->map(function (int $weekday) use ($rules): array {
                $dayRules = $rules->get($weekday, collect());

                return [
                    'weekday' => $weekday,
                    'label' => Week::label($weekday),
                    'enabled' => $dayRules->isNotEmpty(),
                    'ranges' => $dayRules->isEmpty()
                        ? [['starts_at' => '09:00', 'ends_at' => '18:00']]
                        : $dayRules->map(fn (AvailabilityRule $rule) => [
                            'starts_at' => Week::time($rule->starts_at),
                            'ends_at' => Week::time($rule->ends_at),
                        ])->values()->all(),
                ];
            })->all(),
            'uncovered' => [
                'count' => $uncovered->count(),
                'items' => $uncovered->take(5)->map(function (Booking $booking) use ($timezone): array {
                    $start = $booking->starts_at->setTimezone($timezone);

                    return [
                        'id' => $booking->id,
                        'day' => $start->translatedFormat('D d M'),
                        'line' => $start->format('H:i').' · '.$booking->displayCustomerName().' · '.$booking->service->name,
                    ];
                })->values()->all(),
            ],
            'exceptions' => $member->availabilityExceptions()->upcoming()->orderBy('date')->get()
                ->map(fn (AvailabilityException $exception) => [
                    'id' => $exception->id,
                    'date' => $exception->date->translatedFormat('D d M Y'),
                    'closed' => $exception->is_unavailable,
                    'hours' => $exception->is_unavailable ? null : Week::time($exception->starts_at).' – '.Week::time($exception->ends_at),
                    'reason' => $exception->reason,
                ])->all(),
            'timeBlocks' => $member->timeBlocks()->upcoming()->orderBy('starts_at')->get()
                ->map(function (TimeBlock $block) use ($timezone): array {
                    $start = $block->starts_at->setTimezone($timezone);

                    return [
                        'id' => $block->id,
                        'date' => $start->translatedFormat('D d M Y'),
                        'hours' => $start->format('H:i').' – '.$block->ends_at->setTimezone($timezone)->format('H:i'),
                        'reason' => $block->reason,
                    ];
                })->all(),
            'closures' => $user->isManager() ? $this->closures($user->tenant) : [],
        ]);
    }

    /**
     * Replace the weekly hours. They are a setting, so they save even when they
     * leave upcoming bookings outside: the page then lists those.
     */
    public function saveSchedule(WeeklyScheduleRequest $request, SaveWeeklySchedule $saveWeeklySchedule): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->member($user, (string) $request->validated('member'));

        $uncovered = $saveWeeklySchedule->handle($member, $request->schedule());

        if ($uncovered->isEmpty()) {
            Toast::success(__('Availability saved.'));
        } else {
            Toast::warning(trans_choice('Saved, but one booking now falls outside your hours.|Saved, but :count bookings now fall outside your hours.', $uncovered->count()));
        }

        return back();
    }

    /**
     * A single day with no hours, or with hours of its own.
     */
    public function storeException(Request $request, SetDateOverride $setDateOverride): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'member' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'unavailable' => ['required', 'boolean'],
            'starts_at' => ['exclude_if:unavailable,true', 'required', 'date_format:H:i'],
            'ends_at' => ['exclude_if:unavailable,true', 'required', 'date_format:H:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'date' => __('date'),
            'starts_at' => __('start time'),
            'ends_at' => __('end time'),
            'reason' => __('reason'),
        ]);

        $member = $this->member($user, (string) $data['member']);

        try {
            $setDateOverride->handle($member, $data['date'], (bool) $data['unavailable'], $data['starts_at'] ?? null, $data['ends_at'] ?? null, $data['reason'] ?? null);
        } catch (PeriodHasBookingsException $exception) {
            throw ValidationException::withMessages(['date' => $exception->getMessage()]);
        }

        Toast::success(__('Date override saved.'));

        return back();
    }

    public function destroyException(Request $request, AvailabilityException $exception): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->member($user, (string) $exception->user_id);

        $exception->delete();

        Toast::success(__('Date override removed.'));

        return back();
    }

    /**
     * A few hours away, expressed in the portal timezone.
     */
    public function storeTimeBlock(Request $request, BlockTime $blockTime): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'member' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'date' => __('date'),
            'starts_at' => __('start time'),
            'ends_at' => __('end time'),
            'reason' => __('reason'),
        ]);

        $member = $this->member($user, (string) $data['member']);
        $timezone = $member->tenant->timezone;

        try {
            $blockTime->handle(
                $member,
                CarbonImmutable::parse($data['date'].' '.$data['starts_at'], $timezone),
                CarbonImmutable::parse($data['date'].' '.$data['ends_at'], $timezone),
                $data['reason'] ?? null,
            );
        } catch (PeriodHasBookingsException $exception) {
            throw ValidationException::withMessages(['date' => $exception->getMessage()]);
        }

        Toast::success(__('Time block saved.'));

        return back();
    }

    public function destroyTimeBlock(Request $request, TimeBlock $block): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->member($user, (string) $block->user_id);

        $block->delete();

        Toast::success(__('Time block removed.'));

        return back();
    }

    /**
     * Shut the portal for a stretch of days, for everybody at once.
     */
    public function storeClosure(Request $request, CloseThePortal $closeThePortal): RedirectResponse
    {
        $tenant = $this->managedTenant($request);

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'starts_on' => __('first day'),
            'ends_on' => __('last day'),
            'reason' => __('reason'),
        ]);

        try {
            $closeThePortal->handle(
                $tenant,
                CarbonImmutable::parse($data['starts_on'], $tenant->timezone),
                CarbonImmutable::parse($data['ends_on'], $tenant->timezone),
                $data['reason'] ?? null,
            );
        } catch (PeriodHasBookingsException $exception) {
            throw ValidationException::withMessages(['starts_on' => $exception->getMessage()]);
        }

        Toast::success(__('Portal closure saved.'));

        return back();
    }

    public function destroyClosure(Request $request, PortalClosure $closure): RedirectResponse
    {
        $tenant = $this->managedTenant($request);

        abort_unless($closure->tenant_id === $tenant->id, 404);

        $closure->delete();

        Toast::success(__('Portal closure removed.'));

        return back();
    }

    /**
     * The person whose availability is edited: oneself, or for a manager any
     * member of the portal.
     */
    private function member(User $user, string $memberId): User
    {
        if ((string) $user->id === $memberId) {
            return $user;
        }

        $member = ctype_digit($memberId) ? User::query()->find((int) $memberId) : null;

        abort_unless($member !== null && $user->isManager() && $user->canAccessTenant($member->tenant_id), 403);

        return $member;
    }

    private function managedTenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->tenant === null, 404);
        Gate::authorize('update', $user->tenant);

        return $user->tenant;
    }

    /**
     * @return array<int, array{id: int, label: string, days: string, reason: string|null}>
     */
    private function closures(Tenant $tenant): array
    {
        return PortalClosure::query()
            ->forTenant($tenant)
            ->upcoming($tenant->timezone)
            ->orderBy('starts_on')
            ->get()
            ->map(fn (PortalClosure $closure) => [
                'id' => $closure->id,
                'label' => $closure->starts_on->translatedFormat('D d M Y').($closure->starts_on->isSameDay($closure->ends_on) ? '' : ' – '.$closure->ends_on->translatedFormat('D d M Y')),
                'days' => trans_choice('one day|:count days', $closure->days()),
                'reason' => $closure->reason,
            ])
            ->values()
            ->all();
    }
}
