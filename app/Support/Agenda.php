<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The agenda of a portal as the bookings page reads it: the filters of the
 * URL, the table, the month, week and day calendars, the details of one
 * booking and what the reader may do to each of them.
 *
 * Days are grouped in the portal's timezone, the one the business works on;
 * times are printed in the reader's own.
 */
class Agenda
{
    /** @var array<int, string> */
    public const VIEWS = ['table', 'month', 'week', 'day'];

    /** @var array<int, string> */
    public const RANGES = ['upcoming', 'today', 'past', 'all'];

    /**
     * @param  array{view: string, d: string, q: string, range: string, status: string, service: string, operator: string}  $filters
     */
    public function __construct(
        private readonly User $user,
        private readonly Tenant $tenant,
        private readonly array $filters,
    ) {}

    /**
     * Read the filters of the URL, anything unknown back to its default.
     */
    public static function fromRequest(Request $request, User $user, Tenant $tenant): self
    {
        $read = function (string $key, string $default) use ($request): string {
            $value = $request->query($key);

            return is_string($value) ? trim($value) : $default;
        };

        $view = $read('view', 'table');
        $range = $read('range', 'upcoming');
        $status = $read('status', 'all');
        $focus = $read('d', '');

        return new self($user, $tenant, [
            'view' => in_array($view, self::VIEWS, true) ? $view : 'table',
            'd' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $focus) === 1 && checkdate((int) substr($focus, 5, 2), (int) substr($focus, 8, 2), (int) substr($focus, 0, 4)) ? $focus : '',
            'q' => $read('q', ''),
            'range' => in_array($range, self::RANGES, true) ? $range : 'upcoming',
            'status' => BookingStatus::tryFrom($status) !== null ? $status : 'all',
            'service' => ctype_digit($read('service', 'all')) ? $read('service', 'all') : 'all',
            'operator' => $user->isManager() && ctype_digit($read('operator', 'all')) ? $read('operator', 'all') : 'all',
        ]);
    }

    /**
     * @return array{view: string, d: string, q: string, range: string, status: string, service: string, operator: string}
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function seesWholePortal(): bool
    {
        return $this->user->isManager();
    }

    /**
     * The agenda with the filters every view shares.
     *
     * @return Builder<Booking>
     */
    public function query(): Builder
    {
        $filters = $this->filters;

        return Booking::query()
            ->forTenant($this->tenant)
            ->with(['service.tenant', 'operator'])
            ->unless($this->seesWholePortal(), fn ($query) => $query->where('user_id', $this->user->id))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['service'] !== 'all', fn ($query) => $query->where('service_id', $filters['service']))
            ->when($filters['operator'] !== 'all', fn ($query) => $query->where('user_id', $filters['operator']))
            ->when($filters['q'] !== '', fn ($query) => Search::contains($query, ['customer_name', 'customer_email', 'customer_phone'], $filters['q']));
    }

    /**
     * The table's date range, applied on top of the shared filters.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function withinRange(Builder $query): Builder
    {
        return $query
            ->when($this->filters['range'] === 'upcoming', fn ($query) => $query->where('starts_at', '>=', now()))
            ->when($this->filters['range'] === 'past', fn ($query) => $query->where('starts_at', '<', now()))
            ->when($this->filters['range'] === 'today', fn ($query) => $query->whereBetween('starts_at', [
                now($this->tenant->timezone)->startOfDay()->utc(),
                now($this->tenant->timezone)->endOfDay()->utc(),
            ]));
    }

    /**
     * What the export hands over: exactly what is on screen, the date range of
     * the table or the days the calendar draws.
     *
     * @return Builder<Booking>
     */
    public function exportQuery(): Builder
    {
        $query = $this->query()->with(['service', 'operator', 'answers']);

        if ($this->filters['view'] === 'table') {
            return $this->withinRange($query)->orderBy('starts_at');
        }

        $days = $this->calendarDays();

        return $query->whereBetween('starts_at', [$days[0]->startOfDay()->utc(), $days[count($days) - 1]->endOfDay()->utc()])->orderBy('starts_at');
    }

    /**
     * The table, fifteen bookings a page.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function table(): LengthAwarePaginator
    {
        return $this->withinRange($this->query())
            ->orderBy('starts_at', $this->filters['range'] === 'past' ? 'desc' : 'asc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Booking $booking) => $this->row($booking));
    }

    /**
     * How many bookings still wait for the reader's approval.
     */
    public function pendingCount(): int
    {
        return Booking::query()
            ->forTenant($this->tenant)
            ->unless($this->seesWholePortal(), fn ($query) => $query->where('user_id', $this->user->id))
            ->where('status', BookingStatus::Pending)
            ->upcoming()
            ->count();
    }

    /**
     * The day the calendar is centred on, in the portal timezone. Month, week
     * and day all read from it, so switching view keeps the reader in place.
     */
    public function focusDay(): CarbonImmutable
    {
        return $this->filters['d'] !== ''
            ? CarbonImmutable::parse($this->filters['d'], $this->tenant->timezone)->startOfDay()
            : CarbonImmutable::now($this->tenant->timezone)->startOfDay();
    }

    /**
     * The days the current calendar view draws, in order.
     *
     * @return array<int, CarbonImmutable>
     */
    public function calendarDays(): array
    {
        return match ($this->filters['view']) {
            'week' => Week::daysOf($this->focusDay()),
            'day' => [$this->focusDay()],
            default => array_merge(...Week::monthMatrix($this->focusDay())),
        };
    }

    /**
     * The calendar of the current view, for the month, the week or the day.
     *
     * @return array<string, mixed>
     */
    public function calendar(): array
    {
        $view = $this->filters['view'];
        $focus = $this->focusDay();
        $days = $this->calendarDays();
        $today = CarbonImmutable::now($this->tenant->timezone);

        $bookings = $this->query()
            ->whereBetween('starts_at', [$days[0]->startOfDay()->utc(), $days[count($days) - 1]->endOfDay()->utc()])
            ->orderBy('starts_at')
            ->get()
            ->toBase()
            ->groupBy(fn (Booking $booking) => $booking->startsAtIn($this->tenant->timezone)->format('Y-m-d'));

        return [
            'heading' => match ($view) {
                'week' => $days[0]->translatedFormat('d M').' – '.$days[6]->translatedFormat('d M Y'),
                'day' => $focus->translatedFormat('D d M Y'),
                default => $focus->translatedFormat('F Y'),
            },
            'focus' => $focus->format('Y-m-d'),
            'previous' => (match ($view) {
                'week' => $focus->subWeek(),
                'day' => $focus->subDay(),
                default => $focus->startOfMonth()->subMonth(),
            })->format('Y-m-d'),
            'next' => (match ($view) {
                'week' => $focus->addWeek(),
                'day' => $focus->addDay(),
                default => $focus->startOfMonth()->addMonth(),
            })->format('Y-m-d'),
            'today' => $today->format('Y-m-d'),
            'weekdays' => array_values(Week::shortLabels()),
            'days' => collect($days)->map(fn (CarbonImmutable $day) => [
                'date' => $day->format('Y-m-d'),
                'number' => $day->day,
                'weekday' => $day->translatedFormat('D'),
                'in_month' => $day->month === $focus->month,
                'is_today' => $day->isSameDay($today),
                'bookings' => ($bookings[$day->format('Y-m-d')] ?? collect())->map(fn (Booking $booking) => $this->row($booking))->values()->all(),
            ])->all(),
            'grid' => $view === 'week' ? $this->weekGrid($bookings) : null,
            'now_minute' => $today->hour * 60 + $today->minute,
            'empty' => $bookings->isEmpty(),
        ];
    }

    /**
     * The week laid out on a time grid: the hours to draw and each block's
     * place, in minutes from local midnight.
     *
     * The grid covers 08–19 and widens to fit whatever is booked earlier or
     * later. Positions are read from the clock face rather than counted, so a
     * daylight saving change does not shift the blocks of that day. The seats
     * of one group session make a single block: six participants of the same
     * class are one event on the wall, not six narrow columns.
     *
     * @param  Collection<array-key, Collection<int, Booking>>  $bookings
     * @return array{startHour: int, endHour: int, days: array<string, array<int, array<string, mixed>>>}
     */
    private function weekGrid(Collection $bookings): array
    {
        $timezone = $this->tenant->timezone;
        $startHour = 8;
        $endHour = 19;
        $days = [];

        foreach ($bookings as $date => $dayBookings) {
            $blocks = $dayBookings->groupBy(fn (Booking $booking): string => $booking->service_session_id !== null ? 'session-'.$booking->service_session_id : 'booking-'.$booking->id);
            $intervals = [];

            foreach ($blocks as $key => $block) {
                $starts = $block->first()->startsAtIn($timezone);
                $ends = $block->first()->endsAtIn($timezone);

                $start = $starts->hour * 60 + $starts->minute;
                $end = $ends->format('Y-m-d') === $date ? $ends->hour * 60 + $ends->minute : 24 * 60;
                $end = min(24 * 60, max($end, $start + 15));

                $intervals[$key] = ['start' => $start, 'end' => $end];
                $startHour = min($startHour, intdiv($start, 60));
                $endHour = max($endHour, (int) ceil($end / 60));
            }

            $days[$date] = collect(TimeGrid::place($intervals))
                ->map(function (array $place, string $key) use ($blocks): array {
                    $block = $blocks[$key];
                    $active = $block->reject(fn (Booking $booking): bool => $booking->status === BookingStatus::Cancelled);
                    $booking = $active->first() ?? $block->first();
                    $isSession = $booking->service_session_id !== null;

                    return [
                        ...$place,
                        'booking' => $this->row($booking),
                        'session' => $isSession,
                        'label' => $isSession ? trans_choice('one participant|:count participants', $active->count()) : $booking->displayCustomerName(),
                    ];
                })
                ->values()
                ->all();
        }

        return ['startHour' => $startHour, 'endHour' => $endHour, 'days' => $days];
    }

    /**
     * One booking as a line of the agenda, with what the reader may do to it.
     *
     * @return array<string, mixed>
     */
    public function row(Booking $booking): array
    {
        $timezone = $this->user->timezone;
        $start = $booking->startsAtIn($timezone);
        $outcomes = Gate::forUser($this->user)->allows('recordOutcome', $booking)
            ? collect(BookingStatus::outcomes())->reject(fn (BookingStatus $outcome) => $booking->status === $outcome)
                ->map(fn (BookingStatus $outcome) => ['value' => $outcome->value, 'label' => $outcome->outcomeLabel()])->values()->all()
            : [];

        return [
            'id' => $booking->id,
            'uid' => $booking->uid,
            'date' => $start->translatedFormat('D d M Y'),
            'time' => $start->format('H:i'),
            'hours' => $start->format('H:i').' – '.$booking->endsAtIn($timezone)->format('H:i'),
            'customer' => $booking->displayCustomerName(),
            'email' => $booking->customer_email,
            'service' => $booking->service->name,
            'color' => $booking->service->displayColor(),
            'operator' => $booking->operator->name,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'status_color' => $booking->status->color(),
            'cancelled' => $booking->status === BookingStatus::Cancelled,
            'notes' => $booking->customer_notes,
            'notes_preview' => $booking->customer_notes ? Str::limit($booking->customer_notes, 18) : null,
            'customer_page' => route('portal.booking', [$this->tenant, $booking]),
            'can' => [
                'confirm' => Gate::forUser($this->user)->allows('confirm', $booking),
                'cancel' => Gate::forUser($this->user)->allows('cancel', $booking),
                'anonymise' => Gate::forUser($this->user)->allows('anonymise', $booking),
            ],
            'outcomes' => $outcomes,
        ];
    }

    /**
     * Everything about one booking: the customer, the group session and who
     * gives it, the programme of a package, the resources it holds, the
     * answers and the note.
     *
     * @return array<string, mixed>
     */
    public function details(Booking $booking): array
    {
        $booking->load(['service.tenant', 'operator', 'answers', 'phases', 'session.operators', 'session.service', 'resourceAllocations', 'sharedResourceAllocations']);

        $timezone = $this->user->timezone;
        $session = $booking->session;
        $canManage = Gate::forUser($this->user)->allows('update', $this->tenant);
        $cancelled = $booking->status === BookingStatus::Cancelled;

        $allocations = $booking->service_session_id === null
            ? ($cancelled ? $booking->resourceAllocations : $booking->resourceAllocations->whereNull('released_at'))
            : $booking->sharedResourceAllocations;

        return [
            ...$this->row($booking),
            'phone' => $booking->customer_phone,
            'session' => $session === null ? null : [
                'seats' => trans_choice('one of :capacity places|:count of :capacity places', $session->seatsTaken(), ['capacity' => $session->capacity]),
                'operators' => $session->operators->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name])->values()->all(),
                'candidates' => $canManage ? $this->sessionCandidates($booking) : [],
                'can_manage' => $canManage,
            ],
            'phases' => $booking->phases->map(fn ($phase) => [
                'service' => $phase->service_name,
                'hours' => $phase->starts_at->setTimezone($this->tenant->timezone)->format('H:i').' – '.$phase->ends_at->setTimezone($this->tenant->timezone)->format('H:i'),
                'pause' => $phase->pause_after_minutes > 0 ? trans_choice('one minute pause|:count minutes pause', $phase->pause_after_minutes) : null,
            ])->values()->all(),
            'allocations' => [
                'title' => $cancelled ? __('Resource history') : __('Assigned resources'),
                'rows' => $allocations->map(fn ($allocation) => [
                    'id' => $allocation->id,
                    'name' => $allocation->requirement_name.': '.$allocation->resource_name,
                    'period' => $allocation->starts_at->setTimezone($timezone)->format('d/m/Y H:i').' — '.$allocation->ends_at->setTimezone($timezone)->format('d/m/Y H:i'),
                ])->values()->all(),
            ],
            'answers' => $booking->answers->map(fn (BookingAnswer $answer) => ['id' => $answer->id, 'label' => $answer->label, 'value' => $answer->value])->values()->all(),
        ];
    }

    /**
     * The staff who could still be added to a group session: only the ones
     * free at that hour, since putting somebody in two places at once is
     * exactly what this application exists to prevent.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function sessionCandidates(Booking $booking): array
    {
        $session = $booking->session;
        $taken = $session->operators->pluck('id')->all();

        return $session->service->operators()
            ->bookable()
            ->whereKeyNot($taken)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $operator) => (new SlotFinder($session->service, $operator))->joining($session)->canBookAt($session->starts_at))
            ->map(fn (User $operator) => ['id' => $operator->id, 'name' => $operator->name])
            ->values()
            ->all();
    }
}
