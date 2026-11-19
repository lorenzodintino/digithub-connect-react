<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\AnonymiseCustomer;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\RecordOutcome;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Agenda;
use App\Support\Csv;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    /**
     * The agenda: a table, or the month, week and day calendars, with the
     * same filters. `?booking=` opens the details of one booking.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        [$user, $tenant] = $this->reader($request);
        $agenda = Agenda::fromRequest($request, $user, $tenant);
        $view = $agenda->filters()['view'];

        return Inertia::render('app/bookings', [
            'filters' => $agenda->filters(),
            'seesWholePortal' => $agenda->seesWholePortal(),
            'pendingCount' => $agenda->pendingCount(),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status) => ['id' => $status->value, 'name' => $status->label()])->all(),
            'services' => $tenant->services()->orderBy('name')->get(['id', 'name'])->map(fn ($service) => ['id' => (string) $service->id, 'name' => $service->name])->all(),
            'operators' => $agenda->seesWholePortal()
                ? $tenant->users()->orderBy('name')->get(['id', 'name'])->map(fn (User $person) => ['id' => (string) $person->id, 'name' => $person->name])->all()
                : [],
            'table' => $view === 'table' ? $agenda->table() : null,
            'calendar' => $view !== 'table' ? $agenda->calendar() : null,
            'details' => $this->details($request, $agenda),
        ]);
    }

    /**
     * Hand the agenda over as a CSV file: what is on screen, filters and dates.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Booking::class);

        [$user, $tenant] = $this->reader($request);
        $timezone = $user->timezone;

        $rows = Agenda::fromRequest($request, $user, $tenant)->exportQuery()->lazy()->map(fn (Booking $booking) => [
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

        $headings = [
            __('Reference'), __('Date'), __('Start'), __('End'), __('Service'), __('Operator'),
            __('Customer'), __('Email'), __('Phone'), __('Status'), __('Price'), __('Notes'), __('Answers'),
        ];

        return Csv::download(Csv::fileName(__('Bookings'), $tenant->name), $headings, $rows);
    }

    /**
     * Approve a booking waiting for confirmation.
     */
    public function confirm(Request $request, Booking $booking, ConfirmBooking $confirmBooking): RedirectResponse
    {
        Gate::authorize('confirm', $booking);

        $confirmBooking->handle($booking, $request->input('meeting_url'));

        Toast::success(__('Booking confirmed.'));

        return back();
    }

    /**
     * Write down how a past appointment went.
     */
    public function outcome(Request $request, Booking $booking, RecordOutcome $recordOutcome): RedirectResponse
    {
        Gate::authorize('recordOutcome', $booking);

        $outcome = $request->validate([
            'outcome' => ['required', Rule::in(array_map(fn (BookingStatus $status) => $status->value, BookingStatus::outcomes()))],
        ])['outcome'];

        $recordOutcome->handle($booking, BookingStatus::from($outcome));

        Toast::success(__('Booking updated.'));

        return back();
    }

    /**
     * Cancel a booking, with a reason kept on the record.
     */
    public function cancel(Request $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $reason = $request->validate(
            ['reason' => ['required', 'string', 'max:200']],
            attributes: ['reason' => __('reason')],
        )['reason'];

        /** @var User $user */
        $user = $request->user();

        $cancelBooking->handle($booking, $user, $reason);

        Toast::success(__('Booking cancelled.'));

        return back();
    }

    /**
     * Erase the customer data the portal holds, across all their bookings.
     */
    public function anonymise(Request $request, Booking $booking, AnonymiseCustomer $anonymiser): RedirectResponse
    {
        Gate::authorize('anonymise', $booking);

        [, $tenant] = $this->reader($request);

        // Erasing somebody who is still expected would leave an appointment
        // nobody can be reached about.
        $upcoming = Booking::query()
            ->forTenant($tenant)
            ->where('customer_email', $booking->customer_email)
            ->blocking()
            ->upcoming()
            ->count();

        if ($upcoming > 0) {
            throw ValidationException::withMessages([
                'anonymise' => trans_choice('This customer still has one upcoming booking. Cancel it first.|This customer still has :count upcoming bookings. Cancel them first.', $upcoming),
            ]);
        }

        $erased = $anonymiser->handle($tenant, $booking->customer_email);

        Toast::success(trans_choice('Customer data erased from one booking.|Customer data erased from :count bookings.', $erased));

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function details(Request $request, Agenda $agenda): ?array
    {
        $uid = $this->filter($request, 'booking');

        if ($uid === '') {
            return null;
        }

        $booking = Booking::query()->where('uid', $uid)->firstOrFail();

        Gate::authorize('view', $booking);

        return $agenda->details($booking);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function reader(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->tenant === null, 404);

        return [$user, $user->tenant];
    }
}
