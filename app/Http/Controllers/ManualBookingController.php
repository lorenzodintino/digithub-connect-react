<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\CreateBooking;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\ManualBookingRequest;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupSlotFinder;
use App\Services\SlotFinder;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManualBookingController extends Controller
{
    public function create(Request $request): Response
    {
        [$actor, $tenant] = $this->actorAndTenant($request);

        $services = $tenant->services()->where('is_active', true)->orderBy('name')->get()
            ->map(function (Service $service) use ($actor): ?array {
                $service = $service->bookingConfiguration();
                $operators = $this->eligibleOperators($service, $actor);

                if (! $service->is_active || $operators->isEmpty()) {
                    return null;
                }

                return [
                    'slug' => $service->slug,
                    'name' => $service->name,
                    'is_group' => $service->isGroup(),
                    'requires_confirmation' => $service->needsManualConfirmation(),
                    'operators' => $operators->map(fn (User $operator) => ['id' => (string) $operator->id, 'name' => $operator->name])->values()->all(),
                    'questions' => $service->bookingQuestions()->map(fn (ServiceQuestion $question) => [
                        'id' => $question->id,
                        'label' => $question->label,
                        'type' => $question->type->value,
                        'required' => $question->is_required,
                        'choices' => $question->choices(),
                    ])->values()->all(),
                ];
            })->filter()->values()->all();

        return Inertia::render('app/new-booking', [
            'services' => $services,
            'canChooseOperator' => $actor->isManager(),
            'today' => CarbonImmutable::now($tenant->timezone)->toDateString(),
            'portalTimezone' => $tenant->timezone,
        ]);
    }

    public function slots(Request $request, Service $ownService): JsonResponse
    {
        [$actor] = $this->actorAndTenant($request);
        $service = $this->bookable($ownService);
        $operator = $this->operator($request, $service, $actor);
        $day = CarbonImmutable::parse($request->validate(['day' => ['required', 'date_format:Y-m-d']])['day'], $service->tenant->timezone);
        $group = $service->isGroup() ? new GroupSlotFinder($service, ignoreMinimumNotice: true, operator: $operator) : null;
        $slots = $group === null
            ? (new SlotFinder($service, $operator, ignoreMinimumNotice: true))->slotsForDay($day)
            : $group->slotsForDay($day);
        $seats = $group?->seatsLeftBetween($day, $day) ?? [];

        return response()->json([
            'slots' => array_map(fn (CarbonImmutable $slot) => [
                'value' => $slot->toIso8601String(),
                'time' => $slot->setTimezone($service->tenant->timezone)->format('H:i'),
                'seats_left' => $group === null ? null : ($seats[$slot->getTimestamp()] ?? $service->capacity),
            ], $slots),
        ]);
    }

    public function store(ManualBookingRequest $request, Service $ownService, CreateBooking $createBooking): RedirectResponse
    {
        [$actor] = $this->actorAndTenant($request);
        $service = $this->bookable($ownService);
        $operator = $this->operator($request, $service, $actor);
        $data = $request->validated();

        try {
            $booking = $createBooking->handle(
                $service,
                $operator,
                CarbonImmutable::parse($data['slot'], 'UTC'),
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                $request->answers(),
                fromAgenda: true,
            );
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['slot' => $exception->getMessage()]);
        }

        Toast::success(__('Booking created'));

        return to_route('app.bookings', ['booking' => $booking->uid]);
    }

    /** @return array{User, Tenant} */
    private function actorAndTenant(Request $request): array
    {
        Gate::authorize('create', Booking::class);

        /** @var User $actor */
        $actor = $request->user();
        $tenant = $actor->tenant;
        abort_if($tenant === null || ! $tenant->isOperational(), 404);

        return [$actor, $tenant];
    }

    private function bookable(Service $service): Service
    {
        $service = $service->bookingConfiguration();
        abort_unless($service->is_active, 404);

        return $service;
    }

    /** @return Collection<int, User> */
    private function eligibleOperators(Service $service, User $actor): Collection
    {
        return $service->operators()->bookable()->where('users.tenant_id', $actor->tenant_id)
            ->unless($actor->isManager(), fn ($operators) => $operators->whereKey($actor->id))
            ->orderBy('name')->get()
            ->filter(fn (User $operator) => ! $service->is_package || $service->operatorCanDeliver($operator));
    }

    private function operator(Request $request, Service $service, User $actor): User
    {
        $data = $request->validate([
            'operator' => $actor->isManager() ? ['required', 'integer'] : ['prohibited'],
        ], attributes: ['operator' => __('Operator')]);
        $operatorId = $actor->isManager() ? (int) $data['operator'] : $actor->id;
        $operator = $this->eligibleOperators($service, $actor)->find($operatorId);

        if ($operator === null) {
            throw ValidationException::withMessages(['operator' => __('Choose an operator who can deliver this service.')]);
        }

        return $operator;
    }
}
