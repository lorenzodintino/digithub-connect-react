@props(['align' => 'start'])

@php
    use Illuminate\Support\Uri;
    use Livewire\Livewire;

    $locales = [
        'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
        'en' => ['label' => 'English', 'flag' => '🇬🇧'],
    ];
    $current = $locales[app()->getLocale()] ?? $locales['it'];

    // During a Livewire update the current request is `/livewire/update`, so
    // `request()->fullUrl()` would build links to Livewire's own endpoint and
    // the switcher would answer with a 405. The snapshot knows the real page.
    $base = Uri::of(Livewire::isLivewireRequest() ? Livewire::originalUrl() : request()->fullUrl());
@endphp

<div {{ $attributes }}>
    <flux:dropdown position="top" :align="$align" class="w-full">
        <flux:button variant="subtle" size="sm" icon-trailing="chevrons-up-down" class="w-full justify-start">
            <span class="me-1">{{ $current['flag'] }}</span> {{ $current['label'] }}
        </flux:button>

        <flux:menu>
            @foreach ($locales as $code => $locale)
                {{-- The href is right on load; the click handler rebuilds it from the
                     address bar, which is the only place that knows the filters a
                     Livewire update has pushed there since. --}}
                <flux:menu.item
                    :href="(string) $base->withQuery(['lang' => $code])"
                    :class="app()->getLocale() === $code ? 'font-semibold' : null"
                    x-data
                    x-on:click.prevent="
                        const target = new URL(window.location.href);
                        target.searchParams.set('lang', @js($code));
                        window.location.href = target.toString();
                    "
                >
                    <span class="me-2">{{ $locale['flag'] }}</span> {{ $locale['label'] }}
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
