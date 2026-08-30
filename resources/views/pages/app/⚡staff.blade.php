<?php

use App\Support\Activity;
use App\Livewire\Forms\StaffForm;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Timezones;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Staff')] class extends Component {
    use WithFileUploads;

    public StaffForm $form;

    public ?TemporaryUploadedFile $photo = null;

    public ?int $deletingId = null;

    public ?string $generatedPassword = null;

    public ?string $generatedEmail = null;

    /**
     * The portal owning the staff shown on this page.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * The operators of the current portal.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return $this->tenant->operators()
            ->with('services')
            ->withCount('availabilityRules')
            ->orderBy('name')
            ->get();
    }

    /**
     * The services that can be assigned to a staff member.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->tenant->services()->orderBy('name')->get();
    }

    /**
     * Store the photo of the member being edited.
     *
     * Managers upload for their staff because half of them never log in: the
     * person who set them up is the one with the photo to hand.
     */
    public function updatedPhoto(): void
    {
        $member = $this->form->member;

        abort_if($member === null, 422);

        $this->authorize('update', $member);

        $this->validateOnly('photo', [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], attributes: ['photo' => __('photo')]);

        $path = $this->photo->store('avatars/'.$member->getKey(), 'public');

        $this->forgetPhotoFile($member->avatar_path);

        $member->forceFill(['avatar_path' => $path])->save();

        $this->photo = null;
        $this->form->member = $member->fresh();

        unset($this->operators);

        Flux::toast(variant: 'success', text: __('Photo updated.'));
    }

    /**
     * Drop the photo of the member being edited.
     */
    public function removePhoto(): void
    {
        $member = $this->form->member;

        abort_if($member === null, 422);

        $this->authorize('update', $member);

        $this->forgetPhotoFile($member->avatar_path);

        $member->forceFill(['avatar_path' => null])->save();

        $this->form->member = $member->fresh();

        unset($this->operators);

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
     * Open the modal with an empty staff form.
     */
    public function createMember(): void
    {
        $this->authorize('create', User::class);

        $this->form->reset();
        $this->resetValidation();
        $this->form->timezone = $this->tenant->timezone;
        $this->form->locale = $this->tenant->locale;

        Flux::modal('staff-form')->show();
    }

    /**
     * Open the modal preloaded with the given staff member.
     */
    public function editMember(User $member): void
    {
        $this->authorize('update', $member);

        $this->resetValidation();
        $this->form->setMember($member);

        Flux::modal('staff-form')->show();
    }

    /**
     * Persist the staff member currently loaded in the form.
     */
    public function saveMember(): void
    {
        if ($this->form->member === null) {
            $this->authorize('create', User::class);

            ['member' => $member, 'password' => $password] = $this->form->store($this->tenant);

            $this->generatedEmail = $member->email;
            $this->generatedPassword = $password;

            Flux::modal('staff-form')->close();
            Flux::modal('staff-credentials')->show();
        } else {
            $this->authorize('update', $this->form->member);

            $this->form->update();

            Flux::modal('staff-form')->close();
            Flux::toast(variant: 'success', text: __('Operator updated.'));
        }

        unset($this->operators);
    }

    /**
     * Toggle whether the authenticated manager can receive bookings.
     */
    public function toggleManagerBookable(): void
    {
        $manager = auth()->user();

        $manager->update(['is_bookable' => ! $manager->is_bookable]);

        Flux::toast(variant: 'success', text: $manager->is_bookable
            ? __('You are now bookable.')
            : __('You no longer receive bookings.'));
    }

    /**
     * Ask for confirmation before removing an operator.
     */
    public function confirmDelete(int $memberId): void
    {
        $this->deletingId = $memberId;

        unset($this->deletingUpcomingCount);

        Flux::modal('staff-delete')->show();
    }

    /**
     * How many upcoming bookings the operator being deleted still holds.
     */
    #[Computed]
    public function deletingUpcomingCount(): int
    {
        if ($this->deletingId === null) {
            return 0;
        }

        return User::findOrFail($this->deletingId)->upcomingBookingsCount();
    }

    /**
     * Permanently remove the selected operator.
     */
    public function deleteMember(): void
    {
        $member = User::findOrFail($this->deletingId);

        $this->authorize('delete', $member);

        // Deleting cascades to their bookings, so refuse while any is still live.
        $upcoming = $member->upcomingBookingsCount();

        if ($upcoming > 0) {
            $this->deletingId = null;

            unset($this->deletingUpcomingCount);

            Flux::modal('staff-delete')->close();
            Flux::toast(variant: 'danger', text: __('This operator still has :count upcoming bookings. Cancel or reassign them first.', ['count' => $upcoming]));

            return;
        }

        Activity::log('staff.deleted', $member, ['role' => $member->role->value]);

        $member->delete();

        $this->deletingId = null;

        unset($this->operators);

        Flux::modal('staff-delete')->close();
        Flux::toast(variant: 'success', text: __('Operator deleted.'));
    }
}; ?>

<x-slot:title>{{ __('Staff') }}</x-slot>

