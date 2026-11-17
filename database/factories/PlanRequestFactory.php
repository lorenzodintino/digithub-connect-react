<?php

namespace Database\Factories;

use App\Enums\PlanRequestStatus;
use App\Models\PlanRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanRequest>
 */
class PlanRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'from_plan' => 'Base',
            'requested_plan_id' => null,
            'requested_plan' => 'Pro',
            'reason' => null,
            'note' => null,
            'status' => PlanRequestStatus::New,
            'response' => null,
            'handled_by' => null,
            'status_changed_at' => null,
        ];
    }
}
