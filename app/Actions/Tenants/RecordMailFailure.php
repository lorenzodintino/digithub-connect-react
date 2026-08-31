<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordMailFailure
{
    /**
     * Write a failed delivery onto the portal that was sending.
     *
     * A queued email that never leaves is otherwise invisible to the person who
     * can fix it: the booking still goes through, the job dies in failed_jobs,
     * and the settings screen keeps reporting the last test — which may have
     * passed months ago, before the password was changed.
     */
    public function handle(Tenant $tenant, Throwable $exception): void
    {
        // A portal without its own SMTP sends through the platform: the manager
        // cannot act on that failure, and it is ours to chase.
        if (! $tenant->hasCustomMailer()) {
            Log::warning('An email of a portal on the platform mailer failed.', [
                'tenant_id' => $tenant->getKey(),
                'error' => self::readable($exception),
            ]);

            return;
        }

        $tenant->forceFill([
            'mail_error' => self::readable($exception),
            'mail_failed_at' => now(),
        ])->save();
    }

    /**
     * Trim the transport error down to something a person can act on.
     *
     * Transport exceptions carry the whole SMTP conversation, which is noise
     * for the manager and can echo back credentials.
     */
    public static function readable(Throwable $exception): string
    {
        $message = trim(explode("\n", $exception->getMessage())[0]);

        return mb_substr($message, 0, 300);
    }
}
