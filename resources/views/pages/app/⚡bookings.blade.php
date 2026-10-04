<?php

use App\Actions\Bookings\AnonymiseCustomer;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\RecordOutcome;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use App\Services\AgendaLock;
use App\Support\Csv;
use App\Support\Search;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bookings')] class extends Component {
    use WithPagination;

    #[Url(except: 'table')]
    public string $view = 'table';

    #[Url(as: 'd', except: '')]
    public string $focus = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'upcoming')]
    public string $range = 'upcoming';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: 'all')]
    public string $service = 'all';

    #[Url(except: 'all')]
    public string $operator = 'all';

    public ?int $cancellingId = null;

    #[Locked]
    public ?int $notesId = null;

    #[Locked]
    public ?int $detailsId = null;

    #[Locked]
    public ?int $anonymisingId = null;

    /**
     * The views the agenda can be shown in.
     *
     * @var array<int, string>
     */
    public const VIEWS = ['table', 'month', 'week', 'day'];

    /**
     * Fall back to the table when the URL carries a view we do not have.
     */
    public function mount(): void
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'table';
        }
    }

    public string $cancellationReason = '';

    /**
     * The portal the agenda belongs to.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * Determine whether the current user sees the whole portal agenda.
     */
    #[Computed]
    public function seesWholePortal(): bool
    {
        return auth()->user()->isManager();
    }

    /**
     * The agenda query with the filters both views share.
     *
     * @return Builder<Booking>
     */
    protected function filteredQuery(): Builder
    {
        return Booking::query()
            ->forTenant($this->tenant)
            ->with(['service.tenant', 'operator'])
            ->unless($this->seesWholePortal, fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->service !== 'all', fn ($query) => $query->where('service_id', $this->service))
            ->when($this->seesWholePortal && $this->operator !== 'all', fn ($query) => $query->where('user_id', $this->operator))
            ->when($this->search !== '', fn ($query) => Search::contains(
                $query,
                ['customer_name', 'customer_email', 'customer_phone'],
                $this->search,
            ));
    }

    /**
     * The bookings matching the current filters.
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        $ascending = $this->range !== 'past';

        return $this->filteredQuery()
            ->when($this->range === 'upcoming', fn ($query) => $query->where('starts_at', '>=', now()))
            ->when($this->range === 'past', fn ($query) => $query->where('starts_at', '<', now()))
            ->when($this->range === 'today', fn ($query) => $query->whereBetween('starts_at', [
                now($this->tenant->timezone)->startOfDay()->utc(),
                now($this->tenant->timezone)->endOfDay()->utc(),
            ]))
            ->orderBy('starts_at', $ascending ? 'asc' : 'desc')
            ->paginate(15);
    }

    /**
     * Hand the filtered agenda over as a CSV file.
     *
     * The same filters the screen is showing, so what you export is what you
     * are looking at — including the date range of the table view.
     */
    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Booking::class);

        $timezone = auth()->user()->timezone;
        $tenant = $this->tenant;

        $bookings = $this->filteredQuery()
            ->with(['service', 'operator', 'answers'])
            ->when($this->view === 'table' && $this->range === 'upcoming', fn ($query) => $query->where('starts_at', '>=', now()))
            ->when($this->view === 'table' && $this->range === 'past', fn ($query) => $query->where('starts_at', '<', now()))
            ->when($this->view === 'table' && $this->range === 'today', fn ($query) => $query->whereBetween('starts_at', [
                now($tenant->timezone)->startOfDay()->utc(),
                now($tenant->timezone)->endOfDay()->utc(),
            ]))
            ->when($this->view !== 'table', fn ($query) => $query->whereBetween('starts_at', [
                $this->calendarDays[0]->startOfDay()->utc(),
                $this->calendarDays[count($this->calendarDays) - 1]->endOfDay()->utc(),
            ]))
            ->orderBy('starts_at')
            ->lazy();

        $headings = [
            __('Reference'), __('Date'), __('Start'), __('End'), __('Service'), __('Operator'),
            __('Customer'), __('Email'), __('Phone'), __('Status'), __('Price'), __('Notes'), __('Answers'),
        ];

        $rows = $bookings->map(fn (Booking $booking) => [
            $booking->uid,
            $booking->startsAtIn($timezone)->format('d/m/Y'),
            $booking->startsAtIn($timezone)->format('H:i'),
            $booking->endsAtIn($timezone)->format('H:i'),
            $booking->service->name,
            $booking->operator->name,
            $booking->displayCustomerName(),
            $booking->customer_email,
            $booking->customer_phone,
            $booking->status->label(),
            $booking->price_cents === null ? '' : number_format($booking->price_cents / 100, 2, ',', ''),
            $booking->customer_notes,
            $booking->answers->map(fn ($answer) => $answer->label.': '.$answer->value)->implode(' | '),
        ]);

        return Csv::download(Csv::fileName(__('Bookings'), $tenant->name), $headings, $rows);
    }

    /**
     * The day the calendar is centred on, in the portal timezone.
     *
     * Month, week and day views all read from this one anchor, so switching
     * between them keeps the visitor where they were.
     */
    #[Computed]
    public function focusDay(): CarbonImmutable
    {
        $timezone = $this->tenant->timezone;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->focus) === 1
            ? CarbonImmutable::parse($this->focus, $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
    }

    /**
     * The Monday-first grid of the month on show.
     *
     * @return array<int, array<int, CarbonImmutable>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        return Week::monthMatrix($this->focusDay);
    }

    /**
     * The days the current view draws, in order.
     *
     * @return array<int, CarbonImmutable>
     */
    #[Computed]
    public function calendarDays(): array
    {
        return match ($this->view) {
            'week' => Week::daysOf($this->focusDay),
            'day' => [$this->focusDay],
            default => array_merge(...$this->calendarWeeks),
        };
    }

    /**
     * The bookings drawn by the current view, keyed by day in the portal timezone.
     *
     * The month grid shows a few days of the neighbouring months, so the window
     * is the grid itself and not the calendar month.
     *
     * @return Collection<string, Collection<int, Booking>>
     */
    #[Computed]
    public function calendarBookings(): Collection
    {
        $days = $this->calendarDays;
        $timezone = $this->tenant->timezone;

        $from = $days[0]->startOfDay()->utc();
        $to = $days[count($days) - 1]->endOfDay()->utc();

        return $this->filteredQuery()
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Booking $booking) => $booking->startsAtIn($timezone)->format('Y-m-d'));
    }

    /**
     * The bookings of the day the day view is showing.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function dayBookings(): Collection
    {
        return $this->calendarBookings[$this->focusDay->format('Y-m-d')] ?? collect();
    }

    /**
     * Step back by one month, week or day, depending on the view.
     */
    public function previous(): void
    {
        $this->moveTo(match ($this->view) {
            'week' => $this->focusDay->subWeek(),
            'day' => $this->focusDay->subDay(),
            default => $this->focusDay->startOfMonth()->subMonth(),
        });
    }

    /**
     * Step forward by one month, week or day, depending on the view.
     */
    public function next(): void
    {
        $this->moveTo(match ($this->view) {
            'week' => $this->focusDay->addWeek(),
            'day' => $this->focusDay->addDay(),
            default => $this->focusDay->startOfMonth()->addMonth(),
        });
    }

    /**
     * Bring the calendar back to today.
     */
    public function goToToday(): void
    {
        $this->moveTo(CarbonImmutable::now($this->tenant->timezone)->startOfDay());
    }

    /**
     * Open the chronological list of a single day.
     */
    public function showDayView(string $date): void
    {
        // Checked before parsing: Carbon throws on anything it does not
        // understand, and a crafted call would come back as a server error
        // instead of a refusal.
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 422);

        $this->view = 'day';

        $this->moveTo(CarbonImmutable::parse($date, $this->tenant->timezone)->startOfDay());
    }

    /**
     * Point every calendar view at the given day.
     */
    protected function moveTo(CarbonImmutable $day): void
    {
        $this->focus = $day->format('Y-m-d');

        unset($this->focusDay, $this->calendarWeeks, $this->calendarDays, $this->calendarBookings, $this->dayBookings);
    }

    /**
     * How many bookings still need manual approval.
     */
    #[Computed]
    public function pendingCount(): int
    {
        return Booking::query()
            ->forTenant($this->tenant)
            ->unless($this->seesWholePortal, fn ($query) => $query->where('user_id', auth()->id()))
            ->where('status', BookingStatus::Pending)
            ->upcoming()
            ->count();
    }

    /**
     * The services available in the filter dropdown.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->tenant->services()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The operators available in the filter dropdown.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return $this->tenant->users()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Reset pagination whenever a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'range', 'status', 'service', 'operator'], true)) {
            $this->resetPage();
        }

        if ($property === 'focus') {
            unset($this->focusDay, $this->calendarWeeks, $this->calendarDays, $this->calendarBookings, $this->dayBookings);
        }
    }

    /**
     * The booking whose note is open, if any.
     */
    #[Computed]
    public function notesBooking(): ?Booking
    {
        return $this->notesId === null ? null : Booking::find($this->notesId);
    }

    /**
     * The booking whose details are open, if any.
     */
    #[Computed]
    public function detailsBooking(): ?Booking
    {
        return $this->detailsId === null
            ? null
            : Booking::query()->with(['service.tenant', 'operator', 'answers', 'phases', 'session.operators', 'resourceAllocations', 'sharedResourceAllocations'])->find($this->detailsId);
    }

    /**
     * Open a booking picked from the calendar.
     */
    public function showBooking(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);

        $this->authorize('view', $booking);

        $this->resetValidation('sessionOperators');
        $this->detailsId = $booking->id;

        unset($this->detailsBooking);

        Flux::modal('booking-details')->show();
    }

    /**
     * The staff who could still be added to the open group session.
     *
     * Only the ones actually free at that hour: putting somebody in two places
     * at once is exactly what this application exists to prevent.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableToSession(): Collection
    {
        $session = $this->detailsBooking?->session;

        if ($session === null) {
            return collect();
        }

        $taken = $session->operators->pluck('id')->all();

        return $session->service->operators()
            ->bookable()
            ->whereKeyNot($taken)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $operator) => (new SlotFinder($session->service, $operator))->joining($session)->canBookAt($session->starts_at))
            ->values();
    }

    /**
     * Put another member of staff on the open group session.
     */
    public function addSessionOperator(int $userId): void
    {
        $this->resetValidation('sessionOperators');
        $session = $this->sessionForEditing();

        $operator = User::query()->whereKey($userId)->firstOrFail();

        abort_unless($operator->tenant_id === $session->tenant_id, 403);
        AgendaLock::forOperator($operator, function () use ($session, $operator): void {
            $session->refresh()->load('service.tenant');
            abort_unless((new SlotFinder($session->service, $operator))->joining($session)->canBookAt($session->starts_at), 422);
            $session->operators()->syncWithoutDetaching([$operator->getKey()]);
        });

        $this->refreshSession();

        Flux::toast(variant: 'success', text: __(':name is now on this session.', ['name' => $operator->name]));
    }

    /**
     * Take somebody off the open group session.
     */
    public function removeSessionOperator(int $userId): void
    {
        $this->resetValidation('sessionOperators');
        $session = $this->sessionForEditing();

        if ($session->operators()->count() <= 1) {
            $this->addError('sessionOperators', __('Somebody has to give this session.'));

            return;
        }

        $session->operators()->detach($userId);

        // The booking points at one of them for the emails and the agenda: if
        // that is the one leaving, hand the booking to whoever is left.
        $remaining = $session->operators()->orderBy('users.id')->first();

        $session->bookings()->where('user_id', $userId)->update(['user_id' => $remaining->getKey()]);

        $this->refreshSession();

        Flux::toast(variant: 'success', text: __('Removed from this session.'));
    }

    /**
     * The session of the open booking, once the reader may change it.
     */
    protected function sessionForEditing(): ServiceSession
    {
        $booking = Booking::findOrFail($this->detailsId);

        $this->authorize('update', $this->tenant);

        abort_if($booking->session === null, 404);

        return $booking->session;
    }

    /**
     * Forget everything the session touched after a change.
     */
    protected function refreshSession(): void
    {
        unset($this->bookings, $this->calendarBookings, $this->detailsBooking, $this->dayBookings, $this->assignableToSession);
    }

    /**
     * Show the whole note the customer left with their booking.
     */
    public function showNotes(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);

        $this->authorize('view', $booking);

        $this->notesId = $booking->id;

        unset($this->notesBooking);

        Flux::modal('booking-notes')->show();
    }

    /**
     * Approve a booking waiting for confirmation.
     */
    public function confirm(Booking $booking, ConfirmBooking $confirmBooking): void
    {
        $this->authorize('confirm', $booking);

        $confirmBooking->handle($booking);

        unset($this->bookings, $this->pendingCount, $this->calendarBookings, $this->detailsBooking, $this->dayBookings);

        Flux::toast(variant: 'success', text: __('Booking confirmed.'));
    }

    /**
     * Write down how a past appointment went.
     */
    public function recordOutcome(Booking $booking, string $outcome, RecordOutcome $recordOutcome): void
    {
        $this->authorize('recordOutcome', $booking);

        $recordOutcome->handle($booking, BookingStatus::from($outcome));

        unset($this->bookings, $this->pendingCount, $this->calendarBookings, $this->detailsBooking, $this->dayBookings);

        Flux::toast(variant: 'success', text: __('Booking updated.'));
    }

    /**
     * The booking whose customer is about to be erased, if any.
     */
    #[Computed]
    public function anonymisingBooking(): ?Booking
    {
        return $this->anonymisingId === null ? null : Booking::find($this->anonymisingId);
    }

    /**
     * Ask before erasing everything the portal knows about a customer.
     */
    public function askToAnonymise(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);

        $this->authorize('anonymise', $booking);

        $this->anonymisingId = $booking->id;

        unset($this->anonymisingBooking);

        Flux::modal('booking-details')->close();
        Flux::modal('anonymise-customer')->show();
    }

    /**
     * Erase the customer data this portal holds, across all their bookings.
     */
    public function anonymiseCustomer(AnonymiseCustomer $anonymiser): void
    {
        $booking = Booking::findOrFail($this->anonymisingId);

        $this->authorize('anonymise', $booking);

        // Erasing somebody who is still expected would leave an appointment
        // nobody can be reached about.
        $upcoming = Booking::query()
            ->forTenant($this->tenant)
            ->where('customer_email', $booking->customer_email)
            ->blocking()
            ->upcoming()
            ->count();

        if ($upcoming > 0) {
            $this->anonymisingId = null;

            Flux::modal('anonymise-customer')->close();
            Flux::toast(variant: 'danger', text: trans_choice('This customer still has one upcoming booking. Cancel it first.|This customer still has :count upcoming bookings. Cancel them first.', $upcoming));

            return;
        }

        $erased = $anonymiser->handle($this->tenant, $booking->customer_email);

        $this->anonymisingId = null;

        unset($this->bookings, $this->calendarBookings, $this->detailsBooking, $this->dayBookings, $this->anonymisingBooking);

        Flux::modal('anonymise-customer')->close();
        Flux::toast(variant: 'success', text: trans_choice('Customer data erased from one booking.|Customer data erased from :count bookings.', $erased));
    }

    /**
     * Ask for a reason before cancelling.
     */
    public function askToCancel(int $bookingId): void
    {
        $this->cancellingId = $bookingId;
        $this->cancellationReason = '';

        $this->resetValidation();

        Flux::modal('cancel-booking')->show();
    }

    /**
     * Cancel the selected booking.
     */
    public function cancel(CancelBooking $cancelBooking): void
    {
        $booking = Booking::findOrFail($this->cancellingId);

        $this->authorize('cancel', $booking);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:200'],
        ], attributes: ['cancellationReason' => __('reason')]);

        $cancelBooking->handle($booking, auth()->user(), $this->cancellationReason);

        $this->cancellingId = null;
        $this->cancellationReason = '';

        unset($this->bookings, $this->pendingCount, $this->calendarBookings, $this->detailsBooking, $this->dayBookings);

        Flux::modal('cancel-booking')->close();
        Flux::toast(variant: 'success', text: __('Booking cancelled.'));
    }
}; ?>

