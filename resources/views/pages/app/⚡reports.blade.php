<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
    /**
     * The periods the report can be read over.
     *
     * @var array<int, string>
     */
    public const PERIODS = ['this_month', 'last_month', 'last_90_days', 'this_year'];

    #[Url(except: 'this_month')]
    public string $period = 'this_month';

    /**
     * Fall back to the current month when the URL asks for something else.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);

        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = 'this_month';
        }
    }

    /**
     * The portal the numbers belong to.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * Determine whether the reader sees the whole portal or only their own work.
     */
    #[Computed]
    public function seesWholePortal(): bool
    {
        return auth()->user()->isManager();
    }

    /**
     * The window the report covers, in the portal timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function window(): array
    {
        $now = CarbonImmutable::now($this->tenant->timezone);

        return match ($this->period) {
            'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'last_90_days' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * The bookings of the window, already scoped to who is reading.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        [$from, $to] = $this->window;

        return Booking::query()
            ->forTenant($this->tenant)
            ->with(['service', 'operator'])
            ->unless($this->seesWholePortal, fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->get();
    }

    /**
     * The headline numbers of the period.
     *
     * @return array<string, int|string>
     */
    #[Computed]
    public function totals(): array
    {
        $bookings = $this->bookings;

        $completed = $bookings->where('status', BookingStatus::Completed);
        $noShows = $bookings->where('status', BookingStatus::NoShow);
        $closed = $completed->count() + $noShows->count();

        return [
            'bookings' => $bookings->count(),
            'confirmed' => $bookings->where('status', BookingStatus::Confirmed)->count(),
            'pending' => $bookings->where('status', BookingStatus::Pending)->count(),
            'cancelled' => $bookings->where('status', BookingStatus::Cancelled)->count(),
            'completed' => $completed->count(),
            'no_shows' => $noShows->count(),
            // Out of the appointments that actually reached their day: measuring
            // no shows against future bookings would only dilute the number.
            'no_show_rate' => $closed === 0 ? '—' : round($noShows->count() / $closed * 100).'%',
            'value' => $completed->sum('price_cents'),
        ];
    }

    /**
     * How the period splits by service.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function byService(): Collection
    {
        return $this->breakdown(fn (Booking $booking) => $booking->service->name);
    }

    /**
     * How the period splits by operator.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function byOperator(): Collection
    {
        return $this->breakdown(fn (Booking $booking) => $booking->operator->name);
    }

    /**
     * Group the bookings of the period and count what matters for each group.
     *
     * @param  callable(Booking): string  $key
     * @return Collection<int, array<string, mixed>>
     */
    protected function breakdown(callable $key): Collection
    {
        return $this->bookings
            ->groupBy($key)
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'bookings' => $group->count(),
                'completed' => $group->where('status', BookingStatus::Completed)->count(),
                'no_shows' => $group->where('status', BookingStatus::NoShow)->count(),
                'cancelled' => $group->where('status', BookingStatus::Cancelled)->count(),
                'value' => $group->where('status', BookingStatus::Completed)->sum('price_cents'),
            ])
            ->sortByDesc('bookings')
            ->values();
    }

    /**
     * A money amount as the portal reads it.
     */
    public function money(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }
}; ?>

<x-slot:title>{{ __('Reports') }}</x-slot>

<div class="w-full">
    @php([$from, $to] = $this->window)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Reports')"
            :subheading="$this->seesWholePortal ? __('How the portal is doing.') : __('How your own work is going.')"
        >
            <x-slot:actions>
                <flux:select wire:model.live="period" class="max-w-52">
                    <flux:select.option value="this_month">{{ __('This month') }}</flux:select.option>
                    <flux:select.option value="last_month">{{ __('Last month') }}</flux:select.option>
                    <flux:select.option value="last_90_days">{{ __('Last 90 days') }}</flux:select.option>
                    <flux:select.option value="this_year">{{ __('This year') }}</flux:select.option>
                </flux:select>
            </x-slot:actions>
        </x-page-header>

        <flux:text class="capitalize">
            {{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}
        </flux:text>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Bookings'), 'value' => $this->totals['bookings'], 'hint' => __(':count cancelled', ['count' => $this->totals['cancelled']])],
                ['label' => __('Completed'), 'value' => $this->totals['completed'], 'hint' => __(':count still to come', ['count' => $this->totals['confirmed'] + $this->totals['pending']])],
                ['label' => __('No shows'), 'value' => $this->totals['no_shows'], 'hint' => __('rate :rate', ['rate' => $this->totals['no_show_rate']])],
                ['label' => __('Value completed'), 'value' => $this->money($this->totals['value']), 'hint' => __('services with a price')],
            ] as $card)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="card-{{ $card['label'] }}">
                    <flux:text size="sm">{{ $card['label'] }}</flux:text>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $card['value'] }}</div>
                    <flux:text size="sm" class="mt-1">{{ $card['hint'] }}</flux:text>
                </div>
            @endforeach
        </div>

        @foreach ([
            ['title' => __('By service'), 'rows' => $this->byService, 'first' => __('Service')],
            ['title' => __('By operator'), 'rows' => $this->byOperator, 'first' => __('Operator')],
        ] as $table)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="table-{{ $table['title'] }}">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ $table['title'] }}</flux:heading>
                </div>

                @if ($table['rows']->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:text>{{ __('Nothing booked in this period.') }}</flux:text>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ $table['first'] }}</flux:table.column>
                            <flux:table.column>{{ __('Bookings') }}</flux:table.column>
                            <flux:table.column>{{ __('Completed') }}</flux:table.column>
                            <flux:table.column>{{ __('No shows') }}</flux:table.column>
                            <flux:table.column>{{ __('Cancelled') }}</flux:table.column>
                            <flux:table.column>{{ __('Value completed') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($table['rows'] as $row)
                                <flux:table.row wire:key="row-{{ $table['title'] }}-{{ $row['label'] }}">
                                    <flux:table.cell variant="strong">{{ $row['label'] }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $row['bookings'] }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $row['completed'] }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $row['no_shows'] }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $row['cancelled'] }}</flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ $this->money($row['value']) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endforeach

        <flux:text size="sm">
            {{ __('Completed appointments are the ones marked as such, by hand or by the nightly close. The value only counts services carrying a price.') }}
        </flux:text>
    </div>
</div>
