<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'actor_name' => fake()->name(),
            'actor_role' => 'manager',
            'action' => 'booking.created',
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'context' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
