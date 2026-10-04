<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;

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

/**
 * The `wire:snapshot` of the first component found in the given HTML.
 */
function snapshotFrom(string $html): string
{
    expect($html)->toContain('wire:snapshot=');

    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

    return html_entity_decode($matches[1], ENT_QUOTES);
}

test('the language links point at the page on first load', function () {
    $this->get(route('portal.booking', [$this->tenant, $this->booking]))
        ->assertOk()
        ->assertSee(route('portal.booking', [$this->tenant, $this->booking]).'?lang=en', escape: false);
});

test('booking link copy labels follow the visitor language', function () {
    foreach (['en' => 'Copy to clipboard', 'it' => 'Copia negli appunti'] as $locale => $label) {
        $this->get(route('portal.booking', [$this->tenant, $this->booking]).'?lang='.$locale)
            ->assertSee('aria-label="'.$label.'"', escape: false);
    }
});

test('the language links survive a Livewire update instead of aiming at its endpoint', function () {
    $page = $this->get(route('portal.booking', [$this->tenant, $this->booking]))->assertOk();

    $response = $this->withHeader('X-Livewire', '1')->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => snapshotFrom($page->getContent()),
            'updates' => [],
            'calls' => [['method' => 'startReschedule', 'params' => [], 'path' => '']],
        ]],
    ])->assertOk();

    $html = $response->json('components.0.effects.html');

    expect($html)
        ->toContain(route('portal.booking', [$this->tenant, $this->booking]).'?lang=en')
        ->not->toContain('update?lang=');
});
