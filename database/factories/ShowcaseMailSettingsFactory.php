<?php

namespace Database\Factories;

use App\Models\ShowcaseMailSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowcaseMailSettings>
 */
class ShowcaseMailSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient' => 'demo@atrio.test',
            'mail_host' => null,
            'mail_port' => null,
            'mail_username' => null,
            'mail_password' => null,
            'mail_encryption' => null,
            'mail_from_address' => null,
            'mail_tested_at' => null,
            'mail_error' => null,
            'mail_failed_at' => null,
            'mail_verified_at' => null,
        ];
    }

    /**
     * With its own SMTP server saved, not proved yet.
     */
    public function withServer(): static
    {
        return $this->state(fn () => [
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'vetrina@example.com',
            'mail_password' => 'secret-password',
            'mail_encryption' => 'tls',
            'mail_from_address' => null,
        ]);
    }

    /**
     * With its own SMTP server saved and a test that went through.
     */
    public function verified(): static
    {
        return $this->withServer()->state(fn () => [
            'mail_tested_at' => now(),
            'mail_verified_at' => now(),
        ]);
    }
}
