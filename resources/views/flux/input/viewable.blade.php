@blaze(fold: false, memo: false)
{{-- Translate the accessible label at render time for the current locale. --}}

@props([
    'iconVariant' => 'mini',
    'size' => null,
])

@php
$attributes = $attributes->merge([
    'variant' => 'subtle',
    'class' => '-me-1',
    'square' => true,
    'size' => null,
]);
@endphp

<flux:button
    :$attributes
    :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
    x-data="fluxInputViewable"
    x-on:click="toggle()"
    x-bind:data-viewable-open="open"
    aria-label="{{ __('Toggle password visibility') }}"
>
    <flux:icon.eye-slash :variant="$iconVariant" class="hidden [[data-viewable-open]>&]:block" />
    <flux:icon.eye :variant="$iconVariant" class="block [[data-viewable-open]>&]:hidden" />
</flux:button>
