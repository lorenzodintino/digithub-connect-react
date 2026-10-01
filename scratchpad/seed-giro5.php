<?php

/**
 * Riempie il portale demo `studio-rossi` di dati veri, perché il Giro 5 (e il
 * Giro 6) si possano giudicare su schermate piene invece che sulle tre
 * prenotazioni future del seeder.
 *
 * Aggiunge: due mesi di storico con tutti gli stati, richieste da confermare,
 * un servizio di gruppo, uno a un solo operatore, uno nascosto, domande
 * personalizzate, lista d'attesa, una chiusura, blocchi ed eccezioni di
 * disponibilità, note sui clienti, il registro azioni retrodatato e due job
 * falliti veri nella coda.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan tinker scratchpad/seed-giro5.php
 *
 * Non è un test: nessuna asserzione, nessun esito. È l'allestimento del banco
 * di prova per il collaudo a mano, e va eseguito su un database appena
 * seminato — si rifiuta di girare due volte sullo stesso.
 */

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Mail\Customer\BookingConfirmedMail;
use App\Mail\Staff\BookingPlacedMail;
use App\Models\ActivityLog;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PortalClosure;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\TimeBlock;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\GroupSlotFinder;
use App\Services\SlotFinder;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

$tenant = Tenant::where('slug', 'studio-rossi')->firstOrFail();

if ($tenant->services()->where('name', 'Corso base di contabilità')->exists()) {
    echo "Già eseguito su questo database. Per rifarlo da zero:\n";
    echo "  php artisan migrate:fresh --seed\n";
    echo "  php artisan tinker scratchpad/seed-giro5.php\n";

    return;
}

$zone = $tenant->timezone;
$today = CarbonImmutable::now($zone)->startOfDay();

$manager = $tenant->users()->where('email', 'manager@digithub.test')->sole();
$giulia = $tenant->users()->where('email', 'giulia@digithub.test')->sole();
$luca = $tenant->users()->where('email', 'luca@digithub.test')->sole();

$firstConsultation = $tenant->services()->where('name', 'Prima consulenza')->sole();
$taxConsultation = $tenant->services()->where('name', 'Consulenza fiscale')->sole();
$vatOpening = $tenant->services()->where('name', 'Apertura partita IVA')->sole();

// ---------------------------------------------------------------------------
// 1. Due job falliti veri nella coda
// ---------------------------------------------------------------------------
// Messi in coda per davvero e poi spostati a mano in `failed_jobs`, così il
// pulsante «Riprova» della pagina Coda lavora su un payload autentico invece
// che su una riga finta. La coda dedicata evita che il worker, se è già
// acceso, li porti via prima che li sposti.

$anyBooking = Booking::where('tenant_id', $tenant->id)->orderBy('starts_at')->firstOrFail();

$failures = [
    [
        'mail' => (new BookingConfirmedMail($anyBooking))->onQueue('seed-giro5'),
        'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection could not be established with host \"smtp.studiorossi.test:587\": stream_socket_client(): Unable to connect to smtp.studiorossi.test:587 (Connection timed out)\n#0 /var/www/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(154)",
        'at' => CarbonImmutable::now()->subHours(5),
    ],
    [
        'mail' => (new BookingPlacedMail($anyBooking))->onQueue('seed-giro5'),
        'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Expected response code \"250\" but got code \"550\", with message \"550 5.7.1 Relaying denied\".\n#0 /var/www/vendor/symfony/mailer/Transport/AbstractTransport.php(87)",
        'at' => CarbonImmutable::now()->subHours(2),
    ],
];

foreach ($failures as $failure) {
    Mail::to($anyBooking->customer_email)->queue($failure['mail']);

    $row = DB::table('jobs')->where('queue', 'seed-giro5')->orderByDesc('id')->first();

    if ($row === null) {
        echo "Il job non è arrivato in coda: salto i job falliti.\n";

        break;
    }

    DB::table('jobs')->where('id', $row->id)->delete();

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => $row->payload,
        'exception' => $failure['exception'],
        'failed_at' => $failure['at'],
    ]);

    $log = Activity::log('job.failed', null, ['job' => class_basename($failure['mail'])], $tenant);
    $log->forceFill(['created_at' => $failure['at']])->saveQuietly();
}

