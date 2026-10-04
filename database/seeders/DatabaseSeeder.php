<?php

namespace Database\Seeders;

use App\Actions\Bookings\CreateBooking;
use App\Enums\LocationType;
use App\Models\AvailabilityRule;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@digithub.test',
        ]);

        $plans = $this->seedPlans();

        $this->seedDemoPortal($plans['Studio']);

        $this->call(ResourceSeeder::class);
    }

    /**
     * The three plans the platform starts from.
     *
     * Nothing enforces the limits yet: they are here to be looked at and
     * argued about before any check reads them.
     *
     * @return array<string, Plan>
     */
    private function seedPlans(): array
    {
        $plans = collect([
            [
                'name' => 'Base',
                'description' => 'Per chi inizia: un servizio alla volta e poche prenotazioni.',
                'price_cents' => 1900,
                'max_services' => 3,
                'max_operators' => 1,
                'max_bookings_per_month' => 100,
                'allows_custom_smtp' => false,
                'position' => 1,
            ],
            [
                'name' => 'Pro',
                'description' => 'Per uno studio con più persone e un giro di prenotazioni costante.',
                'price_cents' => 3900,
                'max_services' => 15,
                'max_operators' => 5,
                'max_bookings_per_month' => 1000,
                'allows_custom_smtp' => true,
                'position' => 2,
            ],
            [
                'name' => 'Studio',
                'description' => 'Nessun limite, invio dal proprio server di posta.',
                'price_cents' => 7900,
                'max_services' => null,
                'max_operators' => null,
                'max_bookings_per_month' => null,
                'allows_custom_smtp' => true,
                'position' => 3,
            ],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['name'] => Plan::create([
                'currency' => 'EUR',
                'is_active' => true,
                ...$attributes,
            ]),
        ]);

        return $plans->all();
    }

    /**
     * Create a fully configured demo portal to explore the application.
     */
    private function seedDemoPortal(Plan $plan): void
    {
        $tenant = Tenant::create([
            'plan_id' => $plan->id,
            'name' => 'Studio Rossi',
            'slug' => 'studio-rossi',
            'timezone' => 'Europe/Rome',
            'locale' => 'it',
            'brand_color' => '#0f766e',
            'description' => 'Studio di consulenza fiscale e societaria a Milano.',
            'contact_email' => 'info@studiorossi.test',
            'contact_phone' => '+39 02 1234567',
            'website' => 'https://studiorossi.test',
            'address' => 'Via Roma 1, Milano',
        ]);

        User::factory()->manager($tenant)->create([
            'name' => 'Marco Rossi',
            'email' => 'manager@digithub.test',
            'job_title' => 'Titolare',
            'is_bookable' => true,
        ]);

        $operators = collect([
            ['name' => 'Giulia Bianchi', 'email' => 'giulia@digithub.test', 'job_title' => 'Commercialista'],
            ['name' => 'Luca Verdi', 'email' => 'luca@digithub.test', 'job_title' => 'Consulente del lavoro'],
        ])->map(fn (array $attributes) => User::factory()->operator($tenant)->create($attributes));

        $services = collect([
            [
                'name' => 'Prima consulenza',
                'duration_minutes' => 30,
                'price_cents' => null,
                'location_type' => LocationType::Video,
                'location_value' => 'Link Google Meet inviato via email',
            ],
            [
                'name' => 'Consulenza fiscale',
                'duration_minutes' => 60,
                'price_cents' => 12000,
                'location_type' => LocationType::InPerson,
                'location_value' => 'Via Roma 1, Milano',
            ],
            [
                'name' => 'Apertura partita IVA',
                'duration_minutes' => 45,
                'price_cents' => 9000,
                'location_type' => LocationType::Phone,
                'location_value' => 'Ti chiamiamo al numero indicato',
            ],
        ])->map(fn (array $attributes, int $index) => Service::create([
            'tenant_id' => $tenant->id,
            'capacity' => 1,
            'cancellation_notice_hours' => 48,
            'slug' => Service::uniqueSlug($attributes['name'], $tenant->id),
            'color' => null,
            'position' => $index,
            ...$attributes,
        ]));

        $bookable = $tenant->users()->bookable()->get();

        $services->each(fn (Service $service) => $service->operators()->sync($bookable->pluck('id')));

        $bookable->each(function (User $member): void {
            foreach ([1, 2, 3, 4, 5] as $weekday) {
                AvailabilityRule::create([
                    'user_id' => $member->id,
                    'weekday' => $weekday,
                    'starts_at' => '09:00:00',
                    'ends_at' => '13:00:00',
                ]);

                AvailabilityRule::create([
                    'user_id' => $member->id,
                    'weekday' => $weekday,
                    'starts_at' => '14:00:00',
                    'ends_at' => '18:00:00',
                ]);
            }
        });

        $this->seedDemoBookings($tenant, $services->first(), $operators->first());
    }

    /**
     * Fill the demo agenda with a few bookings so the screens are not empty.
     */
    private function seedDemoBookings(Tenant $tenant, Service $service, User $operator): void
    {
        $customers = [
            ['name' => 'Anna Ferrari', 'email' => 'anna.ferrari@example.com', 'notes' => 'Prima volta, arrivo con i documenti.'],
            ['name' => 'Paolo Greco', 'email' => 'paolo.greco@example.com', 'notes' => null],
            ['name' => 'Sara Conti', 'email' => 'sara.conti@example.com', 'notes' => 'Mi serve la fattura.'],
        ];

        // The service was just inserted, so the column defaults it relies on are
        // still missing from the in-memory model. Reload before reading them.
        $service = $service->refresh();

        $slots = collect((new SlotFinder($service, $operator))->slotsBetween(
            CarbonImmutable::now($tenant->timezone)->addDay(),
            CarbonImmutable::now($tenant->timezone)->addDays(7),
        ))->flatten();

        // Demo data should not send anything, not even to the log driver.
        Mail::fake();

        $createBooking = app(CreateBooking::class);

        foreach ($customers as $index => $customer) {
            $slot = $slots->get($index * 3);

            if ($slot === null) {
                continue;
            }

            $createBooking->handle($service, $operator, $slot, [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => '+39 333 00000'.$index,
                'notes' => $customer['notes'],
                'timezone' => $tenant->timezone,
            ]);
        }
    }
}
