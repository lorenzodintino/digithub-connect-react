<?php

namespace App\Livewire\Forms;

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int $duration_minutes = 30;

    public int $capacity = 1;

    public bool $is_package = false;

    public bool $use_component_prices = true;

    /** @var array<int, array{service_id: int|string, pause_after_minutes: int}> */
    public array $package_items = [];

    public int $buffer_before_minutes = 0;

    public int $buffer_after_minutes = 0;

    public int $slot_interval_minutes = 15;

    public int $min_notice_minutes = 120;

    public int $max_advance_days = 60;

    public int $cancellation_notice_hours = 48;

    public string $price = '';

    public bool $use_brand_color = true;

    public string $color = '#4f46e5';

    public string $location_type = 'in_person';

    public string $location_value = '';

    public bool $requires_confirmation = false;

    public bool $is_active = true;

    /**
     * The operator ids assigned to this service.
     *
     * @var array<int, int>
     */
    public array $operator_ids = [];

    /** @var array<int, array{name: string, resource_ids: array<int, int>}> */
    public array $resource_requirements = [];

    /**
     * The extra questions asked at booking time, in the order they are shown.
     *
     * Each row: id (null when new), label, type, options as one per line,
     * is_required.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $questions = [];

    /**
     * Validation rules for a portal service.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_package' => ['boolean'],
            'use_component_prices' => ['boolean'],
            'package_items' => $this->is_package ? ['required', 'array', 'min:2', 'max:10'] : ['array'],
            'package_items.*' => ['array:service_id,pause_after_minutes'],
            'package_items.*.service_id' => ['required', 'integer', 'distinct', Rule::exists(Service::class, 'id')->where('tenant_id', auth()->user()->tenant_id)->where('is_package', 0)->where('capacity', 1)],
            'package_items.*.pause_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:20160'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'use_brand_color' => ['boolean'],
            'color' => [Rule::requiredIf(fn (): bool => ! $this->use_brand_color), 'regex:/^#[0-9a-fA-F]{6}$/'],
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'location_value' => ['nullable', 'string', 'max:255'],
            'requires_confirmation' => ['boolean'],
            'is_active' => ['boolean'],
            'operator_ids' => ['array'],
            'operator_ids.*' => ['integer', 'exists:users,id'],
            'resource_requirements' => $this->is_package ? ['prohibited'] : ['array', 'max:'.ServiceResourceRequirement::LIMIT],
            'resource_requirements.*' => ['array:name,resource_ids'],
            'resource_requirements.*.name' => ['required', 'string', 'max:80'],
            'resource_requirements.*.resource_ids' => ['required', 'array', 'min:1'],
            'resource_requirements.*.resource_ids.*' => ['integer', 'distinct', Rule::exists(Resource::class, 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'questions' => ['array', 'max:'.ServiceQuestion::LIMIT],
            'questions.*.label' => ['required', 'string', 'max:160'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['nullable', 'string', 'max:600'],
        ];
    }

    /**
     * Attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
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

    /**
     * Fill the form from an existing service.
     */
    public function setService(Service $service): void
    {
        $this->service = $service;

        $this->fill($service->only([
            'name', 'duration_minutes', 'capacity', 'buffer_before_minutes', 'buffer_after_minutes',
            'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days',
            'cancellation_notice_hours', 'requires_confirmation', 'is_active',
            'is_package', 'use_component_prices',
        ]));

        $this->use_brand_color = $service->color === null;
        $this->color = $service->color ?? $service->tenant->brand_color;

        $this->description = $service->description ?? '';
        $this->location_type = $service->location_type->value;
        $this->location_value = $service->location_value ?? '';
        $this->price = $service->price_cents === null ? '' : (string) ($service->price_cents / 100);
        $this->operator_ids = $service->operators()->pluck('users.id')->all();

        $this->package_items = $service->packageItems()->get()->map(fn ($item) => [
            'service_id' => $item->service_id, 'pause_after_minutes' => $item->pause_after_minutes,
        ])->all();

        $this->resource_requirements = $service->resourceRequirements()->with('resources')->get()
            ->map(fn (ServiceResourceRequirement $requirement): array => [
                'name' => $requirement->name,
                'resource_ids' => $requirement->resources->pluck('id')->all(),
            ])->all();

        $this->questions = $service->questions->map(fn (ServiceQuestion $question) => [
            'id' => $question->id,
            'label' => $question->label,
            'type' => $question->type->value,
            'options' => implode("\n", $question->choices()),
            'is_required' => $question->is_required,
        ])->all();
    }

    /**
     * Add an empty question to the form.
     */
    public function addQuestion(): void
    {
        if (count($this->questions) >= ServiceQuestion::LIMIT) {
            return;
        }

        $this->questions[] = [
            'id' => null,
            'label' => '',
            'type' => QuestionType::ShortText->value,
            'options' => '',
            'is_required' => false,
        ];
    }

    /**
     * Drop a question from the form.
     */
    public function removeQuestion(int $index): void
    {
        unset($this->questions[$index]);

        $this->questions = array_values($this->questions);
    }

    /**
     * Create a new service inside the given portal.
     */
    public function store(Tenant $tenant): Service
    {
        $this->validate();

        $service = AgendaLock::forTenant($tenant, function () use ($tenant): Service {
            $usage = $tenant->planUsage();
            if (! $usage->canAddService()) {
                throw ValidationException::withMessages(['form.name' => trans_choice('Your plan includes up to one service: delete it or ask for a bigger plan.|Your plan includes up to :count services: delete one or ask for a bigger plan.', (int) $usage->serviceLimit())]);
            }
            $this->validatePackage();
            $service = Service::create([
                'tenant_id' => $tenant->id,
                'slug' => Service::uniqueSlug($this->name, $tenant->id),
                'position' => (int) Service::query()->forTenant($tenant)->max('position') + 1,
                ...$this->serviceAttributes(),
            ]);

            $service->operators()->sync($this->operator_ids);

            $this->syncQuestions($service);
            $this->syncResourceRequirements($service);
            $this->syncPackageItems($service);

            Activity::log('service.created', $service);

            return $service;
        });

        $this->reset();

        return $service;
    }

    /**
     * Persist changes to the loaded service.
     */
    public function update(): Service
    {
        $this->validate();

        AgendaLock::forTenant($this->service->tenant_id, function (): void {
            $this->service->refresh();
            if ($this->is_package !== $this->service->is_package && $this->service->upcomingBookingsCount() > 0) {
                throw ValidationException::withMessages(['form.is_package' => __('Cancel upcoming bookings before changing the service type.')]);
            }
            if (($this->is_package || $this->capacity > 1) && ($this->service->usedInPackages()->exists()
                || $this->service->phases()->whereHas('booking', fn ($query) => $query->whereIn('status', BookingStatus::blocking())->where('starts_at', '>', now()))->exists())) {
                throw ValidationException::withMessages(['form.capacity' => __('A service used in a package must remain an individual service.')]);
            }
            $this->validatePackage();
            $this->service->update($this->serviceAttributes());
            $this->service->operators()->sync($this->operator_ids);

            $this->syncQuestions($this->service);
            $this->syncResourceRequirements($this->service);
            $this->syncPackageItems($this->service);

            Activity::log('service.updated', $this->service);
        });

        return $this->service;
    }

    public function addPackageItem(): void
    {
        if (count($this->package_items) < 10) {
            $this->package_items[] = ['service_id' => '', 'pause_after_minutes' => 0];
        }
    }

    public function removePackageItem(int $index): void
    {
        unset($this->package_items[$index]);
        $this->package_items = array_values($this->package_items);
    }

    public function movePackageItem(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (in_array($direction, [-1, 1], true) && isset($this->package_items[$index], $this->package_items[$target])) {
            [$this->package_items[$index], $this->package_items[$target]] = [$this->package_items[$target], $this->package_items[$index]];
        }
    }

    private function validatePackage(): void
    {
        if (! $this->is_package) {
            return;
        }
        $this->validate();
        $services = Service::query()->with('operators')->whereIn('id', array_column($this->package_items, 'service_id'))->get()->keyBy('id');
        $duration = 0;
        $currency = $this->service->currency ?? 'EUR';
        foreach ($this->package_items as $index => $item) {
            $component = $services->get($item['service_id']);
            if ($component === null || $component->id === $this->service?->id || $component->currency !== $currency) {
                throw ValidationException::withMessages(['form.package_items.'.$index.'.service_id' => __('Choose a different individual service with the same currency.')]);
            }
            foreach ($this->operator_ids as $operatorId) {
                if (! $component->operators->contains('id', $operatorId)) {
                    throw ValidationException::withMessages(['form.operator_ids' => __('Each package operator must be assigned to every component service.')]);
                }
            }
            $duration += $component->duration_minutes;
            if ($index < count($this->package_items) - 1) {
                $duration += $item['pause_after_minutes'];
            }
        }
        if ($duration > 1440) {
            throw ValidationException::withMessages(['form.package_items' => __('The package must last no more than 24 hours, including pauses.')]);
        }
        $this->duration_minutes = $duration;
        $this->capacity = 1;
    }

    private function syncPackageItems(Service $service): void
    {
        $service->packageItems()->delete();
        if ($this->is_package) {
            foreach ($this->package_items as $position => $item) {
                $service->packageItems()->create([
                    'tenant_id' => $service->tenant_id, 'service_id' => $item['service_id'], 'position' => $position,
                    'pause_after_minutes' => $position < count($this->package_items) - 1 ? $item['pause_after_minutes'] : 0,
                ]);
            }
            $service->unsetRelation('packageItems');
            $configured = $service->bookingConfiguration();
            $service->update(['duration_minutes' => $configured->duration_minutes, 'price_cents' => $configured->price_cents]);
        }
    }

    public function addResourceRequirement(): void
    {
        if (count($this->resource_requirements) < ServiceResourceRequirement::LIMIT) {
            $this->resource_requirements[] = ['name' => '', 'resource_ids' => []];
        }
    }

    public function removeResourceRequirement(int $index): void
    {
        unset($this->resource_requirements[$index]);
        $this->resource_requirements = array_values($this->resource_requirements);
    }

    private function syncResourceRequirements(Service $service): void
    {
        $service->resourceRequirements()->delete();

        foreach (array_values($this->resource_requirements) as $position => $requirement) {
            $record = $service->resourceRequirements()->create([
                'tenant_id' => $service->tenant_id,
                'name' => $requirement['name'],
                'position' => $position,
            ]);
            $record->resources()->sync($requirement['resource_ids']);
        }
    }

    /**
     * Write the questions of the service, keeping the ones already answered.
     *
     * A question that disappears from the form is deleted, and the answers
     * given to it survive on their bookings with the wording of the day.
     */
    protected function syncQuestions(Service $service): void
    {
        $kept = [];

        foreach (array_values($this->questions) as $position => $question) {
            $type = QuestionType::from($question['type']);

            $attributes = [
                'label' => $question['label'],
                'type' => $type,
                'options' => $type->needsOptions() ? $this->parseOptions($question['options'] ?? '') : null,
                'is_required' => (bool) ($question['is_required'] ?? false),
                'position' => $position,
            ];

            if ($question['id'] === null) {
                $record = $service->questions()->create($attributes);
            } else {
                $record = $service->questions()->where('id', $question['id'])->firstOrFail();
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
    protected function parseOptions(string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options) ?: [])
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The service attributes extracted from the form state.
     *
     * @return array<string, mixed>
     */
    protected function serviceAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'duration_minutes' => $this->duration_minutes,
            'capacity' => $this->capacity,
            'is_package' => $this->is_package,
            'use_component_prices' => $this->use_component_prices,
            'buffer_before_minutes' => $this->buffer_before_minutes,
            'buffer_after_minutes' => $this->buffer_after_minutes,
            'slot_interval_minutes' => $this->slot_interval_minutes,
            'min_notice_minutes' => $this->min_notice_minutes,
            'max_advance_days' => $this->max_advance_days,
            'cancellation_notice_hours' => $this->cancellation_notice_hours,
            'price_cents' => $this->price === '' ? null : (int) round((float) $this->price * 100),
            'color' => $this->use_brand_color ? null : $this->color,
            'location_type' => $this->location_type,
            'location_value' => $this->location_value ?: null,
            'requires_confirmation' => $this->requires_confirmation,
            'is_active' => $this->is_active,
        ];
    }
}
