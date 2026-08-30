<?php

use App\Models\ActivityLog;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Queue')] class extends Component {
    /**
     * The queue belongs to the platform, not to any single portal.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /**
     * The jobs that gave up, most recent first.
     *
     * Read straight from `failed_jobs`: it is Laravel's own table and the only
     * place where the job itself still exists to be retried.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function failures(): Collection
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(100)
            ->get()
            ->map(function (object $row): object {
                $payload = json_decode($row->payload, true);

                $row->job = class_basename($payload['displayName'] ?? __('Unknown job'));
                $row->reason = Str::limit(Str::before($row->exception, "\n"), 140);

                return $row;
            });
    }

    /**
     * How many jobs failed in the last day, from the register.
     */
    #[Computed]
    public function failuresToday(): int
    {
        return ActivityLog::query()
            ->where('action', 'job.failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Put one job back in the queue.
     */
    public function retry(string $uuid): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        unset($this->failures);

        Flux::toast(variant: 'success', text: __('Job queued again.'));
    }

    /**
     * Put every failed job back in the queue.
     */
    public function retryAll(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Artisan::call('queue:retry', ['id' => ['all']]);

        unset($this->failures);

        Flux::toast(variant: 'success', text: __('All failed jobs queued again.'));
    }

    /**
     * Give up on one job for good.
     */
    public function forget(string $uuid): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        unset($this->failures);

        Flux::toast(variant: 'success', text: __('Job removed.'));
    }
}; ?>

<x-slot:title>{{ __('Queue') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Queue')"
            :subheading="__('Background jobs that failed. A failed email never reached anybody.')"
        >
            <x-slot:actions>
                @if ($this->failures->isNotEmpty())
                    <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="retryAll">
                        {{ __('Retry all') }}
                    </flux:button>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if ($this->failuresToday > 0)
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __(':count jobs failed in the last 24 hours. The administrators get an hourly digest.', ['count' => $this->failuresToday]) }}
            </flux:callout>
        @endif

        @if ($this->failures->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="check-circle" class="size-10 text-emerald-500" />
                <flux:heading size="lg">{{ __('Nothing failed') }}</flux:heading>
                <flux:text>{{ __('Every background job went through.') }}</flux:text>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column>{{ __('Job') }}</flux:table.column>
                        <flux:table.column>{{ __('Why') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->failures as $failure)
                            <flux:table.row wire:key="failed-{{ $failure->uuid }}">
                                <flux:table.cell class="tabular-nums">
                                    {{ \Carbon\CarbonImmutable::parse($failure->failed_at)->setTimezone($timezone)->translatedFormat('d M, H:i') }}
                                </flux:table.cell>

                                <flux:table.cell variant="strong">{{ $failure->job }}</flux:table.cell>

                                <flux:table.cell class="whitespace-normal">
                                    <flux:text size="sm">{{ $failure->reason }}</flux:text>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="subtle" icon="arrow-path" wire:click="retry('{{ $failure->uuid }}')">
                                            {{ __('Retry') }}
                                        </flux:button>

                                        <flux:button size="sm" variant="subtle" icon="trash" wire:click="forget('{{ $failure->uuid }}')" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</div>
