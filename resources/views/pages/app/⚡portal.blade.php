<?php

use App\Models\Tenant;
use App\Support\Timezones;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Portal settings')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $description = '';

    public string $brand_color = '#4f46e5';

    public string $timezone = 'Europe/Rome';

    public string $locale = 'it';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $website = '';

    public string $address = '';

    public ?TemporaryUploadedFile $logo = null;

    /**
     * Load the current portal settings into the form.
     */
    public function mount(): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $this->name = $tenant->name;
        $this->description = $tenant->description ?? '';
        $this->brand_color = $tenant->brand_color;
        $this->timezone = $tenant->timezone;
        $this->locale = $tenant->locale;
        $this->contact_email = $tenant->contact_email ?? '';
        $this->contact_phone = $tenant->contact_phone ?? '';
        $this->website = $tenant->website ?? '';
        $this->address = $tenant->address ?? '';
    }

    /**
     * The portal managed by the authenticated user.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * The iframe snippet customers can paste into their website.
     */
    #[Computed]
    public function embedSnippet(): string
    {
        $url = $this->tenant->publicUrl().'?embed=1';

        return '<iframe src="'.$url.'" width="100%" height="760" frameborder="0" style="border:0;border-radius:12px" loading="lazy"></iframe>';
    }

    /**
     * Store the picked logo as soon as it is chosen.
     *
     * Saving on pick keeps the preview honest: what the page shows is what the
     * portal is really serving.
     */
    public function updatedLogo(): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $this->validateOnly('logo', [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        $path = $this->logo->store('logos/'.$tenant->getKey(), 'public');

        $this->forgetLogoFile($tenant->logo_path);

        $tenant->update(['logo_path' => $path]);

        $this->logo = null;

        unset($this->tenant);

        Flux::toast(variant: 'success', text: __('Logo updated.'));
    }

    /**
     * Drop the logo and fall back to the coloured initials.
     */
    public function removeLogo(): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $this->forgetLogoFile($tenant->logo_path);

        $tenant->update(['logo_path' => null]);

        unset($this->tenant);

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    /**
     * Delete a stored logo file, ignoring one that is already gone.
     */
    private function forgetLogoFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Persist the portal settings.
     */
    public function save(): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update(array_map(fn ($value) => $value === '' ? null : $value, $validated));

        Flux::toast(variant: 'success', text: __('Portal settings saved.'));
    }
}; ?>

<x-slot:title>{{ __('Portal settings') }}</x-slot>

<div class="w-full">
    <div class="flex w-full max-w-4xl flex-col gap-6">
        <x-page-header :heading="__('Portal settings')" :subheading="__('How your portal looks and how customers reach you.')" />

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Share your booking page') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Send the link, or embed the page inside your own website.') }}</flux:text>

            <div class="mt-4 space-y-4">
                <flux:input readonly :label="__('Public link')" :value="$this->tenant->publicUrl()" copyable />
                <flux:textarea readonly :label="__('Embed code')" rows="3" class="font-mono text-xs">{{ $this->embedSnippet }}</flux:textarea>
            </div>
        </div>

        <livewire:smtp-settings :tenant="$this->tenant" />

        <form wire:submit="save" class="space-y-6 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Business details') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Business name')" required />
                <flux:input :label="__('Public slug')" :value="$this->tenant->slug" readonly :description="__('Only the platform administrator can change it.')" />
                <flux:input
                    wire:model="contact_email"
                    type="email"
                    :label="__('Contact email')"
                    :description="__('Used as the reply address when none is set under Email delivery.')"
                />

                <flux:input wire:model="contact_phone" :label="__('Contact phone')" />
                <flux:input wire:model="website" type="url" :label="__('Website')" />
                <flux:input wire:model="address" :label="__('Address')" />

                <flux:select wire:model="timezone" :label="__('Timezone')">
                    @foreach (Timezones::options() as $timezone)
                        <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="locale" :label="__('Public page language')">
                    <flux:select.option value="it">Italiano</flux:select.option>
                    <flux:select.option value="en">English</flux:select.option>
                </flux:select>

                <flux:input wire:model.live="brand_color" type="color" :label="__('Brand color')" />
            </div>

            <flux:textarea wire:model="description" :label="__('Short description')" rows="3" />

            <flux:field>
                <flux:label>{{ __('Logo') }}</flux:label>
                <flux:description>{{ __('Shown on your booking page. JPG, PNG or WebP, up to 1 MB.') }}</flux:description>

                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <x-portal-logo :tenant="$this->tenant" size="lg" wire:key="logo-{{ $this->tenant->logo_path }}" />

                    <div class="flex items-center gap-2">
                        <flux:input
                            type="file"
                            wire:model="logo"
                            accept="image/jpeg,image/png,image/webp"
                            class="max-w-xs"
                        />

                        @if ($this->tenant->logo_path)
                            <flux:button size="sm" variant="subtle" icon="trash" wire:click="removeLogo" type="button">
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <flux:error name="logo" />
            </flux:field>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </div>
</div>
