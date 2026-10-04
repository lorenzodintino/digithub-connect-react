<x-layouts::app.sidebar :title="$title ?? null">
    <x-impersonation-banner />
    <x-legal-notice />

    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
