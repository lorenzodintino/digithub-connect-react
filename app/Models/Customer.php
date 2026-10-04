<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Somebody who booked with a portal at least once.
 *
 * The bookings keep their own copy of name, email and phone: that copy is what
 * the customer said the day they booked, and it must not change under them.
 * This record is the current picture of the same person, plus what the portal
 * itself wrote down about them.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id', 'name', 'email', 'phone', 'timezone', 'locale', 'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * The notes are free text about a person, so they are encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notes' => 'encrypted',
        ];
    }

    /**
     * The portal this customer belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Every booking this customer placed with the portal.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope the query to the customers of the given portal.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * The email as it is stored and matched: trimmed and lowercased.
     */
    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * The initials shown when there is no avatar to show.
     */
    public function initials(): string
    {
        return str($this->name)->trim()->explode(' ')
            ->take(2)
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->implode('')
            ?: '?';
    }
}
