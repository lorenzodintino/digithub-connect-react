<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Default,
            'timezone' => 'Europe/Rome',
            'locale' => config('app.locale'),
            'phone' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'bio' => null,
            'avatar_path' => null,
            'is_bookable' => true,
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user is a platform administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => UserRole::Admin,
            'is_bookable' => false,
        ]);
    }

    /**
     * Indicate that the user manages the given portal.
     */
    public function manager(Tenant|int|null $tenant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->getKey() : ($tenant ?? Tenant::factory()),
            'role' => UserRole::Manager,
            'is_bookable' => false,
        ]);
    }

    /**
     * Indicate that the user is an operator of the given portal.
     */
    public function operator(Tenant|int|null $tenant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->getKey() : ($tenant ?? Tenant::factory()),
            'role' => UserRole::Default,
            'is_bookable' => true,
        ]);
    }

    /**
     * Indicate that the user cannot receive bookings.
     */
    public function notBookable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bookable' => false,
        ]);
    }

    /**
     * Indicate that the user account is disabled.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
