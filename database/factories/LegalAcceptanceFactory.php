<?php

namespace Database\Factories;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalAcceptance>
 */
class LegalAcceptanceFactory extends Factory
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
            'legal_document_id' => LegalDocument::factory(),
            'user_id' => null,
            'accepted_by_name' => fake()->name(),
            'ip_address' => fake()->ipv4(),
            'accepted_at' => now(),
        ];
    }
}
