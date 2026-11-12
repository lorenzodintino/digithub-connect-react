<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The background jobs that gave up. The queue belongs to the platform, not to
 * any single portal.
 */
class QueueController extends Controller
{
    /**
     * Read straight from `failed_jobs`: it is Laravel's own table and the only
     * place where the job itself still exists to be retried.
     */
    public function index(Request $request): Response
    {
        /** @var User $admin */
        $admin = $request->user();

        abort_unless($admin->isAdmin(), 403);

        $failures = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(100)
            ->get()
            ->map(function (object $row) use ($admin): array {
                $payload = json_decode($row->payload, true);

                return [
                    'uuid' => $row->uuid,
                    'failed_at' => CarbonImmutable::parse($row->failed_at)->setTimezone($admin->timezone)->translatedFormat('d M, H:i'),
                    'job' => class_basename($payload['displayName'] ?? __('Unknown job')),
                    'reason' => Str::limit(Str::before($row->exception, "\n"), 140),
                ];
            })
            ->all();

        return Inertia::render('admin/queue', [
            'failures' => $failures,
            'failuresToday' => ActivityLog::query()
                ->where('action', 'job.failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ]);
    }

    /**
     * Put one job back in the queue.
     */
    public function retry(Request $request, string $uuid): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        Toast::success(__('Job queued again.'));

        return back();
    }

    /**
     * Put every failed job back in the queue.
     */
    public function retryAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        Artisan::call('queue:retry', ['id' => ['all']]);

        Toast::success(__('All failed jobs queued again.'));

        return back();
    }

    /**
     * Give up on one job for good.
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        Toast::success(__('Job removed.'));

        return back();
    }
}
