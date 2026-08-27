<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffForm extends Form
{
    public ?User $member = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $job_title = '';

    public string $bio = '';

    public string $timezone = 'Europe/Rome';

    public string $locale = 'it';

    public bool $is_bookable = true;

    public bool $is_active = true;

    /**
     * The service ids this staff member is assigned to.
     *
     * @var array<int, int>
     */
    public array $service_ids = [];

    /**
     * Validation rules for a staff account.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->member?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'is_bookable' => ['boolean'],
            'is_active' => ['boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ];
    }

    /**
     * Fill the form from an existing staff member.
     */
    public function setMember(User $member): void
    {
        $this->member = $member;

        $this->fill($member->only(['name', 'email', 'timezone', 'locale', 'is_bookable', 'is_active']));

        $this->phone = $member->phone ?? '';
        $this->job_title = $member->job_title ?? '';
        $this->bio = $member->bio ?? '';
        $this->service_ids = $member->services()->pluck('services.id')->all();
    }

    /**
     * Create a new operator inside the given portal.
     *
     * @return array{member: User, password: string}
     */
    public function store(Tenant $tenant): array
    {
        $this->validate();

        $password = Str::password(14, symbols: false);

        $member = User::create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Default,
            'password' => $password,
            'email_verified_at' => now(),
            ...$this->memberAttributes(),
        ]);

        $member->services()->sync($this->serviceIdsForTenant($tenant));

        $this->reset();

        return ['member' => $member, 'password' => $password];
    }

    /**
     * Persist changes to the loaded staff member.
     */
    public function update(): User
    {
        $this->validate();

        $this->member->update($this->memberAttributes());
        $this->member->services()->sync($this->serviceIdsForTenant($this->member->tenant));

        return $this->member;
    }

    /**
     * The user attributes extracted from the form state.
     *
     * @return array<string, mixed>
     */
    protected function memberAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'job_title' => $this->job_title ?: null,
            'bio' => $this->bio ?: null,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'is_bookable' => $this->is_bookable,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * Keep only the selected services that actually belong to the portal.
     *
     * @return array<int, int>
     */
    protected function serviceIdsForTenant(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        return $tenant->services()->whereKey($this->service_ids)->pluck('id')->all();
    }
}