<div class="w-full">
    @php($manager = auth()->user())

    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Staff')" :subheading="__('The people who deliver your services and receive bookings.')">
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createMember">
                    {{ __('New operator') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$manager" />
                <div>
                    <div class="font-medium">{{ $manager->name }}</div>
                    <flux:text size="sm">{{ __('You manage this portal') }}</flux:text>
                </div>
            </div>

            <flux:switch
                :checked="$manager->is_bookable"
                wire:click="toggleManagerBookable"
                :label="__('I also take bookings')"
                align="left"
            />
        </div>

        @if ($this->operators->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="user-group" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('No operators yet') }}</flux:heading>
                <flux:text class="max-w-md">{{ __('Add the people working with you. Each one gets their own login and availability.') }}</flux:text>
                <flux:button class="mt-2" variant="primary" icon="plus" wire:click="createMember">
                    {{ __('New operator') }}
                </flux:button>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Operator') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('Weekly slots') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->operators as $operator)
                            <flux:table.row wire:key="operator-{{ $operator->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <x-user-avatar :user="$operator" size="xs" />
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $operator->name }}</div>
                                            <flux:text size="sm" class="truncate">{{ $operator->job_title ?: $operator->email }}</flux:text>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($operator->services->isEmpty())
                                        <flux:badge size="sm" color="amber" inset="top bottom">{{ __('None') }}</flux:badge>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($operator->services->take(3) as $service)
                                                <flux:badge size="sm" color="zinc" inset="top bottom">{{ $service->name }}</flux:badge>
                                            @endforeach

                                            @if ($operator->services->count() > 3)
                                                <flux:badge size="sm" color="zinc" inset="top bottom">+{{ $operator->services->count() - 3 }}</flux:badge>
                                            @endif
                                        </div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">{{ $operator->availability_rules_count }}</flux:table.cell>

                                <flux:table.cell>
                                    @if (! $operator->is_active)
                                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Disabled') }}</flux:badge>
                                    @elseif (! $operator->is_bookable)
                                        <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Not bookable') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="emerald" inset="top bottom">{{ __('Bookable') }}</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="editMember({{ $operator->id }})">
                                                {{ __('Edit') }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $operator->id }})">
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    <flux:modal name="staff-form" class="w-full max-w-xl">
        <form wire:submit="saveMember" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $this->form->member ? __('Edit operator') : __('New operator') }}
                </flux:heading>
                <flux:subheading>
                    {{ $this->form->member ? __('Update the profile and the services this person delivers.') : __('A login will be created and the password shown once.') }}
                </flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @if ($this->form->member)
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Photo') }}</flux:label>
                        <flux:description>{{ __('Shown to customers when they choose who to meet. JPG, PNG or WebP, up to 1 MB.') }}</flux:description>

                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <x-user-avatar :user="$this->form->member" size="xl" wire:key="avatar-{{ $this->form->member->avatar_path }}" />

                            <div class="flex items-center gap-2">
                                <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="max-w-xs" />

                                @if ($this->form->member->avatar_path)
                                    <flux:button size="sm" variant="subtle" icon="trash" wire:click="removePhoto" type="button">
                                        {{ __('Remove') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        <flux:error name="photo" />
                    </flux:field>
                @endif

                <flux:input wire:model="form.name" :label="__('Full name')" required autofocus />
                <flux:input wire:model="form.email" type="email" :label="__('Email')" required />
                <flux:input wire:model="form.phone" :label="__('Phone')" />
                <flux:input wire:model="form.job_title" :label="__('Role shown to customers')" />
            </div>

            <flux:textarea wire:model="form.bio" :label="__('Short bio')" rows="2" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.timezone" :label="__('Timezone')">
                    @foreach (Timezones::options() as $timezone)
                        <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.locale" :label="__('Language')">
                    <flux:select.option value="it">Italiano</flux:select.option>
                    <flux:select.option value="en">English</flux:select.option>
                </flux:select>
            </div>

            @if ($this->services->isNotEmpty())
                <flux:checkbox.group wire:model="form.service_ids" :label="__('Services delivered')">
                    @foreach ($this->services as $service)
                        <flux:checkbox :value="$service->id" :label="$service->name" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="space-y-3">
                <flux:switch wire:model="form.is_bookable" :label="__('Can receive bookings')" />
                <flux:switch wire:model="form.is_active" :label="__('Account enabled')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ $this->form->member ? __('Save changes') : __('Create operator') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="staff-credentials" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Operator created') }}</flux:heading>
                <flux:subheading>{{ __('Share these credentials. The password is shown only once.') }}</flux:subheading>
            </div>

            <flux:input readonly :label="__('Email')" :value="$generatedEmail" copyable />
            <flux:input readonly :label="__('Temporary password')" :value="$generatedPassword" copyable />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" type="button">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="staff-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this operator?') }}</flux:heading>
                <flux:subheading>{{ __('Their login, availability and service assignments will be removed.') }}</flux:subheading>
            </div>

            @if ($this->deletingUpcomingCount > 0)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    {{ __('This operator still has :count upcoming bookings. Cancel or reassign them first.', ['count' => $this->deletingUpcomingCount]) }}
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="deleteMember"
                    :disabled="$this->deletingUpcomingCount > 0"
                >{{ __('Delete operator') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
