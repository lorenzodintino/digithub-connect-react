@props(['tenant'])

@unless (request()->boolean('embed'))
    <header class="border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('portal.show', $tenant) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white"
                    style="background-color: var(--portal-brand)"
                >{{ $tenant->initials() }}</span>

                <span class="truncate text-base font-semibold">{{ $tenant->name }}</span>
            </a>

            <x-locale-switcher align="end" />
        </div>
    </header>
@endunless
