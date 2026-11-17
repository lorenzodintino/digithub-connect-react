<?php

namespace App\Models;

use App\Enums\PlanRequestStatus;
use Database\Factories\PlanRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A manager's request for a bigger plan, and what became of it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string|null $from_plan
 * @property int|null $requested_plan_id
 * @property string $requested_plan
 * @property string|null $reason
 * @property string|null $note
 * @property PlanRequestStatus $status
 * @property string|null $response
 * @property int|null $handled_by
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id', 'user_id', 'from_plan', 'requested_plan_id', 'requested_plan', 'reason', 'note',
    'status', 'response', 'handled_by', 'status_changed_at',
])]
class PlanRequest extends Model
{
    /** @use HasFactory<PlanRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanRequestStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Who asked.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    /**
     * The administrator who last answered.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * The requests still waiting for an answer.
     *
     * @param  Builder<PlanRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', PlanRequestStatus::openValues());
    }
}
