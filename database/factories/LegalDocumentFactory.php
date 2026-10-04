<?php

namespace Database\Factories;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => LegalDocumentType::DataProcessingAgreement,
            'version' => fake()->unique()->numerify('v#.#'),
            'file_path' => 'legal/platform/'.fake()->uuid().'.pdf',
            'published_at' => now(),
        ];
    }

    /**
     * Indicate the kind of document.
     */
    public function ofType(LegalDocumentType $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }
}
