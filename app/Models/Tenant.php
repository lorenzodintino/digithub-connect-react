<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $locale
 * @property string $brand_color
 * @property string|null $logo_path
 * @property string|null $description
 * @property string|null $contact_email
 * @property string|null $reply_to_email
 * @property string|null $mail_host
 * @property int|null $mail_port
 * @property string|null $mail_username
 * @property string|null $mail_password
 * @property string|null $mail_encryption
 * @property string|null $mail_from_address
 * @property CarbonImmutable|null $mail_tested_at
 * @property string|null $mail_error
 * @property bool $notify_manager_on_booking
 * @property int|null $reminder_hours_before
 * @property string|null $contact_phone
 * @property string|null $website
 * @property string|null $privacy_policy_url
 * @property string|null $privacy_policy_path
 * @property string|null $terms_url
 * @property string|null $terms_path
 * @property string|null $data_controller
 * @property int|null $data_retention_days
 * @property string|null $address
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'slug', 'timezone', 'locale', 'brand_color', 'logo_path', 'description',
    'privacy_policy_url', 'privacy_policy_path', 'terms_url', 'terms_path',
    'data_controller', 'data_retention_days',
    'contact_email', 'reply_to_email', 'contact_phone', 'website', 'address', 'is_active',
    'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption',
    'mail_from_address', 'mail_tested_at', 'mail_error', 'notify_manager_on_booking',
    'reminder_hours_before',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notify_manager_on_booking' => 'boolean',
            'mail_password' => 'encrypted',
            'mail_tested_at' => 'immutable_datetime',
        ];
    }

    /**
     * Resolve the portal by its public slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * All users belonging to this portal.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The single manager account owning this portal.
     *
     * @return HasOne<User, $this>
     */
    public function manager(): HasOne
    {
        return $this->hasOne(User::class)->where('role', UserRole::Manager);
    }

    /**
     * The operators available for bookings inside this portal.
     *
     * @return HasMany<User, $this>
     */
    public function operators(): HasMany
    {
        return $this->hasMany(User::class)->where('role', UserRole::Default);
    }

    /**
     * The bookable staff members of this portal, managers included when enabled.
     *
     * @return HasMany<User, $this>
     */
    public function bookableUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('is_bookable', true)->where('is_active', true);
    }

    /**
     * The services offered by this portal.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * The bookings placed inside this portal.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * How many upcoming bookings would be destroyed along with this record.
     */
    public function upcomingBookingsCount(): int
    {
        return $this->bookings()->blocking()->upcoming()->count();
    }

    /**
     * Scope the query to active portals only.
     *
     * @param  Builder<Tenant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Where replies to notification emails should land.
     *
     * Falls back to the public contact address, and to nothing at all when the
     * portal has given neither: in that case the mail simply has no Reply-To.
     */
    public function notificationReplyTo(): ?string
    {
        return $this->reply_to_email ?: $this->contact_email ?: null;
    }

    /**
     * Determine whether this portal reminds its customers at all.
     */
    public function sendsReminders(): bool
    {
        return $this->reminder_hours_before !== null && $this->reminder_hours_before > 0;
    }

    /**
     * Determine whether this portal sends through its own SMTP server.
     *
     * Host, username and password together are the minimum that makes an
     * authenticated connection possible; anything less falls back to the
     * platform mailer.
     */
    public function hasCustomMailer(): bool
    {
        return filled($this->mail_host)
            && filled($this->mail_username)
            && filled($this->mail_password);
    }

    /**
     * The mailer configuration for this portal's own SMTP server.
     *
     * @return array<string, mixed>
     */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->mail_host,
            'port' => $this->mail_port ?: 587,
            'username' => $this->mail_username,
            'password' => $this->mail_password,
            'encryption' => $this->mail_encryption ?: null,
            'timeout' => 15,
        ];
    }

    /**
     * The name this portal's mailer is registered under at send time.
     *
     * Keyed by id so two portals never share a resolved mailer inside the same
     * queue worker.
     */
    public function mailerName(): string
    {
        return 'tenant.'.$this->getKey();
    }

    /**
     * The address notifications are sent from.
     *
     * With its own SMTP the portal sends as itself; otherwise the platform
     * address is used and only the display name carries the portal.
     */
    public function mailFromAddress(): string
    {
        if ($this->hasCustomMailer()) {
            return $this->mail_from_address ?: $this->mail_username;
        }

        return config('mail.from.address');
    }

    /**
     * The public booking page URL for this portal.
     */
    public function publicUrl(): string
    {
        return route('portal.show', $this);
    }

    /**
     * Build a unique slug from the given name.
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * The public URL of the portal logo, when one was uploaded.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Black or white, whichever stays readable on top of the brand color.
     *
     * Uses the WCAG relative luminance so a pale brand color gets dark text
     * instead of the white one that would disappear on it.
     */
    public function brandForeground(): string
    {
        [$red, $green, $blue] = array_map(
            fn (string $pair): float => hexdec($pair) / 255,
            str_split(ltrim($this->brand_color, '#'), 2),
        );

        $channel = fn (float $value): float => $value <= 0.03928
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;

        $luminance = 0.2126 * $channel($red) + 0.7152 * $channel($green) + 0.0722 * $channel($blue);

        return $luminance > 0.4 ? '#18181b' : '#ffffff';
    }

    /**
     * Where the privacy policy of this portal can be read, if anywhere.
     *
     * A portal either links a page on its own website or uploads a PDF here.
     * The uploaded file wins: it is the one the portal chose to keep with us,
     * and it is the one we can be sure is still reachable.
     */
    public function privacyPolicyLink(): ?string
    {
        return $this->documentLink($this->privacy_policy_path, $this->privacy_policy_url);
    }

    /**
     * Where the terms of service of this portal can be read, if anywhere.
     */
    public function termsLink(): ?string
    {
        return $this->documentLink($this->terms_path, $this->terms_url);
    }

    /**
     * Determine whether the portal published the documents it must publish.
     *
     * Without a privacy policy the booking form still asks for consent — we do
     * process personal data either way — but the portal has nothing to link.
     */
    public function hasLegalDocuments(): bool
    {
        return $this->privacyPolicyLink() !== null;
    }

    /**
     * Resolve an uploaded document first, then the link typed by the portal.
     */
    private function documentLink(?string $path, ?string $url): ?string
    {
        if ($path !== null) {
            return Storage::disk('public')->url($path);
        }

        return $url;
    }

    /**
     * The portal initials used as a logo fallback.
     */
    public function initials(): string
    {
        return Str::upper(Str::substr(Str::initials($this->name, true), 0, 2));
    }
}
