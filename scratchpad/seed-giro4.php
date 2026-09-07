<?php

/**
 * Riempie il portale `collaudo-giro-4` di roba da perdere, per il passo 11:
 * un operatore, un servizio e una prenotazione futura. Senza questi,
 * l'eliminazione non dimostra niente sulla cascata.
 *
 *   php artisan tinker scratchpad/seed-giro4.php
 */

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;

$tenant = Tenant::where('slug', 'collaudo-giro-4')->firstOrFail();

$operator = User::factory()->operator($tenant)->create([
    'name' => 'Operatore Da Cancellare',
    'email' => 'operatore.g4@studio.test',
]);

$service = Service::factory()->for($tenant)->create([
    'name' => 'Servizio da cancellare',
    'duration_minutes' => 30,
    'slot_interval_minutes' => 30,
    'buffer_before_minutes' => 0,
    'buffer_after_minutes' => 0,
    'min_notice_minutes' => 0,
    'requires_confirmation' => false,
]);

$service->operators()->attach($operator);

foreach (range(0, 6) as $weekday) {
    $operator->availabilityRules()->create([
        'weekday' => $weekday,
        'starts_at' => '09:00:00',
        'ends_at' => '17:00:00',
    ]);
}

$booking = Booking::factory()
    ->forSlot($service, $operator, now('UTC')->addDays(3)->setTime(10, 0))
    ->create();

echo "tenant:   {$tenant->id} {$tenant->slug}\n";
echo "operator: {$operator->id}\n";
echo "service:  {$service->id}\n";
echo "booking:  {$booking->id} ({$booking->uid})\n";
