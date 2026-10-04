<x-layouts::app.sidebar :title="$title ?? null">
    <x-impersonation-banner />
    <x-legal-notice />
    <x-subscription-notice />

    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
