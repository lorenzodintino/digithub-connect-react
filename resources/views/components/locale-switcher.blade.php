@props(['align' => 'start'])

@php
    $locales = [
        'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
        'en' => ['label' => 'English', 'flag' => '🇬🇧'],
    ];
    $current = $locales[app()->getLocale()] ?? $locales['it'];
@endphp

<div {{ $attributes }}>
    <flux:dropdown position="top" :align="$align" class="w-full">
        <flux:button variant="subtle" size="sm" icon-trailing="chevrons-up-down" class="w-full justify-start">
            <span class="me-1">{{ $current['flag'] }}</span> {{ $current['label'] }}
        </flux:button>

        <flux:menu>
            @foreach ($locales as $code => $locale)
                <flux:menu.item
                    :href="request()->fullUrlWithQuery(['lang' => $code])"
                    :class="app()->getLocale() === $code ? 'font-semibold' : null"
                >
                    <span class="me-2">{{ $locale['flag'] }}</span> {{ $locale['label'] }}
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
