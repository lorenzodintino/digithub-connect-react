<?php

use App\Actions\Legal\PublishLegalDocument;
use App\Enums\LegalDocumentType;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Legal documents')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?string $publishingType = null;

    public string $version = '';

    public ?TemporaryUploadedFile $file = null;

    public function mount(): void
    {
        $this->authorize('viewAny', LegalDocument::class);
    }

    /**
     * Every published version, newest first, grouped by type value.
     *
     * @return Collection<string, Collection<int, LegalDocument>>
     */
    #[Computed]
    public function versions(): Collection
    {
        return LegalDocument::query()
            ->withCount('acceptances')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (LegalDocument $document): string => $document->type->value);
    }

    /**
     * The portals, each with the version of every binding document it accepted last.
     *
     * @return Collection<int, array{portal: Tenant, accepted: array<string, LegalAcceptance|null>}>
     */
    #[Computed]
    public function portals(): Collection
    {
        $binding = collect(LegalDocumentType::cases())->filter->requiresAcceptance();

        $acceptances = LegalAcceptance::query()
            ->with('document')
            ->orderByDesc('accepted_at')
            ->get()
            ->groupBy('tenant_id');

        return Tenant::query()->orderBy('name')->get()->map(fn (Tenant $portal): array => [
            'portal' => $portal,
            'accepted' => $binding->mapWithKeys(fn (LegalDocumentType $type): array => [
                $type->value => $acceptances->get($portal->getKey(), collect())
                    ->first(fn (LegalAcceptance $acceptance): bool => $acceptance->document->type === $type),
            ])->all(),
        ]);
    }

    /**
     * Open the modal to publish a new version of one document.
     */
    public function startPublishing(string $type): void
    {
        $this->authorize('create', LegalDocument::class);

        $this->publishingType = LegalDocumentType::from($type)->value;
        $this->reset('version', 'file');
        $this->resetValidation();

        Flux::modal('legal-publish')->show();
    }

    /**
     * Put the uploaded version in force.
     */
    public function publish(PublishLegalDocument $publish): void
    {
        $this->authorize('create', LegalDocument::class);

        $type = LegalDocumentType::from((string) $this->publishingType);

        $validated = $this->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('legal_documents', 'version')->where('type', $type->value)],
            'file' => ['required', 'file', 'mimes:pdf', 'max:8192'],
        ], attributes: ['version' => __('version'), 'file' => __('document')]);

        /** @var User $admin */
        $admin = auth()->user();

        $publish->handle($type, trim($validated['version']), $this->file, $admin);

        $this->reset('publishingType', 'version', 'file');
        unset($this->versions, $this->portals);

        Flux::modal('legal-publish')->close();
        Flux::toast(variant: 'success', text: __('New version published.'));
    }
}; ?>

<x-slot:title>{{ __('Legal documents') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full max-w-5xl flex-col gap-6">
        <x-page-header :heading="__('Legal documents')" :subheading="__('What the platform publishes towards the businesses it serves, and which version each portal accepted.')" />

        <div class="grid gap-4 md:grid-cols-2">
            @foreach (LegalDocumentType::cases() as $type)
                @php($versions = $this->versions->get($type->value, collect()))
                @php($current = $versions->first())

                <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="legal-{{ $type->value }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>{{ $type->label() }}</flux:heading>
                            <flux:text size="sm">
                                {{ $type->requiresAcceptance() ? __('Every portal must accept each new version.') : __('Shown to every portal, no acceptance needed.') }}
                            </flux:text>
                        </div>

                        <flux:button size="sm" icon="arrow-up-tray" wire:click="startPublishing('{{ $type->value }}')">
                            {{ __('New version') }}
                        </flux:button>
                    </div>

                    @if ($current)
                        <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($versions as $document)
                                <li class="flex items-center justify-between gap-3 py-2" wire:key="legal-version-{{ $document->id }}">
                                    <span class="flex items-center gap-2">
                                        <a href="{{ $document->url() }}" target="_blank" class="font-medium underline">{{ $document->version }}</a>

                                        @if ($document->is($current))
                                            <flux:badge size="sm" color="emerald">{{ __('In force') }}</flux:badge>
                                        @endif
                                    </span>

                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        {{ $document->published_at->setTimezone($timezone)->translatedFormat('d M Y') }}

                                        @if ($type->requiresAcceptance())
                                            · {{ trans_choice('accepted by one portal|accepted by :count portals', $document->acceptances_count) }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <flux:text size="sm" class="italic">{{ __('Not published yet.') }}</flux:text>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="p-5 pb-0">
                <flux:heading size="lg">{{ __('Acceptance by portal') }}</flux:heading>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Portal') }}</flux:table.column>
                    @foreach (LegalDocumentType::cases() as $type)
                        @if ($type->requiresAcceptance())
                            <flux:table.column>{{ $type->label() }}</flux:table.column>
                        @endif
                    @endforeach
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->portals as $row)
                        <flux:table.row wire:key="legal-portal-{{ $row['portal']->id }}">
                            <flux:table.cell class="font-medium">{{ $row['portal']->name }}</flux:table.cell>

                            @foreach ($row['accepted'] as $typeValue => $acceptance)
                                @php($current = $this->versions->get($typeValue, collect())->first())

                                <flux:table.cell>
                                    @if ($current === null)
                                        <span class="text-zinc-400">—</span>
                                    @elseif ($acceptance === null)
                                        <flux:badge size="sm" color="red">{{ __('Not accepted') }}</flux:badge>
                                    @elseif ($acceptance->legal_document_id === $current->id)
                                        <flux:badge size="sm" color="emerald">{{ $acceptance->document->version }}</flux:badge>
                                        <flux:text size="sm">{{ $acceptance->accepted_at->setTimezone($timezone)->translatedFormat('d M Y') }} · {{ $acceptance->accepted_by_name }}</flux:text>
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('Old version :version', ['version' => $acceptance->document->version]) }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="legal-publish" class="w-full max-w-md">
        <form wire:submit="publish" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Publish a new version') }}</flux:heading>
                <flux:subheading>
                    {{ $publishingType ? LegalDocumentType::from($publishingType)->label() : '' }}.
                    {{ __('It is in force from now on; earlier versions and their acceptances are kept.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="version" :label="__('Version')" :placeholder="__('e.g. 2026-10')" required />

            <flux:field>
                <flux:label>{{ __('PDF document') }}</flux:label>
                <flux:input type="file" wire:model="file" accept="application/pdf" />
                <flux:error name="file" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Publish') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
