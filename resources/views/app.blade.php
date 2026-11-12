<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        {{-- Before the page draws, so it never flashes in the wrong theme. The
             key is the one Flux used: a choice saved back then still counts. --}}
        <script>
            (function () {
                const appearance = window.localStorage.getItem('flux.appearance') || 'system';
                const dark = appearance === 'dark'
                    || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])

        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        <x-inertia::app />
    </body>
</html>
