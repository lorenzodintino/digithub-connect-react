<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);

    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    $tomorrow = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder($this->service, $this->operator))->slotsForDay($tomorrow)[0];

    $this->booking = Booking::factory()->forSlot($this->service, $this->operator, $slot)->create();
});

test('a language picked on a public page is kept over the portal one', function () {
    // The switcher reloads the page it is on with ?lang=, read from the address
    // bar; from then on the session remembers the choice.
    $this->tenant->update(['locale' => 'it']);

    $this->get(route('portal.booking', [$this->tenant, $this->booking]))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'it'));

    $this->get(route('portal.booking', [$this->tenant, $this->booking, 'lang' => 'en']))
        ->assertInertia(fn (Assert $page) => $page->component('portal/booking')->where('locale', 'en'));

    $this->get(route('portal.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('booking link copy labels follow the visitor language', function () {
    // The labels are drawn by the page from the language it is served in.
    foreach (['en', 'it'] as $locale) {
        $this->get(route('portal.booking', [$this->tenant, $this->booking]).'?lang='.$locale)
            ->assertSee('<html lang="'.$locale.'">', escape: false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
    }
});
