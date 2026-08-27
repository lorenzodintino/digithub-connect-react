@props([
    'label',
    'value',
    'icon' => null,
    'hint' => null,
])

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900') }}>
    <div class="flex items-start justify-between gap-3">
        <flux:text size="sm" class="font-medium">{{ $label }}</flux:text>

        @if ($icon)
            <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon :name="$icon" variant="micro" />
            </span>
        @endif
    </div>

    <div class="mt-3 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">
        {{ $value }}
    </div>

    @if ($hint)
        <flux:text size="sm" class="mt-1">{{ $hint }}</flux:text>
    @endif
</div>