// Da qui in poi niente parte: i dati demo non devono scrivere nemmeno nel log.
Mail::fake();

// ---------------------------------------------------------------------------
// 2. Orari diversi fra due operatori — serve al Giro 6
// ---------------------------------------------------------------------------
// «Operatore indifferente» si giudica solo se il calendario dei due non
// coincide: Luca tiene lo sportello serale il martedì e il giovedì.

foreach ([2, 4] as $weekday) {
    AvailabilityRule::create([
        'user_id' => $luca->id,
        'weekday' => $weekday,
        'starts_at' => '18:00:00',
        'ends_at' => '20:00:00',
    ]);
}

// ---------------------------------------------------------------------------
// 3. I servizi che mancavano
// ---------------------------------------------------------------------------

$vatOpening->update(['requires_confirmation' => true]);

$signature = Service::create([
    'tenant_id' => $tenant->id,
    'name' => 'Firma digitale',
    'slug' => Service::uniqueSlug('Firma digitale', $tenant->id),
    'description' => 'Rilascio della firma digitale, con riconoscimento allo sportello.',
    'duration_minutes' => 15,
    'capacity' => 1,
    'buffer_before_minutes' => 0,
    'buffer_after_minutes' => 0,
    'slot_interval_minutes' => 15,
    'min_notice_minutes' => 120,
    'max_advance_days' => 30,
    'cancellation_notice_hours' => 24,
    'price_cents' => 3000,
    'currency' => 'EUR',
    'color' => null,
    'location_type' => LocationType::InPerson,
    'location_value' => 'Via Roma 1, Milano',
    'requires_confirmation' => false,
    'is_active' => true,
    'position' => 3,
]);

// Un solo operatore: sulla pagina pubblica il pulsante «Operatore
// indifferente» non deve comparire.
$signature->operators()->sync([$giulia->id]);

$course = Service::create([
    'tenant_id' => $tenant->id,
    'name' => 'Corso base di contabilità',
    'slug' => Service::uniqueSlug('Corso base di contabilità', $tenant->id),
    'description' => 'Un'."'".'ora e mezza di contabilità di base, in gruppo, fino a otto persone.',
    'duration_minutes' => 90,
    'capacity' => 8,
    'buffer_before_minutes' => 0,
    'buffer_after_minutes' => 15,
    'slot_interval_minutes' => 90,
    'min_notice_minutes' => 240,
    'max_advance_days' => 60,
    'cancellation_notice_hours' => 48,
    'price_cents' => 6000,
    'currency' => 'EUR',
    'color' => '#b45309',
    'location_type' => LocationType::InPerson,
    'location_value' => 'Aula corsi, Via Roma 1, Milano',
    'requires_confirmation' => false,
    'is_active' => true,
    'position' => 4,
]);

$course->operators()->sync([$giulia->id, $luca->id]);

$hidden = Service::create([
    'tenant_id' => $tenant->id,
    'name' => 'Revisione bilancio',
    'slug' => Service::uniqueSlug('Revisione bilancio', $tenant->id),
    'description' => 'Non ancora pubblicato: serve a vedere come si comporta un servizio nascosto.',
    'duration_minutes' => 60,
    'capacity' => 1,
    'buffer_before_minutes' => 0,
    'buffer_after_minutes' => 0,
    'slot_interval_minutes' => 30,
    'min_notice_minutes' => 120,
    'max_advance_days' => 60,
    'cancellation_notice_hours' => 48,
    'price_cents' => 25000,
    'currency' => 'EUR',
    'color' => null,
    'location_type' => LocationType::Custom,
    'location_value' => 'Concordiamo il luogo alla conferma',
    'requires_confirmation' => true,
    'is_active' => false,
    'position' => 5,
]);

