<?php

use App\Support\Activity;
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

    public string $privacy_policy_url = '';

    public string $terms_url = '';

    public string $data_controller = '';

    public string $data_retention_days = '';

    public ?TemporaryUploadedFile $logo = null;

    public ?TemporaryUploadedFile $privacyPolicyFile = null;

    public ?TemporaryUploadedFile $termsFile = null;

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
        $this->privacy_policy_url = $tenant->privacy_policy_url ?? '';
        $this->terms_url = $tenant->terms_url ?? '';
        $this->data_controller = $tenant->data_controller ?? '';
        $this->data_retention_days = (string) ($tenant->data_retention_days ?? '');
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
     * Store the privacy policy PDF as soon as it is chosen.
     */
    public function updatedPrivacyPolicyFile(): void
    {
        $this->storeDocument('privacyPolicyFile', 'privacy_policy_path');
    }

    /**
     * Store the terms PDF as soon as it is chosen.
     */
    public function updatedTermsFile(): void
    {
        $this->storeDocument('termsFile', 'terms_path');
    }

    /**
     * Drop the uploaded privacy policy, falling back to the link if there is one.
     */
    public function removePrivacyPolicyFile(): void
    {
        $this->forgetDocument('privacy_policy_path');
    }

    /**
     * Drop the uploaded terms, falling back to the link if there is one.
     */
    public function removeTermsFile(): void
    {
        $this->forgetDocument('terms_path');
    }

    /**
     * Validate, store and attach a legal document to the portal.
     *
     * PDF only: it is the format that looks the same to everybody, and it is
     * not executable in the browser the way an HTML or SVG upload would be.
     */
    private function storeDocument(string $property, string $column): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $this->validateOnly($property, [
            $property => ['required', 'file', 'mimes:pdf', 'max:4096'],
        ], attributes: [$property => __('document')]);

        $path = $this->{$property}->store('legal/'.$tenant->getKey(), 'public');

        $this->forgetDocumentFile($tenant->{$column});

        $tenant->update([$column => $path]);

        $this->{$property} = null;

        unset($this->tenant);

        Flux::toast(variant: 'success', text: __('Document uploaded.'));
    }

    /**
     * Remove an uploaded legal document.
     */
    private function forgetDocument(string $column): void
    {
        $tenant = $this->tenant;

        $this->authorize('update', $tenant);

        $this->forgetDocumentFile($tenant->{$column});

        $tenant->update([$column => null]);

        unset($this->tenant);

        Flux::toast(variant: 'success', text: __('Document removed.'));
    }

    /**
     * Delete a stored document, ignoring one that was never there.
     */
    private function forgetDocumentFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
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
            'privacy_policy_url' => ['nullable', 'url', 'max:255'],
            'terms_url' => ['nullable', 'url', 'max:255'],
            'data_controller' => ['nullable', 'string', 'max:255'],
            'data_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
        ], attributes: [
            'privacy_policy_url' => __('privacy policy link'),
            'terms_url' => __('terms link'),
            'data_controller' => __('data controller'),
            'data_retention_days' => __('retention'),
        ]);

        $tenant->update(array_map(fn ($value) => $value === '' ? null : $value, $validated));

        Activity::log('portal.updated', $tenant, ['fields' => array_keys($validated)]);

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
            <div>
                <flux:heading size="lg">{{ __('Privacy and terms') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Your booking page asks every customer to accept these before they book. The documents are yours: publish them on your website and link them here.') }}
                </flux:text>
            </div>

            @unless ($this->tenant->hasLegalDocuments())
                <flux:callout icon="exclamation-triangle" variant="warning">
                    {{ __('No privacy policy is linked yet. Customers are still asked for consent, but they have nothing to read first.') }}
                </flux:callout>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="privacy_policy_url"
                    type="url"
                    :label="__('Privacy policy link')"
                    :description="__('Leave empty if you upload the document below.')"
                    placeholder="https://…"
                />

                <flux:input
                    wire:model="terms_url"
                    type="url"
                    :label="__('Terms of service link')"
                    :description="__('Leave empty if you upload the document below.')"
                    placeholder="https://…"
                />

                <flux:input
                    wire:model="data_controller"
                    :label="__('Data controller')"
                    :description="__('Who answers for these data: company name, or the professional in charge.')"
                />

                <flux:input
                    wire:model="data_retention_days"
                    type="number"
                    min="30"
                    max="3650"
                    :label="__('Keep customer data for (days)')"
                    :description="__('Leave empty to keep it indefinitely. Past this many days, name, email, phone and notes are erased from old bookings; the appointment stays for your records.')"
                />
            </div>

            <flux:separator :text="__('Or upload the documents')" />

            <flux:text>{{ __('No website to publish them on? Upload each document as a PDF and we will link it for you. An uploaded file wins over the link above.') }}</flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['label' => __('Privacy policy (PDF)'), 'model' => 'privacyPolicyFile', 'link' => $this->tenant->privacy_policy_path ? $this->tenant->privacyPolicyLink() : null, 'remove' => 'removePrivacyPolicyFile'],
                    ['label' => __('Terms of service (PDF)'), 'model' => 'termsFile', 'link' => $this->tenant->terms_path ? $this->tenant->termsLink() : null, 'remove' => 'removeTermsFile'],
                ] as $document)
                    <flux:field wire:key="doc-{{ $document['model'] }}-{{ $document['link'] }}">
                        <flux:label>{{ $document['label'] }}</flux:label>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:input type="file" wire:model="{{ $document['model'] }}" accept="application/pdf" class="max-w-xs" />

                            @if ($document['link'])
                                <flux:button size="sm" variant="subtle" icon="arrow-top-right-on-square" :href="$document['link']" target="_blank">
                                    {{ __('Open') }}
                                </flux:button>

                                <flux:button size="sm" variant="subtle" icon="trash" wire:click="{{ $document['remove'] }}" type="button">
                                    {{ __('Remove') }}
                                </flux:button>
                            @endif
                        </div>

                        <flux:error name="{{ $document['model'] }}" />
                    </flux:field>
                @endforeach
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>

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
