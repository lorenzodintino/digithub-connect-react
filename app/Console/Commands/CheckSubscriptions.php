<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionState;
use App\Mail\Platform\SubscriptionNoticeMail;
use App\Models\Tenant;
use App\Support\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'portals:check-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind managers of the end of their paid period and suspend the portals past their tolerance';

    /**
     * Send whatever reminder is due and suspend what is past its tolerance.
     *
     * Each portal remembers how many notices it already had, so a run that was
     * skipped sends the one that is due now instead of losing it, and a second
     * run on the same day sends nothing twice.
     */
    public function handle(): int
    {
        $reminded = 0;
        $suspended = 0;

        Tenant::query()
            ->with(['manager', 'plan'])
            ->where('is_active', true)
            ->whereNotNull('paid_until')
            ->each(function (Tenant $tenant) use (&$reminded, &$suspended): void {
                $state = $tenant->subscriptionState();

                if ($state === SubscriptionState::Lapsed) {
                    $this->suspend($tenant);
                    $suspended++;

                    return;
                }

                $due = $this->noticesDue($tenant, $state);

                if ($due > $tenant->subscription_reminders_sent) {
                    $this->notify($tenant, $state === SubscriptionState::Grace ? SubscriptionNoticeMail::GRACE : SubscriptionNoticeMail::EXPIRING);
                    $tenant->forceFill(['subscription_reminders_sent' => $due])->save();
                    $reminded++;
                }
            });

        $this->info("Sent {$reminded} reminders, suspended {$suspended} portals.");

        return self::SUCCESS;
    }

    /**
     * How many notices the portal should have had by now.
     *
     * One per reminder day already reached, plus one more once the paid period
     * is over and the tolerance has started.
     */
    private function noticesDue(Tenant $tenant, SubscriptionState $state): int
    {
        $daysLeft = (int) $tenant->subscriptionDaysLeft();

        $reached = count(array_filter(Tenant::SUBSCRIPTION_REMINDER_DAYS, fn (int $days): bool => $daysLeft <= $days));

        return $state === SubscriptionState::Grace ? $reached + 1 : $reached;
    }

    /**
     * Take the portal offline, keeping track that it was for payment.
     */
    private function suspend(Tenant $tenant): void
    {
        $tenant->forceFill(['is_active' => false, 'subscription_suspended_at' => now()])->save();

        Activity::log('portal.suspended', $tenant, ['reason' => 'subscription', 'paid_until' => $tenant->paid_until?->toDateString()], tenant: $tenant);

        $this->notify($tenant, SubscriptionNoticeMail::SUSPENDED);
    }

    /**
     * Write to the manager in their own language.
     *
     * @param  SubscriptionNoticeMail::EXPIRING|SubscriptionNoticeMail::GRACE|SubscriptionNoticeMail::SUSPENDED  $notice
     */
    private function notify(Tenant $tenant, string $notice): void
    {
        $manager = $tenant->manager;

        if ($manager === null) {
            return;
        }

        Mail::to($manager->email)->locale($manager->locale)->queue(new SubscriptionNoticeMail($tenant, $notice));
    }
}
