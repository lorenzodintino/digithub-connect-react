<?php

namespace App\Support;

use App\Enums\SubscriptionState;
use App\Models\User;
use App\Services\Impersonation;

/**
 * The strips above every back-office screen: impersonation, platform documents
 * waiting for acceptance, the paid period and the monthly bookings of the plan.
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
            self::legalDocuments($user),
            self::paidPeriod($user),
            self::monthlyBookings($user),
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
     * @return Notice|null
     */
    private static function paidPeriod(User $user): ?array
    {
        $tenant = $user->isManager() ? $user->tenant : null;

        if ($tenant === null) {
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
            'link' => null,
        ];
    }
}
