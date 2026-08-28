<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uid
 * @property int $tenant_id
 * @property int $service_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_notes
 * @property string $customer_timezone
 * @property string $customer_locale
 * @property CarbonImmutable|null $privacy_accepted_at
 * @property string|null $privacy_accepted_url
 * @property CarbonImmutable|null $anonymised_at
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $blocked_starts_at
 * @property CarbonImmutable $blocked_ends_at
 * @property BookingStatus $status
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by_id
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $rescheduled_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property int $ics_sequence
 * @property CarbonImmutable|null $original_starts_at
 * @property int|null $price_cents
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uid', 'tenant_id', 'service_id', 'user_id', 'customer_id',
    'customer_name', 'customer_email', 'customer_phone', 'customer_notes',
    'customer_timezone', 'customer_locale',
    'privacy_accepted_at', 'privacy_accepted_url', 'anonymised_at',
    'starts_at', 'ends_at', 'blocked_starts_at', 'blocked_ends_at',
    'status', 'confirmed_at', 'cancelled_at', 'cancelled_by_id', 'cancellation_reason',
    'rescheduled_at', 'original_starts_at', 'reminder_sent_at', 'ics_sequence',
    'price_cents', 'currency',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_starts_at' => 'immutable_datetime',
            'blocked_ends_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rescheduled_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'privacy_accepted_at' => 'immutable_datetime',
            'anonymised_at' => 'immutable_datetime',
            'original_starts_at' => 'immutable_datetime',
            'status' => BookingStatus::class,
        ];
    }

    /**
     * Resolve public booking links by their unguessable token.
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /**
     * The portal the booking belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The booked service.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The staff member delivering the booking.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The account that cancelled the booking, when it was not the customer.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /**
     * Scope the query to bookings that still occupy their slot.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blocking());
    }

    /**
     * Scope the query to bookings starting from now onwards.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now());
    }

    /**
     * Scope the query to the bookings of the given portal.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * Determine whether the portal can still cancel or move this booking.
     *
     * The desk is never bound by the notice it asks of its customers: a phone
     * call an hour before is exactly when somebody needs the agenda changed.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
            && $this->starts_at->isFuture();
    }

    /**
     * Determine whether the customer can still cancel or move it themselves.
     */
    public function isCancellableByCustomer(): bool
    {
        if (! $this->isCancellable()) {
            return false;
        }

        $deadline = $this->customerChangeDeadline();

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * The instant after which only the portal can touch this booking.
     *
     * Null when the service asks for no notice at all.
     */
    public function customerChangeDeadline(): ?CarbonImmutable
    {
        $hours = $this->service->cancellation_notice_hours;

        return $hours > 0 ? $this->starts_at->subHours($hours) : null;
    }

    /**
     * Determine whether the outcome of the appointment can still be recorded.
     *
     * Only once it is over: nobody can say how a meeting went before it ends.
     * A cancelled booking never happened, so it has no outcome.
     */
    public function acceptsOutcome(): bool
    {
        return $this->status !== BookingStatus::Cancelled && $this->ends_at->isPast();
    }

    /**
     * What the customer answered to the extra questions of the service.
     *
     * @return HasMany<BookingAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(BookingAnswer::class)->orderBy('position');
    }

    /**
     * The anagraphic record of whoever booked, when there still is one.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Determine whether the customer data of this booking was erased.
     */
    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    /**
     * The customer name to show, once erasure may have emptied it.
     */
    public function displayCustomerName(): string
    {
        return $this->isAnonymised() ? __('Erased customer') : $this->customer_name;
    }

    /**
     * Determine whether the booking is still waiting for approval.
     */
    public function awaitsConfirmation(): bool
    {
        return $this->status === BookingStatus::Pending;
    }

    /**
     * The start time rendered in the given timezone.
     */
    public function startsAtIn(string $timezone): CarbonImmutable
    {
        return $this->starts_at->setTimezone($timezone);
    }

    /**
     * The end time rendered in the given timezone.
     */
    public function endsAtIn(string $timezone): CarbonImmutable
    {
        return $this->ends_at->setTimezone($timezone);
    }

    /**
     * Generate an unguessable public token for a booking.
     */
    public static function newUid(): string
    {
        return Str::lower(Str::random(32));
    }
}
