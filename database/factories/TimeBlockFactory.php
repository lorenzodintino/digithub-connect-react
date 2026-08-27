<?php

namespace Database\Factories;

use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeBlock>
 */
class TimeBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(12, 0);

        return [
            'user_id' => User::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'reason' => fake()->randomElement([null, 'Riunione interna', 'Visita medica']),
        ];
    }

    /**
     * Place the block on a concrete period.
     */
    public function between(CarbonImmutable $startsAt, CarbonImmutable $endsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt->setTimezone('UTC'),
            'ends_at' => $endsAt->setTimezone('UTC'),
        ]);
    }
}
