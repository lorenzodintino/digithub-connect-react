<?php

namespace App\Support;

use App\Enums\LicenseOutcome;
use App\Enums\LicenseReason;
use Carbon\CarbonImmutable;

/**
 * What the rest of the application knows about the licence of a portal:
 * the answer of DigitHub License Manager already interpreted, never the raw
 * HTTP details. Built only by App\Services\LicenseService.
 *
 * Access is decided by `allowed` as the License Manager gave it: dates are
 * shown, never used to recompute the verdict.
 */
final readonly class LicenseStatus
{
    public function __construct(
        public LicenseOutcome $outcome,
        public bool $allowed = false,
        public ?LicenseReason $reason = null,
        public ?string $state = null,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $expiresAt = null,
        public ?CarbonImmutable $graceEndsAt = null,
        public bool $lifetime = false,
        public ?string $planName = null,
        public ?int $outstandingCents = null,
        public ?int $dueCents = null,
        public ?int $paidCents = null,
        public ?string $currency = null,
        /** @var array<int, array{months: int|null, price_cents: int, currency: string}> */
        public array $renewalPrices = [],
        public ?CarbonImmutable $checkedAt = null,
        public ?string $notActivatedReason = null,
        public bool $stale = false,
        public bool $alarm = false,
        public ?string $error = null,
    ) {}

    /**
     * A portal nobody has ever heard an answer about.
     */
    public static function unavailable(?string $error = null): self
    {
        return new self(LicenseOutcome::Unavailable, error: $error);
    }

    /**
     * Whether the public pages of the portal must close.
     *
     * Only on an answer: a License Manager that is down never takes the
     * customers' booking page away.
     */
    public function blocksPublic(): bool
    {
        return match ($this->outcome) {
            LicenseOutcome::Licensed => ! $this->allowed,
            LicenseOutcome::NotActivated, LicenseOutcome::SoftwareInactive => true,
            LicenseOutcome::Unavailable => false,
        };
    }

    /**
     * Whether the back-office of the portal must close: on an answer, and
     * also when nothing could ever be verified (the screen says to retry).
     */
    public function blocksBackOffice(): bool
    {
        return $this->blocksPublic() || $this->outcome === LicenseOutcome::Unavailable;
    }

    /**
     * Whether the licence is valid but ends soon (the License Manager's EXPIRING_14 and EXPIRING_7).
     */
    public function isExpiring(): bool
    {
        return $this->outcome === LicenseOutcome::Licensed
            && $this->allowed
            && in_array($this->state, ['EXPIRING_14', 'EXPIRING_7'], true);
    }

    public function isInGrace(): bool
    {
        return $this->outcome === LicenseOutcome::Licensed && $this->allowed && $this->reason === LicenseReason::Grace;
    }

    /**
     * The plan name as it is compared: trimmed and lowercase.
     */
    public function planKey(): ?string
    {
        return $this->planName === null ? null : mb_strtolower(trim($this->planName));
    }

    /**
     * Whole days from the start of today to the end of the licence, in the
     * given timezone: 0 on the last day. Only for the wording of a notice.
     */
    public function daysLeft(string $timezone): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();

        return (int) $today->diffInDays($this->expiresAt->setTimezone($timezone)->startOfDay(), false);
    }

    /**
     * The words for why access is refused, or null when it is not.
     *
     * Keys are what the blocked screen and the administrator page translate.
     */
    public function blockKey(): ?string
    {
        if (! $this->blocksBackOffice()) {
            return null;
        }

        return match ($this->outcome) {
            LicenseOutcome::Licensed => $this->reason->value ?? 'suspended',
            LicenseOutcome::NotActivated => $this->notActivatedReason ?? 'tenant_not_found',
            LicenseOutcome::SoftwareInactive => 'software_inactive',
            LicenseOutcome::Unavailable => 'unverifiable',
        };
    }
}
