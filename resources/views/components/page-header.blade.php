@props([
    'heading',
    'subheading' => null,
])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>

        @if ($subheading)
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
