<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShowcaseMailSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How the demo requests of the home page reach us: the address they go to and
 * the SMTP server they leave through. A single row for the whole platform.
 *
 * The server follows the rule of the portals' own SMTP: it takes over only
 * after a test message went through it, and a real delivery that fails stands
 * it down to the platform mailer.
 *
 * @property int $id
 * @property string|null $recipient
 * @property string|null $mail_host
 * @property int|null $mail_port
 * @property string|null $mail_username
 * @property string|null $mail_password
 * @property string|null $mail_encryption
 * @property string|null $mail_from_address
 * @property CarbonImmutable|null $mail_tested_at
 * @property string|null $mail_error
 * @property CarbonImmutable|null $mail_failed_at
 * @property CarbonImmutable|null $mail_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'recipient', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption',
    'mail_from_address', 'mail_tested_at', 'mail_error', 'mail_failed_at', 'mail_verified_at',
])]
class ShowcaseMailSettings extends Model
{
    /** @use HasFactory<ShowcaseMailSettingsFactory> */
    use HasFactory;

    /** The name the SMTP server is registered under at send time. */
    public const MAILER = 'showcase';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_password' => 'encrypted',
            'mail_tested_at' => 'immutable_datetime',
            'mail_failed_at' => 'immutable_datetime',
            'mail_verified_at' => 'immutable_datetime',
        ];
    }

    /**
     * The one row, created empty the first time anybody asks for it.
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create([
            'recipient' => null,
            'mail_host' => null,
            'mail_port' => null,
            'mail_username' => null,
            'mail_password' => null,
            'mail_encryption' => null,
            'mail_from_address' => null,
        ]);
    }

    /**
     * Where a demo request goes: the address saved by the administrator, else the one in the environment.
     */
    public function recipientAddress(): ?string
    {
        return filled($this->recipient) ? $this->recipient : (config('app.demo_email') ?: null);
    }

    /**
     * Determine whether the form of the home page can send: with nobody to receive the requests it does not.
     */
    public function acceptsRequests(): bool
    {
        return $this->recipientAddress() !== null;
    }

    public function hasMailerSettings(): bool
    {
        return filled($this->mail_host)
            && filled($this->mail_username)
            && filled($this->mail_password);
    }

    /**
     * Determine whether the requests leave through the own SMTP server.
     *
     * Settings are not enough: the server must have proved itself with a test
     * that went through. Anything else leaves through the platform mailer.
     */
    public function hasCustomMailer(): bool
    {
        return $this->hasMailerSettings() && $this->mail_verified_at !== null;
    }

    /**
     * Determine whether the last real delivery failed and no test has passed since.
     */
    public function mailDeliveryFailing(): bool
    {
        if ($this->mail_error === null || $this->mail_failed_at === null) {
            return false;
        }

        return $this->mail_tested_at === null
            || $this->mail_failed_at->greaterThan($this->mail_tested_at);
    }

    /**
     * The mailer configuration for the own SMTP server.
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
     * The address the own server sends as, proved or not.
     */
    public function ownMailFromAddress(): ?string
    {
        if (! $this->hasMailerSettings()) {
            return null;
        }

        return $this->mail_from_address ?: $this->mail_username;
    }

    /**
     * The address the requests are sent from right now.
     */
    public function mailFromAddress(): string
    {
        if ($this->hasCustomMailer()) {
            return $this->ownMailFromAddress() ?? config('mail.from.address');
        }

        return config('mail.from.address');
    }
}