$hidden->operators()->sync([$manager->id]);

// ---------------------------------------------------------------------------
// 4. Domande personalizzate sul modulo pubblico
// ---------------------------------------------------------------------------

$questions = [
    ['label' => 'Partita IVA (se ne hai già una)', 'type' => QuestionType::ShortText, 'options' => null, 'is_required' => false],
    ['label' => 'Come ci hai trovato?', 'type' => QuestionType::SingleChoice, 'options' => ['Passaparola', 'Ricerca su Google', 'Social', 'Sono già cliente'], 'is_required' => true],
    ['label' => 'Di cosa vuoi parlare?', 'type' => QuestionType::LongText, 'options' => null, 'is_required' => false],
];

foreach ($questions as $position => $question) {
    ServiceQuestion::create([
        'service_id' => $firstConsultation->id,
        'position' => $position,
        ...$question,
    ]);
}

ServiceQuestion::create([
    'service_id' => $course->id,
    'label' => 'Documenti che porti con te',
    'type' => QuestionType::MultipleChoice,
    'options' => ['Documento di identità', 'Tessera sanitaria', 'Ultimo bilancio', 'Nessuno'],
    'is_required' => true,
    'position' => 0,
]);

$firstConsultation->load('questions');
$course->load('questions');

// ---------------------------------------------------------------------------
// 5. Due mesi di storico
// ---------------------------------------------------------------------------

/** Il giorno lavorativo N giorni indietro, saltando i fine settimana. */
$businessDaysAgo = function (int $days) use ($today): CarbonImmutable {
    $day = $today;

    while ($days > 0) {
        $day = $day->subDay();

        if ($day->isWeekday()) {
            $days--;
        }
    }

    return $day;
};

$customers = [
    'anna' => ['name' => 'Anna Ferrari', 'email' => 'anna.ferrari@example.com', 'phone' => '+39 333 1000001'],
    'paolo' => ['name' => 'Paolo Greco', 'email' => 'paolo.greco@example.com', 'phone' => '+39 333 1000002'],
    'sara' => ['name' => 'Sara Conti', 'email' => 'sara.conti@example.com', 'phone' => '+39 333 1000003'],
    'marta' => ['name' => 'Marta Villa', 'email' => 'marta.villa@example.com', 'phone' => '+39 347 2000004'],
    'davide' => ['name' => 'Davide Neri', 'email' => 'davide.neri@example.com', 'phone' => '+39 347 2000005'],
    'chiara' => ['name' => 'Chiara Lombardi', 'email' => 'chiara.lombardi@example.com', 'phone' => '+39 320 3000006'],
    'youssef' => ['name' => 'Youssef Amrani', 'email' => 'youssef.amrani@example.com', 'phone' => '+39 320 3000007'],
    'elena' => ['name' => 'Elena Ricci', 'email' => 'elena.ricci@example.com', 'phone' => '+39 388 4000008'],
];

