<?php

use App\Actions\Bookings\CreateBooking;
use App\Mail\Platform\BookingsLimitReachedMail;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 30, 'slot_interval_minutes' => 30, 'min_notice_minutes' => 0, 'max_advance_days' => 120]);
    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create(['weekday' => $weekday, 'starts_at' => '09:00:00', 'ends_at' => '18:00:00']);
    }

    $this->admin = User::factory()->admin()->create();
    User::factory()->admin()->create(['is_active' => false]);
});

/**
 * Book the nth slot of tomorrow through the public action.
 */
function bookSlot(int $index): void
{
    $tomorrow = CarbonImmutable::now(test()->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder(test()->service, test()->operator))->slotsForDay($tomorrow)[$index];

    app(CreateBooking::class)->handle(test()->service, test()->operator, $slot, ['name' => 'Anna Ferrari', 'email' => 'anna'.$index.'@example.test']);
}

test('the administrators are told once a month when a portal takes all the bookings of its plan', function () {
    $plan = Plan::factory()->create(['max_bookings_per_month' => 3, 'max_services' => null]);
    $this->tenant->update(['plan_id' => $plan->id]);

    bookSlot(0);
    bookSlot(1);
    Mail::assertNotQueued(BookingsLimitReachedMail::class);

    bookSlot(2);
    Mail::assertQueued(BookingsLimitReachedMail::class, 1);
    Mail::assertQueued(BookingsLimitReachedMail::class, fn (BookingsLimitReachedMail $mail) => $mail->hasTo($this->admin->email) && $mail->bookings === 3 && $mail->limit === 3);

    // Customers keep booking and the administrators are not written to again this month.
    bookSlot(3);
    bookSlot(4);
    Mail::assertQueued(BookingsLimitReachedMail::class, 1);

    expect(ActivityLog::query()->where('action', 'portal.bookings_limit_reached')->count())->toBe(1);
});

test('the next month is a new occasion to tell them', function () {
    $plan = Plan::factory()->create(['max_bookings_per_month' => 1, 'max_services' => null]);
    $this->tenant->update(['plan_id' => $plan->id]);

    bookSlot(0);
    Mail::assertQueued(BookingsLimitReachedMail::class, 1);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDays(2));
    $this->tenant->refresh();

    bookSlot(0);
    Mail::assertQueued(BookingsLimitReachedMail::class, 2);
});

test('a portal without a limit, or without a plan, tells nobody', function () {
    bookSlot(0);

    $this->tenant->update(['plan_id' => Plan::factory()->unlimited()->create()->id]);
    bookSlot(1);

    Mail::assertNotQueued(BookingsLimitReachedMail::class);
});

test('the message carries the portal, the plan and the numbers', function () {
    $plan = Plan::factory()->create(['name' => 'Base', 'max_bookings_per_month' => 1, 'max_services' => null]);
    $this->tenant->update(['plan_id' => $plan->id]);

    $html = (new BookingsLimitReachedMail($this->tenant, $plan, 1, 1))->render();

    // The name is generated: one with an apostrophe is written as &#039; in HTML.
    expect($html)->toContain(e($this->tenant->name))->toContain('Base');
});
