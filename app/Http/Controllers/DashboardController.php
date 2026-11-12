<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\LicenseMessages;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The first screen after signing in: what matters to each role.
     */
    public function __invoke(Request $request, LicenseService $license): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', match (true) {
            $user->isAdmin() => ['admin' => $this->forAdministrator()],
            $user->isManager() && $user->tenant !== null => ['manager' => $this->forManager($user->tenant, $license)],
            default => ['operator' => $this->forOperator($user)],
        });
    }

    /**
     * Platform wide counters and the most recently provisioned portals.
     *
     * @return array{stats: array{portals: int, active_portals: int, managers: int, operators: int}, recent_portals: array<int, array{id: int, slug: string, name: string, manager_email: string|null, services_count: int, is_active: bool}>}
     */
    private function forAdministrator(): array
    {
        return [
            'stats' => [
                'portals' => Tenant::query()->count(),
                'active_portals' => Tenant::query()->active()->count(),
                'managers' => User::query()->role(UserRole::Manager)->count(),
                'operators' => User::query()->role(UserRole::Default)->whereNotNull('tenant_id')->count(),
            ],
            'recent_portals' => Tenant::query()
                ->withCount('services')
                ->with('manager')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Tenant $portal) => [
                    'id' => $portal->id,
                    'slug' => $portal->slug,
                    'name' => $portal->name,
                    'manager_email' => $portal->manager?->email,
                    'services_count' => (int) $portal->services_count,
                    'is_active' => $portal->is_active,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Counters shown to a portal manager, and the state of the portal's licence
     * when the License Manager governs the portal.
     *
     * @return array{stats: array{services: int, staff: int, upcoming: int, pending: int}, license: array<string, mixed>|null}
     */
    private function forManager(Tenant $tenant, LicenseService $license): array
    {
        $tenantId = $tenant->getKey();

        return [
            'license' => $this->licenseCard($tenant, $license),
            'stats' => [
                'services' => Service::query()->where('tenant_id', $tenantId)->active()->count(),
                'staff' => User::query()->where('tenant_id', $tenantId)->bookable()->count(),
                'upcoming' => Booking::query()->forTenant($tenantId)->blocking()->upcoming()->count(),
                'pending' => Booking::query()->forTenant($tenantId)->where('status', BookingStatus::Pending)->upcoming()->count(),
            ],
        ];
    }

    /**
     * The licence card of the manager: verdict, dates, plan, the totals of what is
     * due and paid, the prices of renewing, the id the License Manager knows the
     * portal by and when it was last asked. Only while the License Manager
     * governs the portal (enforcement on, portal not exempt): otherwise nothing
     * keeps it up to date and nothing is shown. Never the customer record or the
     * single payments, which the platform does not keep.
     *
     * @return array<string, mixed>|null
     */
    private function licenseCard(Tenant $tenant, LicenseService $license): ?array
    {
        if (! $license->governs($tenant)) {
            return null;
        }

        $status = $license->status($tenant);
        $timezone = $tenant->timezone;
        $date = fn (?CarbonImmutable $moment): ?string => $moment?->setTimezone($timezone)->translatedFormat('d M Y');

        $amounts = $status->dueCents === null && $status->paidCents === null && $status->outstandingCents === null ? null : [
            'due' => LicenseMessages::money($status->dueCents, $status->currency),
            'paid' => LicenseMessages::money($status->paidCents, $status->currency),
            'outstanding' => LicenseMessages::money($status->outstandingCents === null ? null : abs($status->outstandingCents), $status->currency),
            // A negative amount to collect is a credit of the customer.
            'outstanding_label' => ($status->outstandingCents ?? 0) < 0 ? __('Credit') : __('Still to pay'),
            'owes' => ($status->outstandingCents ?? 0) > 0,
        ];

        return [
            'state' => LicenseMessages::state($status),
            'color' => LicenseMessages::color($status),
            'plan' => $status->planName,
            'starts_at' => $date($status->startsAt),
            'expires_at' => $status->lifetime ? null : $date($status->expiresAt),
            'lifetime' => $status->lifetime,
            'grace_ends_at' => $status->lifetime ? null : $date($status->graceEndsAt),
            'in_grace' => $status->isInGrace(),
            'checked_at' => $status->checkedAt?->setTimezone($timezone)->translatedFormat('d M Y H:i'),
            // The last attempt failed: what is shown is the last answer received.
            'stale' => $status->stale,
            'amounts' => $amounts,
            'renewal' => array_map(fn (array $price): array => [
                'period' => LicenseMessages::period($price['months']),
                'price' => (string) LicenseMessages::money($price['price_cents'], $price['currency']),
            ], $status->renewalPrices),
            'tenant_id' => (string) $tenant->license_id,
        ];
    }

    /**
     * The next bookings, the weekly hours and the services of a staff member.
     *
     * @return array{next_bookings: array<int, array{id: int, customer: string, service: string, color: string, day: string, time: string}>, week: array<int, array{id: int, day: string, hours: string}>, services_count: int}
     */
    private function forOperator(User $user): array
    {
        $nextBookings = Booking::query()
            ->where('user_id', $user->id)
            ->blocking()
            ->upcoming()
            ->with('service.tenant')
            ->orderBy('starts_at')
            ->take(5)
            ->get()
            ->map(function (Booking $booking) use ($user) {
                $start = $booking->startsAtIn($user->timezone);

                return [
                    'id' => $booking->id,
                    'customer' => $booking->displayCustomerName(),
                    'service' => $booking->service->name,
                    'color' => $booking->service->displayColor(),
                    'day' => $start->translatedFormat('D d M'),
                    'time' => $start->format('H:i'),
                ];
            })
            ->values()
            ->all();

        $week = $user->availabilityRules()
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (AvailabilityRule $rule) => [
                'id' => $rule->id,
                'day' => Week::label($rule->weekday),
                'hours' => substr($rule->starts_at, 0, 5).' – '.substr($rule->ends_at, 0, 5),
            ])
            ->values()
            ->all();

        return [
            'next_bookings' => $nextBookings,
            'week' => $week,
            'services_count' => $user->services()->active()->count(),
        ];
    }
}
