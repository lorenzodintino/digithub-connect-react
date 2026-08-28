<?php

namespace App\Livewire\Forms;

use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int $duration_minutes = 30;

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
            'questions' => __('questions'),
        ];
    }

    /**
     * Fill the form from an existing service.
     */
    public function setService(Service $service): void
    {
        $this->service = $service;

        $this->fill($service->only([
            'name', 'duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes',
            'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days',
            'cancellation_notice_hours', 'requires_confirmation', 'is_active',
        ]));

        $this->use_brand_color = $service->color === null;
        $this->color = $service->color ?? $service->tenant->brand_color;

        $this->description = $service->description ?? '';
        $this->location_type = $service->location_type->value;
        $this->location_value = $service->location_value ?? '';
        $this->price = $service->price_cents === null ? '' : (string) ($service->price_cents / 100);
        $this->operator_ids = $service->operators()->pluck('users.id')->all();

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

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'slug' => Service::uniqueSlug($this->name, $tenant->id),
            'position' => (int) Service::query()->forTenant($tenant)->max('position') + 1,
            ...$this->serviceAttributes(),
        ]);

        $service->operators()->sync($this->operator_ids);

        $this->syncQuestions($service);

        $this->reset();

        return $service;
    }

    /**
     * Persist changes to the loaded service.
     */
    public function update(): Service
    {
        $this->validate();

        $this->service->update($this->serviceAttributes());
        $this->service->operators()->sync($this->operator_ids);

        $this->syncQuestions($this->service);

        return $this->service;
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
