@php($user = auth()->user())
@php($tenant = $user?->isManager() ? $user->tenant : null)

@if ($tenant)
    @php($state = $tenant->subscriptionState())
    @php($usage = $tenant->planUsage())

    @if (in_array($state, [\App\Enums\SubscriptionState::Grace, \App\Enums\SubscriptionState::Lapsed], true))
        <div class="flex items-center gap-2 border-b border-red-300 bg-red-50 px-4 py-2 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <flux:icon name="exclamation-circle" variant="micro" />
            {{ __('The paid period ended on :date. The portal will be suspended on :suspension unless it is renewed: contact the platform administrator.', ['date' => $tenant->paid_until->translatedFormat('d M Y'), 'suspension' => $tenant->suspendsOn()->translatedFormat('d M Y')]) }}
        </div>
    @elseif ($state === \App\Enums\SubscriptionState::Expiring)
        <div class="flex items-center gap-2 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <flux:icon name="clock" variant="micro" />
            {{ trans_choice('{0} The paid period ends today: contact the platform administrator to renew it.|{1} The paid period ends tomorrow, on :date: contact the platform administrator to renew it.|[2,*] The paid period ends in :count days, on :date: contact the platform administrator to renew it.', $tenant->subscriptionDaysLeft(), ['date' => $tenant->paid_until->translatedFormat('d M Y')]) }}
        </div>
    @endif

    @if ($usage->bookingsNearLimit())
        <div class="flex items-center gap-2 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <flux:icon name="chart-bar" variant="micro" />
            {{ $usage->bookingsAtLimit()
                ? __('This month the portal has taken :count bookings and reached the :limit of your plan. Customers can still book; ask the platform administrator about a bigger plan.', ['count' => $usage->bookingsThisMonth(), 'limit' => $usage->bookingLimit()])
                : __('This month the portal has taken :count of the :limit bookings in your plan.', ['count' => $usage->bookingsThisMonth(), 'limit' => $usage->bookingLimit()]) }}
        </div>
    @endif
@endif
