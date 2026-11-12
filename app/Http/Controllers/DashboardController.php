<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Week;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The first screen after signing in: what matters to each role.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', match (true) {
            $user->isAdmin() => ['admin' => $this->forAdministrator()],
            $user->isManager() && $user->tenant_id !== null => ['manager' => $this->forManager($user->tenant_id)],
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
     * Counters shown to a portal manager.
     *
     * @return array{stats: array{services: int, staff: int, upcoming: int, pending: int}}
     */
    private function forManager(int $tenantId): array
    {
        return [
            'stats' => [
                'services' => Service::query()->where('tenant_id', $tenantId)->active()->count(),
                'staff' => User::query()->where('tenant_id', $tenantId)->bookable()->count(),
                'upcoming' => Booking::query()->forTenant($tenantId)->blocking()->upcoming()->count(),
                'pending' => Booking::query()->forTenant($tenantId)->where('status', BookingStatus::Pending)->upcoming()->count(),
            ],
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
