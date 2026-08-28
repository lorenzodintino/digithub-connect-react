<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extra question a service asks at booking time.
 *
 * @property int $id
 * @property int $service_id
 * @property string $label
 * @property QuestionType $type
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_id', 'label', 'type', 'options', 'is_required', 'position'])]
class ServiceQuestion extends Model
{
    /**
     * A service asks at most this many questions.
     *
     * Booking is a two minute job: past a handful of questions it stops being
     * a booking form and becomes paperwork.
     */
    public const LIMIT = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    /**
     * The service asking the question.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The choices offered, empty for the free text types.
     *
     * @return array<int, string>
     */
    public function choices(): array
    {
        return $this->type->needsOptions() ? ($this->options ?? []) : [];
    }
}
