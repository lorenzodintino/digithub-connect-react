<?php

namespace App\Listeners;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\WaitingListEntry;
use App\Support\Activity;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Throwable;

class RecordFailedJob
{
    /**
     * Write a failed background job into the register.
     *
     * Until now a bounced email died quietly in `failed_jobs`, which nobody
     * reads: the portal thought it had written to its customer and had not.
     */
    public function handle(JobFailed $event): void
    {
        $payload = $event->job->payload();

        Activity::log('job.failed', null, [
            'job' => class_basename($payload['displayName'] ?? $event->job->resolveName()),
            'queue' => $event->job->getQueue(),
            // Only the head of it: a stack trace in the register would drown
            // everything else, and the full one is in the log file anyway.
            'error' => Str::limit($event->exception->getMessage(), 160),
        ], $this->tenantOf($payload));
    }

    /**
     * The portal the job belonged to, when the job carries something that says so.
     *
     * @param  array<string, mixed>  $payload
     */
    private function tenantOf(array $payload): ?int
    {
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command)) {
            return null;
        }

        try {
            // Restores the models the job was carrying. A job about a booking
            // that has since been deleted simply tells us nothing.
            $job = unserialize($command);
        } catch (Throwable) {
            return null;
        }

        foreach (['booking', 'entry', 'tenant'] as $property) {
            $subject = $this->propertyOf($job, $property);

            if ($subject instanceof Tenant) {
                return $subject->getKey();
            }

            if ($subject instanceof Booking || $subject instanceof WaitingListEntry) {
                return $subject->tenant_id;
            }
        }

        return null;
    }

    /**
     * Read a property off the job without exploding when it is not there.
     */
    private function propertyOf(mixed $job, string $property): mixed
    {
        if (! is_object($job)) {
            return null;
        }

        try {
            return property_exists($job, $property) ? $job->{$property} : null;
        } catch (Throwable) {
            return null;
        }
    }
}
