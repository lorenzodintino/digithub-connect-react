@props(['tenant'])

@unless (request()->boolean('embed'))
    <footer class="mx-auto w-full max-w-5xl px-4 pb-10 sm:px-6">
        <div class="flex flex-col items-center justify-between gap-3 border-t border-zinc-200 pt-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row">
            <span>&copy; {{ now()->year }} {{ $tenant->name }}</span>

            <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                @if ($tenant->privacyPolicyLink())
                    <a href="{{ $tenant->privacyPolicyLink() }}" target="_blank" rel="noopener" class="hover:underline">
                        {{ __('Privacy policy') }}
                    </a>
                @endif

                @if ($tenant->termsLink())
                    <a href="{{ $tenant->termsLink() }}" target="_blank" rel="noopener" class="hover:underline">
                        {{ __('Terms of service') }}
                    </a>
                @endif

                <span>{{ __('Powered by :app', ['app' => config('app.name')]) }}</span>
            </span>
        </div>
    </footer>
@endunless
