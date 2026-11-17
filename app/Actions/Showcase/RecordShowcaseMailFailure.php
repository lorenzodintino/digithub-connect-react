<?php

namespace App\Actions\Showcase;

use App\Actions\Tenants\RecordMailFailure;
use App\Models\ShowcaseMailSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordShowcaseMailFailure
{
    /**
     * Write a failed delivery of a demo request onto the settings.
     *
     * A queued email that never leaves is otherwise invisible: the visitor saw
     * "request sent", the job died in failed_jobs, and the settings page kept
     * reporting a test that may have passed months ago.
     */
    public function handle(Throwable $exception): void
    {
        $settings = ShowcaseMailSettings::current();

        // On the platform mailer there is no server of ours to blame: it is
        // the platform's own mail, and the log is where it is chased.
        if (! $settings->hasCustomMailer()) {
            Log::warning('A demo request of the home page could not be delivered.', ['error' => RecordMailFailure::readable($exception)]);

            return;
        }

        // Stand the server down: the next request leaves through the platform
        // mailer, and a test that passes puts the server back in service.
        $settings->forceFill([
            'mail_error' => RecordMailFailure::readable($exception),
            'mail_failed_at' => now(),
            'mail_verified_at' => null,
        ])->save();
    }
}
