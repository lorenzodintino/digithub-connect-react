<?php

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveOperator
{
    /**
     * Create an operator inside the portal, with a password shown only once.
     *
     * The operators of the plan are a hard limit (the manager does not count):
     * past it the account is refused, read through PlanUsage.
     *
     * @param  array<string, mixed>  $attributes  validated by StaffRequest
     * @return array{member: User, password: string}
     */
    public function create(Tenant $tenant, array $attributes): array
    {
        $usage = $tenant->planUsage();

        if (! $usage->canAddOperator()) {
            throw ValidationException::withMessages([
                'name' => trans_choice('Your plan includes up to one operator: remove it or ask for a bigger plan.|Your plan includes up to :count operators: remove one or ask for a bigger plan.', (int) $usage->operatorLimit()),
            ]);
        }

        $password = Str::password(14, symbols: false);

        $member = User::create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Default,
            'password' => $password,
            'email_verified_at' => now(),
            ...$this->profile($attributes),
        ]);

        $member->services()->sync($this->servicesOf($tenant, $attributes));

        return ['member' => $member, 'password' => $password];
    }

    /**
     * Update the profile and the services an operator delivers.
     *
     * @param  array<string, mixed>  $attributes  validated by StaffRequest
     */
    public function update(User $member, array $attributes): User
    {
        $member->update($this->profile($attributes));

        $member->services()->sync($member->tenant ? $this->servicesOf($member->tenant, $attributes) : []);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function profile(array $attributes): array
    {
        return collect($attributes)->only([
            'name', 'email', 'phone', 'job_title', 'bio', 'timezone', 'locale', 'is_bookable', 'is_active',
        ])->all();
    }

    /**
     * Keep only the picked services that really belong to the portal.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, int>
     */
    private function servicesOf(Tenant $tenant, array $attributes): array
    {
        return $tenant->services()->whereKey($attributes['service_ids'] ?? [])->pluck('id')->all();
    }
}
