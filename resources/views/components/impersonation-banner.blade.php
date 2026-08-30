@php($impersonation = app(\App\Services\Impersonation::class))

@if ($impersonation->isActive())
    {{-- Impossible to miss on purpose: nobody should forget they are somebody
         else, least of all inside a customer's portal. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
        <span class="flex items-center gap-2">
            <flux:icon name="exclamation-triangle" variant="micro" />

            {{ __('You are working as :name.', ['name' => auth()->user()->name]) }}

            @if ($impersonation->expiresAt())
                <span class="opacity-80">
                    {{ __('It ends at :time.', ['time' => $impersonation->expiresAt()->setTimezone($impersonation->actor()?->timezone ?? config('app.timezone'))->format('H:i')]) }}
                </span>
            @endif
        </span>

        <a href="{{ route('impersonation.stop') }}" class="font-semibold underline">
            {{ __('Back to my account') }}
        </a>
    </div>
@endif