/** Scrive una prenotazione passata, il cliente in anagrafica e le righe di registro. */
$pastBooking = function (Service $service, User $operator, CarbonImmutable $startsAt, BookingStatus $status, array $customer, ?string $notes = null) use ($tenant, $manager): Booking {
    $record = Customer::updateOrCreate(
        ['tenant_id' => $tenant->id, 'email' => Customer::normaliseEmail($customer['email'])],
        [
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'timezone' => $tenant->timezone,
            'locale' => 'it',
        ],
    );

    $cancelled = $status === BookingStatus::Cancelled;

    $booking = Booking::factory()->forSlot($service, $operator, $startsAt)->create([
        'customer_id' => $record->id,
        'customer_name' => $customer['name'],
        'customer_email' => $record->email,
        'customer_phone' => $customer['phone'],
        'customer_notes' => $notes,
        'customer_timezone' => $tenant->timezone,
        'customer_locale' => 'it',
        'privacy_accepted_at' => $startsAt->subDays(3),
        'privacy_accepted_url' => null,
        'status' => $status,
        'confirmed_at' => $startsAt->subDays(3),
        'cancelled_at' => $cancelled ? $startsAt->subDay() : null,
        'cancelled_by_id' => $cancelled ? $manager->id : null,
        'cancellation_reason' => $cancelled ? 'Il cliente ha chiesto di rimandare a dopo le ferie.' : null,
        'ics_sequence' => 0,
    ]);

    $booking->forceFill([
        'created_at' => $startsAt->subDays(3),
        'updated_at' => $startsAt->subDay(),
    ])->saveQuietly();

    $log = Activity::log('booking.created', $booking, ['service' => $service->name, 'operator' => $operator->name], $tenant);
    $log->forceFill(['created_at' => $startsAt->subDays(3)])->saveQuietly();

    if ($cancelled) {
        $log = Activity::log('booking.cancelled', $booking, ['service' => $service->name], $tenant, null, $manager);
        $log->forceFill(['created_at' => $startsAt->subDay()])->saveQuietly();
    }

    if (in_array($status, BookingStatus::outcomes(), true)) {
        $log = Activity::log('booking.outcome', $booking, ['status' => $status->value], $tenant, null, $operator);
        $log->forceFill(['created_at' => $startsAt->addHours(3)])->saveQuietly();
    }

    return $booking;
};

$completed = BookingStatus::Completed;
$absent = BookingStatus::NoShow;
$cancelled = BookingStatus::Cancelled;
$confirmed = BookingStatus::Confirmed;

// Giorni lavorativi indietro, ora, servizio, operatore, esito, cliente.
// Le ultime tre sono ancora «Confermata» a bella posta: sono quelle su cui il
// Giro 6 prova «Segna come completato» e «Segna come assente».
$history = [
    [30, 9, $taxConsultation, $giulia, $completed, 'anna'],
    [29, 11, $firstConsultation, $luca, $completed, 'paolo'],
    [28, 15, $vatOpening, $giulia, $completed, 'marta'],
    [27, 10, $taxConsultation, $manager, $completed, 'davide'],
    [26, 16, $firstConsultation, $giulia, $absent, 'chiara'],
    [24, 9, $vatOpening, $luca, $completed, 'youssef'],
    [23, 14, $taxConsultation, $giulia, $cancelled, 'elena'],
    [21, 11, $firstConsultation, $manager, $completed, 'sara'],
    [19, 15, $taxConsultation, $luca, $completed, 'anna'],
    [18, 10, $vatOpening, $giulia, $completed, 'paolo'],
    [16, 9, $firstConsultation, $giulia, $completed, 'marta'],
    [15, 17, $taxConsultation, $manager, $absent, 'davide'],
    [13, 11, $vatOpening, $luca, $cancelled, 'chiara'],
    [11, 14, $firstConsultation, $giulia, $completed, 'youssef'],
    [9, 10, $taxConsultation, $giulia, $completed, 'elena'],
    [8, 16, $vatOpening, $manager, $completed, 'sara'],
    [6, 9, $firstConsultation, $luca, $cancelled, 'anna'],
    [4, 15, $taxConsultation, $giulia, $completed, 'paolo'],
    [3, 11, $firstConsultation, $giulia, $confirmed, 'marta'],
    [2, 10, $vatOpening, $giulia, $confirmed, 'davide'],
    [1, 14, $taxConsultation, $luca, $confirmed, 'chiara'],
];

$customerNotes = [
    'anna' => 'Porto il bilancio 2025 e le fatture del primo trimestre.',
    'youssef' => 'Preferisco parlare in inglese, se possibile.',
];

foreach ($history as [$days, $hour, $service, $operator, $status, $who]) {
    $pastBooking(
        $service,
        $operator,
        $businessDaysAgo($days)->setTime($hour, 0),
        $status,
        $customers[$who],
        $customerNotes[$who] ?? null,
    );
}

