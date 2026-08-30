<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\SlotFinder;
use App\Support\QrCode;
use App\Support\Timezones;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    #[Locked]
    public Tenant $tenant;

    #[Locked]
    public Booking $booking;

    public bool $rescheduling = false;

    public string $month = '';

    public string $selectedDate = '';

    public string $visitorTimezone = '';

    /**
     * Resolve the booking from its unguessable public token.
     */
    public function mount(Tenant $tenant, Booking $booking): void
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);

        $this->tenant = $tenant;
        $this->booking = $booking;
        $this->visitorTimezone = Timezones::isSupported($booking->customer_timezone)
            ? $booking->customer_timezone
            : $tenant->timezone;
        $this->month = $booking->starts_at->setTimezone($tenant->timezone)->startOfMonth()->format('Y-m-d');

        if (! session()->has('locale') && ! request()->filled('lang')) {
            app()->setLocale($tenant->locale);
        }
    }

    /**
     * The public link that manages this booking.
     */
    #[Computed]
    public function manageUrl(): string
    {
        return route('portal.booking', [$this->tenant, $this->booking]);
    }

    /**
     * The QR code for the manage link, as inline SVG.
     *
     * It encodes the link, never the appointment details: if the booking is
     * moved or cancelled the same code keeps pointing at the current state.
     */
    #[Computed]
    public function qrCode(): string
    {
        return QrCode::svg($this->manageUrl, 160);
    }

    /**
     * The file name proposed when the visitor downloads the QR code.
     */
    #[Computed]
    public function qrFileName(): string
    {
        return Str::slug($this->tenant->name.'-'.$this->booking->service->name).'-'.$this->booking->uid.'.png';
    }

    /**
     * Links that put the appointment into the customer's own calendar.
     *
     * Google and Outlook take a plain URL; Apple and everything else get the
     * same `.ics` file, inline for Apple so iOS opens it in the Calendar app
     * instead of parking it in Files.
     *
     * @return array{google: string, outlook: string, apple: string, download: string}
     */
    #[Computed]
    public function calendarLinks(): array
    {
        $booking = $this->booking;
        $service = $booking->service;

        $title = $service->name.' · '.$this->tenant->name;
        $details = __('With :name', ['name' => $booking->operator->name])."
".$this->manageUrl;
        $location = $service->location_value
            ? $service->location_type->label().' · '.$service->location_value
            : $service->location_type->label();

        $startUtc = $booking->starts_at->setTimezone('UTC');
        $endUtc = $booking->ends_at->setTimezone('UTC');

        return [
            'google' => 'https://calendar.google.com/calendar/render?'.http_build_query([
                'action' => 'TEMPLATE',
                'text' => $title,
                'dates' => $startUtc->format('Ymd\THis\Z').'/'.$endUtc->format('Ymd\THis\Z'),
                'details' => $details,
                'location' => $location,
            ]),
            'outlook' => 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query([
                'path' => '/calendar/action/compose',
                'rru' => 'addevent',
                'subject' => $title,
                'startdt' => $startUtc->toIso8601ZuluString(),
                'enddt' => $endUtc->toIso8601ZuluString(),
                'body' => $details,
                'location' => $location,
            ]),
            'apple' => route('portal.booking.calendar', [$this->tenant, $booking, 'inline' => 1]),
            'download' => route('portal.booking.calendar', [$this->tenant, $booking]),
        ];
    }

    /**
     * The slot engine for this booking, ignoring the booking itself.
     */
    #[Computed]
    public function finder(): SlotFinder
    {
        return (new SlotFinder($this->booking->service, $this->booking->operator))->ignoring($this->booking);
    }

    /**
     * Slots of the drawn month, keyed by date in the portal timezone.
     *
     * @return array<string, array<int, CarbonImmutable>>
     */
    #[Computed]
    public function monthSlots(): array
    {
        $month = CarbonImmutable::parse($this->month, $this->tenant->timezone);

        return $this->finder->slotsBetween($month->startOfMonth(), $month->endOfMonth());
    }

    /**
     * The calendar grid of the drawn month.
     *
     * @return array<int, array<int, CarbonImmutable>>
     */
    #[Computed]
    public function calendar(): array
    {
        return Week::monthMatrix(CarbonImmutable::parse($this->month, $this->tenant->timezone));
    }

    /**
     * The slots of the selected day.
     *
     * @return array<int, CarbonImmutable>
     */
    #[Computed]
    public function daySlots(): array
    {
        return $this->selectedDate === '' ? [] : ($this->monthSlots[$this->selectedDate] ?? []);
    }

    /**
     * Determine whether the visitor can still step back to an earlier month.
     */
    #[Computed]
    public function canGoBack(): bool
    {
        return CarbonImmutable::parse($this->month, $this->tenant->timezone)
            ->greaterThan(CarbonImmutable::now($this->tenant->timezone)->startOfMonth());
    }

    /**
     * Open the reschedule calendar.
     */
    public function startReschedule(): void
    {
        abort_unless($this->booking->isCancellableByCustomer() && $this->tenant->is_active, 403);

        // A seat on a class is not moved: the class is the appointment.
        abort_if($this->booking->service->isGroup(), 422);

        $this->rescheduling = true;
        $this->month = CarbonImmutable::now($this->tenant->timezone)->startOfMonth()->format('Y-m-d');
        $this->selectedDate = '';
    }

    /**
     * Close the reschedule calendar.
     */
    public function stopReschedule(): void
    {
        $this->rescheduling = false;
        $this->selectedDate = '';
    }

    /**
     * Move the calendar one month back.
     */
    public function previousMonth(): void
    {
        if (! $this->canGoBack) {
            return;
        }

        $this->month = CarbonImmutable::parse($this->month, $this->tenant->timezone)->subMonth()->format('Y-m-d');
        $this->selectedDate = '';

        unset($this->monthSlots, $this->calendar, $this->daySlots);
    }

    /**
     * Move the calendar one month forward.
     */
    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month, $this->tenant->timezone)->addMonth()->format('Y-m-d');
        $this->selectedDate = '';

        unset($this->monthSlots, $this->calendar, $this->daySlots);
    }

    /**
     * Pick a day from the calendar.
     */
    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    /**
     * Move the booking to the chosen slot.
     */
    public function selectSlot(string $slot, RescheduleBooking $rescheduleBooking): void
    {
        abort_unless($this->booking->isCancellableByCustomer() && $this->tenant->is_active, 403);

        try {
            $this->booking = $rescheduleBooking->handle($this->booking, CarbonImmutable::parse($slot, 'UTC'));
        } catch (SlotUnavailableException $exception) {
            unset($this->monthSlots, $this->daySlots);

            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->rescheduling = false;
        $this->selectedDate = '';

        unset($this->monthSlots, $this->daySlots, $this->finder);

        Flux::toast(variant: 'success', text: __('Your booking has been moved.'));
    }

    /**
     * Cancel the booking on behalf of the customer.
     */
    public function cancel(CancelBooking $cancelBooking): void
    {
        abort_unless($this->booking->isCancellableByCustomer(), 403);

        $this->booking = $cancelBooking->handle($this->booking);

        Flux::modal('cancel-booking')->close();
        Flux::toast(variant: 'success', text: __('Your booking has been cancelled.'));
    }
}; ?>

