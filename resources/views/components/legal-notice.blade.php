@php($user = auth()->user())
@php($pending = $user?->isManager() && $user->tenant ? $user->tenant->pendingLegalDocuments() : collect())

@if ($pending->isNotEmpty())
    {{-- A notice, not a wall: the portal keeps working while its manager
         reads what changed. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
        <span class="flex items-center gap-2">
            <flux:icon name="document-text" variant="micro" />

            {{ trans_choice('A platform document is waiting for your acceptance: :documents.|:count platform documents are waiting for your acceptance: :documents.', $pending->count(), ['documents' => $pending->map(fn ($document) => $document->type->label())->join(', ')]) }}
        </span>

        <a href="{{ route('app.portal') }}#platform-documents" class="font-semibold underline" wire:navigate>
            {{ __('Read and accept') }}
        </a>
    </div>
@endif
