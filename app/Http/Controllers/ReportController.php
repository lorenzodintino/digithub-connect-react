<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * The periods the report can be read over.
     *
     * @var array<int, string>
     */
    public const PERIODS = ['this_month', 'last_month', 'last_90_days', 'this_year'];

    /**
     * How the portal is doing, or for an operator how their own work is going.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        abort_if($tenant === null, 404);

        $period = $this->filter($request, 'period', 'this_month');
        $period = in_array($period, self::PERIODS, true) ? $period : 'this_month';

        [$from, $to] = $this->window($tenant, $period);

        $bookings = Booking::query()
            ->forTenant($tenant)
            ->with(['service', 'operator'])
            ->unless($user->isManager(), fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->get();

        return Inertia::render('app/reports', [
            'period' => $period,
            'seesWholePortal' => $user->isManager(),
            'window' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y'),
            ],
            'totals' => $this->totals($bookings),
            'byService' => $this->breakdown($bookings, fn (Booking $booking) => $booking->service->name),
            'byOperator' => $this->breakdown($bookings, fn (Booking $booking) => $booking->operator->name),
        ]);
    }

    /**
     * The window the report covers, in the portal timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(Tenant $tenant, string $period): array
    {
        $now = CarbonImmutable::now($tenant->timezone);

        // startOfMonth before subMonth: on the 31st `subMonth()` alone lands on
        // the 3rd of this month, and last month's report would draw this one.
        $lastMonth = $now->startOfMonth()->subMonth();

        return match ($period) {
            'last_month' => [$lastMonth, $lastMonth->endOfMonth()],
            'last_90_days' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * The headline numbers of the period.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array{bookings: int, confirmed: int, pending: int, cancelled: int, completed: int, no_shows: int, no_show_rate: string, value: int, value_label: string}
     */
    private function totals(Collection $bookings): array
    {
        $completed = $bookings->where('status', BookingStatus::Completed);
        $noShows = $bookings->where('status', BookingStatus::NoShow);
        $closed = $completed->count() + $noShows->count();
        $value = (int) $completed->sum('price_cents');

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
            'value' => $value,
            'value_label' => $this->money($value),
        ];
    }

    /**
     * Group the bookings of the period and count what matters for each group.
     *
     * @param  Collection<int, Booking>  $bookings
     * @param  callable(Booking): string  $key
     * @return array<int, array{label: string, bookings: int, completed: int, no_shows: int, cancelled: int, value: string}>
     */
    private function breakdown(Collection $bookings, callable $key): array
    {
        return $bookings
            ->groupBy($key)
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'bookings' => $group->count(),
                'completed' => $group->where('status', BookingStatus::Completed)->count(),
                'no_shows' => $group->where('status', BookingStatus::NoShow)->count(),
                'cancelled' => $group->where('status', BookingStatus::Cancelled)->count(),
                'value' => $this->money((int) $group->where('status', BookingStatus::Completed)->sum('price_cents')),
            ])
            ->sortByDesc('bookings')
            ->values()
            ->all();
    }

    /**
     * A money amount as the portal reads it.
     */
    private function money(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }
}
