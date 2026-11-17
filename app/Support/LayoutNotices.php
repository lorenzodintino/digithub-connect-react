<?php

namespace App\Support;

use App\Enums\SubscriptionState;
use App\Models\User;
use App\Services\Impersonation;
use App\Services\LicenseService;

/**
 * The strips above every back-office screen: impersonation, a licence block the
 * administrator is bypassing, platform documents waiting for acceptance, the
 * licence (or the paid period, for a portal the License Manager does not govern)
 * and the monthly bookings of the plan.
 *
 * The React shell receives them as the `notices` shared prop
 * (HandleInertiaRequests) and draws them above the page.
 *
 * @phpstan-type Notice array{
 *     key: string,
 *     tone: 'amber'|'red'|'sky',
 *     icon: string,
 *     message: string,
 *     detail: string|null,
 *     link: array{label: string, href: string}|null,
 * }
 */
class LayoutNotices
{
    /**
     * Every notice the given person should see right now, in display order.
     *
     * @return array<int, Notice>
     */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter([
            self::impersonation($user),
            self::licenseBypass($user),
            self::platformLicenseProblem($user),
            self::legalDocuments($user),
            self::license($user),
            self::paidPeriod($user),
            self::monthlyBookings($user),
            self::overPlan($user),
        ]));
    }

    /**
     * Impossible to miss on purpose: nobody should forget they are somebody
     * else, least of all inside a customer's portal.
     *
     * @return Notice|null
     */
    private static function impersonation(User $user): ?array
    {
        $impersonation = app(Impersonation::class);

        if (! $impersonation->isActive()) {
            return null;
        }

        return [
            'key' => 'impersonation',
            'tone' => 'amber',
            'icon' => 'exclamation-triangle',
            'message' => __('You are working as :name.', ['name' => $user->name]),
            'detail' => self::impersonationEnds($impersonation),
            'link' => ['label' => __('Back to my account'), 'href' => route('impersonation.stop')],
        ];
    }

    /**
     * When the session drops by itself, on the administrator's clock.
     */
    private static function impersonationEnds(Impersonation $impersonation): ?string
    {
        $expiresAt = $impersonation->expiresAt();

        if ($expiresAt === null) {
            return null;
        }

        $timezone = $impersonation->actor()->timezone ?? config('app.timezone');

        return __('It ends at :time.', ['time' => $expiresAt->setTimezone($timezone)->format('H:i')]);
    }

    /**
     * A notice, not a wall: the portal keeps working while its manager reads
     * what changed.
     *
     * @return Notice|null
     */
    private static function legalDocuments(User $user): ?array
    {
        if (! $user->isManager() || $user->tenant === null) {
            return null;
        }

        $pending = $user->tenant->pendingLegalDocuments();

        if ($pending->isEmpty()) {
            return null;
        }

        return [
            'key' => 'legal-documents',
            'tone' => 'sky',
            'icon' => 'document-text',
            'message' => trans_choice(
                'A platform document is waiting for your acceptance: :documents.|:count platform documents are waiting for your acceptance: :documents.',
                $pending->count(),
                ['documents' => $pending->map(fn ($document) => $document->type->label())->join(', ')],
            ),
            'detail' => null,
            'link' => ['label' => __('Read and accept'), 'href' => route('app.portal').'#platform-documents'],
        ];
    }

    /**
     * The administrator standing in for a manager walks past a licence block the
     * real manager would hit: said on every screen, in red, so it is never mistaken
     * for how the portal looks to its owner.
     *
     * @return Notice|null
     */
    private static function licenseBypass(User $user): ?array
    {
        $tenant = $user->tenant;

        if ($tenant === null || ! app(Impersonation::class)->isActive()) {
            return null;
        }

        $license = app(LicenseService::class);

        if (! $license->blocksBackOffice($tenant)) {
            return null;
        }

        return [
            'key' => 'license-bypass',
            'tone' => 'red',
            'icon' => 'exclamation-circle',
            'message' => __('You are going past a license block (:reason): the real manager would only see the blocked screen.', [
                'reason' => LicenseMessages::reason($license->status($tenant)->blockKey() ?? 'unverifiable'),
            ]),
            'detail' => null,
            'link' => null,
        ];
    }

    /**
     * The License Manager cannot be used with the platform's own configuration:
     * for the administrator only, never held against the customers.
     *
     * @return Notice|null
     */
    private static function platformLicenseProblem(User $user): ?array
    {
        $license = app(LicenseService::class);
        $code = $user->isAdmin() ? $license->platformProblem() : null;

        if ($code === null) {
            $outage = $user->isAdmin() ? $license->outage() : null;

            if ($outage === null) {
                return null;
            }

            return [
                'key' => 'license-outage',
                'tone' => 'red',
                'icon' => 'exclamation-circle',
                'message' => __('The License Manager has not answered since :since (:code). The portals keep working on the last answer received: fix the connection as soon as you can.', [
                    'since' => $outage['since']->setTimezone($user->timezone)->translatedFormat('d M Y, H:i'),
                    'code' => $outage['code'],
                ]),
                'detail' => null,
                'link' => null,
            ];
        }

        return [
            'key' => 'license-platform',
            'tone' => 'red',
            'icon' => 'exclamation-circle',
            'message' => __('The License Manager cannot be used with the current configuration (:code): check the license settings in the .env file.', ['code' => $code]),
            'detail' => null,
            'link' => null,
        ];
    }

    /**
     * The licence of a portal the License Manager governs: a warning while it
     * is in tolerance or about to end, red from three days before the end and
     * in tolerance (the same thresholds as the paid period reminders).
     *
     * @return Notice|null
     */
    private static function license(User $user): ?array
    {
        $tenant = $user->isManager() ? $user->tenant : null;
        $license = app(LicenseService::class);

        if ($tenant === null || ! $license->governs($tenant)) {
            return null;
        }

        $status = $license->status($tenant);

        if ($status->isInGrace()) {
            return [
                'key' => 'license',
                'tone' => 'red',
                'icon' => 'exclamation-circle',
                'message' => __('The license ended on :date. The portal will stop working on :suspension unless it is renewed: contact the platform administrator.', [
                    'date' => $status->expiresAt?->setTimezone($tenant->timezone)->translatedFormat('d M Y'),
                    'suspension' => $status->graceEndsAt?->setTimezone($tenant->timezone)->translatedFormat('d M Y'),
                ]),
                'detail' => null,
                'link' => null,
            ];
        }

        $daysLeft = $status->daysLeft($tenant->timezone);

        if (! $status->isExpiring() || $daysLeft === null) {
            return null;
        }

        return [
            'key' => 'license',
            // The License Manager flags 14 and 7 days; the strip turns red at 3, as the reminders always did.
            'tone' => $daysLeft <= 3 ? 'red' : 'amber',
            'icon' => 'clock',
            'message' => trans_choice(
                '{0} The license ends today: contact the platform administrator to renew it.|{1} The license ends tomorrow, on :date: contact the platform administrator to renew it.|[2,*] The license ends in :count days, on :date: contact the platform administrator to renew it.',
                max(0, $daysLeft),
                ['date' => $status->expiresAt?->setTimezone($tenant->timezone)->translatedFormat('d M Y')],
            ),
            'detail' => null,
            'link' => null,
        ];
    }

    /**
     * @return Notice|null
     */
    private static function paidPeriod(User $user): ?array
    {
        $tenant = $user->isManager() ? $user->tenant : null;

        if ($tenant === null) {
            return null;
        }

        // The License Manager has its own dates for these portals: its notice replaces this one.
        if (app(LicenseService::class)->governs($tenant)) {
            return null;
        }

        $state = $tenant->subscriptionState();

        if (in_array($state, [SubscriptionState::Grace, SubscriptionState::Lapsed], true)) {
            return [
                'key' => 'paid-period',
                'tone' => 'red',
                'icon' => 'exclamation-circle',
                'message' => __('The paid period ended on :date. The portal will be suspended on :suspension unless it is renewed: contact the platform administrator.', [
                    'date' => $tenant->paid_until->translatedFormat('d M Y'),
                    'suspension' => $tenant->suspendsOn()->translatedFormat('d M Y'),
                ]),
                'detail' => null,
                'link' => null,
            ];
        }

        if ($state === SubscriptionState::Expiring) {
            return [
                'key' => 'paid-period',
                'tone' => 'amber',
                'icon' => 'clock',
                'message' => trans_choice(
                    '{0} The paid period ends today: contact the platform administrator to renew it.|{1} The paid period ends tomorrow, on :date: contact the platform administrator to renew it.|[2,*] The paid period ends in :count days, on :date: contact the platform administrator to renew it.',
                    $tenant->subscriptionDaysLeft(),
                    ['date' => $tenant->paid_until->translatedFormat('d M Y')],
                ),
                'detail' => null,
                'link' => null,
            ];
        }

        return null;
    }

    /**
     * The portal has more than its plan now includes (a move to a smaller plan): what exists keeps working,
     * nothing is deleted or hidden, only new creations are refused. Said to the manager, who can act on it.
     *
     * @return Notice|null
     */
    private static function overPlan(User $user): ?array
    {
        $tenant = $user->isManager() ? $user->tenant : null;

        if ($tenant === null) {
            return null;
        }

        $excess = $tenant->planUsage()->excessLabels();

        if ($excess === []) {
            return null;
        }

        return [
            'key' => 'over-plan',
            'tone' => 'amber',
            'icon' => 'exclamation-circle',
            'message' => __('Your plan now includes less than you already have: :list. What you created keeps working and nothing is deleted or hidden, but you cannot add more until you are within the limits.', ['list' => implode('; ', $excess)]),
            'detail' => null,
            'link' => $tenant->upgradePlans()->isEmpty() ? null : ['label' => __('Change plan'), 'href' => route('app.portal').'#your-plan'],
        ];
    }

    /**
     * @return Notice|null
     */
    private static function monthlyBookings(User $user): ?array
    {
        $tenant = $user->isManager() ? $user->tenant : null;

        if ($tenant === null) {
            return null;
        }

        $usage = $tenant->planUsage();

        if (! $usage->bookingsNearLimit()) {
            return null;
        }

        $replace = ['count' => $usage->bookingsThisMonth(), 'limit' => $usage->bookingLimit()];

        return [
            'key' => 'monthly-bookings',
            'tone' => 'amber',
            'icon' => 'chart-bar',
            'message' => $usage->bookingsAtLimit()
                ? __('This month the portal has taken :count bookings and reached the :limit of your plan. Customers can still book; ask the platform administrator about a bigger plan.', $replace)
                : __('This month the portal has taken :count of the :limit bookings in your plan.', $replace),
            'detail' => null,
            // Where the plan can be changed: the request goes to the platform administrator.
            'link' => $tenant->upgradePlans()->isEmpty() ? null : ['label' => __('Change plan'), 'href' => route('app.portal').'#your-plan'],
        ];
    }
}
