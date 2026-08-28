@props([
    'tenant',
    'size' => 'md',
])

@php
    $classes = match ($size) {
        'lg' => 'size-16 rounded-2xl text-xl',
        default => 'size-9 rounded-xl text-sm',
    };
@endphp

@if ($tenant->logoUrl())
    <img
        src="{{ $tenant->logoUrl() }}"
        alt="{{ $tenant->name }}"
        {{ $attributes->class(['shrink-0 object-cover', $classes]) }}
    >
@else
    {{-- The brand variables only exist on the public pages, so read the portal
         directly: this component also renders inside the back-office. --}}
    <span
        {{ $attributes->class(['flex shrink-0 items-center justify-center font-semibold', $classes]) }}
        style="background-color: {{ $tenant->brand_color }}; color: {{ $tenant->brandForeground() }}"
    >{{ $tenant->initials() }}</span>
@endif