// ---------------------------------------------------------------------------
// 6. Prenotazioni future, scritte dalle azioni vere
// ---------------------------------------------------------------------------
// Qui passa `CreateBooking`: slot ricontrollati, cliente collegato, risposte
// registrate e registro scritto come in produzione.

$create = app(CreateBooking::class);

/** I primi slot liberi di un servizio, quelli che vedrebbe un visitatore. */
$freeSlots = function (Service $service, ?User $operator) use ($today): array {
    $finder = $operator === null
        ? new GroupSlotFinder($service)
        : new SlotFinder($service, $operator);

    return collect($finder->slotsBetween($today->addDay(), $today->addDays(20)))
        ->flatten()
        ->values()
        ->all();
};

/** Risposte plausibili alle domande personalizzate del servizio. */
$answersFor = function (Service $service): array {
    return $service->questions
        ->mapWithKeys(fn (ServiceQuestion $question) => [
            $question->id => match ($question->type) {
                QuestionType::SingleChoice => $question->choices()[1] ?? '',
                QuestionType::MultipleChoice => array_slice($question->choices(), 0, 2),
                QuestionType::LongText => 'Vorrei capire se mi conviene il regime forfettario.',
                default => 'IT01234567890',
            },
        ])
        ->all();
};

$upcoming = [
    [$signature, $giulia, 'elena', null],
    [$taxConsultation, $luca, 'youssef', 'Ho ricevuto una lettera dall\'Agenzia delle Entrate e non la capisco: la porto con me.'],
    [$vatOpening, $giulia, 'chiara', null],
    [$vatOpening, $luca, 'sara', 'Apro come libera professionista, forfettario se conviene.'],
    [$firstConsultation, $manager, 'davide', null],
];

foreach ($upcoming as $index => [$service, $operator, $who, $note]) {
    $slots = $freeSlots($service, $operator);
    $slot = $slots[$index * 2] ?? $slots[0] ?? null;

    if ($slot === null) {
        echo "Nessuno slot libero per «{$service->name}»: salto.\n";

        continue;
    }

    $create->handle($service, $operator, $slot, [
        ...$customers[$who],
        'notes' => $note,
        'timezone' => $tenant->timezone,
        'locale' => 'it',
        'privacy_accepted' => true,
    ], $answersFor($service));
}

// Quattro posti sulla stessa lezione: il servizio di gruppo va giudicato
// pieno, non vuoto.
$courseSlots = $freeSlots($course, null);
$courseSlot = $courseSlots[0] ?? null;

if ($courseSlot === null) {
    echo "Nessuno slot libero per il corso di gruppo: salto le iscrizioni.\n";
} else {
    foreach (['anna', 'paolo', 'marta', 'youssef'] as $who) {
        $create->handle($course, null, $courseSlot, [
            ...$customers[$who],
            'notes' => null,
            'timezone' => $tenant->timezone,
            'locale' => 'it',
            'privacy_accepted' => true,
        ], $answersFor($course));
    }
}

// Una spostata e una annullata, per i badge e i filtri dell'agenda. Entrambe
// fuori dal servizio di gruppo: un posto annullato lì cambierebbe il conto dei
// posti liberi, che è un'altra cosa da guardare intatta.
$individual = fn () => Booking::where('tenant_id', $tenant->id)->whereNull('service_session_id')->upcoming();

$moved = $individual()->orderBy('starts_at')->first();

if ($moved !== null) {
    $moved->forceFill([
        'original_starts_at' => $moved->starts_at->subDays(2),
        'rescheduled_at' => CarbonImmutable::now()->subHours(6),
        'ics_sequence' => 1,
    ])->saveQuietly();

    $log = Activity::log('booking.rescheduled', $moved, ['service' => $moved->service->name], $tenant, null, $manager);
    $log->forceFill(['created_at' => CarbonImmutable::now()->subHours(6)])->saveQuietly();
}

