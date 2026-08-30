<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\Platform\FailedJobsReportMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class ReportFailedJobs extends Command
{
    /**
     * How far back each run looks.
     *
     * The same as the interval it runs at, so nothing is counted twice and
     * nothing falls between two runs.
     */
    public const WINDOW_HOURS = 1;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:report-failures';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tell the platform administrators about background jobs that failed';

    /**
     * Send one digest for the last hour, or say nothing at all.
     *
     * A digest rather than a message per failure: a portal with wrong SMTP
     * credentials fails every single email it tries, and a mail per failure
     * would bury the one line that matters.
     */
    public function handle(): int
    {
        $failures = ActivityLog::query()
            ->with('tenant')
            ->where('action', 'job.failed')
            ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->latest('created_at')
            ->get();

        if ($failures->isEmpty()) {
            $this->info('No background job failed in the last hour.');

            return self::SUCCESS;
        }

        $recipients = $this->administrators();

        if ($recipients->isEmpty()) {
            $this->warn('Jobs failed, but there is no administrator to tell.');

            return self::SUCCESS;
        }

        foreach ($recipients as $admin) {
            Mail::to($admin->email)->locale($admin->locale)->send(new FailedJobsReportMail($failures));
        }

        Activity::log('job.reported', null, [
            'failures' => $failures->count(),
            'portals' => $failures->pluck('tenant_id')->filter()->unique()->count(),
        ]);

        $this->info("Reported {$failures->count()} failures to {$recipients->count()} administrators.");

        return self::SUCCESS;
    }

    /**
     * The people who can do something about it.
     *
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->get();
    }
}
