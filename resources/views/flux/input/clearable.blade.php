@blaze(fold: false, memo: false)
{{-- Translate the accessible label at render time for the current locale. --}}

@props([
    'iconVariant' => 'mini',
    'size' => null,
])

@php
$attributes = $attributes->merge([
    'variant' => 'subtle',
    'class' => '-me-1 [[data-flux-input]:has(input:placeholder-shown)_&]:hidden [[data-flux-input]:has(input[disabled])_&]:hidden',
    'square' => true,
    'size' => null,
]);
@endphp

<flux:button
    :$attributes
    :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
    x-data="fluxInputClearable"
    x-on:click="clear()"
    tabindex="-1"
    aria-label="{{ __('Clear input') }}"
    data-flux-clear-button
>
    <flux:icon.x-mark :variant="$iconVariant" />
</flux:button>
