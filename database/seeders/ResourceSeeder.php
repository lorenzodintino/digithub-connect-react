<?php

namespace Database\Seeders;

use App\Enums\ResourceType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'studio-rossi')->first();

        if ($tenant === null) {
            return;
        }

        foreach (['Sala consulenze 1', 'Sala consulenze 2'] as $name) {
            $tenant->resources()->firstOrCreate(['name' => $name], [
                'type' => ResourceType::Room,
                'description' => null,
                'notes' => null,
                'preparation_minutes' => 0,
                'cleanup_minutes' => 10,
                'is_active' => true,
            ]);
        }
    }
}
