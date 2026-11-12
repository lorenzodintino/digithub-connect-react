<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\UpdateCustomerNotesRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Csv;
use App\Support\Search;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Everybody who ever booked with the portal. `?customer=` opens the card
     * of one of them, with the note and what they booked.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        /** @var User $user */
        $user = $request->user();
        $search = $this->filter($request, 'q');

        $customers = $this->directory($this->tenant($user), $search)
            ->withCount(['bookings as upcoming_count' => fn ($query) => $query->blocking()->upcoming()])
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'bookings_count' => (int) $customer->bookings_count,
                'upcoming_count' => (int) $customer->getAttribute('upcoming_count'),
                'no_show_count' => (int) $customer->getAttribute('no_show_count'),
                'last_booking' => $this->lastBookingAt($customer, $user->timezone)?->translatedFormat('d M Y'),
            ]);

        $customerId = $this->filter($request, 'customer');

        return Inertia::render('app/customers', [
            'customers' => $customers,
            'filters' => ['q' => $search],
            'card' => ctype_digit($customerId) ? $this->card((int) $customerId, $user) : null,
        ]);
    }

    /**
     * Hand the directory over as a CSV file, search included.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Customer::class);

        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($user);

        $rows = $this->directory($tenant, $this->filter($request, 'q'))
            ->lazy()
            ->map(fn (Customer $customer) => [
                $customer->name,
                $customer->email,
                $customer->phone,
                $customer->bookings_count,
                $customer->getAttribute('no_show_count'),
                $this->lastBookingAt($customer, $user->timezone)?->format('d/m/Y') ?? '',
                $customer->notes,
            ]);

        $headings = [__('Name'), __('Email'), __('Phone'), __('Bookings'), __('No shows'), __('Last booking'), __('Internal note')];

        return Csv::download(Csv::fileName(__('Customers'), $tenant->name), $headings, $rows);
    }

    /**
     * Save the note the portal keeps about this customer.
     */
    public function updateNotes(UpdateCustomerNotesRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update(['notes' => $request->validated('notes')]);

        Toast::success(__('Note saved.'));

        return back();
    }

    /**
     * The customers of the portal with their counts, in name order.
     *
     * @return Builder<Customer>
     */
    private function directory(Tenant $tenant, string $search): Builder
    {
        return Customer::query()
            ->forTenant($tenant)
            ->withCount([
                'bookings',
                'bookings as no_show_count' => fn ($query) => $query->where('status', BookingStatus::NoShow),
            ])
            ->withMax('bookings as last_booking_at', 'starts_at')
            ->when($search !== '', fn ($query) => Search::contains($query, ['name', 'email', 'phone'], $search))
            ->orderBy('name');
    }

    /**
     * The card of a customer: contacts, note and what they booked, most
     * recent first.
     *
     * @return array{id: int, name: string, email: string, phone: string|null, notes: string, history: array<int, array{id: int, date: string, time: string, service: string, color: string, operator: string, status: string, status_color: string}>}
     */
    private function card(int $customerId, User $user): array
    {
        $customer = Customer::query()->findOrFail($customerId);

        Gate::authorize('view', $customer);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'notes' => $customer->notes ?? '',
            'history' => $customer->bookings()
                ->with(['service.tenant', 'operator'])
                ->orderByDesc('starts_at')
                ->limit(50)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'date' => $booking->startsAtIn($user->timezone)->translatedFormat('d M Y'),
                    'time' => $booking->startsAtIn($user->timezone)->format('H:i'),
                    'service' => $booking->service->name,
                    'color' => $booking->service->displayColor(),
                    'operator' => $booking->operator->name,
                    'status' => $booking->status->label(),
                    'status_color' => $booking->status->color(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * When the customer last booked, in the reader's timezone.
     */
    private function lastBookingAt(Customer $customer, string $timezone): ?CarbonImmutable
    {
        $lastBookingAt = $customer->getAttribute('last_booking_at');

        return $lastBookingAt ? CarbonImmutable::parse($lastBookingAt)->setTimezone($timezone) : null;
    }

    private function tenant(User $user): Tenant
    {
        abort_if($user->tenant === null, 404);

        return $user->tenant;
    }
}