<x-slot:title>{{ __('Your booking').' · '.$tenant->name }}</x-slot>

<div class="flex min-h-screen flex-col" style="--portal-brand: {{ $tenant->brand_color }}; --portal-brand-contrast: {{ $tenant->brandForeground() }}; --color-accent: {{ $tenant->brand_color }}; --color-accent-content: {{ $tenant->brand_color }}; --color-accent-foreground: {{ $tenant->brandForeground() }}">
    <x-portal-header :tenant="$tenant" />

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6 sm:py-12">
        @php
            $service = $booking->service;
            $start = $booking->startsAtIn($visitorTimezone);
            $end = $booking->endsAtIn($visitorTimezone);
        @endphp

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="h-1.5 w-full" style="background-color: {{ $service->displayColor() }}"></div>

            <div class="space-y-6 p-6 sm:p-8">
                <div class="flex flex-col items-center gap-3 text-center">
                    @if ($booking->status === BookingStatus::Cancelled)
                        <span class="flex size-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400">
                            <flux:icon name="x-mark" />
                        </span>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Booking cancelled') }}</h1>
                    @elseif ($booking->awaitsConfirmation())
                        <span class="flex size-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                            <flux:icon name="clock" />
                        </span>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Request received') }}</h1>
                        <p class="text-zinc-600 dark:text-zinc-400">
                            {{ __(':name will confirm your booking shortly.', ['name' => $tenant->name]) }}
                        </p>
                    @else
                        <span class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                            <flux:icon name="check" />
                        </span>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Booking confirmed') }}</h1>
                    @endif
                </div>

                <dl class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Service') }}</dt>
                        <dd class="text-end font-medium">{{ $service->name }}</dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('When') }}</dt>
                        <dd class="text-end">
                            <div class="font-medium capitalize">{{ $start->translatedFormat('l d F Y') }}</div>
                            <div class="text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                                {{ $start->format('H:i') }} – {{ $end->format('H:i') }} ({{ $visitorTimezone }})
                            </div>
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('With') }}</dt>
                        <dd class="text-end font-medium">{{ $booking->operator->name }}</dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Where') }}</dt>
                        <dd class="text-end">
                            <div class="font-medium">{{ $service->location_type->label() }}</div>
                            @if ($service->location_value)
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $service->location_value }}</div>
                            @endif
                        </dd>
                    </div>

                    @if ($service->formattedPrice())
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Price') }}</dt>
                            <dd class="text-end font-medium">{{ $service->formattedPrice() }}</dd>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Booked by') }}</dt>
                        <dd class="text-end">
                            <div class="font-medium">{{ $booking->customer_name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $booking->customer_email }}</div>
                        </dd>
                    </div>
                </dl>

                @if ($booking->rescheduled_at && $booking->original_starts_at)
                    <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Moved from :date', ['date' => $booking->original_starts_at->setTimezone($visitorTimezone)->translatedFormat('d M Y, H:i')]) }}
                    </p>
                @endif

                @if ($booking->status === BookingStatus::Cancelled)
                    @if ($booking->cancellation_reason)
                        <p class="rounded-xl bg-zinc-50 p-4 text-center text-sm text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">
                            {{ $booking->cancellation_reason }}
                        </p>
                    @endif

                    <div class="text-center">
                        <flux:button variant="primary" :href="route('portal.show', $tenant)" wire:navigate>
                            {{ __('Book again') }}
                        </flux:button>
                    </div>
                @elseif ($booking->isCancellable())
                    @unless ($tenant->is_active)
                        {{-- The public page is gone, but this link must keep working:
                             the customer still has to be able to call the appointment off. --}}
                        <flux:callout icon="exclamation-triangle" variant="warning">
                            {{ __('This portal is currently suspended, so the appointment cannot be moved. You can still cancel it.') }}
                        </flux:callout>
                    @endunless

                    @if ($rescheduling)
                        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="font-semibold">{{ __('Pick a new time') }}</h2>
                                <flux:button size="sm" variant="subtle" wire:click="stopReschedule">{{ __('Cancel') }}</flux:button>
                            </div>

                            <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_200px]">
                                @include('partials.booking-calendar')
                                @include('partials.booking-slots')
                            </div>
                        </div>
                    @elseif ($booking->isCancellableByCustomer())
                        <div class="flex flex-wrap justify-center gap-2">
                            @if ($tenant->is_active && ! $service->isGroup())
                                <flux:button variant="filled" icon="calendar-days" wire:click="startReschedule">
                                    {{ __('Reschedule') }}
                                </flux:button>
                            @endif

                            <flux:modal.trigger name="cancel-booking">
                                <flux:button variant="subtle" icon="x-mark">{{ __('Cancel booking') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($booking->customerChangeDeadline())
                            <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('You can change it yourself until :date.', [
                                    'date' => $booking->customerChangeDeadline()->setTimezone($visitorTimezone)->translatedFormat('D d M, H:i'),
                                ]) }}
                            </p>
                        @endif
                    @else
                    {{-- Past the notice the service asks for: the appointment stands,
                         and only the portal can change it now. --}}
                    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 text-center dark:border-zinc-800">
                        <flux:text>
                            {{ __('This appointment can no longer be changed online: :name asks for at least :hours hours notice.', [
                                'name' => $tenant->name,
                                'hours' => $service->cancellation_notice_hours,
                            ]) }}
                        </flux:text>

                        @if ($tenant->contact_phone || $tenant->contact_email)
                            <flux:text>
                                {{ __('Need to change it anyway? Get in touch:') }}

                                @if ($tenant->contact_phone)
                                    <a href="tel:{{ $tenant->contact_phone }}" class="underline">{{ $tenant->contact_phone }}</a>
                                @endif

                                @if ($tenant->contact_phone && $tenant->contact_email)
                                    <span class="text-zinc-400">&middot;</span>
                                @endif

                                @if ($tenant->contact_email)
                                    <a href="mailto:{{ $tenant->contact_email }}" class="underline">{{ $tenant->contact_email }}</a>
                                @endif
                            </flux:text>
                        @endif
                    </div>
                    @endif

                    @unless ($rescheduling)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <h2 class="font-semibold">{{ __('Add it to your calendar') }}</h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('So you get your own reminder, wherever you keep your appointments.') }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <flux:button size="sm" variant="filled" icon="calendar-days" :href="$this->calendarLinks['google']" target="_blank">
                                    Google Calendar
                                </flux:button>

                                <flux:button size="sm" variant="filled" icon="calendar-days" :href="$this->calendarLinks['outlook']" target="_blank">
                                    Outlook
                                </flux:button>

                                <flux:button size="sm" variant="filled" icon="calendar-days" :href="$this->calendarLinks['apple']">
                                    Apple Calendar
                                </flux:button>

                                <flux:button size="sm" variant="subtle" icon="arrow-down-tray" :href="$this->calendarLinks['download']">
                                    {{ __('Other calendars (.ics)') }}
                                </flux:button>
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div>
                                        <h2 class="font-semibold">{{ __('Save your booking') }}</h2>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('This link always shows the current details, even if the booking is moved.') }}
                                        </p>
                                    </div>

                                    <flux:input readonly size="sm" :value="$this->manageUrl" copyable />
                                </div>

                                <div
                                    class="flex flex-col items-center gap-2"
                                    x-data="{
                                        downloadQr() {
                                            const source = new XMLSerializer().serializeToString($refs.qr.querySelector('svg'));
                                            const image = new Image();

                                            image.onload = () => {
                                                const scale = 4;
                                                const canvas = document.createElement('canvas');
                                                canvas.width = image.width * scale;
                                                canvas.height = image.height * scale;

                                                const context = canvas.getContext('2d');
                                                context.fillStyle = '#ffffff';
                                                context.fillRect(0, 0, canvas.width, canvas.height);
                                                context.drawImage(image, 0, 0, canvas.width, canvas.height);

                                                const link = document.createElement('a');
                                                link.download = '{{ $this->qrFileName }}';
                                                link.href = canvas.toDataURL('image/png');
                                                link.click();
                                            };

                                            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(source);
                                        },
                                    }"
                                >
                                    <div class="rounded-lg bg-white p-2 text-zinc-900" x-ref="qr">
                                        {!! $this->qrCode !!}
                                    </div>

                                    <flux:button size="xs" variant="subtle" icon="arrow-down-tray" x-on:click="downloadQr()">
                                        {{ __('Download QR') }}
                                    </flux:button>

                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Scan to reopen') }}</span>
                                </div>
                            </div>
                        </div>
                    @endunless
                @endif
            </div>
        </div>

        @if ($booking->isCancellableByCustomer())
            {{-- Not rendered at all once the notice has passed: no dialog for an
                 action the page would refuse anyway. --}}
            <flux:modal name="cancel-booking" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Cancel this booking?') }}</flux:heading>
                    <flux:subheading>{{ __('The slot will be released and made available again.') }}</flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Keep it') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="cancel">{{ __('Cancel booking') }}</flux:button>
                </div>
            </div>
            </flux:modal>
        @endif
    </main>

    <x-portal-footer :tenant="$tenant" />
</div>