<x-slot:title>{{ __('Bookings') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Bookings')"
            :subheading="$this->seesWholePortal ? __('Every booking taken by your portal.') : __('The bookings assigned to you.')"
        >
            <x-slot:actions>
                @if ($this->pendingCount > 0)
                    <flux:badge color="amber" icon="clock">
                        {{ __(':count waiting for approval', ['count' => $this->pendingCount]) }}
                    </flux:badge>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="flex w-fit items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-700">
            <flux:button
                size="sm"
                icon="bars-3"
                :variant="$view === 'table' ? 'filled' : 'ghost'"
                wire:click="$set('view', 'table')"
            >{{ __('Table') }}</flux:button>

            <flux:separator vertical class="mx-1 my-1" />

            @foreach (['month' => __('Month'), 'week' => __('Week'), 'day' => __('Day')] as $mode => $label)
                <flux:button
                    size="sm"
                    :variant="$view === $mode ? 'filled' : 'ghost'"
                    wire:click="$set('view', '{{ $mode }}')"
                >{{ $label }}</flux:button>
            @endforeach
        </div>

        <div class="-mt-3 flex justify-end">
            <flux:button size="sm" variant="subtle" icon="arrow-down-tray" wire:click="exportCsv">
                {{ __('Export CSV') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Customer')"
                :placeholder="__('Search by customer')"
                class="lg:max-w-xs"
                clearable
            />

            @if ($view === 'table')
                <flux:select wire:model.live="range" :label="__('Period')" class="lg:max-w-40">
                    <flux:select.option value="upcoming">{{ __('Upcoming') }}</flux:select.option>
                    <flux:select.option value="today">{{ __('Today') }}</flux:select.option>
                    <flux:select.option value="past">{{ __('Past') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('All dates') }}</flux:select.option>
                </flux:select>
            @endif

            <flux:select wire:model.live="status" :label="__('Status')" class="lg:max-w-44">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                @foreach (BookingStatus::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="service" :label="__('Service')" class="lg:max-w-52">
                <flux:select.option value="all">{{ __('All services') }}</flux:select.option>
                @foreach ($this->services as $item)
                    <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->seesWholePortal)
                <flux:select wire:model.live="operator" :label="__('Operator')" class="lg:max-w-52">
                    <flux:select.option value="all">{{ __('Everyone') }}</flux:select.option>
                    @foreach ($this->operators as $person)
                        <flux:select.option :value="$person->id">{{ $person->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        @if ($view !== 'table')
            @include('partials.agenda-calendar')
        @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            @if ($this->bookings->isEmpty())
                <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <flux:icon name="calendar-days" class="size-10 text-zinc-400" />
                    <flux:heading size="lg">{{ __('No bookings here') }}</flux:heading>
                    <flux:text>{{ __('Nothing matches these filters yet.') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Service') }}</flux:table.column>
                        @if ($this->seesWholePortal)
                            <flux:table.column>{{ __('Operator') }}</flux:table.column>
                        @endif
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="w-36">{{ __('Notes') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->bookings as $booking)
                            @php($start = $booking->startsAtIn($timezone))

                            <flux:table.row wire:key="booking-{{ $booking->id }}">
                                <flux:table.cell>
                                    <div class="font-medium capitalize">{{ $start->translatedFormat('D d M Y') }}</div>
                                    <flux:text size="sm" class="tabular-nums">
                                        {{ $start->format('H:i') }} – {{ $booking->endsAtIn($timezone)->format('H:i') }}
                                    </flux:text>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">{{ $booking->displayCustomerName() }}</div>
                                        <flux:text size="sm" class="truncate">{{ $booking->customer_email }}</flux:text>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $booking->service->displayColor() }}"></span>
                                        <span class="truncate">{{ $booking->service->name }}</span>
                                    </div>
                                </flux:table.cell>

                                @if ($this->seesWholePortal)
                                    <flux:table.cell>{{ $booking->operator->name }}</flux:table.cell>
                                @endif

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$booking->status->color()" inset="top bottom">
                                        {{ $booking->status->label() }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="w-36">
                                    @if ($booking->customer_notes)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="chat-bubble-left-ellipsis"
                                            inset="top bottom"
                                            class="max-w-full"
                                            wire:click="showNotes({{ $booking->id }})"
                                        >
                                            <span class="block truncate italic">{{ Str::limit($booking->customer_notes, 18) }}</span>
                                        </flux:button>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    @include('partials.booking-actions', ['booking' => $booking])
                                </flux:table.cell>
                            </flux:table.row>

                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        @if ($this->bookings->hasPages())
            {{ $this->bookings->links() }}
        @endif
        @endif
    </div>

    <flux:modal name="booking-details" class="w-full max-w-lg">
        @if ($this->detailsBooking)
            @php($details = $this->detailsBooking)
            @php($detailsStart = $details->startsAtIn($timezone))

            <div class="space-y-6">
                <div class="space-y-2 pe-8">
                    <flux:heading size="lg">{{ $details->displayCustomerName() }}</flux:heading>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$details->status->color()">{{ $details->status->label() }}</flux:badge>

                        <flux:subheading class="capitalize">
                            {{ $detailsStart->translatedFormat('D d M Y') }} ·
                            {{ $detailsStart->format('H:i') }} – {{ $details->endsAtIn($timezone)->format('H:i') }}
                        </flux:subheading>
                    </div>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Service') }}</dt>
                        <dd class="flex items-center gap-2 font-medium">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $details->service->displayColor() }}"></span>
                            {{ $details->service->name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Operator') }}</dt>
                        <dd class="font-medium">{{ $details->operator->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt>
                        <dd class="truncate font-medium">{{ $details->customer_email }}</dd>
                    </div>

                    @if ($details->customer_phone)
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Phone') }}</dt>
                            <dd class="font-medium">{{ $details->customer_phone }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($details->session)
                    @php($session = $details->session)

                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-medium">{{ __('Group session') }}</div>
                            <flux:badge size="sm" color="zinc">
                                {{ trans_choice('one of :capacity places|:count of :capacity places', $session->seatsTaken(), ['capacity' => $session->capacity]) }}
                            </flux:badge>
                        </div>

                        <div>
                            <flux:error name="sessionOperators" />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($session->operators as $teacher)
                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pe-1 ps-3 dark:bg-zinc-800" wire:key="teacher-{{ $teacher->id }}">
                                    {{ $teacher->name }}

                                    @can('update', $this->tenant)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="x-mark"
                                            :title="__('Remove')"
                                            :aria-label="__('Remove :name from this session', ['name' => $teacher->name])"
                                            wire:click="removeSessionOperator({{ $teacher->id }})"
                                        />
                                    @endcan
                                </span>
                            @endforeach
                        </div>

                        @can('update', $this->tenant)
                            @if ($this->assignableToSession->isNotEmpty())
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:text size="sm">{{ __('Add somebody free:') }}</flux:text>

                                    @foreach ($this->assignableToSession as $candidate)
                                        <flux:button
                                            size="xs"
                                            variant="subtle"
                                            icon="plus"
                                            wire:key="candidate-{{ $candidate->id }}"
                                            wire:click="addSessionOperator({{ $candidate->id }})"
                                        >{{ $candidate->name }}</flux:button>
                                    @endforeach
                                </div>
                            @endif
                        @endcan
                    </div>
                @endif

                @include('partials.booking-phases', ['phaseBooking' => $details, 'phaseTimezone' => $this->tenant->timezone])
                @php($allocations = $details->service_session_id === null ? ($details->status === BookingStatus::Cancelled ? $details->resourceAllocations : $details->resourceAllocations->whereNull('released_at')) : $details->sharedResourceAllocations)
                @if ($allocations->isNotEmpty())
                    <div>
                        <flux:heading size="sm">{{ $details->status === BookingStatus::Cancelled ? __('Resource history') : __('Assigned resources') }}</flux:heading>
                        @foreach ($allocations as $allocation)
                            <div wire:key="allocation-{{ $allocation->id }}">
                                <flux:text>{{ $allocation->requirement_name }}: {{ $allocation->resource_name }}</flux:text>
                                <flux:text>{{ $allocation->starts_at->setTimezone($timezone)->format('d/m/Y H:i') }} — {{ $allocation->ends_at->setTimezone($timezone)->format('d/m/Y H:i') }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($details->answers->isNotEmpty())
                    <dl class="space-y-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/60">
                        @foreach ($details->answers as $answer)
                            <div wire:key="answer-{{ $answer->id }}">
                                <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $answer->label }}</dt>
                                <dd class="whitespace-pre-line">{{ $answer->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($details->customer_notes)
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Note from the customer') }}</div>
                        <p class="mt-1 whitespace-pre-line text-sm">{{ $details->customer_notes }}</p>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2">
                    @include('partials.booking-actions', ['booking' => $details, 'showDetailsAction' => false])

                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="anonymise-customer" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Erase this customer?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Name, email, phone and notes are removed from every booking of this customer in your portal. The appointments stay, so your history and counts do not change. This cannot be undone.') }}
                </flux:subheading>
            </div>

            @if ($this->anonymisingBooking)
                <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/60">
                    <div class="font-medium">{{ $this->anonymisingBooking->customer_name }}</div>
                    <div class="text-zinc-500 dark:text-zinc-400">{{ $this->anonymisingBooking->customer_email }}</div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Keep it') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="anonymiseCustomer">{{ __('Erase customer data') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="booking-notes" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Note from the customer') }}</flux:heading>

                @if ($this->notesBooking)
                    <flux:subheading>
                        {{ $this->notesBooking->displayCustomerName() }} ·
                        {{ $this->notesBooking->startsAtIn($timezone)->translatedFormat('d M Y, H:i') }}
                    </flux:subheading>
                @endif
            </div>

            @if ($this->notesBooking)
                <p class="whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $this->notesBooking->customer_notes }}
                </p>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="cancel-booking" class="w-full max-w-md">
        <form wire:submit="cancel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel this booking?') }}</flux:heading>
                <flux:subheading>{{ __('The slot is released and the reason is kept on the record.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="cancellationReason"
                :label="__('Reason')"
                :placeholder="__('Shown on the booking record')"
                required
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Keep it') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Cancel booking') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
