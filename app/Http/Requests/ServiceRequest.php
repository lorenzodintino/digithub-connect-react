<?php

namespace App\Http\Requests;

use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\ServiceResourceRequirement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * A new service for the manager's portal, or one of its own
     * (`{ownService}` only resolves inside the portal).
     */
    public function authorize(): bool
    {
        $service = $this->route('ownService');

        return $service instanceof Service
            ? $this->user()?->can('update', $service) ?? false
            : $this->user()?->can('create', Service::class) ?? false;
    }

    /**
     * The service form of App\Support\ServiceFormData.
     *
     * A package has no requirements of its own (its phases bring theirs) and
     * is made of two to ten individual services of the same portal.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $isPackage = $this->boolean('is_package');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_package' => ['required', 'boolean'],
            'use_component_prices' => ['required', 'boolean'],
            'package_items' => $isPackage ? ['required', 'array', 'min:2', 'max:10'] : ['array'],
            'package_items.*' => ['array:service_id,pause_after_minutes'],
            'package_items.*.service_id' => ['required', 'integer', 'distinct', Rule::exists(Service::class, 'id')->where('tenant_id', $user->tenant_id)->where('is_package', 0)->where('capacity', 1)],
            'package_items.*.pause_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:20160'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'use_brand_color' => ['required', 'boolean'],
            'color' => [Rule::requiredIf(! $this->boolean('use_brand_color')), 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'location_value' => [Rule::excludeIf($this->input('location_type') === LocationType::Video->value), 'nullable', 'string', 'max:255'],
            'requires_confirmation' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'operator_ids' => ['array'],
            'operator_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')->where('tenant_id', $user->tenant_id)],
            'resource_requirements' => $isPackage ? ['prohibited'] : ['array', 'max:'.ServiceResourceRequirement::LIMIT],
            'resource_requirements.*' => ['array:name,resource_ids'],
            'resource_requirements.*.name' => ['required', 'string', 'max:80'],
            'resource_requirements.*.resource_ids' => ['required', 'array', 'min:1'],
            'resource_requirements.*.resource_ids.*' => ['integer', 'distinct', Rule::exists(Resource::class, 'id')->where('tenant_id', $user->tenant_id)],
            'questions' => ['array', 'max:'.ServiceQuestion::LIMIT],
            // Kept so an edited question stays the same row and its answers stay linked.
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.label' => ['required', 'string', 'max:160'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['nullable', 'string', 'max:600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'duration_minutes' => __('duration'),
            'buffer_before_minutes' => __('buffer before'),
            'buffer_after_minutes' => __('buffer after'),
            'slot_interval_minutes' => __('slot interval'),
            'min_notice_minutes' => __('minimum notice'),
            'max_advance_days' => __('booking window'),
            'cancellation_notice_hours' => __('cancellation notice'),
            'location_value' => __('location details'),
            'use_brand_color' => __('brand color'),
            'operator_ids' => __('operators'),
            'package_items' => __('Package phases'),
            'package_items.*.service_id' => __('Service'),
            'package_items.*.pause_after_minutes' => __('Pause after this service (minutes)'),
            'questions' => __('questions'),
            'resource_requirements.*.name' => __('Requirement name'),
            'resource_requirements.*.resource_ids' => __('Resource alternatives'),
            'resource_requirements.*.resource_ids.*' => __('Resource'),
        ];
    }
}
