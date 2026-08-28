<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PortalForm extends Form
{
    public ?Tenant $portal = null;

    public string $name = '';

    public string $slug = '';

    public string $timezone = 'Europe/Rome';

    public string $locale = 'it';

    public string $brand_color = '#4f46e5';

    public string $description = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $website = '';

    public string $address = '';

    public bool $is_active = true;

    public string $plan_id = '';

    public string $manager_name = '';

    public string $manager_email = '';

    /**
     * Validation rules for the portal and its manager account.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:120', 'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($this->portal?->getKey()),
            ],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'manager_name' => [Rule::requiredIf($this->portal === null), 'string', 'max:120'],
            'manager_email' => [
                Rule::requiredIf($this->portal === null), 'email', 'max:255',
                Rule::unique('users', 'email'),
            ],
        ];
    }

    /**
     * Attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'manager_name' => __('manager name'),
            'manager_email' => __('manager email'),
            'brand_color' => __('brand color'),
        ];
    }

    /**
     * Fill the form from an existing portal.
     */
    public function setPortal(Tenant $portal): void
    {
        $this->portal = $portal;

        $this->fill($portal->only([
            'name', 'slug', 'timezone', 'locale', 'brand_color',
            'contact_phone', 'address', 'is_active',
        ]));

        $this->plan_id = (string) ($portal->plan_id ?? '');

        $this->description = $portal->description ?? '';
        $this->contact_email = $portal->contact_email ?? '';
        $this->website = $portal->website ?? '';
        $manager = $portal->manager()->first();

        $this->manager_name = $manager->name ?? '';
        $this->manager_email = $manager->email ?? '';
    }

    /**
     * Create the portal together with its single manager account.
     *
     * @return array{portal: Tenant, password: string}
     */
    public function store(): array
    {
        $this->validate();

        $password = Str::password(14, symbols: false);

        $portal = DB::transaction(function () use ($password): Tenant {
            $portal = Tenant::create($this->portalAttributes());

            User::create([
                'tenant_id' => $portal->id,
                'name' => $this->manager_name,
                'email' => $this->manager_email,
                'password' => $password,
                'role' => UserRole::Manager,
                'timezone' => $portal->timezone,
                'locale' => $portal->locale,
                'is_bookable' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return $portal;
        });

        $this->reset();

        return ['portal' => $portal, 'password' => $password];
    }

    /**
     * Persist changes to an existing portal.
     */
    public function update(): Tenant
    {
        $this->validate();

        $this->portal->update($this->portalAttributes());

        return $this->portal;
    }

    /**
     * The tenant attributes extracted from the form state.
     *
     * @return array<string, mixed>
     */
    protected function portalAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'brand_color' => $this->brand_color,
            'description' => $this->description ?: null,
            'contact_email' => $this->contact_email ?: null,
            'contact_phone' => $this->contact_phone ?: null,
            'website' => $this->website ?: null,
            'address' => $this->address ?: null,
            'plan_id' => $this->plan_id === '' ? null : (int) $this->plan_id,
            'is_active' => $this->is_active,
        ];
    }
}