// Annullata fra le confermate: le due richieste in attesa servono intere al
// manager, che su quelle prova «Conferma».
$dropped = $individual()->where('status', BookingStatus::Confirmed)->orderByDesc('starts_at')->first();

if ($dropped !== null && $dropped->isNot($moved)) {
    $dropped->forceFill([
        'status' => BookingStatus::Cancelled,
        'cancelled_at' => CarbonImmutable::now()->subHours(2),
        'cancelled_by_id' => $manager->id,
        'cancellation_reason' => 'Chiuso per lutto in famiglia: richiamiamo noi per riprogrammare.',
    ])->saveQuietly();

    $log = Activity::log('booking.cancelled', $dropped, ['service' => $dropped->service->name], $tenant, null, $manager);
    $log->forceFill(['created_at' => CarbonImmutable::now()->subHours(2)])->saveQuietly();
}

// ---------------------------------------------------------------------------
// 7. Lista d'attesa
// ---------------------------------------------------------------------------

$waiting = [
    ['who' => 'sara', 'from' => 2, 'to' => 20, 'notified' => false],
    ['who' => 'davide', 'from' => 5, 'to' => 12, 'notified' => false],
    ['who' => 'chiara', 'from' => 1, 'to' => 45, 'notified' => true],
];

foreach ($waiting as $entry) {
    WaitingListEntry::create([
        'uid' => WaitingListEntry::newUid(),
        'tenant_id' => $tenant->id,
        'service_id' => $taxConsultation->id,
        'customer_name' => $customers[$entry['who']]['name'],
        'customer_email' => $customers[$entry['who']]['email'],
        'customer_phone' => $customers[$entry['who']]['phone'],
        'customer_timezone' => $tenant->timezone,
        'customer_locale' => 'it',
        'wanted_from' => $today->addDays($entry['from'])->toDateString(),
        'wanted_to' => $today->addDays($entry['to'])->toDateString(),
        'privacy_accepted_at' => CarbonImmutable::now()->subDays(2),
        'privacy_accepted_url' => null,
        'notified_at' => $entry['notified'] ? CarbonImmutable::now()->subDay() : null,
    ]);
}

// ---------------------------------------------------------------------------
// 8. Chiusura, blocchi ed eccezioni
// ---------------------------------------------------------------------------
// Tutti oltre le tre settimane: più vicino andrebbero a sbattere contro le
// prenotazioni future appena create, e sarebbero dati che l'interfaccia si
// rifiuterebbe di produrre.

$closureStart = $today->addDays(35);

PortalClosure::create([
    'tenant_id' => $tenant->id,
    'starts_on' => $closureStart->toDateString(),
    'ends_on' => $closureStart->addDays(6)->toDateString(),
    'reason' => 'Studio chiuso per aggiornamento professionale.',
]);

$blockDay = $today->addDays(24);

while (! $blockDay->isWeekday()) {
    $blockDay = $blockDay->addDay();
}

TimeBlock::create([
    'user_id' => $giulia->id,
    'starts_at' => $blockDay->setTime(15, 0)->setTimezone('UTC'),
    'ends_at' => $blockDay->setTime(16, 30)->setTimezone('UTC'),
    'reason' => 'Riunione di studio',
]);

// Una settimana dopo il blocco: sullo stesso giorno una giornata di ferie lo
// renderebbe invisibile, e non si giudicherebbe né l'uno né l'altra.
$holiday = $today->addDays(31);

while (! $holiday->isWeekday()) {
    $holiday = $holiday->addDay();
}

AvailabilityException::create([
    'user_id' => $giulia->id,
    'date' => $holiday->toDateString(),
    'is_unavailable' => true,
    'starts_at' => null,
    'ends_at' => null,
    'reason' => 'Ferie',
]);

$shortDay = $holiday->addDay();

while (! $shortDay->isWeekday()) {
    $shortDay = $shortDay->addDay();
}

