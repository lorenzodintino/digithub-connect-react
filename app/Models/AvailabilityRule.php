<?php

namespace App\Models;

use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'weekday', 'starts_at', 'ends_at'])]
class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory;

    /**
     * The staff member this weekly rule belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The rule start expressed in minutes from midnight.
     */
    public function startsAtMinutes(): int
    {
        return static::toMinutes($this->starts_at);
    }

    /**
     * The rule end expressed in minutes from midnight.
     */
    public function endsAtMinutes(): int
    {
        return static::toMinutes($this->ends_at);
    }

    /**
     * Convert a H:i(:s) string into minutes from midnight.
     */
    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
