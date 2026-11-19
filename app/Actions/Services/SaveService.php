<?php

namespace App\Actions\Services;

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a service of a portal from the form validated by
 * ServiceRequest, with its operators, questions, resource requirements and,
 * for a package, its phases.
 *
 * Everything runs under the portal lock: the plan limit, the checks on
 * packages and the requirements decide what the agenda can promise.
 */
class SaveService
{
    /**
     * @param  array<string, mixed>  $data  validated by ServiceRequest
     */
    public function handle(Tenant $tenant, ?Service $service, array $data): Service
    {
        return $service === null ? $this->create($tenant, $data) : $this->update($service, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(Tenant $tenant, array $data): Service
    {
        return AgendaLock::forTenant($tenant, function () use ($tenant, $data): Service {
            $usage = $tenant->planUsage();

            if (! $usage->canAddService()) {
                $message = trans_choice('Your plan includes up to one service: delete it or ask for a bigger plan.|Your plan includes up to :count services: delete one or ask for a bigger plan.', (int) $usage->serviceLimit());

                // `plan_limit` names the reason, so the form can offer the request for a bigger plan next to the error.
                throw ValidationException::withMessages(['name' => $message, 'plan_limit' => 'services']);
            }

            $this->checkPlanFeatures($tenant, $data, null);

            $data = $this->checkPackage($data, null);

            $service = Service::create([
                'tenant_id' => $tenant->id,
                'slug' => Service::uniqueSlug($data['name'], $tenant->id),
                'position' => (int) Service::query()->forTenant($tenant)->max('position') + 1,
                ...$this->attributes($data),
            ]);

            $this->syncRelations($service, $data);

            Activity::log('service.created', $service);

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function update(Service $service, array $data): Service
    {
        return AgendaLock::forTenant($service->tenant_id, function () use ($service, $data): Service {
            $service->refresh();

            $isPackage = (bool) $data['is_package'];

            if ($isPackage !== $service->is_package && $service->upcomingBookingsCount() > 0) {
                throw ValidationException::withMessages([
                    'is_package' => __('Cancel upcoming bookings before changing the service type.'),
                ]);
            }

            $becomesShared = $isPackage || (int) $data['capacity'] > 1;
            $isComponent = $service->usedInPackages()->exists()
                || $service->phases()->whereHas('booking', fn ($query) => $query->whereIn('status', BookingStatus::blocking())->where('starts_at', '>', now()))->exists();

            if ($becomesShared && $isComponent) {
                throw ValidationException::withMessages([
                    'capacity' => __('A service used in a package must remain an individual service.'),
                ]);
            }

            $this->checkPlanFeatures($service->tenant, $data, $service);

            $data = $this->checkPackage($data, $service);

            $attributes = $this->attributes($data);

            if ($service->location_type === LocationType::Video && $data['location_type'] === LocationType::Video->value) {
                unset($attributes['location_value']);
            }

            $service->update($attributes);

            $this->syncRelations($service, $data);

            Activity::log('service.updated', $service);

            return $service;
        });
    }

    /**
     * What the plan includes beyond the number of services: group services and packages.
     *
     * Only what is new is refused. A service that already is a group one, or a package, keeps
     * being edited whatever the plan says: it is already in the agenda and in the bookings.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkPlanFeatures(Tenant $tenant, array $data, ?Service $existing): void
    {
        $isPackage = (bool) $data['is_package'];

        if (! $isPackage && (int) $data['capacity'] > 1 && ! ($existing?->isGroup() ?? false) && ! $tenant->planAllows('group_services')) {
            throw ValidationException::withMessages([
                'capacity' => __('Group services are not included in your plan: ask for a bigger plan.'),
                'plan_limit' => 'groups',
            ]);
        }

        if (! $isPackage || ($existing?->is_package ?? false)) {
            return;
        }

        if (! $tenant->planAllows('packages')) {
            throw ValidationException::withMessages([
                'is_package' => __('Service packages are not included in your plan: ask for a bigger plan.'),
                'plan_limit' => 'packages',
            ]);
        }

        $usage = $tenant->planUsage();

        if (! $usage->canAddPackage()) {
            throw ValidationException::withMessages([
                'is_package' => trans_choice('Your plan includes up to one package: delete it or ask for a bigger plan.|Your plan includes up to :count packages: delete one or ask for a bigger plan.', (int) $usage->packageLimit()),
                'plan_limit' => 'packages',
            ]);
        }
    }

    /**
     * A package is made of different individual services in the same currency,
     * every operator of the package delivers each of them, and the whole lasts
     * at most a day. Its duration is the sum of the phases and the pauses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function checkPackage(array $data, ?Service $service): array
    {
        if (! $data['is_package']) {
            return $data;
        }

        /** @var array<int, array{service_id: int, pause_after_minutes: int}> $items */
        $items = array_values($data['package_items']);
        $components = Service::query()->with('operators')->whereIn('id', array_column($items, 'service_id'))->get()->keyBy('id');
        $currency = $service->currency ?? 'EUR';
        $duration = 0;

        foreach ($items as $index => $item) {
            $component = $components->get($item['service_id']);

            if ($component === null || $component->id === $service?->id || $component->currency !== $currency) {
                throw ValidationException::withMessages([
                    'package_items.'.$index.'.service_id' => __('Choose a different individual service with the same currency.'),
                ]);
            }

            foreach ($data['operator_ids'] ?? [] as $operatorId) {
                if (! $component->operators->contains('id', $operatorId)) {
                    throw ValidationException::withMessages([
                        'operator_ids' => __('Each package operator must be assigned to every component service.'),
                    ]);
                }
            }

            $duration += $component->duration_minutes;

            if ($index < count($items) - 1) {
                $duration += $item['pause_after_minutes'];
            }
        }

        if ($duration > 1440) {
            throw ValidationException::withMessages([
                'package_items' => __('The package must last no more than 24 hours, including pauses.'),
            ]);
        }

        return [...$data, 'duration_minutes' => $duration, 'capacity' => 1];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $video = $data['location_type'] === LocationType::Video->value;

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'capacity' => $data['capacity'],
            'is_package' => $data['is_package'],
            'use_component_prices' => $data['use_component_prices'],
            'buffer_before_minutes' => $data['buffer_before_minutes'],
            'buffer_after_minutes' => $data['buffer_after_minutes'],
            'slot_interval_minutes' => $data['slot_interval_minutes'],
            'min_notice_minutes' => $data['min_notice_minutes'],
            'max_advance_days' => $data['max_advance_days'],
            'cancellation_notice_hours' => $data['cancellation_notice_hours'],
            'price_cents' => ($data['price'] ?? null) === null ? null : (int) round((float) $data['price'] * 100),
            'color' => $data['use_brand_color'] ? null : $data['color'],
            'location_type' => $data['location_type'],
            'location_value' => $video ? null : ($data['location_value'] ?? null),
            'requires_confirmation' => $video || (bool) $data['requires_confirmation'],
            'is_active' => $data['is_active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Service $service, array $data): void
    {
        $service->operators()->sync(
            User::query()->where('tenant_id', $service->tenant_id)->whereKey($data['operator_ids'] ?? [])->pluck('id')->all()
        );

        $this->syncQuestions($service, $data['questions'] ?? []);

        // A plan without resources keeps the requirements a service already has (they still hold the agenda)
        // and refuses to change them: whatever the form sent is ignored.
        if ($service->tenant->planAllows('resources')) {
            $this->syncResourceRequirements($service, $data['resource_requirements'] ?? []);
        }

        $this->syncPackageItems($service, (bool) $data['is_package'], array_values($data['package_items'] ?? []));
    }

    /**
     * Write the questions of the service, keeping the ones already answered.
     *
     * A question that disappears from the form is deleted, and the answers
     * given to it survive on their bookings with the wording of the day.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function syncQuestions(Service $service, array $questions): void
    {
        $kept = [];

        foreach (array_values($questions) as $position => $question) {
            $type = QuestionType::from($question['type']);

            $attributes = [
                'label' => $question['label'],
                'type' => $type,
                'options' => $type->needsOptions() ? $this->parseOptions($question['options'] ?? '') : null,
                'is_required' => (bool) ($question['is_required'] ?? false),
                'position' => $position,
            ];

            $id = $question['id'] ?? null;

            if ($id === null) {
                $record = $service->questions()->create($attributes);
            } else {
                $record = $service->questions()->where('id', $id)->firstOrFail();
                $record->update($attributes);
            }

            $kept[] = $record->id;
        }

        $service->questions()->whereKeyNot($kept)->delete();
    }

    /**
     * Turn the options typed one per line into a clean list.
     *
     * @return array<int, string>
     */
    private function parseOptions(string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options) ?: [])
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, resource_ids: array<int, int>}>  $requirements
     */
    private function syncResourceRequirements(Service $service, array $requirements): void
    {
        $service->resourceRequirements()->delete();

        foreach (array_values($requirements) as $position => $requirement) {
            $record = $service->resourceRequirements()->create([
                'tenant_id' => $service->tenant_id,
                'name' => $requirement['name'],
                'position' => $position,
            ]);

            $record->resources()->sync($requirement['resource_ids']);
        }
    }

    /**
     * The phases of a package, in order. The pause after the last one is
     * meaningless and stored as zero; duration and price are recalculated.
     *
     * @param  array<int, array{service_id: int, pause_after_minutes: int}>  $items
     */
    private function syncPackageItems(Service $service, bool $isPackage, array $items): void
    {
        $service->packageItems()->delete();

        if (! $isPackage) {
            return;
        }

        foreach ($items as $position => $item) {
            $service->packageItems()->create([
                'tenant_id' => $service->tenant_id,
                'service_id' => $item['service_id'],
                'position' => $position,
                'pause_after_minutes' => $position < count($items) - 1 ? $item['pause_after_minutes'] : 0,
            ]);
        }

        $service->unsetRelation('packageItems');
        $configured = $service->bookingConfiguration();
        $service->update(['duration_minutes' => $configured->duration_minutes, 'price_cents' => $configured->price_cents]);
    }
}