AvailabilityException::create([
    'user_id' => $luca->id,
    'date' => $shortDay->toDateString(),
    'is_unavailable' => false,
    'starts_at' => '10:00:00',
    'ends_at' => '12:00:00',
    'reason' => 'Solo mattina, poi udienza',
]);

// ---------------------------------------------------------------------------
// 8b. Un giorno di corso quasi pieno
// ---------------------------------------------------------------------------
// Il segnale «posti in esaurimento» si giudica solo se qualcosa sta davvero
// esaurendo. Il 13/10 Giulia è fuori e Luca c'è solo dalle 10 alle 12: quel
// giorno il corso offre un'ora sola, e la si riempie fino agli ultimi due
// posti. Cosi il giorno sul calendario e la fascia oraria si accendono
// entrambi, mentre gli altri giorni restano vuoti come sono.

AvailabilityException::create([
    'user_id' => $giulia->id,
    'date' => $shortDay->toDateString(),
    'is_unavailable' => true,
    'starts_at' => null,
    'ends_at' => null,
    'reason' => 'Corso di aggiornamento',
]);

$course->refresh();

$shortDaySlots = (new GroupSlotFinder($course))->slotsForDay($shortDay);

if ($shortDaySlots === []) {
    echo 'Il 13/10 il corso non offre orari: salto il giorno quasi pieno.
';
} else {
    foreach (['davide', 'elena', 'chiara', 'sara', 'anna', 'paolo'] as $who) {
        $create->handle($course, null, $shortDaySlots[0], [
            ...$customers[$who],
            'notes' => null,
            'timezone' => $tenant->timezone,
            'locale' => 'it',
            'privacy_accepted' => true,
        ], $answersFor($course));
    }
}

// ---------------------------------------------------------------------------
// 9. Note dell'anagrafica
// ---------------------------------------------------------------------------
// Le scrive il portale, non il cliente: servono a vedere che restino private.

Customer::forTenant($tenant)->where('email', 'anna.ferrari@example.com')->update([
    'notes' => 'Cliente dal 2021. Preferisce il pomeriggio. Fattura intestata alla società.',
]);

Customer::forTenant($tenant)->where('email', 'chiara.lombardi@example.com')->update([
    'notes' => 'Ha disdetto due volte all\'ultimo momento: confermare per telefono il giorno prima.',
]);

// ---------------------------------------------------------------------------
// Riepilogo
// ---------------------------------------------------------------------------

$counts = Booking::where('tenant_id', $tenant->id)
    ->selectRaw('status, count(*) as total')
    ->groupBy('status')
    ->pluck('total', 'status');

echo "\nPortale: {$tenant->name}  →  /p/{$tenant->slug}\n\n";
echo 'Servizi:          '.$tenant->services()->count()." (1 nascosto, 1 di gruppo, 1 con un solo operatore, 1 a conferma manuale)\n";
echo 'Prenotazioni:     '.$counts->sum()."\n";

foreach ($counts as $status => $total) {
    echo "    {$status}: {$total}\n";
}

echo 'Clienti:          '.Customer::forTenant($tenant)->count()."\n";
echo "Lista d'attesa:   ".WaitingListEntry::where('tenant_id', $tenant->id)->count()."\n";
echo 'Registro azioni:  '.ActivityLog::where('tenant_id', $tenant->id)->count()."\n";
echo 'Job falliti:      '.DB::table('failed_jobs')->count()."\n\n";
echo 'Chiusura portale: '.$closureStart->format('d/m/Y').' → '.$closureStart->addDays(6)->format('d/m/Y')."\n";
echo 'Blocco di Giulia: '.$blockDay->format('d/m/Y')." 15:00-16:30\n";
echo 'Ferie di Giulia:  '.$holiday->format('d/m/Y')."\n";
echo 'Mezza giornata di Luca: '.$shortDay->format('d/m/Y')." 10:00-12:00\n";
