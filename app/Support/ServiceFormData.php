<?php

namespace App\Support;

use App\Enums\LocationType;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;

/**
 * The shape of the service form, the one the React page edits and
 * ServiceRequest validates: what a new service starts from, and what an
 * existing one looks like once loaded into the form.
 *
 * @phpstan-type ServiceForm array{
 *     name: string,
 *     description: string,
 *     duration_minutes: int,
 *     capacity: int,
 *     is_package: bool,
 *     use_component_prices: bool,
 *     package_items: array<int, array{service_id: int|string, pause_after_minutes: int}>,
 *     buffer_before_minutes: int,
 *     buffer_after_minutes: int,
 *     slot_interval_minutes: int,
 *     min_notice_minutes: int,
 *     max_advance_days: int,
 *     cancellation_notice_hours: int,
 *     price: string,
 *     use_brand_color: bool,
 *     color: string,
 *     location_type: string,
 *     location_value: string,
 *     requires_confirmation: bool,
 *     is_active: bool,
 *     operator_ids: array<int, int>,
 *     resource_requirements: array<int, array{name: string, resource_ids: array<int, int>}>,
 *     questions: array<int, array{id: int|null, label: string, type: string, options: string, is_required: bool}>,
 * }
 */
class ServiceFormData
{
    /**
     * A new service: two days of cancellation notice, the brand colour.
     *
     * @return ServiceForm
     */
    public static function defaults(Tenant $tenant): array
    {
        return [
            'name' => '',
            'description' => '',
            'duration_minutes' => 30,
            'capacity' => 1,
            'is_package' => false,
            'use_component_prices' => true,
            'package_items' => [],
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'slot_interval_minutes' => 15,
            'min_notice_minutes' => 120,
            'max_advance_days' => 60,
            'cancellation_notice_hours' => 48,
            'price' => '',
            'use_brand_color' => true,
            'color' => $tenant->brand_color,
            'location_type' => 'in_person',
            'location_value' => '',
            'requires_confirmation' => false,
            'is_active' => true,
            'operator_ids' => [],
            'resource_requirements' => [],
            'questions' => [],
        ];
    }

    /**
     * An existing service as the form shows it.
     *
     * @return ServiceForm
     */
    public static function of(Service $service): array
    {
        $service->loadMissing(['tenant', 'questions', 'packageItems', 'resourceRequirements.resources']);

        return [
            'name' => $service->name,
            'description' => $service->description ?? '',
            'duration_minutes' => $service->duration_minutes,
            'capacity' => $service->capacity,
            'is_package' => $service->is_package,
            'use_component_prices' => $service->use_component_prices,
            'package_items' => $service->packageItems->map(fn ($item) => [
                'service_id' => $item->service_id,
                'pause_after_minutes' => $item->pause_after_minutes,
            ])->values()->all(),
            'buffer_before_minutes' => $service->buffer_before_minutes,
            'buffer_after_minutes' => $service->buffer_after_minutes,
            'slot_interval_minutes' => $service->slot_interval_minutes,
            'min_notice_minutes' => $service->min_notice_minutes,
            'max_advance_days' => $service->max_advance_days,
            'cancellation_notice_hours' => $service->cancellation_notice_hours,
            'price' => $service->price_cents === null ? '' : (string) ($service->price_cents / 100),
            'use_brand_color' => $service->color === null,
            'color' => $service->color ?? $service->tenant->brand_color,
            'location_type' => $service->location_type->value,
            'location_value' => $service->location_type === LocationType::Video ? '' : ($service->location_value ?? ''),
            'requires_confirmation' => $service->needsManualConfirmation(),
            'is_active' => $service->is_active,
            'operator_ids' => $service->operators()->pluck('users.id')->all(),
            'resource_requirements' => $service->resourceRequirements->map(fn (ServiceResourceRequirement $requirement) => [
                'name' => $requirement->name,
                'resource_ids' => $requirement->resources->pluck('id')->all(),
            ])->values()->all(),
            'questions' => $service->questions->map(fn (ServiceQuestion $question) => [
                'id' => $question->id,
                'label' => $question->label,
                'type' => $question->type->value,
                'options' => implode("\n", $question->choices()),
                'is_required' => $question->is_required,
            ])->values()->all(),
        ];
    }
}
