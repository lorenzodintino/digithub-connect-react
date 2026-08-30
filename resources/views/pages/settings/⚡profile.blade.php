<?php

use App\Concerns\ProfileValidationRules;
use App\Support\Timezones;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public ?TemporaryUploadedFile $photo = null;

    public string $name = '';
    public string $email = '';
    public string $timezone = '';
    public string $locale = '';
    public string $phone = '';
    public string $job_title = '';
    public string $bio = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone;
        $this->locale = $user->locale;
        $this->phone = $user->phone ?? '';
        $this->job_title = $user->job_title ?? '';
        $this->bio = $user->bio ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = $this->profileRules($user->id) + $this->preferenceRules();

        if ($this->hasPublicProfile) {
            $rules += $this->publicProfileRules();
        }

        $validated = $this->validate($rules);

        $user->fill(array_map(fn (?string $value) => $value === '' ? null : $value, $validated));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // The session copy wins over the account in SetLocale, so keep the two aligned.
        Session::put('locale', $user->locale);
        app()->setLocale($user->locale);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Store the picked photo straight away, like the portal logo does.
     */
    public function updatedPhoto(): void
    {
        $user = Auth::user();

        $this->validateOnly('photo', [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], attributes: ['photo' => __('photo')]);

        $path = $this->photo->store('avatars/'.$user->getKey(), 'public');

        $this->forgetPhotoFile($user->avatar_path);

        $user->forceFill(['avatar_path' => $path])->save();

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Photo updated.'));
    }

    /**
     * Drop the photo and go back to the coloured initials.
     */
    public function removePhoto(): void
    {
        $user = Auth::user();

        $this->forgetPhotoFile($user->avatar_path);

        $user->forceFill(['avatar_path' => null])->save();

        Flux::toast(variant: 'success', text: __('Photo removed.'));
    }

    /**
     * Delete a stored photo, ignoring one that was never there.
     */
    private function forgetPhotoFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    /**
     * Determine whether the user has a profile shown on a public booking page.
     */
    #[Computed]
    public function hasPublicProfile(): bool
    {
        return Auth::user()->belongsToTenant();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:field>
                <flux:label>{{ __('Photo') }}</flux:label>
                <flux:description>{{ __('Shown to your customers when they pick who to meet. JPG, PNG or WebP, up to 1 MB.') }}</flux:description>

                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <x-user-avatar :user="auth()->user()" size="xl" wire:key="avatar-{{ auth()->user()->avatar_path }}" />

                    <div class="flex items-center gap-2">
                        <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="max-w-xs" />

                        @if (auth()->user()->avatar_path)
                            <flux:button size="sm" variant="subtle" icon="trash" wire:click="removePhoto" type="button">
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <flux:error name="photo" />
            </flux:field>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:separator :text="__('Preferences')" />

            <flux:select wire:model="timezone" :label="__('Timezone')" :description="__('Times across the app are shown in this timezone.')">
                @foreach (Timezones::options() as $timezone)
                    <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="locale" :label="__('Language')" :description="__('Saved on your account, so it follows you on every device.')">
                <flux:select.option value="it">Italiano</flux:select.option>
                <flux:select.option value="en">English</flux:select.option>
            </flux:select>

            @if ($this->hasPublicProfile)
                <flux:separator :text="__('Public profile')" />

                <flux:text size="sm">
                    {{ __('How customers see you on the booking page.') }}
                </flux:text>

                @unless (auth()->user()->is_bookable)
                    <flux:callout icon="information-circle" variant="secondary">
                        {{ __('You are not bookable at the moment, so these details are not shown publicly. Your manager can change this.') }}
                    </flux:callout>
                @endunless

                <flux:input wire:model="phone" :label="__('Phone')" type="tel" autocomplete="tel" />

                <flux:input
                    wire:model="job_title"
                    :label="__('Role shown to customers')"
                    :placeholder="__('e.g. Accountant, Consultant')"
                />

                <flux:textarea wire:model="bio" :label="__('Short bio')" rows="3" />
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
